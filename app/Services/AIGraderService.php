<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\AiProviderConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIGraderService
{
    private string $apiKey;

    private string $provider;

    private string $model;

    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    private bool $enabled = true;

    private float $temperature = 0.1;

    private int $maxOutputTokens = 4096;

    private bool $includeAssignmentInstructions = true;

    private bool $includeRubric = true;

    private bool $contextEnabled = true;

    private ?string $globalContext = null;

    private ?string $essayContext = null;

    private ?string $codeContext = null;

    private ?string $plagiarismContext = null;

    public function __construct()
    {
        $settings = AiSetting::current();
        $provider = AiProviderConfig::active();

        $this->enabled = $settings->enabled;
        $this->provider = $provider?->provider ?? 'gemini';
        $this->apiKey = $provider?->resolvedApiKey() ?? config('services.gemini.key', '');
        $this->model = $provider?->selected_model ?? config('services.gemini.model', 'gemini-3-flash-preview');
        $this->temperature = (float) $settings->temperature;
        $this->maxOutputTokens = $settings->max_output_tokens;
        $this->includeAssignmentInstructions = $settings->include_assignment_instructions;
        $this->includeRubric = $settings->include_rubric;
        $this->contextEnabled = $settings->context_enabled;
        $this->globalContext = $settings->global_context;
        $this->essayContext = $settings->essay_context;
        $this->codeContext = $settings->code_context;
        $this->plagiarismContext = $settings->plagiarism_context;
    }

    public function modelUsed(): string
    {
        return $this->model;
    }

    public function createAssignmentDraft(string $documentText): array
    {
                $prompt = <<<PROMPT
You are an instructional designer. Analyze the following PDF and classify it before creating a draft assignment for a teacher to review.

Return ONLY valid JSON in this exact format:
{
  "title": "short assignment title",
    "type": "essay|mcq|code",
    "category": "quiz|exam|activity|project|null",
    "language": "python|javascript|java|cpp|csharp|php|ruby|go|null",
  "instructions": "clear student-facing instructions",
    "rubric": "concise grading criteria, especially important for essay and code assignments",
    "questions": [
        {
            "question": "question text",
            "points": 1,
            "choices": [
                {"choice_text": "answer choice", "is_correct": true}
            ]
        }
    ]
}

Classification rules:
- Use "mcq" only when the document contains multiple-choice questions with answer options.
- Use "code" when it asks students to write, debug, or submit software/code. Infer the language only when supported by the document.
- Otherwise use "essay".
- Use "quiz" for a short test, "exam" for a formal examination, and otherwise choose activity/project/null based on the document.
- If the text contains numbered questions followed by options such as A., B., C., or D., it MUST be classified as "mcq" and every question and option must be returned in "questions".
- For "mcq", extract every question and its choices. Predict the most likely correct answer from the question and your general knowledge, then set exactly one choice's "is_correct" to true. If the question is genuinely ambiguous, still select the best-supported answer and let the teacher review it.
- For "essay" and "code", return an empty questions array and create a useful grading rubric from the document.
- Do not invent requirements, answer keys, or programming languages that are not supported by the document.

PDF TEXT:
{$documentText}
PROMPT;

                $draft = $this->call($prompt);

                $containsChoiceOptions = preg_match(
                    '/(?:^|\n)\s*(?:\d+[.)]|question\s+\d+[:.)]).*(?:\n\s*[A-D][.)]\s+)/ims',
                    $documentText
                ) === 1;

                if (($draft['type'] ?? null) === 'mcq' || $containsChoiceOptions) {
                    if (empty($draft['questions'])) {
                        $draft['questions'] = $this->extractMultipleChoiceQuestions($documentText);
                    }

                    if (! empty($draft['questions'])) {
                        $draft['type'] = 'mcq';
                        $draft['category'] = $draft['category'] ?? 'quiz';
                    }
                }

                return $draft;
    }

        private function extractMultipleChoiceQuestions(string $documentText): array
        {
                $prompt = <<<PROMPT
Extract every multiple-choice question from the text below.

Return ONLY a JSON array. Do not summarize, omit, or rewrite questions:
[
    {
        "question": "complete question text",
        "points": 1,
        "choices": [
            {"choice_text": "complete option text", "is_correct": false}
        ]
    }
]

Copy all question and option text faithfully. Include options labelled A, B, C, D or similar. Predict the most likely correct answer from the question and your general knowledge, and set exactly one choice's is_correct value to true for every question. This is a suggested answer for teacher review, not an authoritative answer key.

TEXT:
{$documentText}
PROMPT;

                $result = $this->call($prompt, 8192);

                return is_array($result) && array_is_list($result) ? $result : [];
        }

    public function gradeEssay(
        string $title,
        string $instructions,
        string $rubric,
        string $essayText,
        float $maxScore
    ): array {
        $context = $this->contextBlock($this->essayContext);
        $instructionsBlock = $this->includeAssignmentInstructions ? "**Instructions:** {$instructions}" : '**Instructions:** Not included by AI settings.';
        $rubricBlock = $this->includeRubric ? "**Rubric/Criteria:** {$rubric}" : '**Rubric/Criteria:** Not included by AI settings.';

        $prompt = <<<PROMPT
You are an academic essay grader. Grade the following student essay carefully and objectively.

{$context}

**Assignment:** {$title}
{$instructionsBlock}
{$rubricBlock}
**Max Score:** {$maxScore}

**Student Essay:**
{$essayText}

Respond ONLY with valid JSON in this exact format:
{
  "score": <number between 0 and {$maxScore}>,
  "overall_comment": "<overall assessment string>",
  "criterion_feedback": [
    {"criterion": "<criterion name>", "score": <number>, "max": <number>, "feedback": "<string>"}
  ]
}
PROMPT;

        return $this->call($prompt);
    }

    public function gradeCode(
        string $title,
        string $instructions,
        string $rubric,
        string $code,
        string $language,
        float $maxScore
    ): array {
        $context = $this->contextBlock($this->codeContext);
        $instructionsBlock = $this->includeAssignmentInstructions ? "**Problem Description:** {$instructions}" : '**Problem Description:** Not included by AI settings.';
        $rubricBlock = $this->includeRubric ? "**Expected Behavior / Test Cases:** {$rubric}" : '**Expected Behavior / Test Cases:** Not included by AI settings.';

        $prompt = <<<PROMPT
You are a programming assignment grader. Evaluate the following student code.

{$context}

**Assignment:** {$title}
{$instructionsBlock}
{$rubricBlock}
**Language:** {$language}
**Max Score:** {$maxScore}

**Student Code:**
```{$language}
{$code}
```

Respond ONLY with valid JSON in this exact format:
{
  "score": <number between 0 and {$maxScore}>,
  "overall_comment": "<overall assessment>",
  "correctness": <number 0-100>,
  "logic_quality": <number 0-100>,
  "code_quality": <number 0-100>,
  "inline_comments": [{"line": <number>, "comment": "<string>"}]
}
PROMPT;

        return $this->call($prompt);
    }

    public function checkPlagiarism(array $submissions, string $assignmentTitle): array
    {
        if (count($submissions) < 2) {
            return [];
        }

        $submissionsText = '';
        foreach ($submissions as $sub) {
            $submissionsText .= "--- Student ID: {$sub['student_id']} ---\n{$sub['content']}\n\n";
        }

        $context = $this->contextBlock($this->plagiarismContext);

        $prompt = <<<PROMPT
You are an academic integrity checker. Compare the following student submissions for the assignment "{$assignmentTitle}" and detect potential plagiarism.

{$context}

{$submissionsText}

For every pair of students, compute a similarity percentage (0-100) based on content overlap, paraphrasing, and structural similarity.

Respond ONLY with valid JSON — an array of pair comparisons:
[
  {
    "student_a": <student_id>,
    "student_b": <student_id>,
    "similarity": <0-100>,
    "flagged": <true if similarity > 70>,
    "explanation": "<brief reason for this score>"
  }
]
PROMPT;

        return $this->call($prompt);
    }

    private function call(string $prompt, ?int $maxOutputTokens = null): array
    {
        if (! $this->enabled) {
            return ['error' => 'AI assessment is disabled.'];
        }

        if ($this->provider !== 'gemini') {
            return ['error' => "{$this->provider} is saved but not connected to the grader yet."];
        }

        if (empty($this->apiKey)) {
            Log::warning('AIGraderService: GEMINI_API_KEY is not set.');

            return ['error' => 'AI API key not configured.'];
        }

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(60)->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature'       => $this->temperature,
                    'maxOutputTokens'   => $maxOutputTokens ?? $this->maxOutputTokens,
                    'responseMimeType'  => 'application/json',
                    'thinkingConfig'    => ['thinkingBudget' => 0],
                ],
            ])->throw()->json();

            $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $decoded = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Fallback: extract JSON from text if mime type was ignored
                preg_match('/(\{.*\}|\[.*\])/s', $text, $matches);
                $decoded = json_decode($matches[0] ?? '{}', true) ?? [];
            }

            return $decoded ?: ['error' => 'Could not parse AI response.'];
        } catch (RequestException $e) {
            Log::error('AIGraderService error: '.$e->getMessage());

            $status = $e->response?->status();

            return ['error' => match ($status) {
                400, 401, 403 => 'Gemini rejected the API key. Replace the saved key in AI Settings, then run Check Status again.',
                429 => 'Gemini rate limit or quota reached. Please try again later or use another key.',
                default => 'Gemini could not process the request. Please check the provider settings and try again.',
            }];
        } catch (\Throwable $e) {
            Log::error('AIGraderService error: '.$e->getMessage());

            return ['error' => 'The AI request could not be completed. Please try again.'];
        }
    }

    private function contextBlock(?string $assessmentContext): string
    {
        if (! $this->contextEnabled) {
            return '';
        }

        $parts = array_filter([
            trim($this->globalContext ?? ''),
            trim($assessmentContext ?? ''),
        ]);

        if ($parts === []) {
            return '';
        }

        return "**Additional Grading Context:**\n".implode("\n\n", $parts);
    }
}

<?php

namespace App\Services;

use App\Models\AiProviderConfig;
use App\Models\AiSetting;
use App\Models\PlagiarismRunSubmission;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIGraderService
{
    public function __construct(private readonly RichTextSanitizer $richText)
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

    public function toPlainText(?string $value): string
    {
        return $this->richText->toPlainText($value);
    }

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

    public function modelUsed(): string
    {
        return $this->model;
    }

    public function testPlainTextPrompt(string $prompt): array
    {
        if (! config('gemini_playground.enabled') || (config('app.env') === 'production' && ! config('gemini_playground.production_enabled'))) {
            return $this->playgroundError('playground_disabled');
        }

        if ($this->provider !== 'gemini' || empty($this->apiKey)) {
            return $this->playgroundError('provider_not_configured');
        }

        $start = hrtime(true);

        try {
            $response = Http::timeout(60)->post("{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => $this->temperature, 'maxOutputTokens' => $this->maxOutputTokens],
            ]);
            $duration = (int) round((hrtime(true) - $start) / 1_000_000);
            $text = $response->json('candidates.0.content.parts.0.text');

            if ($response->successful() && is_string($text) && $text !== '') {
                return ['success' => true, 'model' => $this->model, 'http_status' => $response->status(), 'duration_ms' => $duration, 'response_text' => $text, 'error_code' => null, 'error_message' => null, 'key_source' => $this->keySource()];
            }

            return $this->playgroundError(match ($response->status()) {
                429 => 'provider_rate_limited', 500, 502, 503, 504 => 'provider_unavailable', default => 'provider_request_failed'
            }, $response->status(), $duration);
        } catch (\Throwable) {
            return $this->playgroundError('provider_request_failed', null, (int) round((hrtime(true) - $start) / 1_000_000));
        }
    }

    public function testInteractionsPrompt(string $prompt): array
    {
        if (! config('gemini_playground.enabled') || (config('app.env') === 'production' && ! config('gemini_playground.production_enabled'))) {
            return $this->playgroundError('playground_disabled');
        }
        if ($this->provider !== 'gemini' || empty($this->apiKey)) {
            return $this->playgroundError('provider_not_configured');
        }
        $start = hrtime(true);
        try {
            $response = Http::timeout(60)->withHeaders(['x-goog-api-key' => $this->apiKey])->post('https://generativelanguage.googleapis.com/v1beta/interactions', ['model' => $this->model, 'input' => $prompt]);
            $duration = (int) round((hrtime(true) - $start) / 1_000_000);
            $text = collect($response->json('steps', []))->where('type', 'model_output')->flatMap(fn ($step) => $step['content'] ?? [])->where('type', 'text')->pluck('text')->filter(fn ($value) => is_string($value))->implode('');
            if ($response->successful() && $text !== '') {
                return ['success' => true, 'model' => $this->model, 'http_status' => $response->status(), 'duration_ms' => $duration, 'response_text' => $text, 'error_code' => null, 'error_message' => null, 'key_source' => $this->keySource()];
            }

            return $this->playgroundError(match ($response->status()) {
                429 => 'provider_rate_limited', 500, 502, 503, 504 => 'provider_unavailable', default => 'provider_request_failed'
            }, $response->status(), $duration);
        } catch (\Throwable) {
            return $this->playgroundError('provider_request_failed', null, (int) round((hrtime(true) - $start) / 1_000_000));
        }
    }

    private function keySource(): string
    {
        $provider = AiProviderConfig::active();

        return filled($provider?->api_key) ? 'database' : 'environment';
    }

    private function playgroundError(string $code, ?int $status = null, ?int $duration = null): array
    {
        $messages = ['playground_disabled' => 'Gemini playground is disabled.', 'provider_not_configured' => 'Gemini is not configured.', 'provider_rate_limited' => 'Gemini rate limit or quota reached.', 'provider_unavailable' => 'Gemini is temporarily unavailable.', 'provider_request_failed' => 'Gemini request could not be completed.'];

        return ['success' => false, 'model' => $this->model, 'http_status' => $status, 'duration_ms' => $duration, 'response_text' => null, 'error_code' => $code, 'error_message' => $messages[$code], 'key_source' => $this->keySource()];
    }

    public function validateGradeResponse(
        array $result,
        float $maxScore,
        string $assignmentType
    ): array {
        if (! in_array($assignmentType, ['essay', 'code'], true)) {
            throw new \InvalidArgumentException('Unsupported AI grading response type.');
        }

        if (! is_finite($maxScore) || $maxScore < 0) {
            throw new \InvalidArgumentException('Invalid assignment maximum score.');
        }

        $this->validateNumber($result['score'] ?? null, 'score', 0, $maxScore);
        $this->validateText($result['overall_comment'] ?? null, 'overall comment', 5000);

        if ($assignmentType === 'essay') {
            $this->validateEssayFeedback($result);
        } else {
            $this->validateCodeFeedback($result);
        }

        try {
            $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('AI feedback is not valid JSON data.');
        }

        if (strlen($encoded) > 20000) {
            throw new \InvalidArgumentException('AI feedback is too large.');
        }

        return $result;
    }

    private function validateEssayFeedback(array $result): void
    {
        $criteria = $result['criterion_feedback'] ?? null;

        if (! is_array($criteria) || ! array_is_list($criteria) || count($criteria) > 50) {
            throw new \InvalidArgumentException('Invalid criterion feedback.');
        }

        foreach ($criteria as $criterion) {
            if (! is_array($criterion)) {
                throw new \InvalidArgumentException('Invalid criterion feedback item.');
            }

            $this->validateText($criterion['criterion'] ?? null, 'criterion name', 250);
            $maximum = $this->validateNumber($criterion['max'] ?? null, 'criterion maximum', 0);
            $this->validateNumber($criterion['score'] ?? null, 'criterion score', 0, $maximum);
            $this->validateText($criterion['feedback'] ?? null, 'criterion feedback', 2000);
        }
    }

    private function validateCodeFeedback(array $result): void
    {
        foreach (['correctness', 'logic_quality', 'code_quality'] as $field) {
            $this->validateNumber($result[$field] ?? null, $field, 0, 100);
        }

        $comments = $result['inline_comments'] ?? null;

        if (! is_array($comments) || ! array_is_list($comments) || count($comments) > 100) {
            throw new \InvalidArgumentException('Invalid inline comments.');
        }

        foreach ($comments as $comment) {
            if (! is_array($comment) || ! is_int($comment['line'] ?? null) || $comment['line'] < 1) {
                throw new \InvalidArgumentException('Invalid inline comment line.');
            }

            $this->validateText($comment['comment'] ?? null, 'inline comment', 1000);
        }
    }

    private function validateNumber(
        mixed $value,
        string $field,
        float $minimum,
        ?float $maximum = null
    ): float {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new \InvalidArgumentException("Invalid {$field}.");
        }

        $number = (float) $value;

        if ($number < $minimum || ($maximum !== null && $number > $maximum)) {
            throw new \InvalidArgumentException("{$field} is outside the allowed range.");
        }

        return $number;
    }

    private function validateText(mixed $value, string $field, int $maximumLength): void
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > $maximumLength) {
            throw new \InvalidArgumentException("Invalid {$field}.");
        }
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
        float $maxScore,
        bool $includeErrorCode = false
    ): array {
        $instructions = $this->toPlainText($instructions);
        $rubric = $this->toPlainText($rubric);
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

        return $this->call(
            $prompt,
            null,
            $includeErrorCode ? max(1, (int) config('ai_processing.timeout_budget_seconds')) : null,
            $includeErrorCode
        );
    }

    public function gradeCode(
        string $title,
        string $instructions,
        string $rubric,
        string $code,
        string $language,
        float $maxScore,
        bool $includeErrorCode = false
    ): array {
        $instructions = $this->toPlainText($instructions);
        $rubric = $this->toPlainText($rubric);
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

        return $this->call(
            $prompt,
            null,
            $includeErrorCode ? max(1, (int) config('ai_processing.timeout_budget_seconds')) : null,
            $includeErrorCode
        );
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

    public function estimatePlagiarismPairInputTokens(
        PlagiarismRunSubmission $first,
        PlagiarismRunSubmission $second,
        string $assignmentTitle
    ): int {
        return max(1, (int) ceil(strlen($this->plagiarismPairPrompt($first, $second, $assignmentTitle)) / 4));
    }

    public function analyzePlagiarismPair(
        PlagiarismRunSubmission $first,
        PlagiarismRunSubmission $second,
        string $assignmentTitle
    ): array {
        return $this->call(
            $this->plagiarismPairPrompt($first, $second, $assignmentTitle),
            max(1, (int) config('ai_processing.plagiarism_max_output_tokens')),
            max(1, (int) config('ai_processing.timeout_budget_seconds')),
            true
        );
    }

    public function validatePlagiarismPairResponse(array $result): array
    {
        $similarity = $result['similarity'] ?? null;
        $flagged = $result['flagged'] ?? null;
        $explanation = $result['explanation'] ?? null;

        if (! is_int($similarity) && ! is_float($similarity)) {
            throw new \InvalidArgumentException('AI similarity must be numeric.');
        }

        if (! is_finite($similarity) || $similarity < 0 || $similarity > 100) {
            throw new \InvalidArgumentException('AI similarity is outside the allowed range.');
        }

        if (! is_bool($flagged) || $flagged !== ($similarity >= 70)) {
            throw new \InvalidArgumentException('AI plagiarism flag is inconsistent with the similarity score.');
        }

        if (! is_string($explanation) || trim($explanation) === '' || strlen($explanation) > 5000) {
            throw new \InvalidArgumentException('AI plagiarism explanation is invalid.');
        }

        return [
            'similarity' => $similarity,
            'flagged' => $flagged,
            'explanation' => trim($explanation),
        ];
    }

    private function plagiarismPairPrompt(
        PlagiarismRunSubmission $first,
        PlagiarismRunSubmission $second,
        string $assignmentTitle
    ): string {
        $combinedContentBytes = strlen($first->content) + strlen($second->content);

        if ($combinedContentBytes > max(1, (int) config('ai_processing.plagiarism_max_pair_content_bytes'))) {
            throw new \InvalidArgumentException('Pair content exceeds the configured limit.');
        }

        $context = $this->contextBlock($this->plagiarismContext);
        $prompt = <<<PROMPT
You are an academic integrity checker. Independently compare these two anonymous submissions for the assignment "{$assignmentTitle}".

{$context}

--- Submission A ---
{$first->content}

--- Submission B ---
{$second->content}

Return ONLY valid JSON with this exact structure:
{
  "similarity": <number from 0 to 100>,
  "flagged": <true when similarity is 70 or higher, otherwise false>,
  "explanation": "<brief reason>"
}
PROMPT;

        if (strlen($prompt) > max(1, (int) config('ai_processing.plagiarism_max_prompt_bytes'))) {
            throw new \InvalidArgumentException('Pair prompt exceeds the configured limit.');
        }

        return $prompt;
    }

    private function call(
        string $prompt,
        ?int $maxOutputTokens = null,
        ?int $timeoutSeconds = null,
        bool $includeErrorCode = false
    ): array {
        if (! $this->enabled) {
            return $this->providerError('ai_disabled', $includeErrorCode);
        }

        if ($this->provider !== 'gemini') {
            return $includeErrorCode
                ? $this->providerError('provider_unavailable', true)
                : ['error' => "{$this->provider} is saved but not connected to the grader yet."];
        }

        if (empty($this->apiKey)) {
            Log::warning('AIGraderService: Gemini API key is not configured.');

            return $this->providerError('provider_not_configured', $includeErrorCode);
        }

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout($timeoutSeconds ?? 60)->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => $this->temperature,
                    'maxOutputTokens' => $maxOutputTokens ?? $this->maxOutputTokens,
                    'responseMimeType' => 'application/json',
                    'thinkingConfig' => ['thinkingBudget' => 0],
                ],
            ])->throw()->json();

            $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $decoded = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Fallback: extract JSON from text if mime type was ignored
                preg_match('/(\{.*\}|\[.*\])/s', $text, $matches);
                $decoded = json_decode($matches[0] ?? '{}', true) ?? [];
            }

            return $decoded ?: $this->providerError('invalid_response', $includeErrorCode);
        } catch (RequestException $e) {
            $status = $e->response?->status();
            Log::warning('AIGraderService request failed.', ['status' => $status]);

            if (! $includeErrorCode) {
                return ['error' => match ($status) {
                    400, 401, 403 => 'Gemini rejected the API key. Replace the saved key in AI Settings, then run Check Status again.',
                    429 => 'Gemini rate limit or quota reached. Please try again later or use another key.',
                    default => 'Gemini could not process the request. Please check the provider settings and try again.',
                }];
            }

            return $this->providerError(match ($status) {
                429 => 'provider_rate_limited',
                500, 502, 503, 504 => 'provider_unavailable',
                default => 'provider_request_failed',
            }, $includeErrorCode);
        } catch (\Throwable $e) {
            Log::warning('AIGraderService request could not be completed.', ['exception' => $e::class]);

            return $this->providerError('provider_request_failed', $includeErrorCode);
        }
    }

    private function providerError(string $code, bool $includeErrorCode): array
    {
        $messages = [
            'ai_disabled' => 'AI assessment is disabled.',
            'provider_not_configured' => 'AI API key not configured.',
            'provider_rate_limited' => 'Gemini rate limit or quota reached. Please try again later.',
            'invalid_response' => 'Could not parse AI response.',
            'provider_unavailable' => 'Gemini could not process the request. Please try again later.',
            'provider_request_failed' => 'The AI request could not be completed. Please try again.',
        ];

        return array_filter([
            'error' => $messages[$code] ?? 'The AI request could not be completed. Please try again.',
            'error_code' => $includeErrorCode ? $code : null,
        ], fn ($value) => $value !== null);
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

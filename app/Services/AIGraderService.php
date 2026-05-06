<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIGraderService
{
    private string $apiKey;

    private string $model;

    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
        $this->model = config('services.gemini.model', 'gemini-2.0-flash');
    }

    public function gradeEssay(
        string $title,
        string $instructions,
        string $rubric,
        string $essayText,
        float $maxScore
    ): array {
        $prompt = <<<PROMPT
You are an academic essay grader. Grade the following student essay carefully and objectively.

**Assignment:** {$title}
**Instructions:** {$instructions}
**Rubric/Criteria:** {$rubric}
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
        $prompt = <<<PROMPT
You are a programming assignment grader. Evaluate the following student code.

**Assignment:** {$title}
**Problem Description:** {$instructions}
**Expected Behavior / Test Cases:** {$rubric}
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

        $prompt = <<<PROMPT
You are an academic integrity checker. Compare the following student submissions for the assignment "{$assignmentTitle}" and detect potential plagiarism.

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

    private function call(string $prompt): array
    {
        if (empty($this->apiKey)) {
            Log::warning('AIGraderService: GEMINI_API_KEY is not set.');

            return ['error' => 'AI API key not configured.'];
        }

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 2048,
                    'responseMimeType' => 'application/json',
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
        } catch (\Throwable $e) {
            Log::error('AIGraderService error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }
}

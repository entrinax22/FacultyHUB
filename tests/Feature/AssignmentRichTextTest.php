<?php

use App\Models\Assignment;
use App\Models\GradingComponent;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use App\Services\RichTextSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $faculty = User::factory()->create(['role' => 'faculty']);
    $semester = Semester::create([
        'name' => 'Term 1', 'school_year' => '2026-2027',
        'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true,
    ]);
    $subject = Subject::create(['code' => 'RT101', 'name' => 'Rich text']);
    $this->section = Section::create([
        'name' => 'Section A', 'semester_id' => $semester->id,
        'subject_id' => $subject->id, 'faculty_id' => $faculty->id,
    ]);
    GradingComponent::create([
        'section_id' => $this->section->id, 'name' => 'Tasks',
        'weight_percentage' => 100, 'max_score' => 100, 'order' => 1,
    ]);
    $this->actingAs($faculty);
});

test('assignment create and update sanitize before persisting', function (string $mode) {
    $payload = [
        'title' => 'Sanitization task', 'type' => 'essay', 'max_score' => 20,
        'is_published' => false, 'proctoring_enabled' => false,
        'instructions' => '<div><span><a href="javascript:alert(1)" onclick="alert(1)">Read</a></span><p><strong>Safe</strong></p></div>',
        'rubric' => '<svg><text>attack</text></svg><p><em>Accuracy</em></p>',
    ];
    if ($mode === 'create') {
        $this->postJson(route('sections.assignments.store', Crypt::encryptString((string) $this->section->id)), $payload)->assertCreated();
        $assignment = Assignment::latest('id')->firstOrFail();
    } else {
        $assignment = Assignment::create([
            'section_id' => $this->section->id, 'title' => 'Legacy',
            'instructions' => 'Legacy text', 'type' => 'essay', 'max_score' => 20,
        ]);
        $this->putJson(route('assignments.update', Crypt::encryptString((string) $assignment->id)), $payload)->assertOk();
        $assignment->refresh();
    }

    expect($assignment->instructions)->toBe('<a>Read</a><p><strong>Safe</strong></p>');
    expect($assignment->rubric)->toBe('<p><em>Accuracy</em></p>');
})->with(['create', 'update']);

test('assignment create and update reject invisible instructions and preserve optional rubric', function (string $mode, string $instructions) {
    $payload = [
        'title' => 'Empty task', 'type' => 'essay', 'max_score' => 20,
        'proctoring_enabled' => false, 'is_published' => false,
        'instructions' => $instructions, 'rubric' => '<p>&nbsp;</p>',
    ];
    if ($mode === 'create') {
        $method = 'postJson';
        $url = route('sections.assignments.store', Crypt::encryptString((string) $this->section->id));
    } else {
        $assignment = Assignment::create([
            'section_id' => $this->section->id, 'title' => 'Legacy',
            'instructions' => 'Legacy text', 'type' => 'essay', 'max_score' => 20,
        ]);
        $method = 'putJson';
        $url = route('assignments.update', Crypt::encryptString((string) $assignment->id));
    }

    $this->{$method}($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('instructions');
    $payload['instructions'] = '<p>Read this task.</p>';
    $this->{$method}($url, $payload)->assertSuccessful();
    expect(Assignment::latest('id')->firstOrFail()->rubric)->toBeNull();
})->with(['create', 'update'])->with([
    '<p><br></p>', '<script>alert(1)</script>', '<div><span></span></div>', '<p>&nbsp;&#8203;</p>',
]);

test('programming examples survive assignment creation and a subsequent edit', function () {
    $plain = 'if (a < b && b > 0) { List<T> result; }';
    $payload = [
        'title' => 'Programming task', 'type' => 'code', 'language' => 'php',
        'max_score' => 20, 'is_published' => false, 'proctoring_enabled' => false,
        'instructions' => $plain, 'rubric' => 'Check a < b and List<T>.',
    ];
    $this->postJson(route('sections.assignments.store', Crypt::encryptString((string) $this->section->id)), $payload)
        ->assertCreated();
    $assignment = Assignment::latest('id')->firstOrFail();
    $stored = $assignment->instructions;
    $payload['instructions'] = $stored;
    $payload['rubric'] = $assignment->rubric;

    $this->putJson(route('assignments.update', Crypt::encryptString((string) $assignment->id)), $payload)
        ->assertOk();
    $assignment->refresh();

    expect($assignment->instructions)->toBe($stored);
    expect(app(RichTextSanitizer::class)->toPlainText($assignment->instructions))->toBe($plain);
    expect(app(RichTextSanitizer::class)->toPlainText($assignment->rubric))->toBe('Check a < b and List<T>.');
});

test('rich text byte limits apply to sanitized create and update payloads', function (string $mode, string $field, int $limit, string $character, int $extra) {
    $textBytes = $limit - 7;
    $content = '<p>'.str_repeat($character, intdiv($textBytes, strlen($character)))
        .str_repeat('x', $textBytes % strlen($character) + $extra).'</p>';
    expect(strlen($content))->toBe($limit + $extra);

    $payload = [
        'title' => 'Boundary task', 'type' => 'essay', 'max_score' => 20,
        'is_published' => false, 'proctoring_enabled' => false,
        'instructions' => 'Read this task.', 'rubric' => 'Accuracy',
        $field => $content,
    ];
    $assignment = null;
    if ($mode === 'create') {
        $method = 'postJson';
        $url = route('sections.assignments.store', Crypt::encryptString((string) $this->section->id));
    } else {
        $assignment = Assignment::create([
            'section_id' => $this->section->id, 'title' => 'Legacy',
            'instructions' => 'Legacy text', 'rubric' => 'Legacy rubric', 'type' => 'essay', 'max_score' => 20,
        ]);
        $method = 'putJson';
        $url = route('assignments.update', Crypt::encryptString((string) $assignment->id));
    }

    $response = $this->{$method}($url, $payload);

    if ($extra > 0) {
        $response->assertUnprocessable()->assertJsonValidationErrors($field)
            ->assertJsonPath('errors.'.$field.'.0', ucfirst($field).' must not exceed '.number_format($limit).' UTF-8 bytes after sanitization.');
        if ($assignment) {
            expect($assignment->fresh()->getAttribute($field))->toBe($field === 'instructions' ? 'Legacy text' : 'Legacy rubric');
        } else {
            $this->assertDatabaseCount('assignments', 0);
        }
    } else {
        $response->assertStatus($mode === 'create' ? 201 : 200);
        expect(Assignment::latest('id')->firstOrFail()->getAttribute($field))->toBe($content);
    }
})->with(['create', 'update'])->with([
    'instructions' => ['instructions', 48000],
    'rubric' => ['rubric', 16000],
])->with([
    'ASCII at limit' => ['a', 0],
    'ASCII one byte over' => ['a', 1],
    'Unicode at limit' => ['界', 0],
    'Unicode one byte over' => ['界', 1],
]);

test('byte limits measure sanitized output rather than raw input or readable text', function (string $field, int $limit) {
    $payload = [
        'title' => 'Sanitized size', 'type' => 'essay', 'max_score' => 20,
        'proctoring_enabled' => false, 'instructions' => 'Read this task.', 'rubric' => 'Accuracy',
        $field => '<p>Safe</p><script>'.str_repeat('x', $limit).'</script>',
    ];
    $url = route('sections.assignments.store', Crypt::encryptString((string) $this->section->id));
    $this->postJson($url, $payload)->assertCreated();
    expect(Assignment::latest('id')->firstOrFail()->getAttribute($field))->toBe('<p>Safe</p>');

    // Escaping ampersands expands the stored byte length fivefold.
    $payload[$field] = str_repeat('&', intdiv($limit, 5) + 1);
    $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('assignments', 1);
})->with([
    'instructions' => ['instructions', 48000],
    'rubric' => ['rubric', 16000],
]);

test('an oversized legacy rubric remains viewable and is not validated when omitted from an update', function () {
    $rubric = str_repeat('x', 16001);
    $assignment = Assignment::create([
        'section_id' => $this->section->id, 'title' => 'Legacy',
        'instructions' => str_repeat('x', 48001), 'rubric' => $rubric, 'type' => 'essay', 'max_score' => 20,
    ]);
    $id = Crypt::encryptString((string) $assignment->id);
    $this->get(route('assignments.show', $id))->assertOk();

    $payload = [
        'title' => 'Updated legacy', 'instructions' => 'Revised instructions.',
        'type' => 'essay', 'max_score' => 20, 'proctoring_enabled' => false,
    ];
    $this->putJson(route('assignments.update', $id), $payload)->assertOk();
    expect($assignment->fresh()->rubric)->toBe($rubric);

    $payload['rubric'] = $rubric;
    $this->putJson(route('assignments.update', $id), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('rubric');
    expect($assignment->fresh()->rubric)->toBe($rubric);
});

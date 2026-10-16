<?php

use App\Jobs\CheckPlagiarismJob;
use App\Models\Assignment;
use Illuminate\Contracts\Queue\ShouldBeUnique;

test('plagiarism jobs are unique per assignment', function () {
    $assignment = new Assignment;
    $assignment->setAttribute('id', 42);

    $job = new CheckPlagiarismJob($assignment);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('42');
});

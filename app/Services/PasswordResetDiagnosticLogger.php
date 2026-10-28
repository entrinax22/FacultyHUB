<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasswordResetDiagnosticLogger
{
    public function record(string $outcome, bool $exceptionOccurred = false): void
    {
        if (! config('password_reset.diagnostics_enabled', false)) {
            return;
        }

        Log::info('password_reset_diagnostic', [
            'request_id' => (string) Str::uuid(),
            'outcome' => $outcome,
            'exception' => $exceptionOccurred,
        ]);
    }
}

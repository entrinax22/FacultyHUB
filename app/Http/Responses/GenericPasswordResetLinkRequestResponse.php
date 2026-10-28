<?php

namespace App\Http\Responses;

use App\Services\PasswordResetDiagnosticLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

class GenericPasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(
        private readonly string $status,
        private readonly PasswordResetDiagnosticLogger $diagnostics,
    ) {}

    public function toResponse($request)
    {
        $outcome = match ($this->status) {
            Password::INVALID_USER => 'invalid_user',
            Password::RESET_THROTTLED => 'reset_throttled',
            default => 'other',
        };

        $this->diagnostics->record($outcome);

        return $request->wantsJson()
            ? new JsonResponse(['message' => __('passwords.sent')], 200)
            : back()->with('status', __('passwords.sent'));
    }
}

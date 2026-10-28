<?php

namespace App\Http\Responses;

use App\Services\PasswordResetDiagnosticLogger;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;

class SuccessfulPasswordResetLinkRequestResponse implements SuccessfulPasswordResetLinkRequestResponseContract
{
    public function __construct(
        private readonly string $status,
        private readonly PasswordResetDiagnosticLogger $diagnostics,
    ) {}

    public function toResponse($request)
    {
        $this->diagnostics->record('sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => trans($this->status)], 200)
            : back()->with('status', trans($this->status));
    }
}

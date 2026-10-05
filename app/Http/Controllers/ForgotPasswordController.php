<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:student_no,email'],
            'identifier' => [
                'required',
                'string',
                $request->input('mode') === 'email'
                    ? 'email'
                    : 'regex:/^\d{4}-\d{5}$/',
            ],
        ]);

        $user = $validated['mode'] === 'student_no'
            ? User::whereHas('student', function ($query) use ($validated) {
                $query->where('student_no', $validated['identifier']);
            })->first()
            : User::where('email', $validated['identifier'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'identifier' => 'We could not find an account matching that information.',
            ]);
        }

        $request->session()->put('password_reset_user_id', $user->id);
        $request->session()->put('password_reset_expires_at', now()->addMinutes(10));

        return back()->with('status', 'Account verified. You can now create a new password.');
    }

    public function reset(Request $request)
    {
        $userId = $request->session()->get('password_reset_user_id');
        $expiresAt = $request->session()->get('password_reset_expires_at');

        if (!$userId || !$expiresAt || now()->greaterThan($expiresAt)) {
            $request->session()->forget([
                'password_reset_user_id',
                'password_reset_expires_at',
            ]);

            throw ValidationException::withMessages([
                'password' => 'Your password reset session has expired. Please verify your account again.',
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::find($userId);

        if (!$user) {
            throw ValidationException::withMessages([
                'password' => 'Unable to find your account.',
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        $request->session()->forget([
            'password_reset_user_id',
            'password_reset_expires_at',
        ]);

        return redirect()
            ->route('login')
            ->with('success', 'Your password has been changed successfully. You can now log in.');
    }
}

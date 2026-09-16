<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'student_no' => [
                'required',
                'regex:/^\d{4}-\d{5}$/',
            ],
        ]);

        $user = User::where('email', $validated['email'])
            ->whereHas('student', function ($query) use ($validated) {
                $query->where('student_no', $validated['student_no']);
            })
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'The email address and student number do not match our records.',
            ]);
        }

        // Store only the user ID in the session.
        $request->session()->put('password_reset_user_id', $user->id);

        // Expire the authorization after 10 minutes.
        $request->session()->put(
            'password_reset_expires_at',
            now()->addMinutes(10)
        );

        return back();
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
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $user = User::find($userId);

        if (!$user) {
            throw ValidationException::withMessages([
                'error',
                'password' => 'Unable to find your account.',
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        // Remove reset authorization.
        $request->session()->forget([
            'password_reset_user_id',
            'password_reset_expires_at',
        ]);

        return redirect()
            ->route('login')
            ->with('success', 'Your password has been changed successfully. You can now log in.');
    }
}

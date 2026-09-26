<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    //Register
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|confirmed|regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Registration Failed",
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            DB::beginTransaction();
            $user = new User;
            $user->username = $request->username;
            $user->email = $request->email;
            $user->password = $request->password;
            $user->profile_picture = $request->file('profile_picture') ? $request->file('profile_picture')->store('profile_pictures', 'public') : null;
            $user->save();

            // Generate a verification token
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store token in the database
            $user->accountVerification()->create([
                'code' => $code,
                'expires_at' => now()->addMinutes(10),
            ]);

            // Send verification email
            Mail::to($user->email)->send(new VerificationCodeMail($user, $code));

            DB::commit();
            return response()->json([
                'message' => 'Register Successfully',
                'user' => $user,
            ], 201);
        } catch (\Exception $error) {
            DB::rollBack();
            return response()->json([
                'message' => "Server Error",
                'error' => $error,
            ], 500);
        }
    }

    //Email Verification
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            //Find the user by email
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }

            if ($user->email_verified_at != null) {
                return response()->json([
                    'message' => 'User already verified',
                ], 400);
            }

            $verification = $user->accountVerification;

            if (!$verification || $verification->code !== $request->code) {
                return response()->json(['message' => 'Invalid verification code.'], 400);
            }

            if ($verification->expires_at < now()) {
                return response()->json(['message' => 'Verification code has expired.'], 400);
            }

            // Mark the user as verified
            $user->email_verified_at = now();
            $user->save();

            // Delete the verification record
            $verification->delete();

            return response()->json([
                'message' => 'Email verified successfully',
            ], 200);
        } catch (\Exception $error) {
            return response()->json([
                'message' => "Server Error",
                'error' => $error,
            ], 500);
        }
    }

    public function verifyEmailResendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {

            //Find the user by email
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }

            if ($user->email_verified_at != null) {
                return response()->json([
                    'message' => 'User already verified',
                ], 400);
            }


            // Generate a new verification token
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::beginTransaction();

            // Store token in the database
            $user->accountVerification()->updateOrCreate(
                ['user_id' => $user->id],
                ['code' => $code, 'expires_at' => now()->addMinutes(10)]
            );

            // Send verification email
            Mail::to($user->email)->send(new VerificationCodeMail($user, $code));

            DB::commit();
            return response()->json([
                'message' => 'Verification code resent successfully',
            ], 200);
        } catch (\Exception $error) {
            DB::rollBack();
            return response()->json([
                'message' => "Server Error",
                'error' => $error,
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Login Failed",
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $throttleKey = strtolower($request->input('email')) . "|" . $request->ip();

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                $minutes = ceil($seconds / 60);
                return response()->json([
                    'message' => "Too many login attempts. Please try again in {$minutes} minute(s).",
                ], 429);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($throttleKey, 300);
                return response()->json(['message' => 'Invalid credentials.'], 401);
            }

            if ($user->email_verified_at === null) {
                return response()->json(['message' => 'Account is not verified. Please verify your email.'], 403);
            }

            RateLimiter::clear($throttleKey);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
            ], 200);
        } catch (\Exception $errors) {
            return response()->json([
                'message' => 'Server Error',
                'errors' => $errors,
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $throttleKey = 'reset|' . strtolower($request->email);

            if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                return response()->json([
                    'message' => "Too many requests. Try again in " . ceil($seconds / 60) . " minute(s).",
                ], 429);
            }

            RateLimiter::hit($throttleKey, 600);

            // Find the user by email
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }

            // Generate a new verification token
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store token in the database
            $user->passwordResetToken()->updateOrCreate(
                ['user_id' => $user->id],
                ['code' => $code, 'expires_at' => now()->addMinutes(10)]
            );

            return response()->json([
                'message' => 'Password reset code sent successfully',
            ], 200);
        } catch (\Exception $error) {
            return response()->json([
                'message' => "Server Error",
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|confirmed|regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $reset = $user->passwordResetToken;

            if (!$reset || $reset->code !== $request->code) {
                return response()->json(['message' => 'Invalid reset code.'], 400);
            }

            if ($reset->expires_at < now()) {
                return response()->json(['message' => 'Reset code has expired.'], 400);
            }

            $user->password = $request->password;
            $user->save();

            $reset->delete();

            return response()->json(['message' => 'Password reset successfully'], 200);
        } catch (\Exception $error) {
            return response()->json([
                'message' => 'Server Error',
                'error' => $error,
            ], 500);
        }
    }

    public function passwordResetCodeResend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $throttleKey = 'reset|' . strtolower($request->email);

            if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                return response()->json([
                    'message' => "Too many requests. Try again in " . ceil($seconds / 60) . " minute(s).",
                ], 429);
            }

            RateLimiter::hit($throttleKey, 600);
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::beginTransaction();

            $user->passwordResetToken()->updateOrCreate(
                ['user_id' => $user->id],
                ['code' => $code, 'expires_at' => now()->addMinutes(10)]
            );

            Mail::to($user->email)->send(new VerificationCodeMail($user, $code));

            DB::commit();
            return response()->json([
                'message' => 'Password reset code resent successfully',
            ], 200);
        } catch (\Exception $error) {
            DB::rollBack();
            return response()->json([
                'message' => 'Server Error',
                'error' => $error,
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|string|max:255|unique:users,username,' . $request->user()->id,
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Update failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $user = $request->user();

            if ($request->has('username')) {
                $user->username = $request->username;
            }

            if ($request->hasFile('profile_picture')) {
                if ($user->profile_picture) {
                    Storage::disk('public')->delete($user->profile_picture);
                }

                $user->profile_picture = $request->file('profile_picture')->store('profile_pictures', 'public');
            }

            $user->save();

            return response()->json([
                'message' => 'Profile updated successfully.',
                'user' => $user,
            ], 200);
        } catch (\Exception $error) {
            return response()->json([
                'message' => 'Server Error',
                'error' => $error,
            ], 500);
        }
    }

}

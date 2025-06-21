<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Notifications\CustomPasswordReset;
use Illuminate\Support\Facades\Log;

class PasswordResetController extends Controller
{
    /**
     * Send password reset link to user's email
     */
    public function sendResetLinkEmail(Request $request)
    {
        // Add CORS headers
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
        ];

        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email tidak ditemukan atau tidak valid.',
                    'errors' => $validator->errors()
                ], 422, $headers);
            }

            $email = $request->email;
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email tidak ditemukan dalam sistem.'
                ], 404, $headers);
            }

            // Generate reset token (plain text, akan di-hash saat disimpan)
            $token = Str::random(64);

            // Delete existing tokens for this email
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            // Create new token record (hash the token for security)
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]);

            // Log untuk debugging
            Log::info('Password reset token created', [
                'email' => $email,
                'token_plain' => $token, // Hanya untuk debugging, hapus di production
                'token_hash' => Hash::make($token)
            ]);

            // Send email notification dengan plain token
            $user->notify(new CustomPasswordReset($token));

            return response()->json([
                'success' => true,
                'message' => 'Link reset password telah dikirim ke email Anda.',
                'data' => [
                    'email' => $email,
                    'sent_at' => now()->toISOString()
                ]
            ], 200, $headers);

        } catch (\Exception $e) {
            Log::error('Password reset send error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengirim email reset password.',
                'error' => $e->getMessage()
            ], 500, $headers);
        }
    }

    /**
     * Reset user password
     */
    public function reset(Request $request)
    {
        // Add CORS headers
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
        ];

        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required',
                'email' => 'required|email|exists:users,email',
                'password' => 'required|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid.',
                    'errors' => $validator->errors()
                ], 422, $headers);
            }

            // Log untuk debugging
            Log::info('Password reset attempt', [
                'email' => $request->email,
                'token_received' => $request->token
            ]);

            // Find the password reset record
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$passwordReset) {
                Log::warning('Password reset token not found', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token reset password tidak valid atau sudah expired.'
                ], 400, $headers);
            }

            // Check if token matches
            if (!Hash::check($request->token, $passwordReset->token)) {
                Log::warning('Password reset token mismatch', [
                    'email' => $request->email,
                    'token_received' => $request->token,
                    'token_hash' => $passwordReset->token
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token reset password tidak valid.'
                ], 400, $headers);
            }

            // Check if token is expired (60 minutes)
            if (now()->diffInMinutes($passwordReset->created_at) > 60) {
                // Delete expired token
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();

                Log::warning('Password reset token expired', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token reset password sudah expired. Silakan request ulang.'
                ], 400, $headers);
            }

            // Find user and update password
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan.'
                ], 404, $headers);
            }

            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            // Delete the password reset token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            // Revoke all existing tokens for security
            $user->tokens()->delete();

            // Fire password reset event
            event(new PasswordReset($user));

            Log::info('Password reset successful', ['email' => $request->email]);

            return response()->json([
                'success' => true,
                'message' => 'Password berhasil direset. Silakan login dengan password baru.',
                'data' => [
                    'email' => $user->email,
                    'reset_at' => now()->toISOString()
                ]
            ], 200, $headers);

        } catch (\Exception $e) {
            Log::error('Password reset error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat reset password.',
                'error' => $e->getMessage()
            ], 500, $headers);
        }
    }

    /**
     * Validate reset token
     */
    public function validateToken(Request $request)
    {
        // Add CORS headers
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
        ];

        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required',
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token atau email tidak valid.',
                    'errors' => $validator->errors()
                ], 422, $headers);
            }

            // Log untuk debugging
            Log::info('Token validation attempt', [
                'email' => $request->email,
                'token_received' => $request->token
            ]);

            // Find the password reset record
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$passwordReset) {
                Log::warning('Token validation failed - not found', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak ditemukan atau sudah expired.',
                    'valid' => false
                ], 400, $headers);
            }

            // Check if token matches
            if (!Hash::check($request->token, $passwordReset->token)) {
                Log::warning('Token validation failed - mismatch', [
                    'email' => $request->email,
                    'token_received' => $request->token,
                    'token_hash' => $passwordReset->token
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid.',
                    'valid' => false
                ], 400, $headers);
            }

            // Check if token is expired (60 minutes)
            if (now()->diffInMinutes($passwordReset->created_at) > 60) {
                // Delete expired token
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();

                Log::warning('Token validation failed - expired', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token sudah expired.',
                    'valid' => false,
                    'expired' => true
                ], 400, $headers);
            }

            Log::info('Token validation successful', ['email' => $request->email]);

            return response()->json([
                'success' => true,
                'message' => 'Token valid.',
                'valid' => true,
                'data' => [
                    'email' => $request->email,
                    'expires_in_minutes' => 60 - now()->diffInMinutes($passwordReset->created_at)
                ]
            ], 200, $headers);

        } catch (\Exception $e) {
            Log::error('Token validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat validasi token.',
                'error' => $e->getMessage(),
                'valid' => false
            ], 500, $headers);
        }
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
  public function register(Request $request)
  {
      $validator = Validator::make($request->all(), [
          'name' => 'required|string|max:255',
          'email' => 'required|string|email|max:255|unique:users',
          'password' => 'required|string|min:8|confirmed',
          'role' => 'required|string|in:student,teacher',
      ]);

      if ($validator->fails()) {
          return response()->json(['errors' => $validator->errors()], 422);
      }

      $user = User::create([
          'name' => $request->name,
          'email' => $request->email,
          'password' => Hash::make($request->password),
      ]);

      $role = Role::where('slug', $request->role)->first();
      if ($role) {
          $user->roles()->attach($role);
      }

      // Create default settings for the user
      Setting::create([
          'user_id' => $user->id,
      ]);

      // Trigger email verification
      event(new Registered($user));

      Log::info('User registered, verification email sent', [
          'user_id' => $user->id,
          'email' => $user->email,
          'role' => $request->role
      ]);

      return response()->json([
          'message' => 'Registration successful. Please check your email to verify your account.',
          'user' => [
              'id' => $user->id,
              'name' => $user->name,
              'email' => $user->email,
              'email_verified_at' => $user->email_verified_at,
          ],
          'role' => $request->role,
          'verification_required' => true,
      ], 201);
  }

  public function login(Request $request)
  {
      $validator = Validator::make($request->all(), [
          'email' => 'required|string|email',
          'password' => 'required|string',
      ]);

      if ($validator->fails()) {
          return response()->json(['errors' => $validator->errors()], 422);
      }

      if (!Auth::attempt($request->only('email', 'password'))) {
          return response()->json([
              'message' => 'Email atau Password salah!',
          ], 401);
      }

      $user = User::where('email', $request->email)->firstOrFail();

      // Check if email is verified
      if (!$user->hasVerifiedEmail()) {
          return response()->json([
              'message' => 'Email belum diverifikasi. Silakan periksa email Anda dan verifikasi akun Anda!',
              'email_verified' => false,
              'user_id' => $user->id,
          ], 403);
      }

      // Hapus token lama
      if (method_exists($user, 'tokens')) {
          $user->tokens()->delete();
      }

      // Get user roles for token abilities
      $roleSlugs = $user->getRoleSlugs();

      // Create token with role abilities
      $token = $user->createToken('auth_token', $roleSlugs)->plainTextToken;

      // Get user role with null check
      $role = $user->roles()->first();
      $roleSlug = $role ? $role->slug : null;

      Log::info('User logged in successfully', [
          'user_id' => $user->id,
          'roles' => $roleSlugs,
          'primary_role' => $roleSlug,
          'token_created' => true,
          'token_abilities' => $roleSlugs
      ]);

      return response()->json([
          'user' => $user,
          'role' => $roleSlug,
          'token' => $token,
          'email_verified' => true,
      ]);
  }

  public function logout(Request $request)
  {
      $request->user()->currentAccessToken()->delete();

      return response()->json([
          'message' => 'Berhasil Logout...',
      ]);
  }

  public function user(Request $request)
  {
      $user = $request->user();
      $role = $user->roles()->first();
      $roleSlug = $role ? $role->slug : null;
      $roleSlugs = $user->getRoleSlugs();

      Log::info('User profile accessed', [
          'user_id' => $user->id,
          'email' => $user->email,
          'roles' => $roleSlugs,
          'primary_role' => $roleSlug,
          'token_abilities' => $request->user()->currentAccessToken()->abilities ?? []
      ]);

      return response()->json([
          'user' => $user,
          'role' => $roleSlug,
      ]);
  }

  public function updateProfile(Request $request)
  {
      $validator = Validator::make($request->all(), [
          'name' => 'required|string|max:255',
          'bio' => 'nullable|string',
          'profile_photo' => 'nullable|image|max:10240',
      ]);

      if ($validator->fails()) {
          return response()->json(['errors' => $validator->errors()], 422);
      }

      $user = $request->user();

      if ($request->hasFile('profile_photo')) {
          // Delete old photo if exists
          if ($user->profile_photo) {
              Storage::disk('public')->delete($user->profile_photo);
          }

          $photoPath = $request->file('profile_photo')->store('profile_photos', 'public');
          $user->profile_photo = $photoPath;
      }

      $user->name = $request->name;
      $user->bio = $request->bio;
      $user->save();

      return response()->json([
          'message' => 'Profile updated successfully',
          'user' => $user
      ]);
  }

  /**
   * Verify email address - TANPA CSRF PROTECTION
   */
  public function verifyEmail(Request $request)
  {
      // Add CORS headers explicitly
      $headers = [
          'Access-Control-Allow-Origin' => '*',
          'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
          'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
      ];

      Log::info('Email verification request received', [
          'request_data' => $request->all(),
          'method' => $request->method(),
          'url' => $request->fullUrl(),
          'headers' => $request->headers->all()
      ]);

      $validator = Validator::make($request->all(), [
          'id' => 'required|integer',
          'hash' => 'required|string',
          'expires' => 'required|integer',
          'signature' => 'required|string',
      ]);

      if ($validator->fails()) {
          Log::error('Validation failed for email verification', [
              'errors' => $validator->errors(),
              'request_data' => $request->all()
          ]);
          return response()->json(['errors' => $validator->errors()], 422, $headers);
      }

      try {
          // Find user
          $user = User::findOrFail($request->id);

          // Check if already verified
          if ($user->hasVerifiedEmail()) {
              return response()->json([
                  'message' => 'Email already verified',
                  'verified' => true,
              ], 200, $headers);
          }

          // Verify hash matches user email
          if (!hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
              Log::error('Hash mismatch for email verification', [
                  'user_id' => $user->id,
                  'provided_hash' => $request->hash,
                  'expected_hash' => sha1($user->getEmailForVerification())
              ]);
              return response()->json([
                  'message' => 'Invalid verification link',
              ], 400, $headers);
          }

          // Check if link has expired
          if (Carbon::now()->timestamp > $request->expires) {
              Log::error('Verification link expired', [
                  'user_id' => $user->id,
                  'expires' => $request->expires,
                  'current_time' => Carbon::now()->timestamp
              ]);
              return response()->json([
                  'message' => 'Verification link has expired',
                  'expired' => true,
              ], 400, $headers);
          }

          // Verify signature
          $expectedUrl = URL::temporarySignedRoute(
              'verification.verify',
              Carbon::createFromTimestamp($request->expires),
              [
                  'id' => $request->id,
                  'hash' => $request->hash,
              ]
          );

          $expectedParsed = parse_url($expectedUrl);
          parse_str($expectedParsed['query'], $expectedParams);

          if ($expectedParams['signature'] !== $request->signature) {
              Log::error('Signature mismatch for email verification', [
                  'user_id' => $user->id,
                  'provided_signature' => $request->signature,
                  'expected_signature' => $expectedParams['signature']
              ]);
              return response()->json([
                  'message' => 'Invalid verification signature',
              ], 400, $headers);
          }

          // Mark email as verified
          $user->markEmailAsVerified();
          event(new Verified($user));

          Log::info('Email Berhasil Diverifikasi!', [
              'user_id' => $user->id,
              'email' => $user->email
          ]);

          return response()->json([
              'message' => 'Email Berhasil Diverifikasi! Kamu dapat login sekarang...',
              'verified' => true,
          ], 200, $headers);

      } catch (\Exception $e) {
          Log::error('Email verification error', [
              'error' => $e->getMessage(),
              'trace' => $e->getTraceAsString(),
              'request_data' => $request->all()
          ]);

          return response()->json([
              'message' => 'Verification failed: ' . $e->getMessage(),
          ], 500, $headers);
      }
  }

  /**
   * Resend verification email
   */
  public function resendVerificationEmail(Request $request)
  {
      $headers = [
          'Access-Control-Allow-Origin' => '*',
          'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
          'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
      ];

      $validator = Validator::make($request->all(), [
          'email' => 'required|email|exists:users,email',
      ]);

      if ($validator->fails()) {
          return response()->json(['errors' => $validator->errors()], 422, $headers);
      }

      $user = User::where('email', $request->email)->first();

      if (!$user) {
          return response()->json([
              'message' => 'User not found',
          ], 404, $headers);
      }

      if ($user->hasVerifiedEmail()) {
          return response()->json([
              'message' => 'Email already verified',
              'verified' => true,
          ], 200, $headers);
      }

      // Send verification email
      $user->sendEmailVerificationNotification();

      Log::info('Verification email resent', [
          'user_id' => $user->id,
          'email' => $user->email
      ]);

      return response()->json([
          'message' => 'Verification email sent successfully',
      ], 200, $headers);
  }

  /**
   * Check email verification status
   */
  public function checkVerificationStatus(Request $request)
  {
      $headers = [
          'Access-Control-Allow-Origin' => '*',
          'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
          'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
      ];

      $validator = Validator::make($request->all(), [
          'email' => 'required|email|exists:users,email',
      ]);

      if ($validator->fails()) {
          return response()->json(['errors' => $validator->errors()], 422, $headers);
      }

      $user = User::where('email', $request->email)->first();

      if (!$user) {
          return response()->json([
              'message' => 'User not found',
          ], 404, $headers);
      }

      return response()->json([
          'email_verified' => $user->hasVerifiedEmail(),
          'verified_at' => $user->email_verified_at,
      ], 200, $headers);
  }
}


// namespace App\Http\Controllers\API;

// use App\Http\Controllers\Controller;
// use App\Models\Role;
// use App\Models\Setting;
// use App\Models\User;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Hash;
// use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Facades\Validator;

// class AuthController extends Controller
// {
//     public function register(Request $request)
//     {
//         $validator = Validator::make($request->all(), [
//             'name' => 'required|string|max:255',
//             'email' => 'required|string|email|max:255|unique:users',
//             'password' => 'required|string|min:8|confirmed',
//             'role' => 'required|string|in:student,teacher',
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], 422);
//         }

//         $user = User::create([
//             'name' => $request->name,
//             'email' => $request->email,
//             'password' => Hash::make($request->password),
//             'email_verified_at' => now(), // Auto-verify email
//         ]);

//         $role = Role::where('slug', $request->role)->first();
//         $user->roles()->attach($role);

//         // Create default settings for the user
//         Setting::create([
//             'user_id' => $user->id,
//         ]);

//         // Hapus token lama jika ada
//         if (method_exists($user, 'tokens')) {
//             $user->tokens()->delete();
//         }

//         $token = $user->createToken('auth_token')->plainTextToken;

//         return response()->json([
//             'user' => $user,
//             'role' => $request->role,
//             'token' => $token,
//         ], 201);
//     }

//     public function login(Request $request)
//     {
//         $validator = Validator::make($request->all(), [
//             'email' => 'required|string|email',
//             'password' => 'required|string',
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], 422);
//         }

//         if (!Auth::attempt($request->only('email', 'password'))) {
//             return response()->json([
//                 'message' => 'Invalid login credentials',
//             ], 401);
//         }

//         $user = User::where('email', $request->email)->firstOrFail();

//         // Hapus token lama
//         if (method_exists($user, 'tokens')) {
//             $user->tokens()->delete();
//         }

//         $token = $user->createToken('auth_token')->plainTextToken;

//         // Get user role with null check
//         $role = $user->roles()->first();
//         $roleSlug = $role ? $role->slug : null;

//         return response()->json([
//             'user' => $user,
//             'role' => $roleSlug,
//             'token' => $token,
//         ]);
//     }

//     public function logout(Request $request)
//     {
//         $request->user()->currentAccessToken()->delete();

//         return response()->json([
//             'message' => 'Logged out successfully',
//         ]);
//     }

//     public function user(Request $request)
//     {
//         $user = $request->user();
//         $role = $user->roles()->first();
//         $roleSlug = $role ? $role->slug : null;

//         return response()->json([
//             'user' => $user,
//             'role' => $roleSlug,
//         ]);
//     }

//     public function updateProfile(Request $request)
//     {
//         $validator = Validator::make($request->all(), [
//             'name' => 'required|string|max:255',
//             'bio' => 'nullable|string',
//             'profile_photo' => 'nullable|image|max:2048',
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], 422);
//         }

//         $user = $request->user();

//         if ($request->hasFile('profile_photo')) {
//             // Delete old photo if exists
//             if ($user->profile_photo) {
//                 Storage::disk('public')->delete($user->profile_photo);
//             }

//             $photoPath = $request->file('profile_photo')->store('profile_photos', 'public');
//             $user->profile_photo = $photoPath;
//         }

//         $user->name = $request->name;
//         $user->bio = $request->bio;
//         $user->save();

//         return response()->json([
//             'message' => 'Profile updated successfully',
//             'user' => $user
//         ]);
//     }
// }

<?php

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
// use Illuminate\Support\Facades\Log;

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

//         // Tentukan abilities berdasarkan role
//         $abilities = $this->getAbilitiesForRole($request->role);

//         $token = $user->createToken('auth_token', $abilities)->plainTextToken;

//         Log::info('User registered', [
//             'user_id' => $user->id,
//             'role' => $request->role,
//             'token_id' => explode('|', $token)[0],
//             'abilities' => $abilities
//         ]);

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

//         // Hapus semua token lama
//         $user->tokens()->delete();

//         // Get user role with eager loading
//         $user->load('roles');
//         $role = $user->roles()->first();
//         $roleSlug = $role ? $role->slug : null;

//         // Tentukan abilities berdasarkan role
//         $abilities = $this->getAbilitiesForRole($roleSlug);

//         // Buat token baru dengan abilities
//         $token = $user->createToken('auth_token', $abilities)->plainTextToken;

//         Log::info('User logged in', [
//             'user_id' => $user->id,
//             'role' => $roleSlug,
//             'token_id' => explode('|', $token)[0],
//             'abilities' => $abilities
//         ]);

//         return response()->json([
//             'user' => $user,
//             'role' => $roleSlug,
//             'token' => $token,
//         ]);
//     }
//     // Tambahkan method ini di AuthController
//     public function token(Request $request)
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

//         // Hapus semua token lama
//         $user->tokens()->delete();

//         // Get user role with eager loading
//         $user->load('roles');
//         $role = $user->roles()->first();
//         $roleSlug = $role ? $role->slug : null;

//         // Tentukan abilities berdasarkan role
//         $abilities = $this->getAbilitiesForRole($roleSlug);

//         // Buat token baru dengan abilities
//         $token = $user->createToken('auth_token', $abilities)->plainTextToken;

//         Log::info('User token created', [
//             'user_id' => $user->id,
//             'role' => $roleSlug,
//             'token_id' => explode('|', $token)[0],
//             'abilities' => $abilities
//         ]);

//         return response()->json([
//             'user' => $user,
//             'role' => $roleSlug,
//             'token' => $token,
//         ]);
//     }

//     public function logout(Request $request)
//     {
//         $tokenId = $request->user()->currentAccessToken()->id;
//         $request->user()->currentAccessToken()->delete();

//         Log::info('User logged out', [
//             'user_id' => $request->user()->id,
//             'token_id' => $tokenId
//         ]);

//         return response()->json([
//             'message' => 'Logged out successfully',
//         ]);
//     }

//     public function user(Request $request)
//     {
//         $user = $request->user()->load('roles');
//         $role = $user->roles()->first();
//         $roleSlug = $role ? $role->slug : null;

//         Log::info('User info requested', [
//             'user_id' => $user->id,
//             'role' => $roleSlug,
//             'token_id' => $request->user()->currentAccessToken()->id,
//             'token_abilities' => $request->user()->currentAccessToken()->abilities
//         ]);

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

//     /**
//      * Mendapatkan abilities berdasarkan role
//      */
//     private function getAbilitiesForRole(?string $role): array
//     {
//         if (!$role) {
//             return [];
//         }

//         switch ($role) {
//             case 'teacher':
//                 return ['*']; // Teacher dapat melakukan semua
//             case 'student':
//                 return ['view', 'progress']; // Student hanya dapat melihat dan update progress
//             default:
//                 return [];
//         }
//     }
// }

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
            'email_verified_at' => now(), // Auto-verify email
        ]);

        $role = Role::where('slug', $request->role)->first();
        $user->roles()->attach($role);

        // Create default settings for the user
        Setting::create([
            'user_id' => $user->id,
        ]);

        // Hapus token lama jika ada
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'role' => $request->role,
            'token' => $token,
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
                'message' => 'Invalid login credentials',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Hapus token lama
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Get user role with null check
        $role = $user->roles()->first();
        $roleSlug = $role ? $role->slug : null;

        return response()->json([
            'user' => $user,
            'role' => $roleSlug,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $role = $user->roles()->first();
        $roleSlug = $role ? $role->slug : null;

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
            'profile_photo' => 'nullable|image|max:2048',
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
}

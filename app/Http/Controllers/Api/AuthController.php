<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Jobs\SendWelcomeMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = DB::transaction(function () use ($request) {
                return User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);
            });

            SendWelcomeMail::dispatch($user);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'user' => new UserResource($user),
                'token' => $token,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Registration failed', ['email' => $request->email, 'error' => $e]);

            return response()->json(['message' => 'Registration failed. Please try again.'], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            if (! Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'message' => 'The email or password you entered is incorrect.',
                ], 401);
            }

            /** @var User $user */
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'user' => new UserResource($user),
                'token' => $token,
            ]);
        } catch (\Throwable $e) {
            Log::error('Login failed', ['email' => $request->email, 'error' => $e]);

            return response()->json(['message' => 'Login failed. Please try again.'], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->user()->currentAccessToken();

            // currentAccessToken() returns a TransientToken when the request is
            // authenticated by a guard other than a real personal access token
            // (e.g. actingAs in tests). Only persisted tokens can be deleted.
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json(['message' => 'Logged out successfully']);
        } catch (\Throwable $e) {
            Log::error('Logout failed', ['error' => $e]);

            return response()->json(['message' => 'Logout failed. Please try again.'], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'user' => new UserResource($request->user()),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch user profile', ['error' => $e]);

            return response()->json(['message' => 'Failed to fetch your profile. Please try again.'], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\CrmUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController
{
    /**
     * Handle login request and return API token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'Email' => 'required|email',
            'Password' => 'required',
            'current_LocalizationsID' => 'sometimes|integer',
        ]);

        $email = $request->input('Email');
        $password = $request->input('Password');

        $user = CrmUser::where('login', $email)->first();

        // Match CRM's authentication logic: try password_verify first, then plaintext comparison
        if (!$user || !(Hash::check($password, $user->Password) || $user->Password === $password)) {
            \Illuminate\Support\Facades\Log::warning('Login failed', [
                'email' => $email,
                'user_exists' => (bool) $user,
            ]);
            throw ValidationException::withMessages([
                'Email' => ['The provided credentials are incorrect.'],
            ]);
        }

        \Illuminate\Support\Facades\Log::info('Login successful', [
            'email' => $email,
            'user_id' => $user->UsersID,
        ]);

        // Usuń poprzednie tokeny (opcjonalnie)
        $user->tokens()->delete();

        // Utworz nowy token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->UsersID,
                'email' => $user->Email,
                'first_name' => $user->FirstName,
                'last_name' => $user->LastName,
                'role' => 2,
            ],
        ]);
    }

    /**
     * Logout and revoke the token.
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}

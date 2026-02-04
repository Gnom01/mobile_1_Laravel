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

        $user = CrmUser::where('Email', $email)->first();

        if (!$user || !Hash::check($password, $user->Password)) {
            throw ValidationException::withMessages([
                'Email' => ['The provided credentials are incorrect.'],
            ]);
        }

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

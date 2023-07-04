<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        $name = $request->input('name');
        $user = $request->input('user');
        $email = $request->input('email');
        $rol = $request->input('rol');
        $password = Hash::make($request->input('password'));

        $register = User::create([
            'name' => $name,
            'user' => $user,
            'rol' => $rol,
            'email' => $email,
            'password' => $password
        ]);

        if ($register) {
            return response()->json([
                'success' => true,
                'message' => 'Register Success!',
                'data' => $register,
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Register Fail!',
                'data' => '',
            ], 401);
        }
    }

    public function login(Request $request)
    {
        //validate incoming request 
        $this->validate($request, [
            'user' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only(['user', 'password']);

        // if (!$token = JWTAuth::attempt($credentials)) {
        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['message' => 'Usuario o contraseña incorrecto'], 401);
        }

        $auth = JWTAuth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 3600,
            'user' => $auth,
            'inventory' => $company->inventory === 1,
            'decimal' => $company->decimal
        ]);
    }

    public function me()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        return response()->json([
            'user' => $auth,
            'inventory' => $company->inventory === 1,
            'decimal' => $company->decimal
        ]);
    }

    public function refreshToken()
    {
        $refreshed = JWTAuth::refresh(JWTAuth::getToken());
        JWTAuth::setToken($refreshed)->toUser();
        return response()->json([
            'token' => $refreshed
        ]);
    }

    public function logout()
    {
        Auth::logout();
        return response()->json(['logout' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\UsuarioResource;
use App\Models\User;
use App\Support\ApiProblem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        $r->merge(['correo' => mb_strtolower(trim((string) $r->input('correo')))]);
        $d = $r->validate(['nombre' => 'required|string|max:100', 'apellido' => 'required|string|max:100',
            'correo' => 'required|email|max:150|unique:Usuarios,Correo', 'telefono' => 'required|string|max:20',
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'rol' => 'prohibited', 'activo' => 'prohibited', 'Rol' => 'prohibited', 'Activo' => 'prohibited']);
        $user = User::create(['Nombre' => $d['nombre'], 'Apellido' => $d['apellido'], 'Correo' => $d['correo'], 'Telefono' => $d['telefono'],
            'PasswordHash' => $d['password'], 'Rol' => 'Usuario', 'Activo' => true, 'FechaRegistro' => now('UTC')]);

        return response()->json(['data' => new UsuarioResource($user)], 201);
    }

    public function login(Request $r)
    {
        $d = $r->validate(['correo' => 'required|email', 'password' => 'required|string']);
        $user = User::where('Correo', mb_strtolower(trim($d['correo'])))->first();
        if (! $user || ! $user->Activo || ! Hash::check($d['password'], $user->PasswordHash)) {
            throw new ApiProblem('credenciales_invalidas', 'Correo o contraseña incorrectos.', 401);
        }
        if (Hash::needsRehash($user->PasswordHash)) {
            $user->forceFill(['PasswordHash' => $d['password']])->save();
        }
        $expires = now()->addMinutes(config('subastas.token_minutes'));
        $token = $user->createToken('api', ['*'], $expires);

        return response()->json(['data' => ['usuario' => new UsuarioResource($user), 'token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $expires->toIso8601String()]]);
    }

    public function me(Request $r)
    {
        return new UsuarioResource($r->user());
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}

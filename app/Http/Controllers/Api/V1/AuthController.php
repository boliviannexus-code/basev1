<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\Tourist\RegisterTouristRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            return $this->errorResponse('Las credenciales no son validas.', null, 422);
        }

        $user = $request->user()->load('roles');
        $token = $user->createToken('api-token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Sesion iniciada correctamente.');
    }

    public function registerTourist(RegisterTouristRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());
        Role::findOrCreate('tourist', 'web');
        $user->assignRole('tourist');
        $user->load('roles');

        return $this->successResponse([
            'token' => $user->createToken('api-token')->plainTextToken,
            'user' => new UserResource($user),
        ], 'Cuenta creada correctamente.', 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->successResponse(null, 'Sesion cerrada correctamente.');
    }
}

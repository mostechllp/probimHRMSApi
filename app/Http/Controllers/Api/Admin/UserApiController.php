<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $users = User::with('role')->get();
        return $this->success(UserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        
        $user = User::create($data);
        return $this->success(new UserResource($user), 'User created successfully', 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('role');
        return $this->success(new UserResource($user));
    }
}

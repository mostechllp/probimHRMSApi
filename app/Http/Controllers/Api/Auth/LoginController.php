<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class LoginController extends ApiController
{
    #[OA\Post(
        path: "/api/auth/login",
        operationId: "loginUser",
        summary: "Login user",
        description: "Login and return an access token",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["username", "password"],
            properties: [
                new OA\Property(property: "username", type: "string", example: "admin@example.com"),
                new OA\Property(property: "password", type: "string", example: "password123")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Successful login",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "access_token", type: "string"),
                new OA\Property(property: "token_type", type: "string", example: "bearer"),
                new OA\Property(property: "user", type: "object")
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Unauthorized")]
    #[OA\Response(response: 422, description: "Validation Error")]
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Error', 422, $validator->errors());
        }

        $loginField = filter_var($request->username, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        if (
            !$token = auth('api')->attempt([
                $loginField => $request->username,
                'password' => $request->password
            ])
        ) {
            return $this->error('Unauthorized - Invalid credentials', 401);
        }

        $user = auth('api')->user();

        if ($user->status === 'onboarding') {
            auth('api')->logout();
            return $this->error('Account is under onboarding and is not yet active.', 403);
        }

        if ($user->status !== 'active') {
            auth('api')->logout();
            return $this->error('Account is inactive. Please contact the administrator.', 403);
        }

        return $this->respondWithToken($token, $user);
    }

    /**
     * Response with token + user + employee + roles
     */
    protected function respondWithToken($token, $user): JsonResponse
    {
        $employee = $user->employee;
        $role = $user->role;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,

            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
                'avatar' => $employee ? $employee->avatar_url : $user->avatar_url,
                'type' => $user->type,
                'role' => $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                ] : null,
                'employee' => $employee ? [
                    'id' => $employee->id,
                    'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                    'employee_id' => $employee->employee_id,
                ] : null,
                'permissions' => $this->formatPermissions($user),
                'sidebar_modules' => $this->formatSidebarModules($user),
            ]
        ], 'Login successful');
    }

    #[OA\Post(
        path: "/api/auth/logout",
        operationId: "logoutUser",
        summary: "Logout user",
        description: "Logout the currently authenticated user",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(response: 200, description: "Successfully logged out")]
    #[OA\Response(response: 401, description: "Unauthenticated")]
    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return $this->success(null, 'Successfully logged out');
    }

    #[OA\Post(
        path: "/api/auth/refresh",
        operationId: "refreshToken",
        summary: "Refresh auth token",
        description: "Refresh the current access token",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(
        response: 200,
        description: "Token refreshed successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "access_token", type: "string"),
                new OA\Property(property: "token_type", type: "string", example: "bearer"),
                new OA\Property(property: "user", type: "object")
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Unauthenticated")]
    public function refresh(): JsonResponse
    {
        try {
            $token = auth('api')->refresh();
            $user = auth('api')->setToken($token)->user();

            if (!$user) {
                return $this->error('User not found', 404);
            }

            return $this->respondWithToken($token, $user);
        } catch (\Exception $e) {
            return $this->error('Token could not be refreshed. ' . $e->getMessage(), 401);
        }
    }

    #[OA\Get(
        path: "/api/auth/me",
        operationId: "getAuthenticatedUser",
        summary: "Get logged-in user",
        description: "Get details of the currently authenticated user",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"]
    )]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 401, description: "Unauthenticated")]
    public function me(): JsonResponse
    {
        if (!auth('api')->check()) {
            return $this->error('Unauthenticated', 401);
        }

        $user = auth('api')->user();
        return $this->respondWithToken(null, $user);
    }

    public function getMyPermissions(): JsonResponse
    {
        $user = auth('api')->user();
        return $this->success($this->formatPermissions($user));
    }

    public function getMySidebarModules(): JsonResponse
    {
        $user = auth('api')->user();
        return $this->success($this->formatSidebarModules($user));
    }

    protected function formatPermissions($user)
    {
        if (!$user->role)
            return [];

        // if ($user->role->name === 'Admin') {
        //     return ['all' => true];
        // }

        return $user->role->permissions->mapWithKeys(function ($p) {
            return [
                $p->module->slug => [
                    'read' => (bool) $p->can_read,
                    'edit' => (bool) $p->can_edit,
                    'delete' => (bool) $p->can_delete,
                ]
            ];
        });
    }

    protected function formatSidebarModules($user)
    {
        if (!$user->role)
            return [];

        // if ($user->role->name === 'Admin') {
        //     return \App\Models\Module::where('status', 'active')->get();
        // }

        return $user->role->permissions()
            ->where('can_read', true)
            ->with([
                'module' => function ($q) {
                    $q->where('status', 'active');
                }
            ])
            ->get()
            ->pluck('module')
            ->filter();
    }
}
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Role;
use App\Models\RolePermission;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Requests\UpdateRolePermissionRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\JsonResponse;

class RoleApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions.module')->get();
        return $this->success(RoleResource::collection($roles));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create($request->validated());
        return $this->success(new RoleResource($role), 'Role created successfully', 201);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions.module');
        return $this->success(new RoleResource($role));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role->update($request->validated());
        return $this->success(new RoleResource($role), 'Role updated successfully');
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();
        return $this->success(null, 'Role deleted successfully');
    }

    public function getPermissions(Role $role): JsonResponse
    {
        $permissions = $role->permissions()->with('module')->get();
        return $this->success(PermissionResource::collection($permissions));
    }

    public function updatePermissions(UpdateRolePermissionRequest $request, Role $role): JsonResponse
    {
        $role->permissions()->delete();

        foreach ($request->permissions as $perm) {
            $role->permissions()->create([
                'module_id' => $perm['module_id'],
                'can_read' => $perm['can_read'] ?? false,
                'can_edit' => $perm['can_edit'] ?? false,
                'can_delete' => $perm['can_delete'] ?? false,
            ]);
        }

        return $this->success(PermissionResource::collection($role->permissions()->with('module')->get()), 'Permissions updated successfully');
    }
}

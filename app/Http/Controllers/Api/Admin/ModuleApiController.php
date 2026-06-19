<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Module;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Http\Resources\ModuleResource;
use Illuminate\Http\JsonResponse;

class ModuleApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $modules = Module::all();
        return $this->success(ModuleResource::collection($modules));
    }

    public function store(StoreModuleRequest $request): JsonResponse
    {
        $module = Module::create($request->validated());
        return $this->success(new ModuleResource($module), 'Module created successfully', 201);
    }

    public function show(Module $module): JsonResponse
    {
        return $this->success(new ModuleResource($module));
    }

    public function update(UpdateModuleRequest $request, Module $module): JsonResponse
    {
        $module->update($request->validated());
        return $this->success(new ModuleResource($module), 'Module updated successfully');
    }

    public function destroy(Module $module): JsonResponse
    {
        $module->delete();
        return $this->success(null, 'Module deleted successfully');
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\EmployeeSalaryComponent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeSalaryComponentApiController extends ApiController
{
    public function update(Request $request, $id): JsonResponse
    {
        $component = EmployeeSalaryComponent::findOrFail($id);

        $validated = $request->validate([
            'component_name' => 'required|string|max:255',
            'value' => 'required|numeric|min:0',
        ]);

        $component->update($validated);

        return $this->success($component, 'Salary component updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $component = EmployeeSalaryComponent::findOrFail($id);
        $component->delete();

        return $this->success(null, 'Salary component deleted successfully');
    }
}

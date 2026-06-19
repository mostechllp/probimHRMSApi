<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\EmployeeBankDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeBankDetailApiController extends ApiController
{
    public function update(Request $request, $id): JsonResponse
    {
        $bank = EmployeeBankDetail::findOrFail($id);

        $validated = $request->validate([
            'bank_country' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'iban_number' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'ifsc_code' => 'nullable|string|max:255',
        ]);

        $bank->update($validated);

        return $this->success($bank, 'Bank details updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $bank = EmployeeBankDetail::findOrFail($id);
        $bank->delete();

        return $this->success(null, 'Bank details deleted successfully');
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class CompanyApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organization_id = $request->get('organization_id');
        $companies = Company::with('organization')
            ->when($organization_id, function ($query) use ($organization_id) {
                return $query->where('organization_id', $organization_id);
            })
            ->latest()
            ->get();

        return $this->success($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'company_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address' => 'nullable|string',
            'trade_license' => 'nullable|in:freezone,mainland'
        ]);

        $data = $request->except('logo');

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos/companies', 'public');
        }

        $company = Company::create($data);

        return $this->success($company, 'Company created successfully', 201);
    }

    public function show(Company $company): JsonResponse
    {
        return $this->success($company->load('organization'));
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'company_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address' => 'nullable|string',
            'trade_license' => 'nullable|in:freezone,mainland'
        ]);

        $data = $request->except('logo');

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos/companies', 'public');
        }

        $company->update($data);

        return $this->success($company, 'Company updated successfully');
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();
        return $this->success(null, 'Company deleted successfully');
    }
}

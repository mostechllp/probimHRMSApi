<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $organization_id = $request->get('organization_id');
        $companies = Company::with('organization')
            ->when($organization_id, function($query) use ($organization_id) {
                return $query->where('organization_id', $organization_id);
            })
            ->latest()
            ->get();
        
        return view('admin.companies.index', compact('companies', 'organization_id'));
    }

    public function getByOrganization($organization_id)
    {
        $companies = Company::where('organization_id', $organization_id)->get();
        return response()->json($companies);
    }

    public function create(Request $request)
    {
        $organization_id = $request->get('organization_id');
        $organizations = organization::where('has_multiple_companies', true)->get();
        return view('admin.companies.create', compact('organization_id', 'organizations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'company_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address' => 'nullable|string',
        ]);

        $data = $request->except('logo');
        
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos/companies', 'public');
            $data['logo'] = $path;
        }

        $company = Company::create($data);

        // If AJAX request
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'company' => $company
            ]);
        }

        // Normal form submit

        return redirect()->route('companies.index', ['organization_id' => $request->organization_id])
            ->with('success', 'Company created successfully.');
    }

    public function edit(Company $company)
    {
        $organizations = organization::where('has_multiple_companies', true)->get();
        return view('admin.companies.edit', compact('company', 'organizations'));
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'company_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address' => 'nullable|string',
        ]);

        $data = $request->except('logo');

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $path = $request->file('logo')->store('logos/companies', 'public');
            $data['logo'] = $path;
        }

        $company->update($data);

        return redirect()->route('companies.index', ['organization_id' => $company->organization_id])
            ->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company)
    {
        $org_id = $company->organization_id;
        $company->delete();
        return redirect()->route('companies.index', ['organization_id' => $org_id])
            ->with('success', 'Company deleted successfully.');
    }
}

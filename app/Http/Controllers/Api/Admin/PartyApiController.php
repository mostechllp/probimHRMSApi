<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PartyApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $parties = Party::latest()->paginate($perPage);
        return $this->success($parties);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $validated['created_by'] = auth()->id();
        $party = Party::create($validated);
        return $this->success($party, 'Party created successfully', 201);
    }

    public function show(Party $party): JsonResponse
    {
        return $this->success($party);
    }

    public function update(Request $request, Party $party): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $party->update($validated);
        return $this->success($party, 'Party updated successfully');
    }

    public function destroy(Party $party): JsonResponse
    {
        $party->update(['deleted_by' => auth()->id()]);
        $party->delete();
        return $this->success(null, 'Party deleted successfully');
    }
}

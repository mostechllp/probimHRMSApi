<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\User;
use App\Models\Party;
use App\Models\Employee;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);

        $organization_files = Document::where('type', 'organization')->latest()->get();
        $agreements = Document::where('type', 'agreement')->latest()->get();
        $others = Document::where('type', 'others')->latest()->get();
        $hr = Document::where('type', 'hr')->latest()->get();
        $folders = Document::select('folder')
            ->distinct()
            ->pluck('folder');
        $share_with = User::select('id', 'name')->get();
        $employees = Employee::with(['company', 'department'])
            ->where('status', 'active')
            ->get();


        return view('documents.documents', compact('organization_files', 'agreements', 'others', 'hr', 'folders', 'employees', 'share_with'));
    }

    public function upload(Request $request)
    {
        if ($request->hasFile('file')) {

            $file = $request->file('file');

            $path = $file->store('temp', 'public');

            return response()->json([
                'path' => $path,
                'filename' => $file->getClientOriginalName()
            ]);
        }
    }

    public function store(Request $request)
    {
        $folder = $request->folder;

        // create folder if not exists
        if (!Storage::disk('public')->exists('documents/' . $folder)) {
            Storage::disk('public')->makeDirectory('documents/' . $folder);
        }

        $tempFile = $request->file_path;

        $filename = basename($tempFile);

        $newPath = 'documents/' . $folder . '/' . $filename;

        Storage::disk('public')->move($tempFile, $newPath);

        Document::create([
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
            'file_path' => $newPath,
            'folder' => $folder,
            'party_id' => $request->party_id,
            'share_with' => $request->share_with,
            'expiry_date' => $request->expiry_date
        ]);

        return response()->json([
            'success' => true
        ]);
    }

    public function storeParty(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $party = Party::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Party created successfully',
            'data' => $party
        ], 200);
    }

    public function deleteDocument($id)
    {
        Document::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}

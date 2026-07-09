<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Document;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class DocumentApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type');
        $folder = $request->get('folder');
        $perPage = $request->get('per_page', 15);

        $query = Document::join('folders', 'documents.folder_id', '=', 'folders.id')
            ->select('documents.*', 'folders.name as folder_name')
            ->with(['party'])
            ->latest();

        if ($type) {
            $query->where('type', $type);
        }

        if ($folder) {
            $query->where('folder', $folder);
        }

        $documents = $query->paginate($perPage);
        $documents->getCollection()->each->append('shared_users');

        return $this->success($documents);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB limit
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('temp', 'public');

            return $this->success([
                'path' => $path,
                'filename' => $file->getClientOriginalName()
            ], 'File uploaded to temporary storage');
        }

        return $this->error('No file uploaded', 400);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:organization,agreements,hr,others',
            'description' => 'nullable|string',
            'file_path' => 'sometimes|nullable|string|starts_with:temp/',
            'folder_id' => 'required|exists:folders,id',
            'party_id' => 'nullable|exists:parties,id',
            'share_with' => 'nullable|array',
            'expiry_date' => 'nullable|date|after:today'
        ]);

        $folder_id = $request->folder_id;
        $folder = Folder::find($folder_id);
        $folder_name = $folder->name;
        $tempPath = $request->file_path;

        // Ensure temporary file exists
        if (!Storage::disk('public')->exists($tempPath)) {
            return $this->error('Temporary file not found', 404);
        }

        $filename = basename($tempPath);
        $newPath = 'documents/' . $folder_name . '/' . $filename;

        // Move file from temp to final destination
        Storage::disk('public')->move($tempPath, $newPath);

        $document = Document::create([
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
            'file_path' => $newPath,
            'folder_id' => $folder_id,
            'party_id' => $request->party_id,
            'share_with' => $request->share_with ?? [],
            'expiry_date' => $request->expiry_date
        ]);

        return $this->success($document, 'Document created successfully', 201);
    }

    public function show(Document $document): JsonResponse
    {
        return $this->success($document->load(['party'])->append('shared_users'));
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:organization,agreements,hr,others',
            'description' => 'nullable|string',
            'file_path' => 'sometimes|nullable|string|starts_with:temp/',
            'folder_id' => 'required|exists:folders,id',
            'party_id' => 'nullable|exists:parties,id',
            'share_with' => 'nullable|array',
            'expiry_date' => 'nullable|date|after:today'
        ]);

        $data = $request->only([
            'name',
            'type',
            'description',
            'party_id',
            'folder_id',
            'share_with',
            'expiry_date'
        ]);

        // Handle file update
        if ($request->filled('file_path')) {

            $tempPath = $request->file_path;

            // Check temp file exists
            if (!Storage::disk('public')->exists($tempPath)) {
                return $this->error('Temporary file not found', 404);
            }

            // Delete old file
            if ($document->file_path &&
                Storage::disk('public')->exists($document->file_path)) {

                Storage::disk('public')->delete($document->file_path);
            }

            // Get folder
            $folder = Folder::find($request->folder_id);

            $folderName = $folder->name;

            // Create new path
            $filename = basename($tempPath);

            $newPath = 'documents/' . $folderName . '/' . $filename;

            // Move new file
            Storage::disk('public')->move($tempPath, $newPath);

            // Save new path
            $data['file_path'] = $newPath;
        }

        $document->update($data);

        return $this->success(
            $document,
            'Document updated successfully'
        );
    }

    public function destroy(Document $document): JsonResponse
    {
        // Delete file from storage
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();
        return $this->success(null, 'Document deleted successfully');
    }

    public function getFolders(): JsonResponse
    {
        $folders = Document::select('folder')
            ->distinct()
            ->pluck('folder');

        return $this->success($folders);
    }

    public function getShareableUsers(): JsonResponse
    {
        $users = \App\Models\User::whereIn('type', ['admin', 'manager'])
            ->with('employee')
            ->get(['id', 'username', 'type']);

        $formatted = $users->map(function ($user) {
            $name = $user->employee
                ? $user->employee->first_name . ' ' . $user->employee->last_name
                : $user->username;

            return [
                'id' => $user->id,
                'name' => trim($name),
                'type' => $user->type
            ];
        });

        return $this->success($formatted);
    }

    public function getExpiringDocuments(): JsonResponse
    {
        $thresholdDays = 30;
        $today = \Carbon\Carbon::today();
        $thresholdDate = $today->copy()->addDays($thresholdDays);

        // General Documents
        $expiringDocuments = Document::whereBetween('expiry_date', [$today, $thresholdDate])->get();

        // Employee Documents
        $expiringEmployees = \App\Models\Employee::where(function ($query) use ($today, $thresholdDate) {
            $query->whereBetween('passport_expiry_date', [$today, $thresholdDate])
                ->orWhereBetween('visa_expiry_date', [$today, $thresholdDate])
                ->orWhereBetween('labor_expiry_date', [$today, $thresholdDate])
                ->orWhereBetween('eid_expiry_date', [$today, $thresholdDate]);
        })->get();

        $employeeDocs = [];
        $expiryFields = [
            'passport_expiry_date' => 'Passport',
            'visa_expiry_date' => 'Visa',
            'labor_expiry_date' => 'Labor Card',
            'eid_expiry_date' => 'EID'
        ];

        foreach ($expiringEmployees as $employee) {
            $employeeName = trim($employee->first_name . ' ' . $employee->last_name);
            foreach ($expiryFields as $field => $label) {
                if ($employee->$field && $employee->$field >= $today->toDateString() && $employee->$field <= $thresholdDate->toDateString()) {
                    $employeeDocs[] = [
                        'type' => 'employee_document',
                        'document_type' => $label,
                        'employee_name' => $employeeName,
                        'expiry_date' => $employee->$field,
                    ];
                }
            }
        }

        return $this->success([
            'general_documents' => $expiringDocuments,
            'employee_documents' => $employeeDocs
        ]);
    }

    public function sendExpiryAlerts(): JsonResponse
    {
        \Illuminate\Support\Facades\Artisan::call('documents:check-expiry');
        $output = \Illuminate\Support\Facades\Artisan::output();

        return $this->success([
            'message' => 'Expiry alerts sent successfully.',
            'output' => trim($output)
        ]);
    }
}

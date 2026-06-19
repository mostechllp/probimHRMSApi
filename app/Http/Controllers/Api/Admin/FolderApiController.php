<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FolderApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $folders = Folder::orderBy('name')->get();
        return $this->success($folders);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:folders,name,NULL,id,deleted_at,NULL'
        ]);

        $folderName = $request->name;

        // Create physical directory
        if (!Storage::disk('public')->exists('documents/' . $folderName)) {
            Storage::disk('public')->makeDirectory('documents/' . $folderName);
        }

        $folder = Folder::create([
            'name' => $folderName,
            'created_by' => Auth::id()
        ]);

        return $this->success($folder, 'Folder created successfully', 201);
    }

    public function show(Folder $folder): JsonResponse
    {
        return $this->success($folder);
    }

    public function update(Request $request, Folder $folder): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:folders,name,' . $folder->id . ',id,deleted_at,NULL'
        ]);

        $oldName = $folder->name;
        $newName = $request->name;

        // Rename physical directory if name changed
        if ($oldName !== $newName) {
            if (Storage::disk('public')->exists('documents/' . $oldName)) {
                Storage::disk('public')->move('documents/' . $oldName, 'documents/' . $newName);
            } else {
                Storage::disk('public')->makeDirectory('documents/' . $newName);
            }
        }

        $folder->update([
            'name' => $newName
        ]);

        return $this->success($folder, 'Folder updated successfully');
    }

    public function destroy(Folder $folder): JsonResponse
    {
        $folder->update([
            'deleted_by' => Auth::id()
        ]);
        
        $folder->delete();

        return $this->success(null, 'Folder deleted successfully');
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileApiController extends ApiController
{
    /**
     * General file download API
     */
    public function download(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'disk' => 'nullable|string|in:public,private,local',
        ]);

        $path = $request->path;
        $disk = $request->disk ?? 'public';

        if (!Storage::disk($disk)->exists($path)) {
            return $this->error('File not found', 404);
        }

        return Storage::disk($disk)->download($path);
    }
}

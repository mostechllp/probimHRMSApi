<?php

namespace App\Http\Controllers;
use App\Models\Document;
use App\Models\Party;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);

        $agreements = Document::with('shareWith')->where('type', 'agreement')->latest()->get();
        $parties = Party::select('id', 'name')->get();
        $share_with = User::select('id', 'name')->get();

        $folders = Document::select('folder')
            ->distinct()
            ->pluck('folder');

        return view('documents.agreements', compact('agreements', 'folders', 'parties', 'share_with'));
    }
    
}

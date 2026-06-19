<?php

namespace App\Http\Controllers;
use App\Models\Department;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function store(Request $request)
    {
        $department = Department::create([
            'name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'department' => $department
        ]);
    }
}

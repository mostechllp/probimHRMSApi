<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Asset;
use App\Models\AssetAssignment;
use OpenApi\Attributes as OA;

class AssetApiController extends Controller
{
    #[OA\Get(
        path: "/api/admin/assets",
        operationId: "getAssets",
        summary: "Get all assets",
        tags: ["Assets"]
    )]
    #[OA\Response(response: 200, description: "Success")]
    public function index()
    {
        return response()->json(Asset::with(['type', 'assigned_to.employee'])->get(), 200);
    }

    #[OA\Post(
        path: "/api/admin/assets",
        operationId: "storeAsset",
        summary: "Create asset",
        tags: ["Assets"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["asset_name", "asset_type_id"],
            properties: [
                new OA\Property(property: "asset_name", type: "string"),
                new OA\Property(property: "asset_type_id", type: "integer"),
                new OA\Property(property: "brand", type: "string"),
                new OA\Property(property: "model", type: "string"),
                new OA\Property(property: "serial_number", type: "string"),
                new OA\Property(property: "purchase_price", type: "number"),
                new OA\Property(property: "purchase_date", type: "string", format: "date"),
                new OA\Property(property: "warranty_expiry", type: "string", format: "date"),
                new OA\Property(property: "status", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Created")]
    public function store(Request $request)
    {
        $request->validate([
            'asset_name' => 'required|string',
            'asset_type_id' => 'nullable',
        ]);
        $asset = Asset::create($request->all());
        return response()->json($asset, 201);
    }

    #[OA\Put(
        path: "/api/admin/assets/{id}",
        operationId: "updateAsset",
        summary: "Update asset",
        tags: ["Assets"]
    )]
    #[OA\Response(response: 200, description: "Updated")]
    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        $asset->update($request->all());
        return response()->json($asset, 200);
    }

    #[OA\Post(
        path: "/api/admin/assets/{id}/assign",
        operationId: "assignAsset",
        summary: "Assign asset to employee",
        tags: ["Assets"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["employee_id", "assigned_date"],
            properties: [
                new OA\Property(property: "employee_id", type: "integer"),
                new OA\Property(property: "assigned_date", type: "string", format: "date"),
                new OA\Property(property: "notes", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Assigned")]
    public function assign(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        $request->validate(['employee_id' => 'required|exists:employees,id', 'assigned_date' => 'nullable']);

        $asset->update(['status' => 'Assigned']);
        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'employee_id' => $request->employee_id,
            'assigned_date' => $request->assigned_date,
            'notes' => $request->notes,
            'status' => 'Active'
        ]);

        return response()->json($assignment, 200);
    }

    #[OA\Post(
        path: "/api/admin/assets/{id}/revoke",
        operationId: "revokeAsset",
        summary: "Revoke asset from employee",
        tags: ["Assets"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["returned_date"],
            properties: [
                new OA\Property(property: "returned_date", type: "string", format: "date"),
                new OA\Property(property: "return_condition", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Revoked")]
    public function revoke(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        $assignment = AssetAssignment::where('asset_id', $id)->where('status', 'Active')->firstOrFail();

        $assignment->update([
            'status' => 'Returned',
            'returned_date' => $request->returned_date,
            'return_condition' => $request->return_condition ?? null
        ]);
        $asset->update(['status' => 'Available']);

        return response()->json([
            'asset' => $asset,
            'assignment' => $assignment,
            'message' => 'Asset revoked successfully'
        ], 200);
    }

    #[OA\Delete(
        path: "/api/admin/assets/{id}",
        operationId: "deleteAsset",
        summary: "Delete asset",
        tags: ["Assets"]
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        required: true,
        description: "Asset ID",
        schema: new OA\Schema(type: "integer")
    )]
    #[OA\Response(
        response: 200,
        description: "Asset deleted successfully"
    )]
    #[OA\Response(
        response: 404,
        description: "Asset not found"
    )]
    public function destroy($id)
    {
        $asset = Asset::findOrFail($id);

        // Delete related assignments first if foreign key doesn't cascade
        AssetAssignment::where('asset_id', $asset->id)->delete();

        $asset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset deleted successfully'
        ], 200);
    }

    #[OA\Get(
        path: "/api/employee/assets/{employeeId}",
        operationId: "getEmployeeAssets",
        summary: "Get assets assigned to an employee",
        tags: ["Assets"]
    )]
    #[OA\Parameter(
        name: "employeeId",
        in: "path",
        required: true,
        description: "Employee ID",
        schema: new OA\Schema(type: "integer")
    )]
    #[OA\Response(
        response: 200,
        description: "Employee assets fetched successfully"
    )]
    #[OA\Response(
        response: 404,
        description: "Employee not found"
    )]
    public function employeeAssets($id)
    {
        $assets = AssetAssignment::where('employee_id', $id)
            ->where('status', 'Active')
            ->get();

        if ($assets->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active assets assigned to this employee.'
            ], 404);
        }

        $data = $assets->map(function ($assignment) {
            return [
                'assignment_id' => $assignment->id,
                'assigned_date' => $assignment->assigned_date,
                'expected_return_date' => $assignment->expected_return_date,
                'notes' => $assignment->notes,
                'asset' => [
                    'id' => $assignment->asset->id,
                    'asset_name' => $assignment->asset->asset_name,
                    'asset_code' => $assignment->asset->asset_code,
                    'brand' => $assignment->asset->brand,
                    'model' => $assignment->asset->model,
                    'serial_number' => $assignment->asset->serial_number,
                    'status' => $assignment->asset->status,
                    'type' => $assignment->asset->type,
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee assets fetched successfully.',
            'data' => $data
        ], 200);
    }

    public function getEmployeeAssets($id)
    {
        $assets = AssetAssignment::where('employee_id', $id)->get();

        if ($assets->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active assets assigned to this employee.'
            ], 404);
        }

        $data = $assets->map(function ($assignment) {
            return [
                'assignment_id' => $assignment->id,
                'assigned_date' => $assignment->assigned_date,
                'expected_return_date' => $assignment->expected_return_date,
                'notes' => $assignment->notes,
                'asset' => [
                    'id' => $assignment->asset->id,
                    'asset_name' => $assignment->asset->asset_name,
                    'asset_code' => $assignment->asset->asset_code,
                    'brand' => $assignment->asset->brand,
                    'model' => $assignment->asset->model,
                    'serial_number' => $assignment->asset->serial_number,
                    'status' => $assignment->asset->status,
                    'type' => $assignment->asset->type,
                ],
                'assignment_status' => $assignment->status,
                'returned_date' => $assignment->returned_date,
                'return_condition' => $assignment->return_condition,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee assets fetched successfully.',
            'data' => $data
        ], 200);
    }
}

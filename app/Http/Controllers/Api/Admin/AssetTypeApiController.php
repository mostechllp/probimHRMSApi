<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AssetType;
use OpenApi\Attributes as OA;

class AssetTypeApiController extends Controller
{
    #[OA\Get(
        path: "/api/admin/assets/types",
        operationId: "getAssetTypes",
        summary: "Get all asset types",
        tags: ["Asset Types"]
    )]
    #[OA\Response(response: 200, description: "Success")]
    public function index()
    {
        return response()->json(AssetType::all(), 200);
    }

    #[OA\Post(
        path: "/api/admin/assets/types",
        operationId: "storeAssetType",
        summary: "Create asset type",
        tags: ["Asset Types"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name"],
            properties: [
                new OA\Property(property: "name", type: "string"),
                new OA\Property(property: "description", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Created")]
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:asset_types']);
        $type = AssetType::create($request->all());
        return response()->json($type, 201);
    }

    #[OA\Get(
        path: "/api/admin/assets/types/{id}",
        operationId: "getAssetType",
        summary: "Get asset type by ID",
        tags: ["Asset Types"]
    )]
    #[OA\Response(response: 200, description: "Success")]
    public function show($id)
    {
        return response()->json(AssetType::findOrFail($id), 200);
    }

    #[OA\Put(
        path: "/api/admin/assets/types/{id}",
        operationId: "updateAssetType",
        summary: "Update asset type",
        tags: ["Asset Types"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name"],
            properties: [
                new OA\Property(property: "name", type: "string"),
                new OA\Property(property: "description", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Updated")]
    public function update(Request $request, $id)
    {
        $type = AssetType::findOrFail($id);
        $request->validate(['name' => 'required|string|unique:asset_types,name,'.$id]);
        $type->update($request->all());
        return response()->json($type, 200);
    }

    #[OA\Delete(
        path: "/api/admin/assets/types/{id}",
        operationId: "deleteAssetType",
        summary: "Delete asset type",
        tags: ["Asset Types"]
    )]
    #[OA\Response(response: 200, description: "Deleted")]
    public function destroy($id)
    {
        AssetType::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted successfully'], 200);
    }
}

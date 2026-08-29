<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\OffboardingChecklistCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Checklist Categories",
    description: "Manage Offboarding Checklist Categories"
)]
class OffboardingChecklistCategoryController extends ApiController
{
    #[OA\Get(
        path: "/api/admin/checklist-categories",
        operationId: "getChecklistCategories",
        summary: "Get all checklist categories",
        security: [["bearerAuth" => []]],
        tags: ["Checklist Categories"]
    )]
    #[OA\Response(
        response: 200,
        description: "Checklist categories fetched successfully"
    )]
    public function index(): JsonResponse
    {
        $categories = OffboardingChecklistCategory::orderBy('name')
            ->get();

        return $this->success(
            $categories,
            'Checklist categories fetched successfully.'
        );
    }

    #[OA\Post(
        path: "/api/admin/checklist-categories",
        operationId: "createChecklistCategory",
        summary: "Create checklist category",
        security: [["bearerAuth" => []]],
        tags: ["Checklist Categories"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name"],
            properties: [
                new OA\Property(
                    property: "name",
                    type: "string",
                    example: "Visa Cancellation"
                ),
                new OA\Property(
                    property: "description",
                    type: "string",
                    nullable: true,
                    example: "Visa related cancellation tasks"
                ),
                new OA\Property(
                    property: "is_active",
                    type: "boolean",
                    example: true
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Checklist category created successfully"
    )]
    #[OA\Response(
        response: 422,
        description: "Validation error"
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:offboarding_checklist_categories,name',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        $category = OffboardingChecklistCategory::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checklist category created successfully.',
            'data' => $category
        ], 201);
    }

    #[OA\Get(
        path: "/api/admin/checklist-categories/{id}",
        operationId: "getChecklistCategory",
        summary: "Get checklist category details",
        security: [["bearerAuth" => []]],
        tags: ["Checklist Categories"]
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        required: true,
        schema: new OA\Schema(
            type: "integer",
            example: 1
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Checklist category retrieved successfully"
    )]
    #[OA\Response(
        response: 404,
        description: "Checklist category not found"
    )]
    public function show($id): JsonResponse
    {
        $category = OffboardingChecklistCategory::find($id);

        if (!$category) {
            return $this->error(
                'Checklist category not found.',
                404
            );
        }

        return $this->success(
            $category,
            'Checklist category details retrieved successfully.'
        );
    }

    #[OA\Put(
        path: "/api/admin/checklist-categories/{id}",
        operationId: "updateChecklistCategory",
        summary: "Update checklist category",
        security: [["bearerAuth" => []]],
        tags: ["Checklist Categories"]
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        required: true,
        schema: new OA\Schema(
            type: "integer",
            example: 1
        )
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name"],
            properties: [
                new OA\Property(
                    property: "name",
                    type: "string",
                    example: "Asset Return"
                ),
                new OA\Property(
                    property: "description",
                    type: "string",
                    nullable: true,
                    example: "Asset return related tasks"
                ),
                new OA\Property(
                    property: "is_active",
                    type: "boolean",
                    example: true
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Checklist category updated successfully"
    )]
    #[OA\Response(
        response: 404,
        description: "Checklist category not found"
    )]
    #[OA\Response(
        response: 422,
        description: "Validation error"
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $category = OffboardingChecklistCategory::find($id);

        if (!$category) {
            return $this->error(
                'Checklist category not found.',
                404
            );
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:offboarding_checklist_categories,name,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true)
        ]);

        return $this->success(
            $category,
            'Checklist category updated successfully.'
        );
    }

    #[OA\Delete(
        path: "/api/admin/checklist-categories/{id}",
        operationId: "deleteChecklistCategory",
        summary: "Delete checklist category",
        security: [["bearerAuth" => []]],
        tags: ["Checklist Categories"]
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        required: true,
        schema: new OA\Schema(
            type: "integer",
            example: 1
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Checklist category deleted successfully"
    )]
    #[OA\Response(
        response: 404,
        description: "Checklist category not found"
    )]
    public function destroy($id): JsonResponse
    {
        $category = OffboardingChecklistCategory::find($id);

        if (!$category) {
            return $this->error(
                'Checklist category not found.',
                404
            );
        }

        $category->delete();

        return $this->success(
            null,
            'Checklist category deleted successfully.'
        );
    }
}

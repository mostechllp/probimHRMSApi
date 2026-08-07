<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OffboardingChecklist;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Offboarding Checklist',
    description: 'Manage checklist items within an offboarding process'
)]
class OffboardingChecklistApiController extends Controller
{
    // -------------------------------------------------------------------------
    // INDEX
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/admin/checklists/{id}',
        operationId: 'getOffboardingChecklists',
        summary: 'Get all checklist items for an offboarding record',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding Checklist']
    )]
    #[OA\Parameter(
        name: 'offboardingId',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Checklist items retrieved successfully',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                    new OA\Property(property: 'category_id', type: 'integer', example: 1),
                    new OA\Property(property: 'task_name', type: 'string', example: 'Submit visa cancellation to GDRFA'),
                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                    new OA\Property(property: 'responsible_role', type: 'string', example: 'PRO'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: null),
                ]
            )
        )
    )]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function index($offboardingId)
    {
        return response()->json(
            OffboardingChecklist::where('offboarding_id', $offboardingId)->get()
        );
    }

    // -------------------------------------------------------------------------
    // STORE
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/admin/checklists/{id}',
        operationId: 'createChecklist',
        summary: 'Create a new checklist item',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding Checklist']
    )]
    #[OA\Parameter(
        name: 'offboardingId',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['category_id', 'task_name'],
            properties: [
                new OA\Property(property: 'category_id', type: 'integer', example: 1),
                new OA\Property(property: 'task_name', type: 'string', example: 'Return access card'),
                new OA\Property(property: 'responsible_role', type: 'string', nullable: true, example: 'HR'),
                new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Collect from security desk'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Checklist item created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Checklist created successfully'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 5),
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(property: 'category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'task_name', type: 'string', example: 'Return access card'),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'responsible_role', type: 'string', example: 'HR'),
                        new OA\Property(property: 'notes', type: 'string', nullable: true, example: null),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(Request $request, $offboardingId)
    {
        $request->validate([
            'category_id' => 'required',
            'task_name' => 'required|string',
            'responsible_role' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $checklist = OffboardingChecklist::create([
            'offboarding_id' => $offboardingId,
            'category_id' => $request->category_id,
            'task_name' => $request->task_name,
            'responsible_role' => $request->responsible_role,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checklist created successfully',
            'data' => $checklist,
        ], 201);
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/admin/admin/checklists/{id}',
        operationId: 'updateChecklist',
        summary: 'Update a checklist item',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding Checklist']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Checklist item ID',
        schema: new OA\Schema(type: 'integer', example: 5)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'category_id', type: 'integer', example: 1),
                new OA\Property(property: 'task_name', type: 'string', example: 'Return laptop'),
                new OA\Property(property: 'responsible_role', type: 'string', nullable: true, example: 'IT'),
                new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Wipe data before returning'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Checklist item updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Checklist updated successfully'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 5),
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(property: 'category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'task_name', type: 'string', example: 'Return laptop'),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'responsible_role', type: 'string', example: 'IT'),
                        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Wipe data before returning'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Checklist item not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(Request $request, $id)
    {
        $checklist = OffboardingChecklist::findOrFail($id);

        $checklist->update($request->only([
            'category_id',
            'task_name',
            'responsible_role',
            'notes',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Checklist updated successfully',
            'data' => $checklist,
        ]);
    }

    // -------------------------------------------------------------------------
    // UPDATE STATUS
    // -------------------------------------------------------------------------

    #[OA\Patch(
        path: '/api/admin/admin/checklists/{id}/status',
        operationId: 'updateChecklistStatus',
        summary: 'Update the status of a checklist item',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding Checklist']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Checklist item ID',
        schema: new OA\Schema(type: 'integer', example: 5)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(
                    property: 'status',
                    type: 'string',
                    enum: ['pending', 'completed', 'not_applicable'],
                    example: 'completed'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Checklist status updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Checklist status updated successfully'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 5),
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(property: 'task_name', type: 'string', example: 'Return access card'),
                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Checklist item not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,not_applicable',
        ]);

        $checklist = OffboardingChecklist::findOrFail($id);

        $checklist->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Checklist status updated successfully',
            'data' => $checklist,
        ]);
    }

    // -------------------------------------------------------------------------
    // DESTROY
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/admin/admin/checklists/{id}',
        operationId: 'deleteChecklist',
        summary: 'Delete a checklist item',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding Checklist']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Checklist item ID',
        schema: new OA\Schema(type: 'integer', example: 5)
    )]
    #[OA\Response(
        response: 200,
        description: 'Checklist item deleted successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Checklist deleted successfully'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Checklist item not found')]
    public function destroy($id)
    {
        $checklist = OffboardingChecklist::findOrFail($id);

        $checklist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Checklist deleted successfully',
        ]);
    }
}
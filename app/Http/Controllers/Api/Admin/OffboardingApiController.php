<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\Offboarding;
use App\Models\OffboardingChecklist;
use App\Models\EmployeeAsset;
use App\Models\OffboardingInterview;
use App\Models\OffboardingSettlement;
use App\Models\OffboardingLetter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Offboarding',
    description: 'Endpoints for managing the employee offboarding lifecycle'
)]
class OffboardingApiController extends ApiController
{
    // -------------------------------------------------------------------------
    // INDEX
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding',
        operationId: 'listOffboardings',
        summary: 'List all offboarding records',
        description: 'Returns a paginated list of offboarding records. Admins and HR Managers see all records; other roles see only employees they manage.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        required: false,
        description: 'Page number for pagination',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding records retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object',
                    description: 'Laravel paginated result containing offboarding records'
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized access')]
    public function index(Request $request): JsonResponse
    {
        $query = Offboarding::with(['employee', 'checklists', 'assets']);

        $user = $request->user();
        if ($user->type !== 'admin' && $user->role?->name !== 'HR Manager') {
            if ($user->employee) {
                $query->whereHas('employee', function ($q) use ($user) {
                    $q->where('reporting_manager_id', $user->employee->id);
                });
            } else {
                return $this->error('Unauthorized access', 403);
            }
        }

        $offboardings = $query->paginate(15);

        return $this->success($offboardings);
    }

    // -------------------------------------------------------------------------
    // INITIATE
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/initiate',
        operationId: 'initiateOffboarding',
        summary: 'Initiate or save a draft offboarding process',
        description: 'Creates or updates an offboarding record for an employee. Pass `is_draft: true` to save as draft, or `false` to formally initiate.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['employee_id'],
            properties: [
                new OA\Property(property: 'employee_id',       type: 'integer', example: 2),
                new OA\Property(property: 'last_working_day',  type: 'string',  format: 'date',    nullable: true, example: '2026-06-10'),
                new OA\Property(property: 'separation_type',   type: 'string',  nullable: true,    example: 'resignation'),
                new OA\Property(property: 'notice_period_days',type: 'integer', nullable: true,    example: 30),
                new OA\Property(property: 'notice_start_date', type: 'string',  format: 'date',    nullable: true, example: '2026-05-10'),
                new OA\Property(property: 'visa_sponsorship',  type: 'string',  nullable: true,    example: 'Company sponsored'),
                new OA\Property(property: 'nationality',       type: 'string',  nullable: true,    example: 'British'),
                new OA\Property(property: 'reason_for_leaving',type: 'string',  nullable: true,    example: 'Better opportunity abroad'),
                new OA\Property(property: 'is_draft',          type: 'boolean',                    example: false),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding initiated or draft saved',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Offboarding process initiated successfully.'),
                new OA\Property(property: 'data',    type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Not authorized to initiate offboarding for this employee')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id'        => 'required|exists:employees,id',
            'last_working_day'   => 'nullable|date',
            'separation_type'    => 'nullable|string',
            'notice_period_days' => 'nullable|integer',
            'notice_start_date'  => 'nullable|date',
            'visa_sponsorship'   => 'nullable|string',
            'nationality'        => 'nullable|string',
            'reason_for_leaving' => 'nullable|string',
            'is_draft'           => 'boolean',
        ]);

        $employee = Employee::findOrFail($request->employee_id);

        if (!$this->isAuthorized($request->user(), $employee)) {
            return $this->error(
                'You are not authorized to initiate offboarding for this employee.',
                403
            );
        }

        $offboarding = Offboarding::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'status'             => $request->boolean('is_draft') 
                                            ? 'draft' 
                                            : ($request->visa_sponsorship === 'non-applicable' ? 'pending_checklist' : 'pending_visa'),
                'last_working_day'   => $request->last_working_day,
                'separation_type'    => $request->separation_type,
                'notice_period_days' => $request->notice_period_days,
                'notice_start_date'  => $request->notice_start_date,
                'visa_sponsorship'   => $request->visa_sponsorship,
                'nationality'        => $request->nationality,
                'reason_for_leaving' => $request->reason_for_leaving,
            ]
        );

        return $this->success(
            $offboarding,
            $request->boolean('is_draft')
                ? 'Offboarding draft saved successfully.'
                : 'Offboarding process initiated successfully.'
        );
    }

    // -------------------------------------------------------------------------
    // SHOW
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/{id}',
        operationId: 'showOffboarding',
        summary: 'Get full details of an offboarding record',
        description: 'Retrieves a single offboarding record with all related data: employee, checklists, assets, interview, settlement and letters.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding details retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Offboarding details retrieved successfully.'),
                new OA\Property(property: 'data',    type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function show(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'employee',
            'checklists',
            'assets',
            'interview',
            'settlement',
            'letters',
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to view this offboarding record.',
                403
            );
        }

        return $this->success(
            $offboarding,
            'Offboarding details retrieved successfully.'
        );
    }

    // -------------------------------------------------------------------------
    // GET VISA STATUS
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/{id}/visa-status',
        operationId: 'getVisaStatus',
        summary: 'Get visa cancellation details and checklist',
        description: 'Returns the employee\'s visa details along with all visa_cancellation checklist tasks and their completion progress.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Visa cancellation details fetched successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Visa cancellation details fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'employee',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id',            type: 'integer', example: 2),
                                new OA\Property(property: 'name',          type: 'string',  example: 'Dr. Vipul Paul Thomas'),
                                new OA\Property(property: 'employee_code', type: 'string',  example: 'MSC-DOC-0002'),
                            ]
                        ),
                        new OA\Property(
                            property: 'visa_details',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'visa_number',          type: 'string',          example: '98765432111'),
                                new OA\Property(property: 'visa_expiry_date',     type: 'string', format: 'date', example: '2026-06-30'),
                                new OA\Property(property: 'eid_number',           type: 'string',          example: '768-7679277893-88'),
                                new OA\Property(property: 'eid_expiry_date',      type: 'string', format: 'date', example: '2026-07-02'),
                                new OA\Property(property: 'labor_number',         type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'visa_type',            type: 'string',          example: 'company_visa'),
                            ]
                        ),
                        new OA\Property(
                            property: 'tasks',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id',               type: 'integer', example: 1),
                                    new OA\Property(property: 'task_name',        type: 'string',  example: 'Submit visa cancellation to GDRFA/ICP'),
                                    new OA\Property(property: 'status',           type: 'string',  example: 'pending'),
                                    new OA\Property(property: 'responsible_role', type: 'string',  example: 'PRO'),
                                ]
                            )
                        ),
                        new OA\Property(property: 'progress', type: 'integer', example: 33),
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function getVisaStatus(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'employee',
            'checklists' => fn($q) => $q->where('category_id', 1),
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to view this offboarding record.',
                403
            );
        }

        $employee = $offboarding->employee;
        $tasks    = $offboarding->checklists;

        $totalTasks     = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed')->count();
        $progress       = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        return $this->success([
            'offboarding_id' => $offboarding->id,
            'employee'       => [
                'id'            => $employee->id,
                'name'          => trim("{$employee->first_name} {$employee->last_name}"),
                'employee_code' => $employee->employee_id,
            ],
            'visa_details'   => [
                'visa_number'      => $employee->visa_number,
                'visa_expiry_date' => $employee->visa_expiry_date,    // fixed: was visa_expiry
                'eid_number'       => $employee->eid_number,           // fixed: was emirates_id_number
                'eid_expiry_date'  => $employee->eid_expiry_date,      // fixed: was emirates_id_expiry
                'labor_number'     => $employee->labor_number,         // fixed: was labour_card_number
                'visa_type'        => $employee->visa_type,
            ],
            'tasks'    => $tasks,
            'progress' => $progress,
        ], 'Visa cancellation details fetched successfully.');
    }

    // -------------------------------------------------------------------------
    // COMPLETE VISA STATUS
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/visa-status/complete',
        operationId: 'completeVisaStatus',
        summary: 'Complete the visa cancellation stage',
        description: 'Marks the visa cancellation stage as completed and advances the offboarding status to `pending_checklist`. All visa_cancellation tasks must be completed or marked not_applicable first.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Visa cancellation stage completed successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Visa cancellation process completed successfully.'),
                new OA\Property(property: 'data',    type: 'object',  nullable: true, example: null),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Pending tasks still exist',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string',  example: 'All visa cancellation tasks must be completed before proceeding.'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function completeVisaStatus(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'checklists' => fn ($q) => $q->where('category_id', 'visa_cancellation'),
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to complete visa cancellation for this employee.',
                403
            );
        }

        $pendingTasks = $offboarding->checklists
            ->where('status', 'pending')
            ->count();

        if ($pendingTasks > 0) {
            return $this->error(
                'All visa cancellation tasks must be completed before proceeding.',
                422
            );
        }

        $offboarding->update(['status' => 'pending_checklist']);

        return $this->success(null, 'Visa cancellation process completed successfully.');
    }

    // -------------------------------------------------------------------------
    // UPDATE CHECKLIST
    // -------------------------------------------------------------------------

    #[OA\Patch(
        path: '/api/admin/offboarding/{id}/checklists',
        operationId: 'updateOffboardingChecklist',
        summary: 'Update the status of a checklist item',
        description: 'Updates the status of a single offboarding checklist task (any category_id). The task must belong to the given offboarding record.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['task_id', 'status'],
            properties: [
                new OA\Property(property: 'task_id', type: 'integer', example: 5),
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
        description: 'Checklist item updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Checklist item updated successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id',             type: 'integer', example: 5),
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(property: 'category_id',       type: 'string',  example: 'general'),
                        new OA\Property(property: 'task_name',      type: 'string',  example: 'Return access card'),
                        new OA\Property(property: 'status',         type: 'string',  example: 'completed'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding or checklist item not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function updateChecklist(Request $request, $id): JsonResponse
    {
        $request->validate([
            'task_id' => 'required|exists:offboarding_checklists,id',
            'status'  => 'required|in:pending,completed,not_applicable',
        ]);

        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to update checklist status for this employee.',
                403
            );
        }

        $task = OffboardingChecklist::where('id', $request->task_id)
            ->where('offboarding_id', $id)
            ->first();

        if (!$task) {
            return $this->error('Checklist item not found.', 404);
        }

        $task->update(['status' => $request->status]);

        return $this->success($task, 'Checklist item updated successfully.');
    }

    // -------------------------------------------------------------------------
    // UPDATE ASSETS
    // -------------------------------------------------------------------------

    #[OA\Patch(
        path: '/api/admin/offboarding/{id}/assets',
        operationId: 'updateAssets',
        summary: 'Update asset return status',
        description: 'Updates the return status and condition of one or more assets linked to an offboarding record.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['assets'],
            properties: [
                new OA\Property(
                    property: 'assets',
                    type: 'array',
                    items: new OA\Items(
                        required: ['id', 'status'],
                        properties: [
                            new OA\Property(property: 'id',        type: 'integer', example: 3),
                            new OA\Property(property: 'status',    type: 'string',  example: 'Returned'),
                            new OA\Property(property: 'condition', type: 'string',  nullable: true, example: 'Good'),
                        ]
                    )
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Assets updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Assets updated successfully.'),
                new OA\Property(property: 'data',    type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function updateAssets(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        foreach ($request->input('assets', []) as $asset) {
            EmployeeAsset::where('id', $asset['id'])
                ->where('offboarding_id', $offboarding->id)
                ->update([
                    'status'    => $asset['status'],
                    'condition' => $asset['condition'] ?? null,
                ]);
        }

        return $this->success(
            $offboarding->load('assets'),
            'Assets updated successfully.'
        );
    }

    // -------------------------------------------------------------------------
    // SUBMIT EXIT INTERVIEW
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/interview',
        operationId: 'submitInterview',
        summary: 'Submit or update the exit interview',
        description: 'Creates or updates the exit interview record for an offboarding process.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'interviewer',                  type: 'string',  example: 'Fatima Al Zaabi (HR)'),
                new OA\Property(property: 'interview_date',               type: 'string',  format: 'date', example: '2026-06-17'),
                new OA\Property(property: 'interview_mode',               type: 'string',  example: 'In person'),
                new OA\Property(property: 'overall_satisfaction',         type: 'string',  example: 'Satisfied'),
                new OA\Property(property: 'primary_reason',               type: 'string',  example: 'Better opportunity'),
                new OA\Property(property: 'work_life_rating',             type: 'string',  nullable: true, example: '4'),
                new OA\Property(property: 'manager_relationship_rating',  type: 'string',  nullable: true, example: '5'),
                new OA\Property(property: 'enjoyed_most',                 type: 'string',  nullable: true, example: 'Collaborative team culture'),
                new OA\Property(property: 'areas_for_improvement',        type: 'string',  nullable: true, example: 'Clearer promotion paths'),
                new OA\Property(property: 'would_recommend',              type: 'boolean',                  example: true),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Exit interview submitted successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Exit interview submitted successfully.'),
                new OA\Property(property: 'data',    type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function submitInterview(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $interview = OffboardingInterview::updateOrCreate(
            ['offboarding_id' => $offboarding->id],
            $request->only([
                'interviewer',
                'interview_date',
                'interview_mode',
                'overall_satisfaction',
                'primary_reason',
                'work_life_rating',
                'manager_relationship_rating',
                'enjoyed_most',
                'areas_for_improvement',
                'would_recommend',
            ])
        );

        return $this->success($interview, 'Exit interview submitted successfully.');
    }

    // -------------------------------------------------------------------------
    // UPDATE SETTLEMENT
    // -------------------------------------------------------------------------

    #[OA\Patch(
        path: '/api/admin/offboarding/{id}/settlement',
        operationId: 'updateSettlement',
        summary: 'Create or update the final settlement',
        description: 'Creates or updates the final settlement record for an offboarding process.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'total_payable',    type: 'number', format: 'float', example: 45000.00),
                new OA\Property(property: 'total_deductions', type: 'number', format: 'float', example: 2000.00),
                new OA\Property(property: 'net_payable',      type: 'number', format: 'float', example: 43000.00),
                new OA\Property(property: 'status',           type: 'string',                  example: 'pending'),
                new OA\Property(property: 'remarks',          type: 'string', nullable: true,  example: 'Includes gratuity and leave encashment'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Settlement updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Settlement updated successfully.'),
                new OA\Property(property: 'data',    type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function updateSettlement(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $settlement = OffboardingSettlement::updateOrCreate(
            ['offboarding_id' => $offboarding->id],
            $request->only(['total_payable', 'total_deductions', 'net_payable', 'status', 'remarks'])
        );

        return $this->success($settlement, 'Settlement updated successfully.');
    }

    // -------------------------------------------------------------------------
    // GENERATE / UPDATE LETTERS
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/letters',
        operationId: 'generateLetters',
        summary: 'Generate or update an offboarding letter record',
        description: 'Creates or updates a letter record (e.g. experience letter, NOC, visa cancellation letter) for an offboarding process.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['letter_type'],
            properties: [
                new OA\Property(property: 'letter_type',   type: 'string', example: 'experience_letter'),
                new OA\Property(property: 'document_path', type: 'string', nullable: true, example: 'letters/exp_letter_emp2.pdf'),
                new OA\Property(property: 'status',        type: 'string', example: 'pending'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Letter record updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Letter record updated successfully.'),
                new OA\Property(property: 'data',    type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function generateLetters(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $data   = $request->only(['letter_type', 'document_path', 'status']);
        $letter = OffboardingLetter::updateOrCreate(
            ['offboarding_id' => $offboarding->id, 'letter_type' => $data['letter_type']],
            $data
        );

        return $this->success($letter, 'Letter record updated successfully.');
    }

    // -------------------------------------------------------------------------
    // GET PROGRESS
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/{id}/progress',
        operationId: 'getOffboardingProgress',
        summary: 'Get overall offboarding progress',
        description: 'Returns a breakdown of all offboarding steps with their individual status and an overall completion percentage.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding progress retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string',  example: 'Offboarding progress fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'offboarding_id',      type: 'integer', example: 1),
                        new OA\Property(property: 'current_status',      type: 'string',  example: 'pending_checklist'),
                        new OA\Property(property: 'completed_steps',     type: 'integer', example: 2),
                        new OA\Property(property: 'total_steps',         type: 'integer', example: 7),
                        new OA\Property(property: 'progress_percentage', type: 'integer', example: 29),
                        new OA\Property(
                            property: 'steps',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'name',   type: 'string', example: 'Visa Cancellation'),
                                    new OA\Property(property: 'key',    type: 'string', example: 'visa'),
                                    new OA\Property(
                                        property: 'status',
                                        type: 'string',
                                        enum: ['pending', 'in_progress', 'completed'],
                                        example: 'completed'
                                    ),
                                ]
                            )
                        ),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function getProgress(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'checklists',
            'interview',
            'settlement',
            'assets',
            'letters',
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to view this offboarding record.',
                403
            );
        }

        $steps          = $this->getOffboardingSteps($offboarding);
        $completedSteps = collect($steps)->where('status', 'completed')->count();
        $totalSteps     = count($steps);

        return $this->success([
            'offboarding_id'      => $offboarding->id,
            'current_status'      => $offboarding->status,
            'completed_steps'     => $completedSteps,
            'total_steps'         => $totalSteps,
            'progress_percentage' => $totalSteps > 0
                ? round(($completedSteps / $totalSteps) * 100)
                : 0,
            'steps'               => $steps,
        ], 'Offboarding progress fetched successfully.');
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Build the ordered step-status array for an offboarding record.
     * Relations that must be eager-loaded: checklists, assets, interview,
     * settlement, letters.
     */
    private function getOffboardingSteps(Offboarding $offboarding): array
    {
        return [
            [
                'name'   => 'Initiation',
                'key'    => 'initiation',
                'status' => $offboarding->id ? 'completed' : 'pending',
            ],
            [
                'name'   => 'Visa Cancellation',
                'key'    => 'visa',
                'status' => $this->isVisaCompleted($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_visa' ? 'in_progress' : 'pending'),
            ],
            [
                'name'   => 'General Checklist',
                'key'    => 'checklist',
                'status' => $this->isChecklistCompleted($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_checklist' ? 'in_progress' : 'pending'),
            ],
            [
                'name'   => 'Exit Interview',
                'key'    => 'interview',
                'status' => $this->isInterviewCompleted($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_interview' ? 'in_progress' : 'pending'),
            ],
            [
                'name'   => 'Final Settlement',
                'key'    => 'settlement',
                'status' => $this->isSettlementCompleted($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_settlement' ? 'in_progress' : 'pending'),
            ],
            [
                'name'   => 'Letters & Documents',
                'key'    => 'letters',
                'status' => $this->isLettersGenerated($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_letters' ? 'in_progress' : 'pending'),
            ],
            [
                'name'   => 'Asset Return',
                'key'    => 'assets',
                'status' => $this->isAssetsReturned($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_assets' ? 'in_progress' : 'pending'),
            ]
        ];
    }

    /**
     * True when all visa_cancellation checklist tasks are done/not_applicable.
     */
    private function isVisaCompleted(Offboarding $offboarding): bool
    {
        $visaTasks = $offboarding->checklists
            ->where('category_id', 'visa_cancellation');

        return $visaTasks->isNotEmpty()
            && $visaTasks->whereNotIn('status', ['completed', 'not_applicable'])->isEmpty();
    }

    /**
     * True when all general checklist tasks are done/not_applicable.
     */
    private function isChecklistCompleted(Offboarding $offboarding): bool
    {
        $generalTasks = $offboarding->checklists
            ->where('category_id', '!=', 'visa_cancellation');

        return $generalTasks->isNotEmpty()
            && $generalTasks->whereNotIn('status', ['completed', 'not_applicable'])->isEmpty();
    }

    /**
     * True when every asset linked to this offboarding has been returned.
     */
    private function isAssetsReturned(Offboarding $offboarding): bool
    {
        $assets = $offboarding->assets ?? collect();

        return $assets->isNotEmpty()
            && $assets->where('status', '!=', 'Returned')->isEmpty();
    }

    /**
     * True when an exit interview record exists for this offboarding.
     */
    private function isInterviewCompleted(Offboarding $offboarding): bool
    {
        return $offboarding->relationLoaded('interview')
            ? $offboarding->interview !== null
            : $offboarding->interview()->exists();
    }

    /**
     * True when a settlement record exists and its status is not 'pending'.
     */
    private function isSettlementCompleted(Offboarding $offboarding): bool
    {
        $settlement = $offboarding->relationLoaded('settlement')
            ? $offboarding->settlement
            : $offboarding->settlement()->first();

        return $settlement !== null && $settlement->status !== 'pending';
    }

    /**
     * True when at least one letter record exists for this offboarding.
     */
    private function isLettersGenerated(Offboarding $offboarding): bool
    {
        $letters = $offboarding->relationLoaded('letters')
            ? $offboarding->letters
            : $offboarding->letters()->get();

        return $letters->isNotEmpty();
    }

    /**
     * Check if the requesting user is authorised to manage this offboarding.
     * Admins and HR Managers always pass; other users pass only if they are
     * the reporting manager of the employee.
     */
    private function isAuthorized($user, $employee): bool
    {
        if ($user->type === 'admin' || $user->role?->name === 'HR Manager') {
            return true;
        }

        return $user->employee
            && $employee->reporting_manager_id === $user->employee->id;
    }
}
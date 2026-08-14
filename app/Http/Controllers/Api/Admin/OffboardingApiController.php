<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\Offboarding;
use App\Models\OffboardingChecklist;
use App\Models\EmployeeAsset;
use App\Models\OffboardingInterview;
use App\Models\OffboardingSettlement;
use App\Models\OffboardingLetter;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\AttendanceLog;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Carbon\Carbon;

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
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    description: 'Laravel paginated result containing offboarding records'
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized access')]
    public function index(Request $request): JsonResponse
    {
        $query = Offboarding::with([
            'employee',
            'reportingManager',
            'checklists',
            'assets'
        ]);

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
    // STATS
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/stats',
        operationId: 'getOffboardingStats',
        summary: 'Get offboarding dashboard card statistics',
        description: 'Returns aggregate counts for the offboarding dashboard cards: total, pending initiation, in progress, completed, and per-step pending counts (asset return, final settlement, visa cancellation, exit interview, letters & documents).',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding statistics fetched successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Offboarding statistics fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'total_offboarding', type: 'integer', example: 24),
                        new OA\Property(property: 'pending_initiation', type: 'integer', example: 3),
                        new OA\Property(property: 'in_progress', type: 'integer', example: 15),
                        new OA\Property(property: 'completed_offboarding', type: 'integer', example: 6),
                        new OA\Property(property: 'pending_asset_return', type: 'integer', example: 9),
                        new OA\Property(property: 'pending_final_settlement', type: 'integer', example: 11),
                        new OA\Property(property: 'pending_visa_cancellation', type: 'integer', example: 5),
                        new OA\Property(property: 'pending_exit_interview', type: 'integer', example: 7),
                        new OA\Property(property: 'pending_letters_documents', type: 'integer', example: 13),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized access')]
    public function getStats(Request $request): JsonResponse
    {
        $query = Offboarding::with(['checklists', 'assets', 'interview', 'settlement', 'letters']);

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

        $offboardings = $query->get();
        $activeOffboardings = $offboardings->where('status', '!=', 'completed');

        return $this->success([
            'total_offboarding' => $offboardings->count(),
            'pending_initiation' => $offboardings->where('status', 'draft')->count(),
            'in_progress' => $offboardings->whereNotIn('status', ['draft', 'completed'])->count(),
            'completed_offboarding' => $offboardings->where('status', 'completed')->count(),
            'pending_asset_return' => $activeOffboardings->filter(fn($o) => !$this->isAssetsReturned($o))->count(),
            'pending_final_settlement' => $activeOffboardings->filter(fn($o) => !$this->isSettlementCompleted($o))->count(),
            'pending_visa_cancellation' => $activeOffboardings->filter(fn($o) => !$this->isVisaCompleted($o))->count(),
            'pending_exit_interview' => $activeOffboardings->filter(fn($o) => !$this->isInterviewCompleted($o))->count(),
            'pending_letters_documents' => $activeOffboardings->filter(fn($o) => !$this->isLettersGenerated($o))->count(),
        ], 'Offboarding statistics fetched successfully.');
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
                new OA\Property(property: 'employee_id', type: 'integer', example: 2),
                new OA\Property(property: 'last_working_day', type: 'string', format: 'date', nullable: true, example: '2026-06-10'),
                new OA\Property(property: 'separation_type', type: 'string', nullable: true, example: 'resignation'),
                new OA\Property(property: 'notice_period_days', type: 'integer', nullable: true, example: 30),
                new OA\Property(property: 'notice_start_date', type: 'string', format: 'date', nullable: true, example: '2026-05-10'),
                new OA\Property(property: 'visa_sponsorship', type: 'string', nullable: true, example: 'Company sponsored'),
                new OA\Property(property: 'nationality', type: 'string', nullable: true, example: 'British'),
                new OA\Property(property: 'reason_for_leaving', type: 'string', nullable: true, example: 'Better opportunity abroad'),
                new OA\Property(property: 'is_draft', type: 'boolean', example: false),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding initiated or draft saved',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Offboarding process initiated successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Not authorized to initiate offboarding for this employee')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'reporting_manager_id' => 'required|exists:employees,id',
            'last_working_day' => 'nullable|date',
            'separation_type' => 'nullable|string',
            'resignation_date' => 'nullable|date',
            'notice_period_days' => 'nullable|integer',
            'notice_start_date' => 'nullable|date',
            'visa_sponsorship' => 'nullable|string',
            'nationality' => 'nullable|string',
            'reason_for_leaving' => 'nullable|string',
            'is_draft' => 'boolean',
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
                'status' => $request->boolean('is_draft')
                    ? 'draft'
                    : 'pending_assets',
                'last_working_day' => $request->last_working_day,
                'separation_type' => $request->separation_type,
                'notice_period_days' => $request->notice_period_days,
                'notice_start_date' => $request->notice_start_date,
                'resignation_date' => $request->resignation_date,
                'visa_sponsorship' => $request->visa_sponsorship,
                'nationality' => $request->nationality,
                'reason_for_leaving' => $request->reason_for_leaving,
                'reporting_manager_id' => $request->reporting_manager_id,
            ]
        );

        return $this->success(
            $offboarding,
            $request->boolean('is_draft')
                ? 'Offboarding draft saved successfully.'
                : 'Offboarding process initiated successfully.'
        );
    }

    public function reportingManagers(): JsonResponse
    {
        $employees = Employee::whereHas('user', function ($query) {
            $query->whereIn('type', ['manager', 'hr'])->where('status', 'active');
        })
            ->with(['user'])
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'employee_id' => $employee->employee_id,
                    'full_name' => trim(
                        $employee->first_name . ' ' . $employee->last_name
                    ),
                    'user_type' => $employee->user?->type,

                    'department' => $employee->user?->department?->name,
                    'designation' => $employee->user?->designation?->name,
                ];
            });

        return $this->success($employees);
    }

    public function getAllEmployees(): JsonResponse
    {
        $offboardingEmployeeIds = Offboarding::pluck('employee_id');

        $employees = Employee::whereNotIn('id', $offboardingEmployeeIds)
            ->whereHas('user', function ($query) {
                $query->where('type', '!=', 'admin')
                    ->where('status', '!=', 'offboarding');
            })
            ->with(['user.department', 'user.designation'])
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'employee_id' => $employee->employee_id,
                    'full_name' => trim(
                        $employee->first_name . ' ' . $employee->last_name
                    ),
                    'user_type' => $employee->user?->type,

                    'department' => $employee->user?->department?->name,
                    'designation' => $employee->user?->designation?->name,
                    'email' => $employee->personal_email,
                ];
            });

        return $this->success($employees);
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
                new OA\Property(property: 'message', type: 'string', example: 'Offboarding details retrieved successfully.'),
                new OA\Property(property: 'data', type: 'object'),
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
                new OA\Property(property: 'message', type: 'string', example: 'Visa cancellation details fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'employee',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 2),
                                new OA\Property(property: 'name', type: 'string', example: 'Dr. Vipul Paul Thomas'),
                                new OA\Property(property: 'employee_code', type: 'string', example: 'MSC-DOC-0002'),
                            ]
                        ),
                        new OA\Property(
                            property: 'visa_details',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'visa_number', type: 'string', example: '98765432111'),
                                new OA\Property(property: 'visa_expiry_date', type: 'string', format: 'date', example: '2026-06-30'),
                                new OA\Property(property: 'eid_number', type: 'string', example: '768-7679277893-88'),
                                new OA\Property(property: 'eid_expiry_date', type: 'string', format: 'date', example: '2026-07-02'),
                                new OA\Property(property: 'labor_number', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'visa_type', type: 'string', example: 'company_visa'),
                            ]
                        ),
                        new OA\Property(
                            property: 'tasks',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'task_name', type: 'string', example: 'Submit visa cancellation to GDRFA/ICP'),
                                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                    new OA\Property(property: 'responsible_role', type: 'string', example: 'PRO'),
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
        $tasks = $offboarding->checklists;

        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed')->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        return $this->success([
            'offboarding_id' => $offboarding->id,
            'employee' => [
                'id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'employee_code' => $employee->employee_id,
            ],
            'visa_details' => [
                'visa_number' => $employee->visa_number,
                'visa_expiry_date' => $employee->visa_expiry_date,    // fixed: was visa_expiry
                'eid_number' => $employee->eid_number,           // fixed: was emirates_id_number
                'eid_expiry_date' => $employee->eid_expiry_date,      // fixed: was emirates_id_expiry
                'labor_number' => $employee->labor_number,         // fixed: was labour_card_number
                'visa_type' => $employee->visa_type,
            ],
            'cancellation' => [
                'status' => $offboarding->cancellation_status,
                'date' => $offboarding->cancellation_date,
                'reference' => $offboarding->cancellation_reference,
                'document' => $offboarding->cancellation_document,
                'remarks' => $offboarding->cancellation_remarks,
            ],
            'tasks' => $tasks,
            'progress' => $progress,
        ], 'Visa cancellation details fetched successfully.');
    }

    // -------------------------------------------------------------------------
    // UPDATE VISA STATUS (cancellation details)
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/visa-status',
        operationId: 'updateVisaStatus',
        summary: 'Save visa cancellation details',
        description: 'Stores the cancellation status, date, reference number, supporting document, and remarks for the visa cancellation stage.',
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
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'cancellation_status',
                        type: 'string',
                        enum: ['pending', 'in_progress', 'completed', 'not_required'],
                        example: 'pending'
                    ),
                    new OA\Property(property: 'cancellation_date', type: 'string', format: 'date', nullable: true, example: '2026-08-20'),
                    new OA\Property(property: 'cancellation_reference', type: 'string', nullable: true, example: 'MOHRE-1234567'),
                    new OA\Property(property: 'cancellation_document', type: 'string', format: 'binary', nullable: true),
                    new OA\Property(property: 'cancellation_remarks', type: 'string', nullable: true, example: 'Submitted to GDRFA'),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Visa cancellation details updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Visa cancellation details updated successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function updateVisaStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'cancellation_status' => 'nullable|in:pending,in_progress,completed,not_required',
            'cancellation_date' => 'nullable|date',
            'cancellation_reference' => 'nullable|string|max:255',
            'cancellation_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'cancellation_remarks' => 'nullable|string',
        ]);

        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to update visa cancellation for this employee.',
                403
            );
        }

        $data = $request->only([
            'cancellation_status',
            'cancellation_date',
            'cancellation_reference',
            'cancellation_remarks',
        ]);

        // Handle optional file upload
        if ($request->hasFile('cancellation_document')) {
            $path = $request->file('cancellation_document')
                ->store('offboarding/visa-cancellation', 'public');
            $data['cancellation_document'] = $path;
        }

        $offboarding->update($data);

        return $this->success(
            $offboarding->fresh(),
            'Visa cancellation details updated successfully.'
        );
    }

    public function getSalaryPackages($id): JsonResponse
    {
        $employee = Employee::with('salaryPackages.salaryComponents')->findOrFail($id);
        return $this->success($employee, 'Salary packages fetched successfully.');
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
                new OA\Property(property: 'message', type: 'string', example: 'Visa cancellation process completed successfully.'),
                new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Pending tasks still exist',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'All visa cancellation tasks must be completed before proceeding.'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function completeVisaStatus(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'checklists' => fn($q) => $q->where('category_id', 1),
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

        $offboarding->update(['status' => 'pending_interview']);

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
                new OA\Property(property: 'message', type: 'string', example: 'Checklist item updated successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 5),
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(property: 'category_id', type: 'string', example: 'general'),
                        new OA\Property(property: 'task_name', type: 'string', example: 'Return access card'),
                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
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
            'task_id' => 'nullable|exists:offboarding_checklists,id',
            'status' => 'required|in:pending,completed,not_applicable',
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
                            new OA\Property(property: 'id', type: 'integer', example: 3),
                            new OA\Property(property: 'status', type: 'string', example: 'Returned'),
                            new OA\Property(property: 'condition', type: 'string', nullable: true, example: 'Good'),
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
                new OA\Property(property: 'message', type: 'string', example: 'Assets updated successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function updateAssets(Request $request, $offboarding_id): JsonResponse
    {
        $offboarding = Offboarding::find($offboarding_id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        foreach ($request->input('assets', []) as $asset) {
            EmployeeAsset::updateOrCreate(
                [
                    'id' => $asset['id'],
                    'employee_id' => $offboarding->employee->id,
                    'offboarding_id' => $offboarding->id,
                    'asset_name' => $asset['name'],
                    'status' => $asset['status'],
                    'condition' => $asset['return_condition'] ?? null,
                ]
            );
        }

        // If all assets are completed, move offboarding to next step
        if ($request->input('assets_status') === 'completed') {
            $offboarding->update([
                'status' => 'pending_settlement',
            ]);
        }

        return $this->success(
            [
                'assets' => EmployeeAsset::where('offboarding_id', $offboarding_id)->get(),
                'status' => $offboarding->status
            ],
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
                new OA\Property(property: 'interviewer', type: 'string', example: 'Fatima Al Zaabi (HR)'),
                new OA\Property(property: 'interview_date', type: 'string', format: 'date', example: '2026-06-17'),
                new OA\Property(property: 'interview_mode', type: 'string', example: 'In person'),
                new OA\Property(property: 'overall_satisfaction', type: 'string', example: 'Satisfied'),
                new OA\Property(property: 'primary_reason', type: 'string', example: 'Better opportunity'),
                new OA\Property(property: 'work_life_rating', type: 'string', nullable: true, example: '4'),
                new OA\Property(property: 'manager_relationship_rating', type: 'string', nullable: true, example: '5'),
                new OA\Property(property: 'enjoyed_most', type: 'string', nullable: true, example: 'Collaborative team culture'),
                new OA\Property(property: 'areas_for_improvement', type: 'string', nullable: true, example: 'Clearer promotion paths'),
                new OA\Property(property: 'would_recommend', type: 'boolean', example: true),
                new OA\Property(property: 'additional_comments', type: 'string', nullable: true, example: 'Would consider rejoining in the future.'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Exit interview submitted successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Exit interview submitted successfully.'),
                new OA\Property(property: 'data', type: 'object'),
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
                'additional_comments'
            ])
        );

        $offboarding->update(['status' => 'pending_letters']);

        return $this->success($interview, 'Exit interview submitted successfully.');
    }

    // -------------------------------------------------------------------------
    // GET SETTLEMENT
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/{id}/settlement',
        operationId: 'getSettlement',
        summary: 'Get the final settlement for an offboarding record',
        description: 'Returns the final settlement record (total payable, deductions, net payable, status, and remarks), the calculated leave encashment, and the employee\'s salary packages (with their salary components) for the given offboarding ID. Returns null settlement if none has been created yet.',
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
        description: 'Settlement fetched successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Settlement fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    nullable: true,
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 3),
                        new OA\Property(property: 'total_payable', type: 'number', format: 'float', example: 45000.00),
                        new OA\Property(property: 'total_deductions', type: 'number', format: 'float', example: 2000.00),
                        new OA\Property(property: 'net_payable', type: 'number', format: 'float', example: 43000.00),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'remarks', type: 'string', nullable: true),
                    ]
                ),
                new OA\Property(
                    property: 'calculated_settlement',
                    type: 'object',
                    description: 'Everything that can be auto-derived for the final settlement. Deductions default to 0.',
                    properties: [
                        new OA\Property(property: 'employee', type: 'object'),
                        new OA\Property(property: 'salary', type: 'object'),
                        new OA\Property(property: 'service_period', type: 'object', nullable: true),
                        new OA\Property(property: 'attendance', type: 'object'),
                        new OA\Property(property: 'leave', type: 'object'),
                        new OA\Property(property: 'leave_encashment', type: 'object'),
                        new OA\Property(property: 'gratuity', type: 'object'),
                        new OA\Property(property: 'overtime', type: 'object'),
                        new OA\Property(property: 'notice_period', type: 'object'),
                        new OA\Property(property: 'total_payable', type: 'number', format: 'float', example: 45000.00),
                        new OA\Property(property: 'total_deductions', type: 'number', format: 'float', example: 0.00),
                        new OA\Property(property: 'net_payable', type: 'number', format: 'float', example: 45000.00),
                    ]
                ),
                new OA\Property(
                    property: 'salary_packages',
                    type: 'array',
                    items: new OA\Items(type: 'object')
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function getSettlement(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'employee.user.designation',
            'employee.user.department',
            'employee.salaryPackages.salaryComponents',
            'settlement',
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $employee = $offboarding->employee;
        $salaryPackages = $employee->salaryPackages;

        $joiningDate = $employee->joining_date ? Carbon::parse($employee->joining_date) : null;
        $lastWorkingDay = $offboarding->last_working_day ? Carbon::parse($offboarding->last_working_day) : null;
        $settlementYear = $lastWorkingDay ? $lastWorkingDay->format('Y') : now()->format('Y');

        // ── Salary: basic + gross from the employee's active salary package(s) ──
        $activePackages = $salaryPackages->where('is_active', true);
        $packagesForSalary = $activePackages->isEmpty() ? $salaryPackages : $activePackages;

        $grossSalary = $packagesForSalary->reduce(function ($carry, $package) {
            return $carry + $package->salaryComponents->sum(fn($component) => (float) $component->value);
        }, 0.0);

        $basicSalary = $packagesForSalary->reduce(function ($carry, $package) {
            $basicComponent = $package->salaryComponents->first(
                fn($component) => stripos($component->component_name, 'basic') !== false
            );
            return $carry + ($basicComponent ? (float) $basicComponent->value : 0.0);
        }, 0.0);

        // ── Service period ──
        $servicePeriod = null;
        $serviceYears = 0.0;
        if ($joiningDate && $lastWorkingDay) {
            $totalDays = $joiningDate->diffInDays($lastWorkingDay);
            $diff = $joiningDate->diff($lastWorkingDay);
            $serviceYears = round($totalDays / 365, 2);

            $servicePeriod = [
                'years' => $diff->y,
                'months' => $diff->m,
                'days' => $diff->d,
                'total_days' => $totalDays,
                'total_years' => $serviceYears,
            ];
        }

        // ── Working days vs. days worked in the final (exit) month ──
        $workingDays = 0;
        $daysWorked = 0;
        if ($lastWorkingDay) {
            $periodStart = $lastWorkingDay->copy()->startOfMonth();
            if ($joiningDate && $joiningDate->gt($periodStart)) {
                $periodStart = $joiningDate->copy();
            }

            $workingDays = max(1, $periodStart->diffInWeekdays($lastWorkingDay) + 1);

            $daysWorked = AttendanceLog::where('userid', $employee->user_id)
                ->whereBetween('log_date', [$periodStart->toDateString(), $lastWorkingDay->toDateString()])
                ->where('log_status', 'out')
                ->count();
        }

        // ── Leave: allocation, taken, unpaid leave, balance ──
        $leaveAllocated = LeaveAllocation::where('employee_id', $employee->id)
            ->where('year', $settlementYear)
            ->sum('allocated_days');

        $leaveTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->sum('duration_days');

        $unpaidLeaveDays = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('claim_salary', false)
            ->sum('duration_days');

        $leaveBalanceDays = max(0, $leaveAllocated - $leaveTaken);

        // ── Leave encashment ──
        $perDaySalary = $grossSalary / 30;
        $leaveEncashmentAmount = round($perDaySalary * $leaveBalanceDays, 2);

        // ── Gratuity (UAE labour law: 21 days/year up to 5 years, 30 days/year beyond, capped at 2 years' basic pay) ──
        $gratuityDays = 0.0;
        if ($serviceYears >= 1) {
            $gratuityDays = $serviceYears <= 5
                ? 21 * $serviceYears
                : (21 * 5) + (30 * ($serviceYears - 5));
        }
        $dailyBasicSalary = $basicSalary / 30;
        $gratuityAmount = round($dailyBasicSalary * $gratuityDays, 2);
        $gratuityCap = round($basicSalary * 24, 2);
        if ($gratuityCap > 0) {
            $gratuityAmount = min($gratuityAmount, $gratuityCap);
        }

        // ── Overtime owed: sum from payroll records not yet completed ──
        $overtimeAmount = (float) Payroll::where('user_id', $employee->user_id)
            ->where('status', '!=', 'completed')
            ->sum('overtime');

        // ── Notice period ──
        $noticePeriodDays = $offboarding->notice_period_days;
        $noticeStartDate = $offboarding->notice_start_date ? Carbon::parse($offboarding->notice_start_date) : null;
        $noticeEndDate = ($noticeStartDate && $noticePeriodDays)
            ? $noticeStartDate->copy()->addDays($noticePeriodDays)->toDateString()
            : null;
        $noticeDaysServed = ($noticeStartDate && $lastWorkingDay)
            ? max(0, $noticeStartDate->diffInDays($lastWorkingDay) + 1)
            : null;
        $noticeShortfallDays = ($noticePeriodDays !== null && $noticeDaysServed !== null)
            ? max(0, $noticePeriodDays - $noticeDaysServed)
            : null;

        // ── Totals (deductions default to 0) ──
        $totalDeductions = 0.0;
        $totalPayable = round($leaveEncashmentAmount + $gratuityAmount + $overtimeAmount, 2);
        $netPayable = round($totalPayable - $totalDeductions, 2);

        return $this->success([
            'settlement' => $offboarding->settlement,
            'calculated_settlement' => [
                'employee' => [
                    'id' => $employee->id,
                    'employee_id' => $employee->employee_id,
                    'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                    'designation' => $employee->user->designation->name ?? null,
                    'department' => $employee->user->department->name ?? null,
                    'joining_date' => $employee->joining_date,
                    'last_working_day' => $offboarding->last_working_day,
                ],
                'salary' => [
                    'basic_salary' => round($basicSalary, 2),
                    'gross_salary' => round($grossSalary, 2),
                    'per_day_salary' => round($perDaySalary, 2),
                ],
                'service_period' => $servicePeriod,
                'attendance' => [
                    'working_days' => $workingDays,
                    'days_worked' => $daysWorked,
                ],
                'leave' => [
                    'leave_allocated' => (float) $leaveAllocated,
                    'leave_taken' => (float) $leaveTaken,
                    'unpaid_leave_days' => (float) $unpaidLeaveDays,
                    'leave_balance_days' => (float) $leaveBalanceDays,
                ],
                'leave_encashment' => [
                    'per_day_salary' => round($perDaySalary, 2),
                    'leave_balance_days' => (float) $leaveBalanceDays,
                    'amount' => $leaveEncashmentAmount,
                ],
                'gratuity' => [
                    'eligible_service_years' => $serviceYears,
                    'gratuity_days' => round($gratuityDays, 2),
                    'daily_basic_salary' => round($dailyBasicSalary, 2),
                    'amount' => $gratuityAmount,
                ],
                'overtime' => [
                    'amount' => round($overtimeAmount, 2),
                ],
                'notice_period' => [
                    'notice_period_days' => $noticePeriodDays,
                    'notice_start_date' => $offboarding->notice_start_date,
                    'notice_end_date' => $noticeEndDate,
                    'days_served' => $noticeDaysServed,
                    'shortfall_days' => $noticeShortfallDays,
                ],
                'total_payable' => $totalPayable,
                'total_deductions' => $totalDeductions,
                'net_payable' => $netPayable,
            ],
            'salary_packages' => $salaryPackages,
        ], 'Settlement fetched successfully.');
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
                new OA\Property(property: 'total_payable', type: 'number', format: 'float', example: 45000.00),
                new OA\Property(property: 'total_deductions', type: 'number', format: 'float', example: 2000.00),
                new OA\Property(property: 'net_payable', type: 'number', format: 'float', example: 43000.00),
                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                new OA\Property(property: 'remarks', type: 'string', nullable: true, example: 'Includes gratuity and leave encashment'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Settlement updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Settlement updated successfully.'),
                new OA\Property(property: 'data', type: 'object'),
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

        // If all assets are completed, move offboarding to next step
        if ($request->input('status') === 'approved') {
            $offboarding->update([
                'status' => 'pending_visa',
            ]);
        }

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
                new OA\Property(property: 'letter_type', type: 'string', example: 'experience_letter'),
                new OA\Property(property: 'document_path', type: 'string', nullable: true, example: 'letters/exp_letter_emp2.pdf'),
                new OA\Property(property: 'status', type: 'string', example: 'pending'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Letter record updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Letter record updated successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function generateLetters(Request $request, $id): JsonResponse
    {
        $request->validate([
            'letter_type' => 'required|string|in:experience_letter,noc,relieving_letter,final_settlement,resignation_acceptance',
            'status' => 'nullable|string',
        ]);

        $offboarding = Offboarding::with([
            'employee.user.designation',
            'employee.user.department',
            'reportingManager.user.designation',
            'settlement',
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $letterType = $request->input('letter_type');

        /*
        |--------------------------------------------------------------------------
        | Map letter_type → Blade view
        |--------------------------------------------------------------------------
        */
        $viewMap = [
            'experience_letter' => 'pdf.offboarding.experience_letter',
            'noc' => 'pdf.offboarding.noc',
            'relieving_letter' => 'pdf.offboarding.relieving_letter',
            'final_settlement' => 'pdf.offboarding.final_settlement',
            'resignation_acceptance' => 'pdf.offboarding.resignation_acceptance',
        ];

        $view = $viewMap[$letterType] ?? null;

        if (!$view || !view()->exists($view)) {
            return $this->error("Template not found for letter type: {$letterType}", 500);
        }

        try {
            /*
            |----------------------------------------------------------------------
            | Generate PDF
            |----------------------------------------------------------------------
            */
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, [
                'offboarding' => $offboarding,
            ])->setPaper('a4', 'portrait');

            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', false);
            $pdf->setOption('defaultFont', 'DejaVu Sans');

            /*
            |----------------------------------------------------------------------
            | Save to storage/app/public/offboarding/letters/
            |----------------------------------------------------------------------
            */
            $filename = "{$letterType}_offboarding_{$offboarding->id}_" . now()->format('YmdHis') . '.pdf';
            $storedPath = 'offboarding/letters/' . $filename;

            \Storage::disk('public')->put($storedPath, $pdf->output());

            /*
            |----------------------------------------------------------------------
            | Persist the OffboardingLetter record
            |----------------------------------------------------------------------
            */
            $letter = OffboardingLetter::updateOrCreate(
                [
                    'offboarding_id' => $offboarding->id,
                    'letter_type' => $letterType,
                ],
                [
                    'document_path' => $storedPath,
                    'status' => $request->input('status', 'generated'),
                ]
            );

            $letter->document_url = \Storage::disk('public')->url($storedPath);

            return $this->success($letter, 'Letter generated successfully.');
        } catch (\Exception $e) {
            \Log::error("Failed to generate {$letterType} for offboarding {$offboarding->id}: " . $e->getMessage());
            return $this->error('Failed to generate letter: ' . $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // UPLOAD LETTER FILE
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/letters/upload',
        operationId: 'uploadOffboardingLetter',
        summary: 'Upload a letter document for an offboarding record',
        description: 'Accepts a file upload (PDF, DOCX, JPG, PNG) for a specific letter type and stores it. Creates or updates the OffboardingLetter record with the stored file path.',
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
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['letter_type', 'file'],
                properties: [
                    new OA\Property(
                        property: 'letter_type',
                        type: 'string',
                        example: 'experience_letter',
                        description: 'Type of the letter (e.g. experience_letter, noc, visa_cancellation)'
                    ),
                    new OA\Property(
                        property: 'file',
                        type: 'string',
                        format: 'binary',
                        description: 'Document file (PDF, DOCX, JPG, PNG – max 10 MB)'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Letter file uploaded successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Letter uploaded successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function uploadLetter(Request $request, $id): JsonResponse
    {
        $request->validate([
            'letter_type' => 'required|string|max:100',
            'file' => 'required|file|mimes:pdf,docx,jpg,jpeg,png|max:10240',
        ]);

        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $letterType = $request->input('letter_type');
        $file = $request->file('file');

        // Build a deterministic filename:
        //   offboarding_{id}_{letter_type}_{timestamp}.{ext}
        $ext = $file->getClientOriginalExtension();
        $filename = "offboarding_{$offboarding->id}_{$letterType}_" . now()->format('YmdHis') . ".{$ext}";

        // Store in storage/app/public/offboarding/letters/
        $storedPath = $file->storeAs('offboarding/letters', $filename, 'public');

        // Update or create the letter record
        $letter = OffboardingLetter::updateOrCreate(
            [
                'offboarding_id' => $offboarding->id,
                'letter_type' => $letterType,
            ],
            [
                'document_path' => $storedPath,
                'status' => 'uploaded',
            ]
        );

        $letter->document_url = \Storage::disk('public')->url($storedPath);

        return $this->success($letter, 'Letter uploaded successfully.');
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
                new OA\Property(property: 'message', type: 'string', example: 'Offboarding progress fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(property: 'current_status', type: 'string', example: 'pending_checklist'),
                        new OA\Property(property: 'completed_steps', type: 'integer', example: 2),
                        new OA\Property(property: 'total_steps', type: 'integer', example: 7),
                        new OA\Property(property: 'progress_percentage', type: 'integer', example: 29),
                        new OA\Property(
                            property: 'steps',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'Visa Cancellation'),
                                    new OA\Property(property: 'key', type: 'string', example: 'visa'),
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

        $steps = $this->getOffboardingSteps($offboarding);
        $completedSteps = collect($steps)->where('status', 'completed')->count();
        $totalSteps = count($steps);

        return $this->success([
            'offboarding_id' => $offboarding->id,
            'current_status' => $offboarding->status,
            'completed_steps' => $completedSteps,
            'total_steps' => $totalSteps,
            'progress_percentage' => $totalSteps > 0
                ? round(($completedSteps / $totalSteps) * 100)
                : 0,
            'steps' => $steps,
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
                'name' => 'Initiation',
                'key' => 'initiation',
                'status' => $offboarding->id ? 'completed' : 'pending',
            ],
            [
                'name' => 'Asset Return',
                'key' => 'asset',
                'status' => $this->isAssetsReturned($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_assets' ? 'in_progress' : 'pending'),
            ],
            [
                'name' => 'Final Settlement',
                'key' => 'settlement',
                'status' => $this->isSettlementCompleted($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_settlement' ? 'in_progress' : 'pending'),
            ],
            [
                'name' => 'Visa Cancellation',
                'key' => 'visa',
                'status' => $this->isVisaCompleted($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_visa'
                        ? 'in_progress'
                        : 'pending'),
            ],
            [
                'name' => 'Exit Interview',
                'key' => 'interview',
                'status' => $this->isInterviewCompleted($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_interview' ? 'in_progress' : 'pending'),
            ],
            [
                'name' => 'Letters & Documents',
                'key' => 'letters',
                'status' => $this->isLettersGenerated($offboarding)
                    ? 'completed'
                    : ($offboarding->status === 'pending_letters' ? 'in_progress' : 'pending'),
            ]
        ];
    }


    // -------------------------------------------------------------------------
    // COMPLETE OFFBOARDING
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/complete',
        operationId: 'completeOffboarding',
        summary: 'Mark the offboarding process as completed',
        description: 'Marks the offboarding record\'s status as `completed`. All steps (visa cancellation, checklist, exit interview, final settlement, letters, asset return) must already be completed.',
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
        description: 'Offboarding marked as completed',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Offboarding marked as completed successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Pending steps still exist',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'All offboarding steps must be completed first: Final Settlement, Letters & Documents.'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function completeOffboarding(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
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
                'You are not authorized to complete this offboarding record.',
                403
            );
        }

        $pendingSteps = collect($this->getOffboardingSteps($offboarding))
            ->where('status', '!=', 'completed')
            ->pluck('name');

        if ($pendingSteps->isNotEmpty()) {
            return $this->error(
                'All offboarding steps must be completed first: ' . $pendingSteps->implode(', ') . '.',
                422
            );
        }

        $offboarding->update(['status' => 'completed']);
        // Deactivate the employee's user account
        if ($offboarding->employee?->user) {
            $offboarding->employee->user->update([
                'status' => 'offboarding'
            ]);
        }

        return $this->success($offboarding->fresh(), 'Offboarding marked as completed successfully.');
    }

    // -------------------------------------------------------------------------
    // DELETE OFFBOARDING
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/admin/offboarding/{id}',
        operationId: 'deleteOffboarding',
        summary: 'Delete an offboarding record',
        description: 'Deletes an offboarding record and its related checklist, assets, interview, settlement, and letter records.',
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
        description: 'Offboarding deleted successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'success',
                    type: 'boolean',
                    example: true
                ),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Offboarding record deleted successfully.'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 404,
        description: 'Offboarding record not found'
    )]
    public function destroy(Request $request, $id): JsonResponse
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
            return $this->error(
                'Offboarding record not found.',
                404
            );
        }

        // Check authorization
        if (
            !$this->isAuthorized(
                $request->user(),
                $offboarding->employee
            )
        ) {
            return $this->error(
                'You are not authorized to delete this offboarding record.',
                403
            );
        }

        try {

            \DB::transaction(function () use ($offboarding) {

                // Delete related records
                $offboarding->checklists()->delete();
                $offboarding->assets()->delete();
                $offboarding->interview()->delete();
                $offboarding->settlement()->delete();
                $offboarding->letters()->delete();

                // Delete employee's asset assignments
                AssetAssignment::where('employee_id', $offboarding->employee_id)->delete();

                // Delete offboarding record
                $offboarding->delete();
            });

            return $this->success(
                null,
                'Offboarding record deleted successfully.'
            );
        } catch (\Exception $e) {

            \Log::error(
                "Failed to delete offboarding {$offboarding->id}: "
                    . $e->getMessage()
            );

            return $this->error(
                'Failed to delete offboarding record.',
                500
            );
        }
    }
    /**
     * True when all visa_cancellation checklist tasks are done/not_applicable.
     */
    // private function isVisaCompleted(Offboarding $offboarding): bool
    // {
    //     $visaTasks = $offboarding->checklists
    //         ->where('category_id', 1);

    //     return $visaTasks->isNotEmpty()
    //         && $visaTasks->whereNotIn('status', ['completed', 'not_applicable'])->isEmpty();
    // }

    private function isVisaCompleted(Offboarding $offboarding): bool
    {
        return in_array($offboarding->cancellation_status, ['pending', 'completed']);
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

        // No assets assigned = asset return step completed
        if ($assets->isEmpty()) {
            return true;
        }

        // Assets exist = all must be returned
        return $assets->where('status', '!=', 'returned')->isEmpty();
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

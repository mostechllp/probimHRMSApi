<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\ProjectCostExport;
use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\ProjectCostService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectCostApiController extends ApiController
{
    protected ProjectCostService $projectCostService;

    public function __construct(ProjectCostService $projectCostService)
    {
        $this->projectCostService = $projectCostService;
    }

    /**
     * Check if authenticated user is Super Admin or Management.
     */
    protected function authorizeAccess(Request $request): void
    {
        // Handle token from query parameter for direct download links
        if (!$request->bearerToken() && $request->has('token')) {
            try {
                $user = auth('api')->setToken($request->token)->user();
                if ($user) {
                    auth('api')->setUser($user);
                }
            } catch (\Exception $e) {
                // Handled by subsequent auth check
            }
        }

        $user = auth('api')->user() ?? auth()->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $roleName = strtolower(trim($user->role?->name ?? ''));
        $userType = strtolower(trim($user->type ?? ''));

        $isSuperAdmin = in_array($roleName, ['super admin', 'admin']) || $userType === 'admin';
        $isManagement = in_array($roleName, ['management', 'bim manager', 'hr manager', 'manager'])
            || str_contains($roleName, 'manager')
            || $userType === 'manager';

        if (!$isSuperAdmin && !$isManagement) {
            abort(403, 'Unauthorized access. Project Cost is restricted to Super Admin and Management.');
        }
    }

    /**
     * Display a listing of project cost summaries.
     *
     * GET /api/admin/projects/cost
     * Query Parameters:
     * - filter: this_month (default) | last_month | this_quarter | project_to_date | custom
     * - start_date, end_date (for custom filter, YYYY-MM-DD)
     * - month, year (optional, integer)
     * - project_id (optional, integer)
     * - search (optional, string)
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $report = $this->projectCostService->getProjectCostReport($request->all());

        return $this->success($report, 'Project cost data retrieved successfully.');
    }

    /**
     * Display detailed cost, employee breakdown, and daily hours for a specific project.
     *
     * GET /api/admin/projects/cost/{project}
     */
    public function show($id, Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $details = $this->projectCostService->getProjectCostDetails((int) $id, $request->all());

        if (!$details) {
            return $this->error('Project not found.', 404);
        }

        return $this->success($details, 'Project cost details retrieved successfully.');
    }

    /**
     * Unified Export Endpoint supporting both Excel and PDF.
     *
     * GET /api/admin/projects/cost/export?format=xlsx|pdf
     */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);

        $format = strtolower($request->get('format', 'xlsx'));

        if ($format === 'pdf') {
            return $this->exportPdf($request);
        }

        return $this->exportExcel($request);
    }

    /**
     * Export Project Cost report as an Excel spreadsheet (.xlsx).
     *
     * GET /api/admin/projects/cost/export/excel
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorizeAccess($request);

        $report = $this->projectCostService->getProjectCostReport($request->all());
        $filename = 'Project_Cost_Report_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ProjectCostExport($report), $filename, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export Project Cost report as a PDF document (.pdf).
     *
     * GET /api/admin/projects/cost/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $this->authorizeAccess($request);

        $report = $this->projectCostService->getProjectCostReport($request->all());
        $filename = 'Project_Cost_Report_' . now()->format('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('reports.project_cost_pdf', $report)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        return $pdf->download($filename);
    }
}


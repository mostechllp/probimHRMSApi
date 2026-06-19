<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PayrollController extends Controller
{
    /**
     * Get the current draft payroll for an employee
     */
    public function getDraft($user_id): JsonResponse
    {
        $employee = Employee::where('user_id', $user_id)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found for this user'], 404);
        }

        $draft = Payroll::where('user_id', $user_id)
            ->where('status', 'draft')
            ->first();

        if (!$draft) {
            // Return an empty template
            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user_id,
                    'current_step' => 1,
                    'data' => []
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $draft
        ]);
    }

    /**
     * Save step data
     */
    public function saveStep(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'step' => 'required|integer|min:1|max:6',
            'step_data' => 'required|array'
        ]);

        $user_id = $request->user_id;
        $step = $request->step;
        $step_data = $request->step_data;

        $draft = Payroll::where('user_id', $user_id)
            ->where('status', 'draft')
            ->first();

        if (!$draft) {
            $draft = new Payroll();
            $draft->user_id = $user_id;
            $draft->status = 'draft';
            $draft->data = [];
        }

        // Update basic info from step 1
        if ($step === 1) {
            if (isset($step_data['pay_period_month'])) {
                $draft->pay_period_month = $step_data['pay_period_month'];
            }
            if (isset($step_data['pay_period_year'])) {
                $draft->pay_period_year = $step_data['pay_period_year'];
            }
        }

        // Merge existing data with new step data
        $currentData = $draft->data ?? [];
        $currentData["step_{$step}"] = $step_data;
        $draft->data = $currentData;

        // Update the current step if progressing forward
        if ($step >= $draft->current_step) {
            // Next step to show is step + 1, unless it's step 6
            $draft->current_step = min(6, $step + 1);
        }

        $draft->save();

        return response()->json([
            'success' => true,
            'message' => "Step $step saved successfully",
            'data' => $draft
        ]);
    }

    /**
     * Submit and finalize the payroll
     */
    public function submitPayroll(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $user_id = $request->user_id;

        $draft = Payroll::where('user_id', $user_id)
            ->where('status', 'draft')
            ->first();

        if (!$draft) {
            return response()->json(['success' => false, 'message' => 'No active draft found to submit'], 404);
        }

        // Mark as completed
        $draft->status = 'completed';
        $draft->save();

        // Optionally generate PDF
        try {
            // Load employee relation for PDF
            $draft->load('employee');

            // Create a view for the PDF (e.g., resources/views/pdf/payslip.blade.php)
            // If the view doesn't exist, we can fallback to a basic response.
            if (view()->exists('pdf.payslip')) {
                $pdf = Pdf::loadView('pdf.payslip', ['payroll' => $draft]);

                // Save PDF to storage (optional)
                // $path = storage_path('app/public/payslips/' . $draft->id . '.pdf');
                // $pdf->save($path);

                // We're just returning success here. The frontend can download it if needed.
            }
        } catch (\Exception $e) {
            // Log error, but still return success since status is updated
            \Log::error("Failed to generate PDF for payroll {$draft->id}: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Payroll generated and submitted successfully',
            'data' => $draft
        ]);
    }

    /**
     * Get history of completed payrolls
     */
    public function history(Request $request): JsonResponse
    {
        $user_id = $request->query('user_id');

        $query = Payroll::where('status', 'completed'); // ->with('employee') might need change depending on relation

        if ($user_id) {
            $query->where('user_id', $user_id);
        }

        $payrolls = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $payrolls
        ]);
    }
}

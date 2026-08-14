<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\ForgotPasswordApiController;
use App\Http\Controllers\Api\Admin\OffboardingApiController;
use App\Http\Controllers\Api\Admin\OffboardingChecklistApiController;
use App\Http\Controllers\Api\Admin\EmployeeApiController;
use App\Http\Controllers\Api\Admin\OrganizationApiController;
use App\Http\Controllers\Api\Admin\CompanyApiController;
use App\Http\Controllers\Api\Admin\HRApiController;
use App\Http\Controllers\Api\Admin\DashboardApiController;
use App\Http\Controllers\Api\Admin\PartyApiController;
use App\Http\Controllers\Api\Admin\DocumentApiController;
use App\Http\Controllers\Api\Admin\FolderApiController;
use App\Http\Controllers\Api\Admin\LeaveApiController;
use App\Http\Controllers\Api\Admin\WfhApiController;
use App\Http\Controllers\Api\Admin\AttendanceApiController;
use App\Http\Controllers\Api\Admin\HolidayApiController;
use App\Http\Controllers\Api\Admin\LeaveTypeApiController;
use App\Http\Controllers\Api\Admin\ReportApiController;
use App\Http\Controllers\Api\Admin\TaskReportApiController as AdminTaskReportApiController;
use App\Http\Controllers\Api\Admin\AttendanceRequestApiController as AdminAttendanceRequestApiController;
use App\Http\Controllers\Api\Admin\LeaveAllocationApiController;
use App\Http\Controllers\Api\Employee\EmployeePortalApiController;
use App\Http\Controllers\Api\Employee\ProfileApiController;
use App\Http\Controllers\Api\Employee\AttendanceRequestApiController as EmployeeAttendanceRequestApiController;
use App\Http\Controllers\Api\Admin\FileApiController;
use App\Http\Controllers\Api\Admin\RoleApiController;
use App\Http\Controllers\Api\Admin\ModuleApiController;
use App\Http\Controllers\Api\Admin\UserApiController;
use App\Http\Controllers\Api\Admin\ProjectApiController;
use App\Http\Controllers\Api\Admin\ProjectAssignmentApiController;
use App\Http\Controllers\Api\Admin\EmployeeBankDetailApiController;
use App\Http\Controllers\Api\Admin\EmployeeSalaryComponentApiController;
use App\Http\Controllers\Api\Admin\EmployeeOnboardingApiController;
use App\Http\Controllers\Api\Admin\WorkingHourApiController;
use App\Http\Controllers\Api\Admin\AssetTypeApiController;
use App\Http\Controllers\Api\Admin\AssetApiController;
use App\Http\Controllers\Api\Admin\OffboardingChecklistCategoryController;
use App\Http\Controllers\Api\Admin\PayrollController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Unified Auth Routes
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [LoginController::class, 'refresh']);
    Route::get('me', [LoginController::class, 'me'])->middleware('auth:api');
    Route::get('me/permissions', [LoginController::class, 'getMyPermissions'])->middleware('auth:api');
    Route::get('me/sidebar-modules', [LoginController::class, 'getMySidebarModules'])->middleware('auth:api');

    Route::post('forgot-password', [ForgotPasswordApiController::class, 'sendResetCode']);
    Route::post('reset-password', [ForgotPasswordApiController::class, 'resetPassword']);
});

// RBAC Management Routes
Route::group(['middleware' => 'auth:api', 'prefix' => 'admin'], function () {
    // Roles
    Route::apiResource('roles', RoleApiController::class);
    Route::get('roles/{role}/permissions', [RoleApiController::class, 'getPermissions']);
    Route::post('roles/{role}/permissions', [RoleApiController::class, 'updatePermissions']);

    // Modules
    Route::apiResource('modules', ModuleApiController::class);

    // Users
    Route::apiResource('users', UserApiController::class);
});

Route::group(['middleware' => 'auth:api', 'prefix' => 'admin'], function () {
    // General File Download
    Route::get('download', [FileApiController::class, 'download']);

    // Dashboard
    Route::get('dashboard', [DashboardApiController::class, 'index'])->middleware('permission:dashboard.read');
    Route::get('dashboard/summary', [DashboardApiController::class, 'getSummaryStats'])->middleware('permission:dashboard.read');
    Route::get('dashboard/charts', [DashboardApiController::class, 'getDetailedChartData'])->middleware('permission:dashboard.read');
    Route::get('notifications', [DashboardApiController::class, 'getNotifications'])->middleware('permission:dashboard.read');
    Route::get('notifications/all', [DashboardApiController::class, 'getAllNotifications'])->middleware('permission:dashboard.read');
    Route::get('notifications/read', [DashboardApiController::class, 'getReadNotifications'])->middleware('permission:dashboard.read');
    Route::post('notifications/mark-all-as-read', [DashboardApiController::class, 'markAllAsRead'])->middleware('permission:dashboard.edit');
    Route::get('notifications/{id}', [DashboardApiController::class, 'showNotification'])->middleware('permission:dashboard.read');
    Route::post('notifications/{id}/mark-as-read', [DashboardApiController::class, 'markAsRead'])->middleware('permission:dashboard.edit');

    // Employees
    Route::get('employees', [EmployeeApiController::class, 'index'])->middleware('permission:employees.read');
    Route::get('employees/salary-packages/{id}', [EmployeeOnboardingApiController::class, 'getSalaryPackages'])->middleware('permission:employees.edit');
    Route::get('employees/{employee}', [EmployeeApiController::class, 'show'])->middleware('permission:employees.read');
    Route::post('employees', [EmployeeApiController::class, 'store'])->middleware('permission:employees.edit');
    Route::put('employees/{employee}', [EmployeeApiController::class, 'update'])->middleware('permission:employees.edit');
    Route::delete('employees/{employee}', [EmployeeApiController::class, 'destroy'])->middleware('permission:employees.delete');

    Route::post('employees/upload-temp', [EmployeeApiController::class, 'uploadTemp']);
    Route::post('employees/{employee}/update-status', [EmployeeApiController::class, 'updateStatus'])->middleware('permission:employees.edit');

    // Onboarding
    Route::post('employees/onboard/details', [EmployeeOnboardingApiController::class, 'saveDetails'])->middleware('permission:employees.edit');
    Route::post('employees/onboard/salary', [EmployeeOnboardingApiController::class, 'saveSalary'])->middleware('permission:employees.edit');
    Route::post('employees/onboard/banks', [EmployeeOnboardingApiController::class, 'saveBanks'])->middleware('permission:employees.edit');
    Route::post('employees/onboard/complete', [EmployeeOnboardingApiController::class, 'complete'])->middleware('permission:employees.edit');
    // Employee Bank Details
    Route::put('bank-details/{id}', [EmployeeBankDetailApiController::class, 'update'])->middleware('permission:employees.edit');
    Route::delete('bank-details/{id}', [EmployeeBankDetailApiController::class, 'destroy'])->middleware('permission:employees.edit');

    // Employee Salary Components
    Route::post('salary-components', [EmployeeSalaryComponentApiController::class, 'store'])->middleware('permission:employees.edit');
    Route::put('salary-components/{id}', [EmployeeSalaryComponentApiController::class, 'update'])->middleware('permission:employees.edit');
    Route::delete('salary-components/{id}', [EmployeeSalaryComponentApiController::class, 'destroy'])->middleware('permission:employees.edit');


    // Attendance
    Route::get('attendance/stats', [AttendanceApiController::class, 'stats'])->middleware('permission:attendance.read');
    Route::get('attendance', [AttendanceApiController::class, 'index'])->middleware('permission:attendance.read');
    Route::post('attendance', [AttendanceApiController::class, 'store'])->middleware('permission:attendance.edit');
    Route::put('attendance/{id}', [AttendanceApiController::class, 'update'])->middleware('permission:attendance.edit');
    Route::delete('attendance/{id}', [AttendanceApiController::class, 'destroy'])->middleware('permission:attendance.delete');
    Route::post('attendance/upload', [AttendanceApiController::class, 'upload'])->middleware('permission:attendance.edit');
    Route::get('attendance/upload-status/{id}', [AttendanceApiController::class, 'uploadStatus'])->middleware('permission:attendance.read');
    Route::get('attendance/punch-in-today', [AttendanceApiController::class, 'punchInToday'])->middleware('permission:attendance.read');
    Route::get('attendance/punch-in-yesterday', [AttendanceApiController::class, 'punchInYesterday'])->middleware('permission:attendance.read');
    Route::get('attendance/punch-out-today', [AttendanceApiController::class, 'punchOutToday'])->middleware('permission:attendance.read');
    Route::get('attendance/late-comers', [AttendanceApiController::class, 'lateComers'])->middleware('permission:attendance.read');
    Route::get('attendance/absentees', [AttendanceApiController::class, 'absentees'])->middleware('permission:attendance.read');

    // Attendance Requests
    Route::get('attendance-requests', [AdminAttendanceRequestApiController::class, 'index'])->middleware('permission:attendance.read');
    Route::post('attendance-requests/{attendanceRequest}/status', [AdminAttendanceRequestApiController::class, 'updateStatus'])->middleware('permission:attendance.edit');
    Route::put('attendance-requests/{attendanceRequest}', [AdminAttendanceRequestApiController::class, 'update'])->middleware('permission:attendance.edit');
    Route::get('attendance-requests/{attendanceRequest}', [AdminAttendanceRequestApiController::class, 'show'])->middleware('permission:attendance.edit');
    Route::delete('attendance-requests/{attendanceRequest}', [AdminAttendanceRequestApiController::class, 'destroy'])->middleware('permission:attendance.delete');

    // Organizations & Companies
    Route::apiResource('organizations', OrganizationApiController::class);
    Route::apiResource('companies', CompanyApiController::class);
    Route::apiResource('parties', PartyApiController::class);
    Route::apiResource('documents', DocumentApiController::class);
    Route::apiResource('folders', FolderApiController::class);
    Route::get('documents/expiring', [DocumentApiController::class, 'getExpiringDocuments']);
    Route::post('documents/send-expiry-alerts', [DocumentApiController::class, 'sendExpiryAlerts']);
    Route::post('documents/upload', [DocumentApiController::class, 'upload']);
    Route::get('documents-folders', [DocumentApiController::class, 'getFolders']);
    Route::get('shareable-users', [DocumentApiController::class, 'getShareableUsers']);

    // Projects
    Route::get('projects/eligible-managers', [ProjectApiController::class, 'getEligibleManagers']);
    Route::get('projects/eligible-team-leads', [ProjectApiController::class, 'getEligibleTeamLeads']);
    Route::apiResource('projects', ProjectApiController::class);
    Route::get('project-assignments', [ProjectAssignmentApiController::class, 'index'])->middleware('permission:project-assignments.read');
    Route::get('project-assignments/monthly-hours', [ProjectAssignmentApiController::class, 'monthlyProjectHours'])->middleware('permission:project-assignments.read');
    Route::get('project-assignments/employees', [ProjectAssignmentApiController::class, 'getEmployees'])->middleware('permission:project-assignments.read');
    Route::get('project-assignments/{id}', [ProjectAssignmentApiController::class, 'show'])->middleware('permission:project-assignments.read');
    Route::get('project-assignments/{id}/working-time', [ProjectAssignmentApiController::class, 'workingTime'])->middleware('permission:project-assignments.read');
    Route::post('employees/projects', [ProjectAssignmentApiController::class, 'assign'])->middleware('permission:project-assignments.edit');
    Route::delete('project-assignments/{id}/all', [ProjectAssignmentApiController::class, 'removeAllAssignments'])->middleware('permission:project-assignments.delete');

    // HR Modules
    Route::get('designations', [HRApiController::class, 'indexDesignations'])->middleware('permission:organizations.read');
    Route::post('designations', [HRApiController::class, 'storeDesignation'])->middleware('permission:organizations.read');
    Route::get('designations/{designation}', [HRApiController::class, 'showDesignation'])->middleware('permission:organizations.read');
    Route::put('designations/{designation}', [HRApiController::class, 'updateDesignation'])->middleware('permission:organizations.read');
    Route::delete('designations/{designation}', [HRApiController::class, 'destroyDesignation'])->middleware('permission:organizations.read');

    Route::get('departments', [HRApiController::class, 'indexDepartments'])->middleware('permission:organizations.read');
    Route::post('departments', [HRApiController::class, 'storeDepartment'])->middleware('permission:organizations.read');
    Route::get('departments/{department}', [HRApiController::class, 'showDepartment'])->middleware('permission:organizations.read');
    Route::put('departments/{department}', [HRApiController::class, 'updateDepartment'])->middleware('permission:organizations.read');
    Route::delete('departments/{department}', [HRApiController::class, 'destroyDepartment'])->middleware('permission:organizations.read');

    // Leave Management (Admin side)
    Route::get('leaves', [LeaveApiController::class, 'index'])->middleware('permission:leaves.read');
    Route::get('leaves/{leaveRequest}', [LeaveApiController::class, 'show'])->middleware('permission:leaves.read');
    Route::post('leaves', [LeaveApiController::class, 'store'])->middleware('permission:leaves.create');
    Route::post('leaves/{leaveRequest}', [LeaveApiController::class, 'update'])->middleware('permission:leaves.edit');
    Route::delete('leaves/{leaveRequest}', [LeaveApiController::class, 'destroy'])->middleware('permission:leaves.delete');
    Route::post('leaves/{leaveRequest}/status', [LeaveApiController::class, 'updateStatus'])->middleware('permission:leaves.edit');

    // WFH Requests (Admin side)
    Route::get('wfh-requests', [WfhApiController::class, 'index'])->middleware('permission:wfh-requests.read');
    Route::get('wfh-requests/{wfhRequest}', [WfhApiController::class, 'show'])->middleware('permission:wfh-requests.read');
    Route::put('wfh-requests/{wfhRequest}', [WfhApiController::class, 'update'])->middleware('permission:wfh-requests.edit');
    Route::delete('wfh-requests/{wfhRequest}', [WfhApiController::class, 'destroy'])->middleware('permission:wfh-requests.delete');
    Route::post('wfh-requests/{wfhRequest}/status', [WfhApiController::class, 'updateStatus'])->middleware('permission:wfh-requests.edit');

    // Leave Types (Admin side)
    Route::apiResource('leave-types', LeaveTypeApiController::class)->middleware('permission:leaves.read');
    Route::post('leave-types/{leaveType}/status', [LeaveTypeApiController::class, 'updateStatus'])->middleware('permission:leaves.edit');

    // Working Hours
    Route::get('working-hours', [WorkingHourApiController::class, 'index'])->middleware('permission:settings.read');
    Route::post('working-hours', [WorkingHourApiController::class, 'store'])->middleware('permission:settings.edit');

    // Leave Allocations
    Route::get('leave-allocations', [LeaveAllocationApiController::class, 'index'])->middleware('permission:leaves.read');
    Route::get('leave-allocations/{employee}', [LeaveAllocationApiController::class, 'show'])->middleware('permission:leaves.read');
    Route::post('leave-allocations/{employee}', [LeaveAllocationApiController::class, 'update'])->middleware('permission:leaves.edit');

    // Task Reports (Admin)
    Route::apiResource('task-reports', AdminTaskReportApiController::class)->middleware('permission:task-reports.read');

    //Holidays
    Route::apiResource('holidays', HolidayApiController::class)->middleware('permission:settings.read');

    // Reports
    Route::group(['prefix' => 'reports', 'middleware' => 'permission:reports.read'], function () {
        Route::get('attendance', [ReportApiController::class, 'attendanceReport']);
        Route::get('leaves', [ReportApiController::class, 'leaveReport']);
        Route::get('employees', [ReportApiController::class, 'employeeReport']);
        Route::get('employee-details', [ReportApiController::class, 'employeeDetails']);
        Route::get('employee-nearest-expiry', [ReportApiController::class, 'employeeNearestExpiry']);
        Route::get('employee-upcoming-renewals', [ReportApiController::class, 'employeeUpcomingRenewals']);
        Route::get('company-nearest-expiry', [ReportApiController::class, 'companyNearestExpiry']);
        Route::get('company-upcoming-renewals', [ReportApiController::class, 'companyUpcomingRenewals']);
        Route::get('pending-leaves', [ReportApiController::class, 'pendingLeavesReport']);
        Route::get('projects', [ReportApiController::class, 'projectReport']);
        Route::get('counts', [ReportApiController::class, 'reportCounts']);
        Route::post('export', [ReportApiController::class, 'export']);

        // Probation & Contract Renewal Alerts
        Route::get('employee-probation-ending', [ReportApiController::class, 'employeeProbationEnding']);
        Route::get('employee-contract-renewal', [ReportApiController::class, 'employeeContractRenewal']);
        Route::post('send-probation-contract-alerts', [ReportApiController::class, 'sendProbationContractAlerts']);

        // Project Cost & Time Report
        Route::get('project-cost-time', [ReportApiController::class, 'projectCostTimeReport']);
    });

    // Offboarding Routes
    Route::group(['prefix' => 'offboarding'], function () {
        Route::get('/', [OffboardingApiController::class, 'index'])->middleware('permission:offboarding.read');
        Route::post('/initiate', [OffboardingApiController::class, 'initiate'])->middleware('permission:offboarding.edit');
        Route::put('/update-initiate', [OffboardingApiController::class, 'initiate'])->middleware('permission:offboarding.edit');
        Route::get('/reporting-managers', [OffboardingApiController::class, 'reportingManagers'])->middleware('permission:offboarding.read');
        Route::get('/employees', [OffboardingApiController::class, 'getAllEmployees'])->middleware('permission:offboarding.read');
        Route::get('/stats', [OffboardingApiController::class, 'getStats'])->middleware('permission:offboarding.read');
        Route::get('/employees/salary-packages/{id}', [OffboardingApiController::class, 'getSalaryPackages'])->middleware('permission:offboarding.edit');
        Route::get('/{id}/visa-status', [OffboardingApiController::class, 'getVisaStatus'])->middleware('permission:offboarding.read');
        Route::post('/{id}/visa-status', [OffboardingApiController::class, 'updateVisaStatus'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/visa-status/complete', [OffboardingApiController::class, 'completeVisaStatus'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/checklists', [OffboardingApiController::class, 'updateChecklist'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/assets', [OffboardingApiController::class, 'updateAssets'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/interview', [OffboardingApiController::class, 'submitInterview'])->middleware('permission:offboarding.edit');
        Route::get('/{id}/settlement', [OffboardingApiController::class, 'getSettlement'])->middleware('permission:offboarding.read');
        Route::post('/{id}/settlement', [OffboardingApiController::class, 'updateSettlement'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/letters', [OffboardingApiController::class, 'generateLetters'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/letters/upload', [OffboardingApiController::class, 'uploadLetter'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/complete', [OffboardingApiController::class, 'completeOffboarding'])->middleware('permission:offboarding.edit');
        Route::get('/{id}/progress', [OffboardingApiController::class, 'getProgress'])->middleware('permission:offboarding.read');
        Route::get('/{id}', [OffboardingApiController::class, 'show'])->middleware('permission:offboarding.read');
        Route::delete('/{id}', [OffboardingApiController::class, 'destroy'])->middleware('permission:offboarding.delete');
    });

    //Checklists
    Route::prefix('checklists')->group(function () {
        Route::get('/{id}', [OffboardingChecklistApiController::class, 'index']);
        Route::post('/{id}', [OffboardingChecklistApiController::class, 'store']);
        Route::put('/item/{id}', [OffboardingChecklistApiController::class, 'update']);
        Route::patch('/item/{id}/status', [OffboardingChecklistApiController::class, 'updateStatus']);
        Route::delete('/item/{id}', [OffboardingChecklistApiController::class, 'destroy']);
    });

    Route::prefix('checklist-categories')->group(function () {
        Route::get('/', [OffboardingChecklistCategoryController::class, 'index']);
        Route::post('/', [OffboardingChecklistCategoryController::class, 'store']);
        Route::get('/{id}', [OffboardingChecklistCategoryController::class, 'show']);
        Route::put('/{id}', [OffboardingChecklistCategoryController::class, 'update']);
        Route::delete('/{id}', [OffboardingChecklistCategoryController::class, 'destroy']);
    });

    // Asset Management
    Route::group(['prefix' => 'assets'], function () {
        Route::apiResource('types', AssetTypeApiController::class);

        Route::get('employee/{id}', [AssetApiController::class, 'getEmployeeAssets']);
        Route::get('/', [AssetApiController::class, 'index']);
        Route::post('/', [AssetApiController::class, 'store']);

        Route::post('/{id}/assign', [AssetApiController::class, 'assign']);
        Route::post('/{id}/revoke', [AssetApiController::class, 'revoke']);
        Route::get('/{id}', [AssetApiController::class, 'show']);
        Route::put('/{id}', [AssetApiController::class, 'update']);
        Route::delete('/{id}', [AssetApiController::class, 'destroy']);
    });

    // Payroll Management (Admin side)
    Route::group(['prefix' => 'payroll', 'middleware' => 'permission:payroll.read'], function () {
        Route::get('stats', [PayrollController::class, 'stats']);
        Route::get('/', [PayrollController::class, 'index']);
        Route::get('history', [PayrollController::class, 'history']);
        Route::get('working-days', [PayrollController::class, 'getWorkingDays']);
        Route::get('employee-summary/{employee_id}', [PayrollController::class, 'employeeSalarySummary']);
        Route::get('draft/{employee_id}', [PayrollController::class, 'getDraft']);
        Route::get('{id}/download', [PayrollController::class, 'downloadPayslip']);
        Route::get('{id}', [PayrollController::class, 'show']);

        Route::post('calculate', [PayrollController::class, 'calculateMonthlySalary'])->middleware('permission:payroll.edit');
        Route::post('overtime', [PayrollController::class, 'calculateOvertime'])->middleware('permission:payroll.edit');
        Route::post('summary', [PayrollController::class, 'calculateTotals'])->middleware('permission:payroll.edit');
        Route::post('save-step', [PayrollController::class, 'saveStep'])->middleware('permission:payroll.edit');
        Route::post('submit', [PayrollController::class, 'submitPayroll'])->middleware('permission:payroll.edit');
        Route::post('generate', [PayrollController::class, 'generatePayroll'])->middleware('permission:payroll.edit');
        Route::post('convert-salary', [PayrollController::class, 'convertSalary'])->middleware('permission:payroll.edit');
        Route::post('{id}/send-payslip', [PayrollController::class, 'sendPayslip'])->middleware('permission:payroll.edit');
        Route::put('{id}', [PayrollController::class, 'update'])->middleware('permission:payroll.edit');
        Route::delete('{id}', [PayrollController::class, 'destroy'])->middleware('permission:payroll.edit');
    });
});

// Employee Protected Routes (Now also using auth:api)
Route::group(['middleware' => 'auth:api', 'prefix' => 'employee'], function () {
    Route::get('dashboard', [EmployeePortalApiController::class, 'dashboard']);
    Route::post('punch-in', [EmployeePortalApiController::class, 'punchIn']);
    Route::post('punch-out', [EmployeePortalApiController::class, 'punchOut']);
    Route::post('break/start', [EmployeePortalApiController::class, 'startBreak']);
    Route::post('break/end', [EmployeePortalApiController::class, 'endBreak']);

    // Leaves
    Route::get('leaves', [EmployeePortalApiController::class, 'leaves']);
    Route::get('leaves/{leave}', [EmployeePortalApiController::class, 'showLeave']);
    Route::get('leave-balance', [EmployeePortalApiController::class, 'leaveTypesAndBalance']);
    Route::post('leaves', [EmployeePortalApiController::class, 'storeLeave']);
    Route::put('leaves/{leave}', [EmployeePortalApiController::class, 'updateLeave']);
    Route::post('leaves/{leave}', [EmployeePortalApiController::class, 'updateLeave']);
    Route::delete('leaves/{leave}', [EmployeePortalApiController::class, 'destroyLeave']);
    Route::get('leave-types', [LeaveTypeApiController::class, 'getAllLeaveTypes']);
    Route::get('leave-allocations/{employee}', [LeaveAllocationApiController::class, 'show']);

    // Task Reports
    Route::get('task-reports', [EmployeePortalApiController::class, 'taskReports']);
    Route::get('task-reports/{taskReport}', [EmployeePortalApiController::class, 'showTaskReport']);
    Route::post('task-reports', [EmployeePortalApiController::class, 'storeTaskReport']);
    Route::put('task-reports/{taskReport}', [EmployeePortalApiController::class, 'updateTaskReport']);
    Route::delete('task-reports/{taskReport}', [EmployeePortalApiController::class, 'destroyTaskReport']);

    // WFH Requests
    Route::get('wfh-requests', [EmployeePortalApiController::class, 'wfhRequests']);
    Route::get('wfh-requests/{wfhRequest}', [EmployeePortalApiController::class, 'showWfhRequest']);
    Route::post('wfh-requests', [EmployeePortalApiController::class, 'storeWfhRequest']);
    Route::put('wfh-requests/{wfhRequest}', [EmployeePortalApiController::class, 'updateWfhRequest']);
    Route::delete('wfh-requests/{wfhRequest}', [EmployeePortalApiController::class, 'destroyWfhRequest']);

    // Attendance Requests
    Route::get('attendance-requests', [EmployeeAttendanceRequestApiController::class, 'index']);
    Route::post('attendance-requests', [EmployeeAttendanceRequestApiController::class, 'store']);
    Route::get('attendance-requests/{attendanceRequest}', [EmployeeAttendanceRequestApiController::class, 'show']);
    Route::put('attendance-requests/{attendanceRequest}', [EmployeeAttendanceRequestApiController::class, 'update']);
    Route::delete('attendance-requests/{attendanceRequest}', [EmployeeAttendanceRequestApiController::class, 'destroy']);

    //Assets
    Route::get('assets/{id}', [AssetApiController::class, 'employeeAssets']);

    // Profile Settings
    Route::post('change-password', [ProfileApiController::class, 'changePassword']);
    Route::post('update-profile', [ProfileApiController::class, 'updateProfile']);

    // My Documents (view uploaded docs & upload temp during onboarding)
    Route::get('my-documents', [EmployeePortalApiController::class, 'myDocuments']);
    Route::post('upload-temp', [EmployeePortalApiController::class, 'uploadTempDocument']);

    // Payroll (Employee Portal)
    Route::get('payroll/summary', [EmployeePortalApiController::class, 'mySalarySummary']);
    Route::get('payroll/history', [EmployeePortalApiController::class, 'mySalaryHistory']);
    Route::get('payroll/{id}/download', [EmployeePortalApiController::class, 'downloadMyPayslip']);
});



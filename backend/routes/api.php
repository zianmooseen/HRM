<?php

use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\AttendanceRecordController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmployeeLeaveBalanceController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeAccountController;
use App\Http\Controllers\Api\EmployeeServicePeriodController;
use App\Http\Controllers\Api\EmployeeTerminationController;
use App\Http\Controllers\Api\JobTitleController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\EmployeeSalaryComponentController;
use App\Http\Controllers\Api\EmployeeOnboardingController;
use App\Http\Controllers\Api\OnboardingTemplateController;
use App\Http\Controllers\Api\PayrollPeriodController;
use App\Http\Controllers\Api\SalaryComponentController;
use App\Http\Controllers\Api\SelfServiceController;
use Illuminate\Support\Facades\Route;

// Feature flow step 1: API login must always return JSON, never Laravel's guest redirect to /home.
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', CurrentUserController::class);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/self/profile', [SelfServiceController::class, 'profile']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/company', [CompanyController::class, 'current']);
    Route::put('/company', [CompanyController::class, 'update']);

    Route::apiResource('branches', BranchController::class)->except(['show']);
    Route::apiResource('departments', DepartmentController::class)->except(['show']);
    Route::apiResource('job-titles', JobTitleController::class)->except(['show']);
    Route::apiResource('employees', EmployeeController::class);
    Route::post('/employees/{employee}/account', [EmployeeAccountController::class, 'store']);
    Route::get('/employees/{employee}/service-periods', [EmployeeServicePeriodController::class, 'index']);
    Route::post('/employees/{employee}/service-periods/extend', [EmployeeServicePeriodController::class, 'extend']);
    Route::post('/employees/{employee}/service-periods/rehire', [EmployeeServicePeriodController::class, 'rehire']);
    Route::get('/employee-terminations', [EmployeeTerminationController::class, 'index']);
    Route::post('/employees/{employee}/termination', [EmployeeTerminationController::class, 'store']);
    Route::post('/employee-terminations/{termination}/mark-paid', [EmployeeTerminationController::class, 'markPaid']);
    Route::get('/onboarding-templates', [OnboardingTemplateController::class, 'index']);
    Route::post('/onboarding-templates', [OnboardingTemplateController::class, 'store']);
    Route::get('/onboarding-cases', [EmployeeOnboardingController::class, 'index']);
    Route::post('/employees/{employee}/onboarding/start', [EmployeeOnboardingController::class, 'start']);
    Route::post('/onboarding-tasks/{task}', [EmployeeOnboardingController::class, 'updateTask']);
    Route::post('/onboarding-cases/{case}/complete', [EmployeeOnboardingController::class, 'complete']);
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
    Route::apiResource('attendance-records', AttendanceRecordController::class);
    Route::get('/leave-types', [LeaveTypeController::class, 'index']);
    Route::get('/leave-balances', [EmployeeLeaveBalanceController::class, 'index']);
    Route::post('/leave-balances/accrue-annual', [EmployeeLeaveBalanceController::class, 'accrueAnnual']);
    Route::post('/leave-balances', [EmployeeLeaveBalanceController::class, 'store']);
    Route::apiResource('leave-requests', LeaveRequestController::class)
        ->only(['index', 'store', 'show'])
        ->parameters(['leave-requests' => 'leaveRequest']);
    Route::get('/leave-requests/{leaveRequest}/sick-pay', [LeaveRequestController::class, 'sickPay']);
    Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
    Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
    Route::apiResource('salary-components', SalaryComponentController::class)->only(['index', 'store', 'update']);
    Route::apiResource('employee-salary-components', EmployeeSalaryComponentController::class)->only(['index', 'store', 'update']);
    Route::apiResource('payroll-periods', PayrollPeriodController::class)->only(['index', 'store', 'show']);
    Route::post('/payroll-periods/{payrollPeriod}/run', [PayrollPeriodController::class, 'run']);
    Route::post('/payroll-periods/{payrollPeriod}/approve', [PayrollPeriodController::class, 'approve']);
    Route::get('/compliance/legal-rules', [ComplianceController::class, 'legalRules']);
    Route::post('/compliance/gratuity', [ComplianceController::class, 'gratuity']);
});

<?php

use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\AttendanceRecordController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeLeaveBalanceController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\JobTitleController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\EmployeeSalaryComponentController;
use App\Http\Controllers\Api\PayrollPeriodController;
use App\Http\Controllers\Api\SalaryComponentController;
use Illuminate\Support\Facades\Route;

// Feature flow step 1: API login must always return JSON, never Laravel's guest redirect to /home.
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', CurrentUserController::class);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/company', [CompanyController::class, 'current']);
    Route::put('/company', [CompanyController::class, 'update']);

    Route::apiResource('branches', BranchController::class)->except(['show']);
    Route::apiResource('departments', DepartmentController::class)->except(['show']);
    Route::apiResource('job-titles', JobTitleController::class)->except(['show']);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('attendance-records', AttendanceRecordController::class);
    Route::get('/leave-types', [LeaveTypeController::class, 'index']);
    Route::get('/leave-balances', [EmployeeLeaveBalanceController::class, 'index']);
    Route::apiResource('leave-requests', LeaveRequestController::class)
        ->only(['index', 'store', 'show'])
        ->parameters(['leave-requests' => 'leaveRequest']);
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

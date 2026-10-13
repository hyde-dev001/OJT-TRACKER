<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Company\AccountController as CompanyAccountController;
use App\Http\Controllers\Company\InternController;
use App\Http\Controllers\Company\RequirementController as CompanyRequirementController;
use App\Http\Controllers\Company\TaskController as CompanyTaskController;
use App\Http\Controllers\Company\WorkLogController as CompanyWorkLogController;
use App\Http\Controllers\Intern\AccountController;
use App\Http\Controllers\Intern\OverviewController as InternOverviewController;
use App\Http\Controllers\Intern\RequirementController as InternRequirementController;
use App\Http\Controllers\Intern\TaskController as InternTaskController;
use App\Http\Controllers\Intern\WorkLogController as InternWorkLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Student\InternshipController;
use App\Http\Controllers\Student\OverviewController;
use App\Http\Controllers\Student\RequirementController;
use App\Http\Controllers\Student\SummaryExportController;
use App\Http\Controllers\Student\TaskController;
use App\Http\Controllers\Student\WorkLogController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'application' => 'OJT Progress Tracker API',
]));

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1');
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:6,1');
Route::post('/company/register', [AuthController::class, 'registerCompany'])
    ->middleware('throttle:6,1');
Route::get('/register/check-email', [AuthController::class, 'checkEmail'])
    ->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(['account-role:company|intern|student', 'intern.password-changed'])->group(function (): void {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    });

    Route::middleware(['account-role:company'])->prefix('company')->group(function (): void {
        Route::put('/password', [CompanyAccountController::class, 'changePassword']);
        Route::get('/interns', [InternController::class, 'index']);
        Route::post('/interns', [InternController::class, 'store']);
        Route::get('/interns/{intern}/tasks', [CompanyTaskController::class, 'index']);
        Route::get('/interns/{intern}/tasks/{task}', [CompanyTaskController::class, 'show']);
        Route::post('/interns/{intern}/tasks', [CompanyTaskController::class, 'store']);
        Route::get('/interns/{intern}/tasks/{task}/proof', [CompanyTaskController::class, 'proof']);
        Route::post('/interns/{intern}/tasks/{task}/approve', [CompanyTaskController::class, 'approve']);
        Route::post('/interns/{intern}/tasks/{task}/request-changes', [CompanyTaskController::class, 'requestChanges']);
        Route::get('/interns/{intern}/requirements', [CompanyRequirementController::class, 'index']);
        Route::get('/interns/{intern}/requirements/{requirement}', [CompanyRequirementController::class, 'show']);
        Route::post('/interns/{intern}/requirements', [CompanyRequirementController::class, 'store']);
        Route::get('/interns/{intern}/requirements/{requirement}/proof', [CompanyRequirementController::class, 'proof']);
        Route::post('/interns/{intern}/requirements/{requirement}/approve', [CompanyRequirementController::class, 'approve']);
        Route::post('/interns/{intern}/requirements/{requirement}/request-changes', [CompanyRequirementController::class, 'requestChanges']);
        Route::put('/interns/{intern}/placement', [InternController::class, 'updatePlacement']);
        Route::post('/interns/{intern}/password-reset', [InternController::class, 'resetPassword']);

        Route::get('/interns/{intern}/work-logs', [CompanyWorkLogController::class, 'index']);
        Route::post('/interns/{intern}/work-logs/time-in', [CompanyWorkLogController::class, 'timeIn']);
        Route::post('/interns/{intern}/work-logs/time-out', [CompanyWorkLogController::class, 'timeOut']);
        Route::put('/interns/{intern}/work-logs/{workLog}', [CompanyWorkLogController::class, 'update']);
    });

    Route::middleware('account-role:intern')->prefix('intern')->group(function (): void {
        Route::put('/password', [AccountController::class, 'changePassword']);

        Route::middleware('intern.password-changed')->group(function (): void {
            Route::get('/overview', InternOverviewController::class);
            Route::get('/overview/export', SummaryExportController::class);
            Route::get('/internship', [InternshipController::class, 'show']);
            Route::put('/internship', [InternshipController::class, 'update']);
            Route::get('/work-logs', [InternWorkLogController::class, 'index']);
            Route::get('/tasks', [InternTaskController::class, 'index']);
            Route::get('/tasks/{task}', [InternTaskController::class, 'show']);
            Route::post('/tasks/{task}/submission', [InternTaskController::class, 'submit']);
            Route::get('/tasks/{task}/proof', [InternTaskController::class, 'proof']);
            Route::get('/requirements', [InternRequirementController::class, 'index']);
            Route::get('/requirements/{requirement}', [InternRequirementController::class, 'show']);
            Route::post('/requirements/{requirement}/submission', [InternRequirementController::class, 'submit']);
            Route::get('/requirements/{requirement}/proof', [InternRequirementController::class, 'proof']);
        });
    });

    Route::middleware('account-role:student')->group(function (): void {
        Route::put('/profile', [AuthController::class, 'updateProfile']);

        Route::prefix('student')->group(function (): void {
            Route::get('/overview', OverviewController::class);
            Route::get('/overview/export', SummaryExportController::class);
            Route::get('/internship', [InternshipController::class, 'show']);
            Route::put('/internship', [InternshipController::class, 'update']);

            Route::get('/work-logs', [WorkLogController::class, 'index']);
            Route::post('/work-logs', [WorkLogController::class, 'store']);
            Route::get('/work-logs/{workLog}', [WorkLogController::class, 'show']);
            Route::put('/work-logs/{workLog}', [WorkLogController::class, 'update']);
            Route::delete('/work-logs/{workLog}', [WorkLogController::class, 'destroy']);

            Route::get('/tasks', [TaskController::class, 'index']);
            Route::post('/tasks', [TaskController::class, 'store']);
            Route::get('/tasks/{task}', [TaskController::class, 'show']);
            Route::put('/tasks/{task}', [TaskController::class, 'update']);
            Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
            Route::post('/tasks/{task}/start', [TaskController::class, 'start']);
            Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);

            Route::get('/requirements', [RequirementController::class, 'index']);
            Route::post('/requirements', [RequirementController::class, 'store']);
            Route::get('/requirements/{requirement}', [RequirementController::class, 'show']);
            Route::put('/requirements/{requirement}', [RequirementController::class, 'update']);
            Route::delete('/requirements/{requirement}', [RequirementController::class, 'destroy']);
            Route::post('/requirements/{requirement}/complete', [RequirementController::class, 'complete']);
            Route::post('/requirements/{requirement}/incomplete', [RequirementController::class, 'incomplete']);
        });
    });
});

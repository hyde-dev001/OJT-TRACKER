<?php

use App\Http\Controllers\AuthController;
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
Route::get('/register/check-email', [AuthController::class, 'checkEmail'])
    ->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('student')->group(function () {
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

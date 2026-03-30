<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\LessonAccessController;

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);


//Lessons routes
Route::get('/lessons', [LessonController::class, 'get']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('admin/lessons', LessonController::class);
});

//Grade routes
Route::get('/grades', [GradeController::class, 'get']);

//Subject routes
Route::get('/subjects', [SubjectController::class, 'get']);

//Lesson routes
Route::middleware('auth:sanctum')->get('/lessons', [LessonController::class, 'get']);

//LessonAccess routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/lesson-access/request', [LessonAccessController::class, 'request']);
    Route::post('/lesson-access/accept', [LessonAccessController::class, 'accept']);
    Route::post('/lesson-access/refuse', [LessonAccessController::class, 'refuse']);
    Route::get('/lesson-access/list_request', [LessonAccessController::class, 'listRequests']);
    Route::post('/lesson-access/accept-multiple', [LessonAccessController::class, 'acceptMultiple']);
    Route::post('/lesson-access/refuse-multiple', [LessonAccessController::class, 'refuseMultiple']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
});

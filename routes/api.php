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
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
});

Route::get('/debug-auth', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'all_headers' => $request->headers->all(),
        'server_vars' => [
            'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET',
            'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'NOT SET',
            'bearer' => $request->bearerToken(),
        ],
        'all_server_auth_keys' => array_filter(
            $_SERVER,
            fn($key) => str_contains(strtolower($key), 'auth'),
            ARRAY_FILTER_USE_KEY
        ),
    ]);
});

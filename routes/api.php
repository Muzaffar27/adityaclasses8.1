<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\LessonAccessController;
use App\Http\Controllers\UserController;
use App\Models\Lesson;
use Illuminate\Support\Facades\Route;

//Auth
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

//User routes
Route::get('/getStudents', [UserController::class, 'getStudents']);
Route::post('/students/{id}/reset-password', [UserController::class, 'resetPassword']);
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/updateUserInfo', [UserController::class, 'updateUserInfo']);
    Route::put('/updateUserPwd', [UserController::class, 'updateUserPwd']);
});

//Lessons routes
Route::get('/lessons', [LessonController::class, 'get']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('admin/lessons', LessonController::class);
});

//Grade routes
Route::get('/grades', [GradeController::class, 'get']);
Route::post('/addGrade', [GradeController::class, 'add']);

//Subject routes
Route::get('/subjects', [SubjectController::class, 'get']);
Route::post('/addSubject', [SubjectController::class, 'add']);

//Lesson routes
Route::middleware('auth:sanctum')->get('/lessons', [LessonController::class, 'get']);
Route::middleware('auth:sanctum')->get('/myCourses', [LessonController::class, 'myCourses']);

//LessonAccess routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/lesson-access/request', [LessonAccessController::class, 'request']);
    Route::post('/lesson-access/accept', [LessonAccessController::class, 'accept']);
    Route::post('/lesson-access/refuse', [LessonAccessController::class, 'refuse']);
    Route::get('/lesson-access/list_request', [LessonAccessController::class, 'listRequests']);
    Route::post('/lesson-access/accept-multiple', [LessonAccessController::class, 'acceptMultiple']);
    Route::post('/lesson-access/refuse-multiple', [LessonAccessController::class, 'refuseMultiple']);
    Route::get('/lesson-access/count', [LessonAccessController::class, 'count']);
});

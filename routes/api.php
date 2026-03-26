<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);


//Lessons routes
Route::get('/lessons', [LessonController::class, 'get']);

//Grade routes
Route::get('/grades', [GradeController::class, 'get']);

//Subject routes
Route::get('/subjects', [SubjectController::class, 'get']);

//Lesson routes
Route::get('/lessons', [LessonController::class, 'get']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
});

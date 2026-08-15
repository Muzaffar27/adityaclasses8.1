<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\LessonAccessController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\HomeImageController;
use App\Http\Controllers\HomepageContentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

//Auth
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::post('/client-error', function (Request $request) {
    $payload = $request->all();
    $context = [
        'type' => substr((string) ($payload['type'] ?? 'unknown'), 0, 120),
        'message' => substr((string) ($payload['message'] ?? ''), 0, 1000),
        'stack' => substr((string) ($payload['stack'] ?? ''), 0, 4000),
        'url' => substr((string) ($payload['url'] ?? ''), 0, 500),
        'source' => substr((string) ($payload['source'] ?? ''), 0, 500),
        'user_agent' => substr((string) ($payload['userAgent'] ?? $request->userAgent() ?? ''), 0, 500),
        'display_mode' => substr((string) ($payload['displayMode'] ?? ''), 0, 80),
        'service_worker' => $payload['serviceWorker'] ?? null,
        'build_asset' => substr((string) ($payload['buildAsset'] ?? ''), 0, 500),
        'asset_origin_mismatch' => (bool) ($payload['assetOriginMismatch'] ?? false),
        'ip' => $request->ip(),
    ];

    Log::warning('Frontend client error', $context);

    file_put_contents(
        storage_path('logs/client-errors.log'),
        now()->toDateTimeString() . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    return response()->noContent();
});

//User routes
Route::get('/getStudents', [UserController::class, 'getStudents']);
Route::post('/students/{id}/reset-password', [UserController::class, 'resetPassword']);
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/updateUserInfo', [UserController::class, 'updateUserInfo']);
    Route::put('/updateUserPwd', [UserController::class, 'updateUserPwd']);
});

//Lessons routes
Route::get('/lessons', [LessonController::class, 'get']);
Route::get('/lessons/all', [LessonController::class, 'all']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/lessons/rename-topic', [LessonController::class, 'renameTopic']);
    Route::post('/admin/lessons/move-topic', [LessonController::class, 'moveTopic']);
    Route::post('/admin/lessons/copy-topic', [LessonController::class, 'copyTopic']);
    Route::post('/admin/lessons/delete-topic', [LessonController::class, 'deleteTopic']);
    Route::post('/admin/lessons/{lesson}/pdf', [LessonController::class, 'uploadPdf']);
    Route::delete('/admin/lessons/{lesson}/pdf/{type}', [LessonController::class, 'removePdf']);
    Route::get('/lessons/{lesson}/pdf/{type}', [LessonController::class, 'viewPdf']);
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
    Route::delete('/lesson-access/{id}', [LessonAccessController::class, 'destroy']);
    Route::get('/lesson-access/list_request', [LessonAccessController::class, 'listRequests']);
    Route::post('/lesson-access/accept-multiple', [LessonAccessController::class, 'acceptMultiple']);
    Route::post('/lesson-access/refuse-multiple', [LessonAccessController::class, 'refuseMultiple']);
    // Route::get('/lesson-access/count', [LessonAccessController::class, 'count']);
});

//Package routes
Route::get('/packages', [PackageController::class, 'index']);
Route::post('/packages/store', [PackageController::class, 'store']);
Route::put('/packages/update/{id}', [PackageController::class, 'update']);
Route::delete('/packages/delete/{id}', [PackageController::class, 'destroy']);
Route::get('/packages/admin', [PackageController::class, 'adminIndex']);

//Homepage image routes
Route::get('/homepage-images', [HomeImageController::class, 'index']);
Route::post('/homepage-images', [HomeImageController::class, 'store']);
Route::put('/homepage-images/{filename}', [HomeImageController::class, 'update']);
Route::delete('/homepage-images/{filename}', [HomeImageController::class, 'destroy']);


//announcement 
Route::get('/announcement', function () {
    $path = config_path('announcement.json');

    if (!file_exists($path)) {
        return response()->json([
            "enabled" => false
        ]);
    }

    return response()->json(
        json_decode(file_get_contents($path), true)
    );
});

Route::post('/announcement/save', function (Request $request) {

    file_put_contents(
        config_path('announcement.json'),
        json_encode($request->all(), JSON_PRETTY_PRINT)
    );

    return response()->json(["status" => "saved"]);
});

// Homepage content
Route::get('/homepage-content', [HomepageContentController::class, 'show']);
Route::post('/homepage-content/save', [HomepageContentController::class, 'save']);

<?php

use Illuminate\Support\Facades\Route;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::get('/admin/clear-cache', function () {
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('cache:clear');
    return response()->json(['done' => true]);
});

Route::get('/admin/debug-token', function () {
    $token = \Laravel\Sanctum\PersonalAccessToken::orderBy('id', 'desc')->take(5)->get();
    $userCount = \App\Models\User::count();

    return response()->json([
        'latest_tokens' => $token,
        'user_count' => $userCount,
        'db_name' => DB::connection()->getDatabaseName(),
    ]);
});

Route::get('/admin/migrate', function () {
    try {
        // This is the same as running "php artisan migrate" in terminal
        Artisan::call('migrate', ['--force' => true]);

        // Show exactly what happened (e.g., "Migrated: create_lessons_table")
        return "<pre>" . Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "Migration Error: " . $e->getMessage();
    }
});

Route::get('/admin/check-db', function () {
    $tables = DB::select('SHOW TABLES');
    return response()->json($tables);
});

// Route::get('/admin/import-final', function () {
//     $path = storage_path('app/fixed_final_migration.csv');

//     if (!File::exists($path)) return "CSV not found!";

//     $file = fopen($path, "r");
//     fgetcsv($file); // Skip Header

//     $count = 0;

//     while (($row = fgetcsv($file)) !== FALSE) {
//         // Make sure there are enough columns
//         if (count($row) < 5) continue;

//         // Map columns
//         $subjectName = $row[0]; // Mathematics
//         $gradeName   = $row[1]; // Grade 9
//         $topic       = $row[2]; // Trigonometry
//         $title       = $row[3]; // Trigonometry part 3
//         $vimeoUrl    = $row[4]; // Vimeo link

//         // Create or get subject/grade
//         $subject = Subject::firstOrCreate(['name' => $subjectName]);
//         $grade   = Grade::firstOrCreate(['name' => $gradeName]);

//         // Extract part number from title
//         $partNumber = null;
//         if (preg_match('/part\s+(\d+)/i', $title, $matches)) {
//             $partNumber = (int)$matches[1];
//         }

//         // Create lesson
//         Lesson::updateOrCreate([
//             'subject_id'  => $subject->id,
//             'grade_id'    => $grade->id,
//             'topic'       => $topic,
//             'title'       => $title,
//             'vimeo_url'   => $vimeoUrl,
//             'part_number' => $partNumber,
//             'is_active'   => true,
//         ]);

//         $count++;
//     }

//     fclose($file);

//     return "Successfully imported $count lessons!";
// });

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');

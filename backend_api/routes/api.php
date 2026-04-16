<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\GameConfigurationController;
use App\Http\Controllers\Api\GameTypeController;
use App\Http\Controllers\Api\GameSessionController;
use App\Http\Controllers\Api\InstructorContentController;
use App\Http\Controllers\Api\InteractiveConfigController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\UserProgressController;
use App\Http\Controllers\Api\AdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ─── Public ──────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas de autenticación SSO (Socialite)
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);

// Public catalog
Route::get('/courses', [CourseController::class, 'catalog']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);

// Public categories
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/game-types', [GameTypeController::class, 'index']);

// Public certificate verification
Route::post('/certificates/verify', [CertificateController::class, 'verify']);

// ─── Protected (Sanctum) ────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Courses CRUD (teacher/admin)
    Route::get('/instructor/courses', [CourseController::class, 'mine'])->middleware('role:instructor,admin');
    Route::get('/instructor/courses/{course}/structure', [InstructorContentController::class, 'courseStructure'])->middleware('role:instructor,admin');
    Route::post('/courses', [CourseController::class, 'store'])->middleware('role:instructor,admin');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->middleware('role:instructor,admin');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->middleware('role:instructor,admin');
    Route::post('/courses/{course}/modules', [InstructorContentController::class, 'storeModule'])->middleware('role:instructor,admin');
    Route::put('/modules/{module}', [InstructorContentController::class, 'updateModule'])->middleware('role:instructor,admin');
    Route::delete('/modules/{module}', [InstructorContentController::class, 'destroyModule'])->middleware('role:instructor,admin');
    Route::post('/modules/{module}/lessons', [InstructorContentController::class, 'storeLesson'])->middleware('role:instructor,admin');
    Route::put('/instructor/lessons/{lesson}', [InstructorContentController::class, 'updateLesson'])->middleware('role:instructor,admin');
    Route::delete('/instructor/lessons/{lesson}', [InstructorContentController::class, 'destroyLesson'])->middleware('role:instructor,admin');

    // Categories CRUD (admin)
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('admin');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('admin');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('admin');

    // Payments & QR Checkout (student only)
    Route::post('/payments/intent', [\App\Http\Controllers\Api\PaymentController::class, 'createIntent'])->middleware('role:student');
    Route::get('/payments/{transaction_id}', [\App\Http\Controllers\Api\PaymentController::class, 'checkStatus'])->middleware('role:student');
    Route::post('/payments/webhook', [\App\Http\Controllers\Api\PaymentController::class, 'confirmMockPayment'])->middleware('role:student');

    // ─── Gamification & Progress ───────────────────────────────

    // User Progress & Dashboard (student only)
    Route::prefix('user')->middleware('role:student')->group(function () {
        Route::get('/dashboard-stats', [UserProgressController::class, 'dashboardStats']);
        Route::get('/courses', [UserProgressController::class, 'userCourses']);
        Route::get('/courses/{course}/progress', [UserProgressController::class, 'courseProgress']);
        Route::get('/recent-activity', [UserProgressController::class, 'recentActivity']);
    });

    // Game Configurations (teacher/admin authoring)
    Route::apiResource('game-configurations', GameConfigurationController::class)
        ->except(['create', 'edit'])
        ->middleware('role:instructor,admin');
    Route::get('/game-configurations/course/{courseSlug}', [GameConfigurationController::class, 'forUserCourse']);
    Route::apiResource('interactive-configs', InteractiveConfigController::class)
        ->except(['create', 'edit'])
        ->middleware('role:instructor,admin');

    // Game Sessions (student gameplay API)
    Route::apiResource('game-sessions', GameSessionController::class)
        ->except(['create', 'edit', 'destroy'])
        ->middleware('role:student');
    Route::post('/game-sessions/start', [GameSessionController::class, 'store'])->middleware('role:student'); // start new session
    Route::get('/game-sessions/stats', [GameSessionController::class, 'stats'])->middleware('role:student');

    // Quizzes & Attempts (student gameplay API)
    Route::apiResource('quizzes', QuizController::class)->only(['index', 'show'])->middleware('role:student');
    Route::post('/quizzes/{quiz}/start', [QuizController::class, 'startAttempt'])->middleware('role:student');
    Route::post('/quiz-attempts/{attempt}/submit', [QuizController::class, 'submitAttempt'])
        ->middleware('role:student')
        ->where('attempt', '[0-9]+'); // UserQuizAttempt model binding
    Route::get('/quizzes/{quiz}/attempt-history', [QuizController::class, 'attemptHistory'])->middleware('role:student');
    Route::get('/quizzes/user/stats', [QuizController::class, 'userStats'])->middleware('role:student');

    // Badges (student profile/inventory)
    Route::apiResource('badges', BadgeController::class)->only(['index', 'show']);
    Route::get('/badges/my', [BadgeController::class, 'myBadges'])->middleware('role:student');
    Route::get('/badges/available', [BadgeController::class, 'availableBadges'])->middleware('role:student');
    Route::get('/badges/stats', [BadgeController::class, 'stats'])->middleware('role:student');

    // Certificates (student)
    Route::apiResource('certificates', CertificateController::class)->only(['index', 'show']);
    Route::post('/certificates/course/{course}/generate', [CertificateController::class, 'generate'])->middleware('role:student');
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->middleware('role:student');

    // Lessons (student arena)
    Route::get('/lessons/{lesson}', [LessonController::class, 'show'])->middleware('role:student,instructor,admin');
    Route::post('/lessons/{lesson}/complete', [LessonController::class, 'complete'])->middleware('role:student,instructor,admin');
    Route::post('/lessons/{lesson}/interactive-complete', [LessonController::class, 'completeInteractive'])->middleware('role:student,instructor,admin');

    // ─── Admin Routes ──────────────────────────────────────────
    Route::prefix('admin')->middleware('admin')->group(function () {
        // Dashboard stats
        Route::get('/dashboard-stats', [AdminController::class, 'dashboardStats']);
        
        // Users management
        Route::get('/users', [AdminController::class, 'index']);
        Route::post('/users', [AdminController::class, 'store']);
        Route::get('/users/{user}', [AdminController::class, 'show']);
        Route::put('/users/{user}', [AdminController::class, 'update']);
        Route::delete('/users/{user}', [AdminController::class, 'destroy']);
        Route::put('/users/{user}/role', [AdminController::class, 'updateRole']);
        Route::put('/users/{user}/status', [AdminController::class, 'updateStatus']);
        
        // Courses management (admin view)
        Route::get('/courses', [AdminController::class, 'courses']);
        Route::get('/courses/{course}', [AdminController::class, 'showCourse']);
        Route::delete('/courses/{course}', [AdminController::class, 'destroyCourse']);
        
        // System
        Route::get('/system-info', [AdminController::class, 'systemInfo']);
        Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
    });
});

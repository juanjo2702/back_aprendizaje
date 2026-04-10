<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\GameConfigurationController;
use App\Http\Controllers\Api\GameSessionController;
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

// Public certificate verification
Route::post('/certificates/verify', [CertificateController::class, 'verify']);

// ─── Protected (Sanctum) ────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Courses CRUD (instructor/admin)
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);

    // Categories CRUD (admin)
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('admin');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('admin');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('admin');

    // Payments & QR Checkout
    Route::post('/payments/intent', [\App\Http\Controllers\Api\PaymentController::class, 'createIntent']);
    Route::get('/payments/{transaction_id}', [\App\Http\Controllers\Api\PaymentController::class, 'checkStatus']);
    Route::post('/payments/webhook', [\App\Http\Controllers\Api\PaymentController::class, 'confirmMockPayment']);

    // ─── Gamification & Progress ───────────────────────────────

    // User Progress & Dashboard
    Route::prefix('user')->group(function () {
        Route::get('/dashboard-stats', [UserProgressController::class, 'dashboardStats']);
        Route::get('/courses', [UserProgressController::class, 'userCourses']);
        Route::get('/courses/{course}/progress', [UserProgressController::class, 'courseProgress']);
        Route::get('/recent-activity', [UserProgressController::class, 'recentActivity']);
    });

    // Game Configurations
    Route::apiResource('game-configurations', GameConfigurationController::class)->except(['create', 'edit']);
    Route::get('/game-configurations/course/{courseSlug}', [GameConfigurationController::class, 'forUserCourse']);

    // Game Sessions
    Route::apiResource('game-sessions', GameSessionController::class)->except(['create', 'edit', 'destroy']);
    Route::post('/game-sessions/start', [GameSessionController::class, 'store']); // start new session
    Route::get('/game-sessions/stats', [GameSessionController::class, 'stats']);

    // Quizzes & Attempts
    Route::apiResource('quizzes', QuizController::class)->only(['index', 'show']);
    Route::post('/quizzes/{quiz}/start', [QuizController::class, 'startAttempt']);
    Route::post('/quiz-attempts/{attempt}/submit', [QuizController::class, 'submitAttempt'])
        ->where('attempt', '[0-9]+'); // UserQuizAttempt model binding
    Route::get('/quizzes/{quiz}/attempt-history', [QuizController::class, 'attemptHistory']);
    Route::get('/quizzes/user/stats', [QuizController::class, 'userStats']);

    // Badges
    Route::apiResource('badges', BadgeController::class)->only(['index', 'show']);
    Route::get('/badges/my', [BadgeController::class, 'myBadges']);
    Route::get('/badges/available', [BadgeController::class, 'availableBadges']);
    Route::get('/badges/stats', [BadgeController::class, 'stats']);

    // Certificates
    Route::apiResource('certificates', CertificateController::class)->only(['index', 'show']);
    Route::post('/certificates/course/{course}/generate', [CertificateController::class, 'generate']);
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download']);

    // Lessons
    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);

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

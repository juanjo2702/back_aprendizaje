<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\GameConfigurationController;
use App\Http\Controllers\Api\GameTypeController;
use App\Http\Controllers\Api\GameSessionController;
use App\Http\Controllers\Api\InstructorContentController;
use App\Http\Controllers\Api\InteractiveConfigController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\TeacherStudentController;
use App\Http\Controllers\Api\UserProgressController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ActivityValidationController;
use App\Http\Controllers\Api\AdminCourseReviewController;
use App\Http\Controllers\Api\AdminFinanceController;
use App\Http\Controllers\Api\AdminGamificationController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminSettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TeacherVideoUploadController;

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
Route::post('/payments/webhook', [PaymentController::class, 'confirmMockPayment']);

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
    Route::put('/courses/{course}/status', [CourseController::class, 'updateStatus'])->middleware('role:instructor,admin');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->middleware('role:instructor,admin');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->middleware('role:instructor,admin');
    Route::post('/courses/{course}/modules', [InstructorContentController::class, 'storeModule'])->middleware('role:instructor,admin');
    Route::put('/modules/{module}', [InstructorContentController::class, 'updateModule'])->middleware('role:instructor,admin');
    Route::delete('/modules/{module}', [InstructorContentController::class, 'destroyModule'])->middleware('role:instructor,admin');
    Route::post('/modules/{module}/lessons', [InstructorContentController::class, 'storeLesson'])->middleware('role:instructor,admin');
    Route::put('/instructor/lessons/{lesson}', [InstructorContentController::class, 'updateLesson'])->middleware('role:instructor,admin');
    Route::delete('/instructor/lessons/{lesson}', [InstructorContentController::class, 'destroyLesson'])->middleware('role:instructor,admin');
    Route::post('/teacher/upload-video', [TeacherVideoUploadController::class, 'storeVideo'])->middleware(['role:instructor,admin', 'course-owner']);
    Route::post('/teacher/upload-resource', [TeacherVideoUploadController::class, 'storeResource'])->middleware(['role:instructor,admin', 'course-owner']);

    // Categories CRUD (admin)
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('admin');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('admin');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('admin');

    // Payments & QR Checkout (student only)
    Route::post('/payments/intent', [PaymentController::class, 'createIntent'])->middleware(['role:student', 'check-level:course_id']);
    Route::get('/payments/{transaction_id}', [PaymentController::class, 'checkStatus'])->middleware('role:student');
    // ─── Gamification & Progress ───────────────────────────────

    // User Progress & Dashboard (student only)
    Route::prefix('user')->middleware('role:student')->group(function () {
        Route::get('/dashboard-stats', [UserProgressController::class, 'dashboardStats']);
        Route::get('/courses', [UserProgressController::class, 'userCourses']);
        Route::get('/courses/{course}/progress', [UserProgressController::class, 'courseProgress']);
        Route::get('/recent-activity', [UserProgressController::class, 'recentActivity']);
    });

    // Student economy
    Route::get('/shop/items', [ShopController::class, 'index'])->middleware('role:student');
    Route::get('/shop/purchases', [ShopController::class, 'purchases'])->middleware('role:student');
    Route::post('/shop/items/{shopItem}/purchase', [ShopController::class, 'purchase'])->middleware('role:student');

    // Game Configurations (teacher/admin authoring)
    Route::apiResource('game-configurations', GameConfigurationController::class)
        ->except(['create', 'edit'])
        ->middleware('role:instructor,admin');
    Route::get('/game-configurations/course/{courseSlug}', [GameConfigurationController::class, 'forUserCourse']);
    Route::apiResource('interactive-configs', InteractiveConfigController::class)
        ->except(['create', 'edit'])
        ->middleware('role:instructor,admin');
    Route::post('/interactive-configs/{interactiveConfig}/reset/{student}', [ActivityValidationController::class, 'reset'])
        ->middleware(['role:instructor,admin', 'course-owner']);

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
    Route::post('/interactive-configs/{interactiveConfig}/attempts', [ActivityValidationController::class, 'store'])->middleware('role:student');

    // Polymorphic comments
    Route::get('/comments', [CommentController::class, 'index'])->middleware('role:student,instructor,admin');
    Route::post('/comments', [CommentController::class, 'store'])->middleware('role:student,instructor,admin');
    Route::post('/comments/{comment}/reply', [CommentController::class, 'reply'])->middleware('role:student,instructor,admin');

    // Teacher student tracking
    Route::get('/instructor/courses/{course}/students', [TeacherStudentController::class, 'index'])->middleware('role:instructor,admin');
    Route::get('/instructor/courses/{course}/students/{student}', [TeacherStudentController::class, 'show'])->middleware('role:instructor,admin');
    Route::get('/instructor/courses/{course}/gradebook', [TeacherStudentController::class, 'gradebook'])->middleware(['role:instructor,admin', 'course-owner']);
    Route::get('/instructor/alerts', [TeacherStudentController::class, 'alerts'])->middleware('role:instructor,admin');

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
        
        // Course review & curation
        Route::get('/courses/review-inbox', [AdminCourseReviewController::class, 'inbox']);
        Route::get('/courses/{course}/review', [AdminCourseReviewController::class, 'show']);
        Route::put('/courses/{course}/approval-status', [AdminCourseReviewController::class, 'updateStatus']);
        Route::delete('/courses/{course}', [AdminController::class, 'destroyCourse']);

        // Finance
        Route::get('/finances/payments', [AdminFinanceController::class, 'payments']);
        Route::post('/finances/payments/{payment}/confirm', [AdminFinanceController::class, 'confirmPayment']);
        Route::post('/finances/payments/{payment}/reject', [AdminFinanceController::class, 'rejectPayment']);
        Route::get('/finances/payouts', [AdminFinanceController::class, 'payouts']);
        Route::put('/finances/payouts/{payout}', [AdminFinanceController::class, 'updatePayout']);

        // Global settings
        Route::get('/settings', [AdminSettingsController::class, 'show']);
        Route::put('/settings', [AdminSettingsController::class, 'update']);

        // Gamification lab
        Route::get('/gamification/badges', [AdminGamificationController::class, 'badges']);
        Route::post('/gamification/badges', [AdminGamificationController::class, 'storeBadge']);
        Route::put('/gamification/badges/{badge}', [AdminGamificationController::class, 'updateBadge']);
        Route::delete('/gamification/badges/{badge}', [AdminGamificationController::class, 'destroyBadge']);
        Route::get('/gamification/rewards', [AdminGamificationController::class, 'rewards']);
        Route::post('/gamification/rewards', [AdminGamificationController::class, 'storeReward']);
        Route::put('/gamification/rewards/{shopItem}', [AdminGamificationController::class, 'updateReward']);
        Route::delete('/gamification/rewards/{shopItem}', [AdminGamificationController::class, 'destroyReward']);

        // Reports
        Route::get('/reports/bottlenecks', [AdminReportController::class, 'bottlenecks']);
        Route::get('/reports/gamification-audit', [AdminReportController::class, 'gamificationAudit']);
        
        // System
        Route::get('/system-info', [AdminController::class, 'systemInfo']);
        Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
    });
});

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Course;
use App\Models\Category;
use App\Models\Payment;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function dashboardStats(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $totalUsers = User::count();
        $totalCourses = Course::where('is_published', true)->count();
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $activeUsers = User::where('last_login_at', '>=', Carbon::now()->subDays(7))->count();
        $totalInstructors = User::where('role', 'instructor')->count();
        $pendingCourses = Course::where('is_published', false)->count();
        
        // Recent activity
        $recentUsers = User::latest()->take(5)->get(['id', 'name', 'email', 'role', 'created_at']);
        $recentPayments = Payment::with('user:id,name', 'course:id,title')
            ->latest()
            ->take(5)
            ->get();
        
        // Course enrollments
        $popularCourses = Course::withCount('users')
            ->orderBy('users_count', 'desc')
            ->take(5)
            ->get(['id', 'title', 'slug', 'instructor_id', 'price']);

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'total_courses' => $totalCourses,
                'total_revenue' => $totalRevenue,
                'active_users' => $activeUsers,
                'total_instructors' => $totalInstructors,
                'pending_courses' => $pendingCourses,
            ],
            'recent_users' => $recentUsers,
            'recent_payments' => $recentPayments,
            'popular_courses' => $popularCourses,
            'activity_summary' => [
                'users_today' => User::whereDate('created_at', Carbon::today())->count(),
                'payments_today' => Payment::whereDate('created_at', Carbon::today())
                    ->where('status', 'completed')
                    ->count(),
                'new_enrollments_today' => DB::table('course_user')
                    ->whereDate('created_at', Carbon::today())
                    ->count(),
            ]
        ]);
    }

    /**
     * Get all users with pagination and filters
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::query();

        // Search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->has('role') && $request->input('role') !== '') {
            $query->where('role', $request->input('role'));
        }

        // Status filter (email verification)
        if ($request->has('status')) {
            if ($request->input('status') === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->input('status') === 'pending') {
                $query->whereNull('email_verified_at');
            }
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]
        ]);
    }

    /**
     * Get single user details
     */
    public function show(Request $request, User $user)
    {
        $admin = $request->user();
        
        if (!$admin->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user->load([
            'courses',
            'badges',
            'certificates',
            'gameSessions' => function ($query) {
                $query->latest()->take(10);
            },
            'quizAttempts' => function ($query) {
                $query->with('quiz')->latest()->take(10);
            }
        ]);

        return response()->json($user);
    }

    /**
     * Create new user (admin only)
     */
    public function store(Request $request)
    {
        $admin = $request->user();
        
        if (!$admin->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,instructor,student',
            'avatar' => 'nullable|url',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['email_verified_at'] = now(); // Auto-verify for admin created users

        $user = User::create($validated);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $admin = $request->user();
        
        if (!$admin->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8|confirmed',
            'role' => 'sometimes|in:admin,instructor,student',
            'avatar' => 'nullable|url',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Update user role
     */
    public function updateRole(Request $request, User $user)
    {
        $admin = $request->user();
        
        if (!$admin->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,instructor,student'
        ]);

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'User role updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Update user status (email verification)
     */
    public function updateStatus(Request $request, User $user)
    {
        $admin = $request->user();
        
        if (!$admin->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:verified,pending,banned'
        ]);

        if ($validated['status'] === 'verified') {
            $user->update(['email_verified_at' => now()]);
        } elseif ($validated['status'] === 'pending') {
            $user->update(['email_verified_at' => null]);
        } elseif ($validated['status'] === 'banned') {
            $user->update(['banned_at' => now()]);
        }

        return response()->json([
            'message' => 'User status updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Delete user
     */
    public function destroy(Request $request, User $user)
    {
        $admin = $request->user();
        
        if (!$admin->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Prevent self-deletion
        if ($user->id === $admin->id) {
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get all courses (admin view)
     */
    public function courses(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Course::with(['instructor:id,name,email', 'category:id,name']);

        // Search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            if ($request->input('status') === 'published') {
                $query->where('is_published', true);
            } elseif ($request->input('status') === 'draft') {
                $query->where('is_published', false);
            }
        }

        // Category filter
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $courses = $query->paginate($perPage);

        return response()->json([
            'data' => $courses->items(),
            'meta' => [
                'total' => $courses->total(),
                'per_page' => $courses->perPage(),
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
            ]
        ]);
    }

    /**
     * Get course details (admin view)
     */
    public function showCourse(Request $request, Course $course)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $course->load([
            'instructor:id,name,email',
            'category:id,name',
            'modules.lessons',
            'users' => function ($query) {
                $query->limit(10);
            },
            'reviews' => function ($query) {
                $query->with('user:id,name')->latest()->limit(10);
            }
        ]);

        // Add enrollment count
        $course->enrollments_count = $course->users()->count();
        $course->completion_rate = $course->users()->wherePivot('completed', true)->count();

        return response()->json($course);
    }

    /**
     * Delete course (admin only)
     */
    public function destroyCourse(Request $request, Course $course)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully'
        ]);
    }

    /**
     * Get system information
     */
    public function systemInfo(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Basic PHP info
        $phpVersion = phpversion();
        $laravelVersion = app()->version();
        
        // Database info
        $dbName = DB::connection()->getDatabaseName();
        $dbSize = $this->getDatabaseSize();
        
        // Server info
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $serverProtocol = $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown';
        
        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        // Storage info
        $storagePath = storage_path();
        $storageFree = disk_free_space($storagePath);
        $storageTotal = disk_total_space($storagePath);
        
        // Active users/sessions
        $activeSessions = GameSession::where('updated_at', '>=', Carbon::now()->subMinutes(30))->count();
        $activeUsers = User::where('last_login_at', '>=', Carbon::now()->subHours(1))->count();

        return response()->json([
            'php' => [
                'version' => $phpVersion,
                'laravel_version' => $laravelVersion,
                'memory_usage' => $this->formatBytes($memoryUsage),
                'memory_peak' => $this->formatBytes($memoryPeak),
            ],
            'database' => [
                'name' => $dbName,
                'size' => $dbSize,
                'tables' => $this->getTableCounts(),
            ],
            'server' => [
                'software' => $serverSoftware,
                'protocol' => $serverProtocol,
                'timezone' => config('app.timezone'),
                'environment' => config('app.env'),
            ],
            'storage' => [
                'free' => $this->formatBytes($storageFree),
                'total' => $this->formatBytes($storageTotal),
                'used_percentage' => $storageTotal > 0 ? round(($storageTotal - $storageFree) / $storageTotal * 100, 2) : 0,
            ],
            'activity' => [
                'active_sessions' => $activeSessions,
                'active_users' => $activeUsers,
                'total_requests' => $this->getRequestCount(),
            ]
        ]);
    }

    /**
     * Get activity logs
     */
    public function activityLogs(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // TODO: Implement proper activity logging system
        // For now, return basic logs from various models
        
        $logs = [
            'recent_logins' => User::whereNotNull('last_login_at')
                ->orderBy('last_login_at', 'desc')
                ->take(20)
                ->get(['id', 'name', 'email', 'last_login_at']),
            'recent_payments' => Payment::with('user:id,name', 'course:id,title')
                ->latest()
                ->take(20)
                ->get(),
            'recent_course_creations' => Course::with('instructor:id,name')
                ->latest()
                ->take(20)
                ->get(['id', 'title', 'instructor_id', 'created_at']),
        ];

        return response()->json($logs);
    }

    /**
     * Helper: Get database size
     */
    private function getDatabaseSize()
    {
        try {
            $dbName = DB::connection()->getDatabaseName();
            $result = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                FROM information_schema.TABLES 
                WHERE table_schema = ?", [$dbName]);
            
            return isset($result[0]->size_mb) ? $result[0]->size_mb . ' MB' : 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Helper: Get table counts
     */
    private function getTableCounts()
    {
        $tables = [
            'users' => User::count(),
            'courses' => Course::count(),
            'categories' => Category::count(),
            'payments' => Payment::count(),
            'game_sessions' => GameSession::count(),
        ];
        
        return $tables;
    }

    /**
     * Helper: Get request count (simplified)
     */
    private function getRequestCount()
    {
        // TODO: Implement proper request counting
        return 'N/A';
    }

    /**
     * Helper: Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
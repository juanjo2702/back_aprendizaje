<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Badge;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GameSession;
use App\Models\InteractiveConfig;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\ShopItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AdminController extends Controller
{
    public function dashboardStats()
    {
        $activeUsers = User::query()
            ->where(function ($query) {
                $query
                    ->whereNotNull('last_active_at')
                    ->where('last_active_at', '>=', now()->subDays(7))
                    ->orWhere('created_at', '>=', now()->subDays(7));
            })
            ->count();

        $totalEnrollments = Enrollment::query()->count();
        $dropoutBase = Enrollment::query()
            ->where('created_at', '<=', now()->subDays(14))
            ->count();
        $dropouts = Enrollment::query()
            ->where('created_at', '<=', now()->subDays(14))
            ->where('progress', '<', 20)
            ->count();

        $popularCategories = Category::query()
            ->select('categories.id', 'categories.name')
            ->leftJoin('courses', 'courses.category_id', '=', 'categories.id')
            ->leftJoin('enrollments', 'enrollments.course_id', '=', 'courses.id')
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('count(enrollments.id) as total_enrollments')
            ->orderByDesc('total_enrollments')
            ->limit(6)
            ->get();

        $videoBytes = (int) Media::query()
            ->where('collection_name', 'lesson_video')
            ->sum('size');

        $storagePath = storage_path();
        $storageTotal = @disk_total_space($storagePath) ?: 0;
        $storageFree = @disk_free_space($storagePath) ?: 0;
        $storageUsed = $storageTotal > 0 ? ($storageTotal - $storageFree) : 0;

        $bottlenecks = InteractiveConfig::query()
            ->with(['course:id,title,slug', 'lesson:id,title'])
            ->withCount([
                'activityResults as total_results',
                'activityResults as failed_results' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->withAvg('activityResults as average_attempts', 'attempts_used')
            ->get()
            ->filter(fn (InteractiveConfig $config) => (int) $config->total_results > 0)
            ->sortByDesc('failed_results')
            ->take(5)
            ->map(fn (InteractiveConfig $config) => [
                'interactive_config_id' => $config->id,
                'course_title' => $config->course?->title,
                'lesson_title' => $config->lesson?->title,
                'activity_type' => $config->activity_type,
                'total_results' => (int) $config->total_results,
                'failed_results' => (int) $config->failed_results,
                'average_attempts' => round((float) $config->average_attempts, 2),
            ]);

        return response()->json([
            'overview' => [
                'total_users' => User::query()->count(),
                'active_users' => $activeUsers,
                'monthly_revenue' => (float) Payment::query()
                    ->where('status', 'completed')
                    ->whereBetween('reviewed_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('amount'),
                'dropout_rate' => $dropoutBase > 0 ? round(($dropouts / $dropoutBase) * 100, 2) : 0,
                'pending_reviews' => Course::query()->where('status', 'pending')->count(),
                'pending_payments' => Payment::query()->where('status', 'pending')->count(),
                'pending_payouts' => Payout::query()->where('status', 'pending')->count(),
                'published_courses' => Course::query()->where('status', 'published')->count(),
            ],
            'popular_categories' => $popularCategories,
            'storage' => [
                'video_bytes' => $videoBytes,
                'video_human' => $this->formatBytes($videoBytes),
                'disk_used_bytes' => $storageUsed,
                'disk_total_bytes' => $storageTotal,
                'disk_used_percentage' => $storageTotal > 0 ? round(($storageUsed / $storageTotal) * 100, 2) : 0,
            ],
            'recent_users' => User::query()
                ->latest()
                ->limit(6)
                ->get(['id', 'name', 'email', 'role', 'created_at']),
            'recent_payments' => Payment::query()
                ->with(['user:id,name,email', 'course:id,title'])
                ->latest()
                ->limit(6)
                ->get(),
            'recent_logs' => AdminActivityLog::query()
                ->with('actor:id,name,email')
                ->latest()
                ->limit(8)
                ->get(),
            'bottlenecks_preview' => $bottlenecks,
            'inventory' => [
                'badges' => Badge::query()->count(),
                'rewards' => ShopItem::query()->count(),
                'courses' => Course::query()->count(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'verified' => $query->whereNotNull('email_verified_at'),
                'pending' => $query->whereNull('email_verified_at'),
                default => null,
            };
        }

        $users = $query
            ->orderBy(
                $request->string('sort_by', 'created_at')->toString(),
                $request->string('sort_dir', 'desc')->toString()
            )
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function show(User $user)
    {
        return response()->json(
            $user->load([
                'courses:id,title,instructor_id,status,price,created_at',
                'badges:id,name,slug,icon,type',
                'certificates',
                'gameSessions' => fn ($query) => $query->latest()->limit(10),
                'quizAttempts' => fn ($query) => $query->with('quiz')->latest()->limit(10),
            ])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,instructor,student',
            'avatar' => 'nullable|url',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['email_verified_at'] = now();

        $user = User::query()->create($validated);

        AdminActivityLog::record($request->user(), 'user.created', $user);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'user' => $user,
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'sometimes|string|min:8|confirmed',
            'role' => 'sometimes|in:admin,instructor,student',
            'avatar' => 'nullable|url',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);

        AdminActivityLog::record($request->user(), 'user.updated', $user);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'user' => $user->fresh(),
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,instructor,student',
        ]);

        $user->update(['role' => $validated['role']]);

        AdminActivityLog::record($request->user(), 'user.role_updated', $user, [
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'user' => $user->fresh(),
        ]);
    }

    public function updateStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => 'required|in:verified,pending',
        ]);

        $user->update([
            'email_verified_at' => $validated['status'] === 'verified' ? now() : null,
        ]);

        AdminActivityLog::record($request->user(), 'user.status_updated', $user, [
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'user' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 422);
        }

        AdminActivityLog::record($request->user(), 'user.deleted', $user);
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    public function destroyCourse(Request $request, Course $course)
    {
        AdminActivityLog::record($request->user(), 'course.deleted', $course);
        $course->delete();

        return response()->json(['message' => 'Curso eliminado correctamente.']);
    }

    public function systemInfo()
    {
        $storagePath = storage_path();
        $storageFree = @disk_free_space($storagePath) ?: 0;
        $storageTotal = @disk_total_space($storagePath) ?: 0;

        return response()->json([
            'php' => [
                'version' => phpversion(),
                'laravel_version' => app()->version(),
                'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            ],
            'database' => [
                'name' => DB::connection()->getDatabaseName(),
                'size' => $this->getDatabaseSize(),
                'tables' => [
                    'users' => User::query()->count(),
                    'courses' => Course::query()->count(),
                    'payments' => Payment::query()->count(),
                    'payouts' => Payout::query()->count(),
                    'badges' => Badge::query()->count(),
                    'rewards' => ShopItem::query()->count(),
                ],
            ],
            'server' => [
                'timezone' => config('app.timezone'),
                'environment' => config('app.env'),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            ],
            'storage' => [
                'free' => $this->formatBytes($storageFree),
                'total' => $this->formatBytes($storageTotal),
                'used_percentage' => $storageTotal > 0 ? round((($storageTotal - $storageFree) / $storageTotal) * 100, 2) : 0,
            ],
            'activity' => [
                'active_sessions' => GameSession::query()->where('updated_at', '>=', Carbon::now()->subMinutes(30))->count(),
                'active_users' => User::query()->where('last_active_at', '>=', Carbon::now()->subHours(1))->count(),
            ],
        ]);
    }

    public function activityLogs(Request $request)
    {
        $logs = AdminActivityLog::query()
            ->with('actor:id,name,email')
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    private function getDatabaseSize(): string
    {
        try {
            $dbName = DB::connection()->getDatabaseName();
            $result = DB::select(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = ?',
                [$dbName]
            );

            return isset($result[0]->size_mb) ? $result[0]->size_mb.' MB' : 'Unknown';
        } catch (\Throwable) {
            return 'Unknown';
        }
    }

    private function formatBytes(float|int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}

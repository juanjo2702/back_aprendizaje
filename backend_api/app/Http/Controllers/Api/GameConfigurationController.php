<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\GameConfiguration;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameConfigurationController extends Controller
{
    /**
     * Listar configuraciones de juegos disponibles para un curso/módulo/lección.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = GameConfiguration::where('is_active', true)
            ->with('gameType:id,name,slug,default_config');

        if ($user && ! $user->isAdmin()) {
            $query->whereHas('course', fn ($courseQuery) => $courseQuery->where('instructor_id', $user->id));
        }

        // Filtrar por curso
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filtrar por módulo
        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        // Filtrar por lección
        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        // Solo configuraciones de nivel curso (sin módulo ni lección específica)
        if ($request->has('course_level_only') && $request->boolean('course_level_only')) {
            $query->whereNull('module_id')->whereNull('lesson_id');
        }

        return $query->paginate($request->get('per_page', 10));
    }

    /**
     * Mostrar una configuración específica con detalles completos.
     */
    public function show(GameConfiguration $gameConfiguration)
    {
        // Verificar que la configuración esté activa
        if (! $gameConfiguration->is_active) {
            abort(404, 'Configuración de juego no disponible.');
        }

        $gameConfiguration->load([
            'gameType:id,name,slug,description,default_config',
            'course:id,title,slug',
            'module:id,title',
            'lesson:id,title',
        ]);

        return response()->json($gameConfiguration);
    }

    /**
     * Crear nueva configuración de juego (admin/instructor).
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user->isAdmin() && ! $user->isInstructor()) {
            abort(403, 'No tienes permiso para crear configuraciones de juego.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'game_type_id' => 'required|exists:game_types,id',
            'course_id' => 'required|exists:courses,id',
            'module_id' => 'nullable|exists:modules,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'config' => 'nullable|array',
            'max_score' => 'required|integer|min:1|max:10000',
            'time_limit' => 'nullable|integer|min:1',
            'max_attempts' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        // Validar jerarquía: si hay lesson_id, debe pertenecer al module_id y course_id
        if ($validated['lesson_id'] ?? null) {
            $lesson = Lesson::findOrFail($validated['lesson_id']);
            if ($lesson->module_id != ($validated['module_id'] ?? null)) {
                return response()->json([
                    'message' => 'La lección no pertenece al módulo especificado.',
                ], 422);
            }
        }

        // Validar que el instructor sea dueño del curso (si no es admin)
        if (! $user->isAdmin()) {
            $course = Course::findOrFail($validated['course_id']);
            if ($course->instructor_id !== $user->id) {
                abort(403, 'Solo puedes crear configuraciones para tus propios cursos.');
            }
        }

        $gameConfig = GameConfiguration::create($validated);

        return response()->json($gameConfig->load('gameType'), 201);
    }

    /**
     * Actualizar configuración existente (admin/instructor dueño).
     */
    public function update(Request $request, GameConfiguration $gameConfiguration)
    {
        $user = Auth::user();
        $this->authorizeOwnerOrAdmin($user, $gameConfiguration);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'game_type_id' => 'sometimes|exists:game_types,id',
            'module_id' => 'nullable|exists:modules,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'config' => 'nullable|array',
            'max_score' => 'sometimes|integer|min:1|max:10000',
            'time_limit' => 'nullable|integer|min:1',
            'max_attempts' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if (($validated['lesson_id'] ?? null) && ($validated['module_id'] ?? null)) {
            $lesson = Lesson::findOrFail($validated['lesson_id']);
            if ((int) $lesson->module_id !== (int) $validated['module_id']) {
                return response()->json([
                    'message' => 'La lección no pertenece al módulo especificado.',
                ], 422);
            }
        }

        $gameConfiguration->update($validated);

        return response()->json($gameConfiguration->fresh()->load('gameType'));
    }

    /**
     * Eliminar configuración (soft delete).
     */
    public function destroy(GameConfiguration $gameConfiguration)
    {
        $user = Auth::user();
        $this->authorizeOwnerOrAdmin($user, $gameConfiguration);

        $gameConfiguration->delete();

        return response()->json(['message' => 'Configuración de juego eliminada.']);
    }

    /**
     * Obtener configuraciones de juegos para el curso actual del usuario.
     */
    public function forUserCourse(Request $request, $courseSlug)
    {
        $user = Auth::user();

        $course = Course::where('slug', $courseSlug)->firstOrFail();

        // Verificar que el usuario esté inscrito en el curso (o sea instructor/admin)
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->exists();
        $isOwner = $course->instructor_id === $user->id;

        if (! $isEnrolled && ! $isOwner && ! $user->isAdmin()) {
            abort(403, 'No estás inscrito en este curso.');
        }

        $configs = GameConfiguration::where('course_id', $course->id)
            ->where('is_active', true)
            ->with(['gameType', 'module', 'lesson'])
            ->orderBy('lesson_id', 'asc')
            ->orderBy('module_id', 'asc')
            ->get();

        return response()->json($configs);
    }

    /**
     * Helper: autorizar dueño (instructor del curso) o admin.
     */
    private function authorizeOwnerOrAdmin($user, GameConfiguration $config)
    {
        $course = $config->course;

        if ($user->id !== $course->instructor_id && ! $user->isAdmin()) {
            abort(403, 'No tienes permiso para modificar esta configuración.');
        }
    }
}

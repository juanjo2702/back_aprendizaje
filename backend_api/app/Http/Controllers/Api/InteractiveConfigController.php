<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InteractiveConfig;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InteractiveConfigController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = InteractiveConfig::query()
            ->where('is_active', true)
            ->with(['course:id,title,slug', 'module:id,title', 'lesson:id,title']);

        if ($user && ! $user->isAdmin()) {
            $query->whereHas('course', fn ($courseQuery) => $courseQuery->where('instructor_id', $user->id));
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->integer('course_id'));
        }

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->integer('module_id'));
        }

        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->integer('lesson_id'));
        }

        return $query->orderByDesc('id')->paginate($request->integer('per_page', 15));
    }

    public function show(InteractiveConfig $interactiveConfig)
    {
        return response()->json(
            $interactiveConfig->load(['course:id,title,slug', 'module:id,title', 'lesson:id,title'])
        );
    }

    /**
     * Tutor no-programador: authoring_mode=form + config_payload.
     * Tutor avanzado: authoring_mode=custom + json/.zip opcionales.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'authoring_mode' => 'required|in:form,custom',
            'activity_type' => 'required|string|max:100',
            'config_payload' => 'nullable|array',
            'custom_payload' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'version' => 'sometimes|integer|min:1',
            'config_json' => 'nullable|file|mimes:json',
            'assets_zip' => 'nullable|file|mimes:zip',
        ]);

        $lesson = Lesson::with('module.course')->findOrFail($validated['lesson_id']);
        $course = $lesson->module->course;

        if (! $user->isAdmin() && $course->instructor_id !== $user->id) {
            return response()->json(['message' => 'No puedes editar actividades de este curso.'], 403);
        }

        $payload = $validated['config_payload'] ?? [];
        if ($validated['authoring_mode'] === 'custom') {
            $payload = $validated['custom_payload'] ?? [];
            if ($request->hasFile('config_json')) {
                $jsonContents = $request->file('config_json')->get();
                $decoded = json_decode($jsonContents, true);
                if (! is_array($decoded)) {
                    return response()->json(['message' => 'El archivo JSON no es válido.'], 422);
                }
                $payload = $decoded;
            }
        }

        if (empty($payload)) {
            return response()->json(['message' => 'Debes proporcionar configuración de actividad.'], 422);
        }

        $assetsPath = null;
        if ($request->hasFile('assets_zip')) {
            $assetsPath = $request->file('assets_zip')->store('interactive-assets', 'local');
        }

        $config = InteractiveConfig::updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'course_id' => $course->id,
                'module_id' => $lesson->module_id,
                'authoring_mode' => $validated['authoring_mode'],
                'activity_type' => $validated['activity_type'],
                'config_payload' => $payload,
                'assets_manifest' => $assetsPath ? ['zip_path' => $assetsPath] : null,
                'source_package_path' => $assetsPath,
                'is_active' => $validated['is_active'] ?? true,
                'version' => $validated['version'] ?? 1,
            ]
        );

        $lesson->update([
            'type' => 'interactive',
            'contentable_type' => InteractiveConfig::class,
            'contentable_id' => $config->id,
        ]);

        return response()->json($config->fresh()->load(['course:id,title,slug', 'module:id,title', 'lesson:id,title']), 201);
    }

    public function update(Request $request, InteractiveConfig $interactiveConfig)
    {
        $user = $request->user();
        $lesson = $interactiveConfig->lesson()->with('module.course')->firstOrFail();
        $course = $lesson->module->course;

        if (! $user->isAdmin() && $course->instructor_id !== $user->id) {
            return response()->json(['message' => 'No puedes editar actividades de este curso.'], 403);
        }

        $validated = $request->validate([
            'lesson_id' => 'sometimes|exists:lessons,id',
            'authoring_mode' => 'sometimes|in:form,custom',
            'activity_type' => 'sometimes|string|max:100',
            'config_payload' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'version' => 'sometimes|integer|min:1',
            'config_json' => 'nullable|file|mimes:json',
            'assets_zip' => 'nullable|file|mimes:zip',
        ]);

        if ($request->hasFile('config_json')) {
            $decoded = json_decode($request->file('config_json')->get(), true);
            if (! is_array($decoded)) {
                return response()->json(['message' => 'El archivo JSON no es válido.'], 422);
            }
            $validated['config_payload'] = $decoded;
        }

        if ($request->hasFile('assets_zip')) {
            if ($interactiveConfig->source_package_path) {
                Storage::disk('local')->delete($interactiveConfig->source_package_path);
            }
            $path = $request->file('assets_zip')->store('interactive-assets', 'local');
            $validated['source_package_path'] = $path;
            $validated['assets_manifest'] = ['zip_path' => $path];
        }

        if (array_key_exists('lesson_id', $validated)) {
            $lesson = Lesson::with('module.course')->findOrFail($validated['lesson_id']);
            if (! $user->isAdmin() && (int) $lesson->module->course->instructor_id !== (int) $user->id) {
                return response()->json(['message' => 'No puedes mover actividades a cursos ajenos.'], 403);
            }

            $validated['course_id'] = $lesson->module->course->id;
            $validated['module_id'] = $lesson->module_id;
        }

        $interactiveConfig->update($validated);

        return response()->json($interactiveConfig->fresh()->load(['course:id,title,slug', 'module:id,title', 'lesson:id,title']));
    }

    public function destroy(Request $request, InteractiveConfig $interactiveConfig)
    {
        $user = $request->user();
        $lesson = $interactiveConfig->lesson()->with('module.course')->firstOrFail();
        $course = $lesson->module->course;

        if (! $user->isAdmin() && $course->instructor_id !== $user->id) {
            return response()->json(['message' => 'No puedes eliminar actividades de este curso.'], 403);
        }

        $interactiveConfig->update(['is_active' => false]);

        return response()->json(['message' => 'Configuración interactiva desactivada.']);
    }
}

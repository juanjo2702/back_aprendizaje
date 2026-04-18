<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\InteractiveConfig;
use App\Models\Lesson;
use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCourseOwnerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Debes iniciar sesión.');
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        if (! $user->isInstructor()) {
            abort(403, 'Solo docentes y administradores pueden gestionar este recurso.');
        }

        $course = $this->resolveCourse($request);
        if (! $course || (int) $course->instructor_id !== (int) $user->id) {
            abort(403, 'No tienes permiso para gestionar recursos de este curso.');
        }

        return $next($request);
    }

    private function resolveCourse(Request $request): ?Course
    {
        $courseParam = $request->route('course');
        if ($courseParam instanceof Course) {
            return $courseParam;
        }

        if (is_numeric($courseParam)) {
            return Course::find((int) $courseParam);
        }

        $moduleParam = $request->route('module');
        if ($moduleParam instanceof Module) {
            return $moduleParam->course;
        }

        if (is_numeric($moduleParam)) {
            return Module::with('course')->find((int) $moduleParam)?->course;
        }

        $lessonParam = $request->route('lesson');
        if ($lessonParam instanceof Lesson) {
            return $lessonParam->module?->course;
        }

        if (is_numeric($lessonParam)) {
            return Lesson::with('module.course')->find((int) $lessonParam)?->module?->course;
        }

        $configParam = $request->route('interactiveConfig');
        if ($configParam instanceof InteractiveConfig) {
            return $configParam->course;
        }

        if (is_numeric($configParam)) {
            return InteractiveConfig::with('course')->find((int) $configParam)?->course;
        }

        if ($request->filled('course_id')) {
            return Course::find((int) $request->integer('course_id'));
        }

        if ($request->filled('lesson_id')) {
            return Lesson::with('module.course')->find((int) $request->integer('lesson_id'))?->module?->course;
        }

        return null;
    }
}

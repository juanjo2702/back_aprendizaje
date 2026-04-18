<?php

namespace App\Http\Middleware;

use App\Models\Course;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLevelMiddleware
{
    public function handle(Request $request, Closure $next, ?string $inputKey = null): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Debes iniciar sesión.');
        }

        $course = null;

        if ($inputKey && $request->filled($inputKey)) {
            $course = Course::find($request->input($inputKey));
        }

        if (! $course && $request->route('course') instanceof Course) {
            $course = $request->route('course');
        }

        if ($course && $user->current_level < (int) $course->minimum_level_required) {
            abort(403, "Necesitas llegar al nivel {$course->minimum_level_required} para acceder a este curso.");
        }

        return $next($request);
    }
}

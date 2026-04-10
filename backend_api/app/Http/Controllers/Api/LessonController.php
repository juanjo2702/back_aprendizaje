<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    /**
     * Obtener detalles de una lección específica con configuración de juego y quiz.
     */
    public function show($lessonId)
    {
        $user = Auth::user();

        $lesson = Lesson::with(['gameConfiguration.gameType', 'quiz.questions.answers', 'module.course'])
            ->findOrFail($lessonId);

        // Verificar que el usuario tiene acceso al curso (inscrito)
        $course = $lesson->module->course;
        $isEnrolled = $course->enrollments()->where('user_id', $user->id)->exists();

        if (! $isEnrolled && ! $user->isAdmin() && ! $user->isInstructor()) {
            return response()->json(['message' => 'No tienes acceso a esta lección.'], 403);
        }

        // Preparar datos del juego si existe
        $gameData = null;
        if ($lesson->gameConfiguration) {
            $gameData = [
                'id' => $lesson->gameConfiguration->id,
                'title' => $lesson->gameConfiguration->title,
                'game_type' => $lesson->gameConfiguration->gameType->name,
                'config' => $lesson->gameConfiguration->config,
                'max_score' => $lesson->gameConfiguration->max_score,
                'time_limit' => $lesson->gameConfiguration->time_limit,
                'max_attempts' => $lesson->gameConfiguration->max_attempts,
            ];
        }

        // Preparar datos del quiz si existe
        $quizData = null;
        if ($lesson->quiz) {
            $quizData = [
                'id' => $lesson->quiz->id,
                'title' => $lesson->quiz->title,
                'description' => $lesson->quiz->description,
                'questions' => $lesson->quiz->questions->map(function ($question) {
                    return [
                        'id' => $question->id,
                        'text' => $question->text,
                        'type' => $question->type,
                        'options' => $question->answers->map(function ($answer) {
                            return [
                                'id' => $answer->id,
                                'text' => $answer->text,
                                'is_correct' => $answer->is_correct,
                            ];
                        }),
                    ];
                }),
                'time_limit' => $lesson->quiz->time_limit,
                'passing_score' => $lesson->quiz->passing_score,
            ];
        }

        return response()->json([
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'type' => $lesson->type,
                'content_url' => $lesson->content_url,
                'content_text' => $lesson->content_text,
                'duration' => $lesson->duration,
                'is_free' => $lesson->is_free,
                'sort_order' => $lesson->sort_order,
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'game' => $gameData,
            'quiz' => $quizData,
            'navigation' => [
                'previous_lesson' => $this->getPreviousLesson($lesson),
                'next_lesson' => $this->getNextLesson($lesson),
            ],
        ]);
    }

    /**
     * Obtener lección anterior en el mismo módulo.
     */
    private function getPreviousLesson(Lesson $lesson)
    {
        $previous = Lesson::where('module_id', $lesson->module_id)
            ->where('sort_order', '<', $lesson->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        return $previous ? ['id' => $previous->id, 'title' => $previous->title] : null;
    }

    /**
     * Obtener siguiente lección en el mismo módulo.
     */
    private function getNextLesson(Lesson $lesson)
    {
        $next = Lesson::where('module_id', $lesson->module_id)
            ->where('sort_order', '>', $lesson->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        return $next ? ['id' => $next->id, 'title' => $next->title] : null;
    }
}

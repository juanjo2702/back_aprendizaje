<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\UserAnswer;
use App\Models\UserQuizAttempt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    /**
     * Listar quizzes disponibles (para el usuario autenticado).
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Quiz::where('is_active', true)
            ->with([
                'course:id,title,slug',
                'module:id,title',
                'questions' => function ($query) {
                    $query->select('id', 'quiz_id', 'question', 'type', 'options', 'correct_answer');
                },
            ]);

        // Filtrar por curso
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filtrar por módulo
        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        // Filtrar por dificultad
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        return $query->paginate($request->get('per_page', 10));
    }

    /**
     * Mostrar un quiz específico con preguntas (sin respuestas correctas).
     */
    public function show(Quiz $quiz)
    {
        if (! $quiz->is_active) {
            abort(404, 'Este quiz no está disponible.');
        }

        // Cargar preguntas sin mostrar la respuesta correcta
        $quiz->load(['questions' => function ($query) {
            $query->select('id', 'quiz_id', 'question', 'type', 'options', 'points')
                ->orderBy('sort_order');
        }]);

        // Ocultar la respuesta correcta
        foreach ($quiz->questions as $question) {
            unset($question->correct_answer);
        }

        return response()->json($quiz);
    }

    /**
     * Iniciar un nuevo intento de quiz.
     */
    public function startAttempt(Request $request, Quiz $quiz)
    {
        $user = Auth::user();

        if (! $quiz->is_active) {
            abort(422, 'Este quiz no está disponible.');
        }

        // Verificar límite de intentos
        $attemptsCount = UserQuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->count();

        if ($quiz->max_attempts > 0 && $attemptsCount >= $quiz->max_attempts) {
            return response()->json([
                'message' => 'Has alcanzado el límite de intentos para este quiz.',
            ], 422);
        }

        // Crear intento
        $attempt = UserQuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'course_id' => $quiz->course_id,
            'module_id' => $quiz->module_id,
            'score' => 0,
            'total_questions' => $quiz->questions()->count(),
            'correct_answers' => 0,
            'time_spent' => 0,
            'status' => 'in_progress',
            'started_at' => Carbon::now(),
        ]);

        // Obtener preguntas (sin respuestas correctas)
        $questions = $quiz->questions()
            ->select('id', 'quiz_id', 'question', 'type', 'options', 'points', 'explanation')
            ->orderBy('sort_order')
            ->get();

        // Ocultar respuesta correcta
        foreach ($questions as $question) {
            unset($question->correct_answer);
        }

        return response()->json([
            'attempt' => $attempt,
            'questions' => $questions,
        ], 201);
    }

    /**
     * Enviar respuestas y finalizar intento de quiz.
     */
    public function submitAttempt(Request $request, UserQuizAttempt $attempt)
    {
        $user = Auth::user();

        // Verificar que el intento pertenezca al usuario
        if ($attempt->user_id !== $user->id) {
            abort(403, 'Este intento no te pertenece.');
        }

        // Verificar que el intento esté en progreso
        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'message' => 'Este intento ya fue completado.',
            ], 422);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'required',
            'time_spent' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($attempt, $validated, $user) {
            $quiz = $attempt->quiz;
            $questions = $quiz->questions()->get()->keyBy('id');

            $totalScore = 0;
            $correctCount = 0;
            $answersData = [];

            // Evaluar cada respuesta
            foreach ($validated['answers'] as $answerData) {
                $question = $questions[$answerData['question_id']] ?? null;
                if (! $question) {
                    continue;
                }

                $isCorrect = $this->checkAnswer($question, $answerData['answer']);
                $score = $isCorrect ? $question->points : 0;

                $answersData[] = [
                    'attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'user_answer' => json_encode($answerData['answer']),
                    'is_correct' => $isCorrect,
                    'points_earned' => $score,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                if ($isCorrect) {
                    $correctCount++;
                    $totalScore += $score;
                }
            }

            // Insertar respuestas
            UserAnswer::insert($answersData);

            // Calcular porcentaje de aciertos
            $percentage = $attempt->total_questions > 0
                ? ($correctCount / $attempt->total_questions) * 100
                : 0;

            // Actualizar intento
            $attempt->update([
                'score' => $totalScore,
                'correct_answers' => $correctCount,
                'percentage' => $percentage,
                'time_spent' => $validated['time_spent'],
                'status' => 'completed',
                'completed_at' => Carbon::now(),
            ]);

            // Otorgar puntos por completar quiz
            $quizPoints = intval($totalScore * 0.5); // 50% del puntaje como puntos
            if ($quizPoints > 0) {
                $user->total_points += $quizPoints;
                $user->save();

                DB::table('points_log')->insert([
                    'user_id' => $user->id,
                    'points' => $quizPoints,
                    'source' => 'quiz_attempt',
                    'source_id' => $attempt->id,
                    'description' => 'Puntos por completar quiz: '.$quiz->title,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            // Verificar si se otorga certificado (si el quiz está asociado a un curso con certificado)
            if ($quiz->course->has_certificate && $percentage >= $quiz->course->certificate_min_score) {
                // Lógica para otorgar certificado (se implementará en CertificateController)
                // Por ahora solo registramos en logs
                \Log::info("Usuario {$user->id} califica para certificado en curso {$quiz->course_id}");
            }
        });

        // Cargar detalles completos del intento
        $attempt->load([
            'quiz',
            'answers.question:id,question,type,correct_answer,explanation',
        ]);

        return response()->json($attempt);
    }

    /**
     * Obtener historial de intentos del usuario para un quiz.
     */
    public function attemptHistory(Request $request, Quiz $quiz)
    {
        $user = Auth::user();

        $attempts = UserQuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->orderBy('completed_at', 'desc')
            ->with(['answers.question'])
            ->paginate($request->get('per_page', 10));

        return response()->json($attempts);
    }

    /**
     * Obtener estadísticas de quizzes del usuario.
     */
    public function userStats(Request $request)
    {
        $user = Auth::user();

        $totalAttempts = UserQuizAttempt::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $avgScore = UserQuizAttempt::where('user_id', $user->id)
            ->where('status', 'completed')
            ->avg('percentage') ?? 0;

        $perfectQuizzes = UserQuizAttempt::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('percentage', 100)
            ->count();

        $recentAttempts = UserQuizAttempt::where('user_id', $user->id)
            ->where('status', 'completed')
            ->with(['quiz', 'course'])
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'total_attempts' => $totalAttempts,
            'average_score' => round($avgScore, 2),
            'perfect_quizzes' => $perfectQuizzes,
            'recent_attempts' => $recentAttempts,
        ]);
    }

    /**
     * Helper: verificar si la respuesta es correcta.
     */
    private function checkAnswer(Question $question, $userAnswer)
    {
        $correctAnswer = $question->correct_answer;

        switch ($question->type) {
            case 'multiple_choice':
            case 'true_false':
                return trim($userAnswer) === trim($correctAnswer);

            case 'multiple_select':
                $userArray = is_array($userAnswer) ? $userAnswer : json_decode($userAnswer, true);
                $correctArray = json_decode($correctAnswer, true);
                sort($userArray);
                sort($correctArray);

                return $userArray === $correctArray;

            case 'short_answer':
                $userAnswer = strtolower(trim($userAnswer));
                $correctAnswer = strtolower(trim($correctAnswer));

                // Permitir pequeñas variaciones
                return levenshtein($userAnswer, $correctAnswer) <= 2;

            default:
                return false;
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InteractiveActivityResult;
use App\Models\InteractiveConfig;

class AdminReportController extends Controller
{
    public function bottlenecks()
    {
        $rows = InteractiveConfig::query()
            ->with(['course:id,title,slug', 'module:id,title', 'lesson:id,title'])
            ->withCount([
                'activityResults as total_results',
                'activityResults as passed_results' => fn ($query) => $query->where('status', 'completed'),
                'activityResults as failed_results' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->withAvg('activityResults as average_attempts', 'attempts_used')
            ->withAvg('activityResults as average_score', 'score')
            ->get()
            ->map(function (InteractiveConfig $config) {
                $total = max(1, (int) $config->total_results);
                $passRate = round(((int) $config->passed_results / $total) * 100, 2);
                $failureRate = round(((int) $config->failed_results / $total) * 100, 2);

                return [
                    'interactive_config_id' => $config->id,
                    'course' => $config->course ? [
                        'id' => $config->course->id,
                        'title' => $config->course->title,
                        'slug' => $config->course->slug,
                    ] : null,
                    'module' => $config->module ? [
                        'id' => $config->module->id,
                        'title' => $config->module->title,
                    ] : null,
                    'lesson' => $config->lesson ? [
                        'id' => $config->lesson->id,
                        'title' => $config->lesson->title,
                    ] : null,
                    'activity_type' => $config->activity_type,
                    'max_attempts' => $config->max_attempts,
                    'xp_reward' => $config->xp_reward,
                    'coin_reward' => $config->coin_reward,
                    'total_results' => (int) $config->total_results,
                    'pass_rate' => $passRate,
                    'failure_rate' => $failureRate,
                    'average_attempts' => round((float) $config->average_attempts, 2),
                    'average_score' => round((float) $config->average_score, 2),
                    'severity' => $failureRate >= 70 || $passRate <= 30 ? 'critical' : ($failureRate >= 50 ? 'warning' : 'healthy'),
                ];
            })
            ->sortBy([
                ['severity', 'desc'],
                ['failure_rate', 'desc'],
                ['total_results', 'desc'],
            ])
            ->values();

        return response()->json($rows);
    }

    public function gamificationAudit()
    {
        $anomalies = InteractiveConfig::query()
            ->with(['course:id,title,slug', 'lesson:id,title'])
            ->get()
            ->map(function (InteractiveConfig $config) {
                $issues = [];
                $questionCount = count((array) data_get($config->config_payload, 'questions', []));

                if ($questionCount > 0 && $config->xp_reward > ($questionCount * 1000)) {
                    $issues[] = 'XP desproporcionado para la cantidad de preguntas.';
                }

                if ($config->max_attempts <= 1 && $config->passing_score >= 90) {
                    $issues[] = 'Actividad demasiado estricta para la cantidad de intentos.';
                }

                if ($config->coin_reward > $config->xp_reward && $config->coin_reward >= 500) {
                    $issues[] = 'La recompensa en monedas supera el equilibrio esperado.';
                }

                if (! $issues) {
                    return null;
                }

                return [
                    'interactive_config_id' => $config->id,
                    'course' => $config->course ? [
                        'id' => $config->course->id,
                        'title' => $config->course->title,
                        'slug' => $config->course->slug,
                    ] : null,
                    'lesson' => $config->lesson ? [
                        'id' => $config->lesson->id,
                        'title' => $config->lesson->title,
                    ] : null,
                    'activity_type' => $config->activity_type,
                    'max_attempts' => $config->max_attempts,
                    'passing_score' => $config->passing_score,
                    'xp_reward' => $config->xp_reward,
                    'coin_reward' => $config->coin_reward,
                    'issues' => $issues,
                ];
            })
            ->filter()
            ->values();

        $globalAttempts = InteractiveActivityResult::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'anomalies' => $anomalies,
            'attempt_distribution' => [
                'completed' => (int) ($globalAttempts['completed'] ?? 0),
                'failed' => (int) ($globalAttempts['failed'] ?? 0),
                'started' => (int) ($globalAttempts['started'] ?? 0),
            ],
        ]);
    }
}

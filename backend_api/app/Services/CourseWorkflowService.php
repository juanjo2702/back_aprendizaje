<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CourseWorkflowService
{
    public function transition(Course $course, User $actor, string $nextStatus, ?string $reviewNotes = null): Course
    {
        $allowedStatuses = $actor->isAdmin()
            ? ['draft', 'pending', 'published']
            : ['draft', 'pending'];

        if (! in_array($nextStatus, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ['El estado solicitado no está permitido para este rol.'],
            ]);
        }

        if (! $actor->isAdmin() && (int) $course->instructor_id !== (int) $actor->id) {
            throw new AuthorizationException('No puedes actualizar el estado de este curso.');
        }

        if ($nextStatus === 'published') {
            if (! $actor->isAdmin()) {
                throw new AuthorizationException('Solo el administrador puede publicar cursos.');
            }

            if (! in_array($course->status, ['draft', 'pending'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Solo se pueden publicar cursos en borrador o pendientes de revisión.'],
                ]);
            }
        }

        $previousStatus = $course->status;

        $course->status = $nextStatus;

        if ($reviewNotes !== null && $actor->isAdmin()) {
            $course->review_notes = trim($reviewNotes) !== '' ? trim($reviewNotes) : null;
        }

        if ($nextStatus === 'pending') {
            $course->submitted_for_review_at = now();
            $course->published_at = null;
            $course->approved_by = null;
        }

        if ($nextStatus === 'draft') {
            $course->published_at = null;

            if (! $actor->isAdmin()) {
                $course->approved_by = null;
            }
        }

        if ($nextStatus === 'published') {
            $course->submitted_for_review_at ??= now();
            $course->published_at = now();
            $course->approved_by = $actor->id;
        }

        $course->save();

        AdminActivityLog::record($actor, 'course.status_changed', $course, [
            'from' => $previousStatus,
            'to' => $nextStatus,
            'review_notes' => $reviewNotes,
        ]);

        return $course->fresh(['category:id,name,slug', 'instructor:id,name,email']);
    }
}

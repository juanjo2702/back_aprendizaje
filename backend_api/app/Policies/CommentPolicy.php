<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Course;
use App\Models\InteractiveConfig;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\LessonResource;
use App\Models\LessonVideo;
use App\Models\User;

class CommentPolicy
{
    public function create(User $user, mixed $commentable): bool
    {
        if ($user->isAdmin() || $user->isInstructor()) {
            return true;
        }

        if ($user->role !== 'student') {
            return false;
        }

        $course = $this->resolveCourse($commentable);

        if (! $course) {
            return false;
        }

        return $course->enrollments()->where('user_id', $user->id)->exists();
    }

    public function reply(User $user, Comment $comment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $course = $this->resolveCourse($comment->commentable);

        if (! $course) {
            return false;
        }

        if ($user->isInstructor()) {
            return (int) $course->instructor_id === (int) $user->id;
        }

        return $course->enrollments()->where('user_id', $user->id)->exists();
    }

    private function resolveCourse(mixed $commentable): ?Course
    {
        return match (true) {
            $commentable instanceof Course => $commentable,
            $commentable instanceof Lesson => $commentable->module?->course,
            $commentable instanceof LessonVideo => $commentable->lesson?->module?->course,
            $commentable instanceof LessonReading => $commentable->lesson?->module?->course,
            $commentable instanceof LessonResource => $commentable->lesson?->module?->course,
            $commentable instanceof InteractiveConfig => $commentable->course ?? $commentable->lesson?->module?->course,
            default => null,
        };
    }
}

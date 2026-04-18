<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\InteractiveConfig;
use App\Models\LessonReading;
use App\Models\LessonResource;
use App\Models\LessonVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'commentable_type' => 'required|string',
            'commentable_id' => 'required|integer',
        ]);

        $commentable = $this->resolveCommentable($validated['commentable_type'], (int) $validated['commentable_id']);
        $this->ensureCommentableAccess($request->user(), $commentable);

        $comments = Comment::query()
            ->whereNull('parent_id')
            ->where('commentable_type', $commentable->getMorphClass())
            ->where('commentable_id', $commentable->getKey())
            ->with([
                'user:id,name,avatar,role,total_points',
                'replies.user:id,name,avatar,role,total_points',
            ])
            ->latest()
            ->get()
            ->map(fn (Comment $comment) => $this->serializeComment($comment));

        return response()->json([
            'comment_target' => [
                'type' => $commentable->getMorphClass(),
                'id' => $commentable->getKey(),
            ],
            'comments' => $comments,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'commentable_type' => 'required|string',
            'commentable_id' => 'required|integer',
            'body' => 'required|string|min:2|max:2000',
            'is_question' => 'sometimes|boolean',
        ]);

        $commentable = $this->resolveCommentable($validated['commentable_type'], (int) $validated['commentable_id']);
        Gate::forUser($request->user())->authorize('create', [Comment::class, $commentable]);

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => $commentable->getMorphClass(),
            'commentable_id' => $commentable->getKey(),
            'body' => $validated['body'],
            'is_question' => (bool) ($validated['is_question'] ?? true),
        ]);

        $comment->load('user:id,name,avatar,role,total_points');

        return response()->json([
            'message' => 'Comentario publicado correctamente.',
            'comment' => $this->serializeComment($comment),
        ], 201);
    }

    public function reply(Request $request, Comment $comment)
    {
        $validated = $request->validate([
            'body' => 'required|string|min:2|max:2000',
        ]);

        Gate::forUser($request->user())->authorize('reply', $comment);

        $reply = Comment::create([
            'user_id' => $request->user()->id,
            'parent_id' => $comment->id,
            'commentable_type' => $comment->commentable_type,
            'commentable_id' => $comment->commentable_id,
            'body' => $validated['body'],
            'is_question' => false,
        ]);

        if ($request->user()->isInstructor() || $request->user()->isAdmin()) {
            $comment->forceFill(['resolved_at' => now()])->save();
        }

        $reply->load('user:id,name,avatar,role,total_points');
        $comment->load([
            'user:id,name,avatar,role,total_points',
            'replies.user:id,name,avatar,role,total_points',
        ]);

        return response()->json([
            'message' => 'Respuesta enviada.',
            'comment' => $this->serializeComment($comment->fresh(['user:id,name,avatar,role,total_points', 'replies.user:id,name,avatar,role,total_points'])),
            'reply' => $this->serializeComment($reply),
        ]);
    }

    private function resolveCommentable(string $type, int $id): mixed
    {
        $modelClass = match ($type) {
            'lesson_video' => LessonVideo::class,
            'lesson_reading' => LessonReading::class,
            'lesson_resource' => LessonResource::class,
            'interactive_config' => InteractiveConfig::class,
            default => abort(422, 'Tipo de comentario no soportado.'),
        };

        return $modelClass::query()->findOrFail($id);
    }

    private function ensureCommentableAccess($user, mixed $commentable): void
    {
        $course = match (true) {
            $commentable instanceof LessonVideo => $commentable->lesson?->module?->course,
            $commentable instanceof LessonReading => $commentable->lesson?->module?->course,
            $commentable instanceof LessonResource => $commentable->lesson?->module?->course,
            $commentable instanceof InteractiveConfig => $commentable->course ?? $commentable->lesson?->module?->course,
            default => null,
        };

        if (! $course) {
            abort(404, 'No se encontró el curso relacionado.');
        }

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isInstructor() && (int) $course->instructor_id === (int) $user->id) {
            return;
        }

        if ($course->enrollments()->where('user_id', $user->id)->exists()) {
            return;
        }

        abort(403, 'No tienes permiso para acceder a estos comentarios.');
    }

    private function serializeComment(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'is_question' => (bool) $comment->is_question,
            'is_resolved' => (bool) $comment->resolved_at,
            'resolved_at' => $comment->resolved_at,
            'created_at' => $comment->created_at,
            'author' => [
                'id' => $comment->user?->id,
                'name' => $comment->user?->name,
                'avatar' => $comment->user?->avatar,
                'role' => $comment->user?->role,
                'level' => $comment->user?->current_level,
                'level_title' => $comment->user?->level_title,
            ],
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn (Comment $reply) => $this->serializeComment($reply))->values()->all()
                : [],
        ];
    }
}

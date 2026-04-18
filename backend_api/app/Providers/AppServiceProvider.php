<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Category;
use App\Models\Comment;
use App\Models\InteractiveConfig;
use App\Models\LessonReading;
use App\Models\LessonResource;
use App\Models\LessonVideo;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\CommentPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);

        Relation::enforceMorphMap([
            'user' => User::class,
            'lesson_video' => LessonVideo::class,
            'lesson_reading' => LessonReading::class,
            'lesson_resource' => LessonResource::class,
            'interactive_config' => InteractiveConfig::class,
        ]);
    }
}

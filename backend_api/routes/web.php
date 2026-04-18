<?php

use App\Http\Controllers\Media\ProtectedMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/media/{media}/{filename}', [ProtectedMediaController::class, 'show'])
    ->where('filename', '.*')
    ->name('protected-media.show');

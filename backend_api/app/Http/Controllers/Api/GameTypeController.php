<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameType;

class GameTypeController extends Controller
{
    public function index()
    {
        return response()->json(
            GameType::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'default_config'])
        );
    }
}

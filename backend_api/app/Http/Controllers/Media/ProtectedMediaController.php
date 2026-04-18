<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProtectedMediaController extends Controller
{
    public function show(Request $request, Media $media, string $filename)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'El enlace del archivo ya expiró o es inválido.');
        }

        if ($filename !== $media->file_name) {
            abort(404);
        }

        $path = $media->getPath();
        if (! is_file($path)) {
            abort(404, 'No se encontró el archivo solicitado.');
        }

        return response()->file($path, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

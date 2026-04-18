<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeacherMediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeacherVideoUploadController extends Controller
{
    public function __construct(
        private readonly TeacherMediaUploadService $uploadService
    ) {
    }

    public function storeVideo(Request $request)
    {
        return $this->handleChunkUpload($request, 'video', [
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'application/vnd.apple.mpegurl',
        ], 1024 * 1024 * 1024);
    }

    public function storeResource(Request $request)
    {
        return $this->handleChunkUpload($request, 'resource', [
            'application/pdf',
            'application/zip',
            'application/x-zip-compressed',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], 512 * 1024 * 1024);
    }

    private function handleChunkUpload(Request $request, string $type, array $allowedMimeTypes, int $maxSize): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'upload_id' => 'nullable|string|max:100',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1|max:500',
            'file_name' => 'required|string|max:255',
            'mime_type' => 'nullable|string|max:150',
            'chunk' => 'required|file|max:102400',
        ]);

        $mimeType = $validated['mime_type'] ?? $request->file('chunk')->getMimeType();
        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            return response()->json([
                'message' => 'Formato no permitido para este tipo de archivo.',
            ], 422);
        }

        if ((int) $request->file('chunk')->getSize() > $maxSize) {
            return response()->json([
                'message' => 'El chunk recibido supera el tamaño permitido.',
            ], 422);
        }

        $payload = $this->uploadService->storeChunk(
            $type,
            $validated['upload_id'] ?: (string) Str::uuid(),
            (int) $validated['chunk_index'],
            (int) $validated['total_chunks'],
            $request->file('chunk'),
            $validated['file_name'],
            $mimeType
        );

        return response()->json($payload);
    }
}

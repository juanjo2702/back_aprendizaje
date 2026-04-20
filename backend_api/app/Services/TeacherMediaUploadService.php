<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TeacherMediaUploadService
{
    private const BASE_DIRECTORY = 'teacher-uploads';

    public function storeChunk(
        string $type,
        string $uploadId,
        int $chunkIndex,
        int $totalChunks,
        UploadedFile $chunk,
        string $originalName,
        ?string $mimeType = null,
        ?int $fileSize = null
    ): array {
        $disk = Storage::disk('local');
        $safeType = $type === 'resource' ? 'resource' : 'video';
        $chunkDirectory = $this->chunkDirectory($safeType, $uploadId);

        if ($chunkIndex === 0) {
            $disk->deleteDirectory($chunkDirectory);
            $disk->deleteDirectory($this->finalDirectory($safeType, $uploadId));
        }

        $disk->makeDirectory($chunkDirectory);

        $chunkPath = "{$chunkDirectory}/chunk-{$chunkIndex}.part";
        $stream = fopen($chunk->getRealPath(), 'rb');
        $disk->put($chunkPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $storedChunks = collect($disk->files($chunkDirectory))
            ->filter(fn (string $path) => str_ends_with($path, '.part'))
            ->count();

        if ($storedChunks < $totalChunks) {
            return [
                'status' => 'chunk_received',
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'received_chunks' => $storedChunks,
                'total_chunks' => $totalChunks,
                'progress' => round(($storedChunks / max(1, $totalChunks)) * 100, 2),
            ];
        }

        $finalDirectory = $this->finalDirectory($safeType, $uploadId);
        $disk->makeDirectory($finalDirectory);
        $sanitizedName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'archivo';
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileName = $extension ? "{$sanitizedName}.{$extension}" : $sanitizedName;
        $finalPath = "{$finalDirectory}/{$fileName}";

        $absolutePath = $disk->path($finalPath);
        $target = fopen($absolutePath, 'wb');

        if (! $target) {
            throw new RuntimeException('No se pudo inicializar el archivo ensamblado.');
        }

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $path = "{$chunkDirectory}/chunk-{$index}.part";
                if (! $disk->exists($path)) {
                    throw new RuntimeException("Falta el chunk {$index} del archivo.");
                }

                $source = fopen($disk->path($path), 'rb');
                stream_copy_to_stream($source, $target);
                fclose($source);
            }
        } finally {
            fclose($target);
        }

        $size = filesize($absolutePath) ?: 0;
        $manifest = [
            'token' => $uploadId,
            'type' => $safeType,
            'file_name' => $fileName,
            'original_name' => $originalName,
            'mime_type' => $mimeType ?: mime_content_type($absolutePath),
            'path' => $finalPath,
            'size' => $size,
            'expected_size' => $fileSize,
            'created_at' => now()->toIso8601String(),
        ];

        $disk->put("{$finalDirectory}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $disk->deleteDirectory($chunkDirectory);

        return [
            'status' => 'completed',
            'upload_id' => $uploadId,
            'upload_token' => $uploadId,
            'progress' => 100,
            'file' => $manifest,
        ];
    }

    public function consumeUpload(string $type, string $token): array
    {
        $disk = Storage::disk('local');
        $safeType = $type === 'resource' ? 'resource' : 'video';
        $manifestPath = $this->manifestPath($safeType, $token);

        if (! $disk->exists($manifestPath)) {
            throw new RuntimeException('No se encontró el archivo temporal para adjuntar.');
        }

        $manifest = json_decode($disk->get($manifestPath), true);

        if (! is_array($manifest) || empty($manifest['path']) || ! $disk->exists($manifest['path'])) {
            throw new RuntimeException('El archivo temporal está corrupto o ya no existe.');
        }

        return $manifest;
    }

    public function cleanupUpload(string $type, string $token): void
    {
        Storage::disk('local')->deleteDirectory($this->finalDirectory($type, $token));
    }

    private function chunkDirectory(string $type, string $uploadId): string
    {
        return self::BASE_DIRECTORY."/chunks/{$type}/{$uploadId}";
    }

    private function finalDirectory(string $type, string $uploadId): string
    {
        return self::BASE_DIRECTORY."/completed/{$type}/{$uploadId}";
    }

    private function manifestPath(string $type, string $token): string
    {
        return $this->finalDirectory($type, $token).'/manifest.json';
    }
}

<?php

namespace App\Services\ExpoLens;

use App\Models\File;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FaceService
{
    /** @return array{face: array, model: string, dimensions: int} */
    public function embed(File $file): array
    {
        return $this->request()
            ->attach('file', Storage::disk($file->disk)->get($file->path), $file->filename)
            ->post($this->url('/embed'))
            ->throw()
            ->json();
    }

    /** @return array{faces: list<array>, model: string, dimensions: int} */
    public function detectAndEmbed(File $file): array
    {
        return $this->request()
            ->attach('file', Storage::disk($file->disk)->get($file->path), $file->filename)
            ->post($this->url('/detect-and-embed'))
            ->throw()
            ->json();
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['X-Service-Token' => (string) config('services.expolens.face_service_token')])
            ->connectTimeout(10)
            ->timeout(180)
            ->retry(2, 500);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.expolens.face_service_url'), '/').$path;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaAssetController extends Controller
{
    public function show(Request $request, Media $media): BinaryFileResponse
    {
        abort_unless(File::exists($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Access-Control-Allow-Origin' => $this->allowedOrigin($request),
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With',
            'Cache-Control' => 'public, max-age=31536000',
            'Content-Type' => $media->mime_type ?: File::mimeType($media->getPath()) ?: 'application/octet-stream',
            'Vary' => 'Origin',
        ]);
    }

    private function allowedOrigin(Request $request): string
    {
        $origin = (string) $request->headers->get('Origin', '');
        $allowedOrigins = config('cors.allowed_origins', []);

        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }

        return $allowedOrigins[0] ?? '*';
    }
}

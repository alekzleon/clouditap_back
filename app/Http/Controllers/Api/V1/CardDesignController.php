<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCardDesignRequest;
use App\Http\Requests\Api\V1\UploadCardDesignAssetRequest;
use App\Http\Requests\Api\V1\UploadCardPrintFilesRequest;
use App\Models\Card;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CardDesignController extends Controller
{
    use ApiResponse;

    public function show(Card $card): JsonResponse
    {
        $this->authorize('view', $card);

        return $this->success([
            'card' => [
                'id' => $card->id,
                'name' => $card->name,
                'slug' => $card->slug,
                'color' => $card->color,
                'status' => $card->status->value,
            ],
            'card_id' => $card->id,
            'design_data' => $card->design_data,
            'media' => $card->mediaSummary(),
        ], 'Diseño obtenido correctamente.');
    }

    public function update(UpdateCardDesignRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        if (! $card->isEditable()) {
            return $this->error('Esta tarjeta ya fue enviada a impresión y su diseño no puede editarse.', null, 403);
        }

        $fromStatus = $card->status;

        $card->fill([
            'design_data' => $request->validated('design_data'),
        ]);

        if ($card->status === CardStatus::Draft) {
            $card->status = CardStatus::Designing;
        }

        $card->save();

        if ($card->status !== $fromStatus) {
            $card->statusLogs()->create([
                'user_id' => $request->user()->id,
                'from_status' => $fromStatus->value,
                'to_status' => $card->status->value,
                'note' => 'El cliente inició el diseño de la tarjeta.',
            ]);
        }

        return $this->success([
            'card' => $card->refresh(),
            'media' => $card->mediaSummary(),
        ], 'Diseño guardado correctamente.');
    }

    public function uploadAsset(UploadCardDesignAssetRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        if (! $card->isEditable()) {
            return $this->error('Esta tarjeta ya fue enviada a impresión y no permite subir más archivos de diseño.', null, 403);
        }

        $validated = $request->validated();
        $media = $card
            ->addMediaFromRequest('file')
            ->toMediaCollection($validated['type']);

        return $this->success([
            'media' => $card->mediaPayload($media),
            'card_media' => $card->mediaSummary(),
        ], 'Archivo de diseño subido correctamente.', 201);
    }

    public function uploadPrintFiles(UploadCardPrintFilesRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        if (! $card->isEditable()) {
            return $this->error('Esta tarjeta ya fue enviada a impresión y no permite cambiar archivos de impresión.', null, 403);
        }

        $frontMedia = $this->replacePrintFile($card, 'front', $request->file('front_file'));
        $backMedia = $this->replacePrintFile($card, 'back', $request->file('back_file'));
        $card->unsetRelation('media');

        return $this->success([
            'front_file' => $card->mediaPayload($frontMedia),
            'back_file' => $card->mediaPayload($backMedia),
            'card_media' => $card->mediaSummary(),
        ], 'Archivos para impresión subidos correctamente.', 201);
    }

    private function replacePrintFile(Card $card, string $side, UploadedFile $file): Media
    {
        $media = $this->latestPrintFileForSide($card, $side);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
        $fileName = "{$side}.{$extension}";

        if (! $media) {
            $media = $card
                ->addMedia($file)
                ->usingName("{$side}-print-file")
                ->usingFileName($fileName)
                ->withCustomProperties(['side' => $side])
                ->toMediaCollection(Card::MEDIA_PRINT_FILES);

            $this->deleteDuplicatedPrintFiles($card, $side, $media);

            return $media;
        }

        $directory = dirname($media->getPath());
        File::ensureDirectoryExists($directory);

        if (File::exists($media->getPath())) {
            File::delete($media->getPath());
        }

        $file->move($directory, $fileName);
        $path = $directory.DIRECTORY_SEPARATOR.$fileName;

        $media->forceFill([
            'name' => "{$side}-print-file",
            'file_name' => $fileName,
            'mime_type' => File::mimeType($path) ?: $file->getMimeType(),
            'size' => File::size($path),
            'custom_properties' => [
                ...$media->custom_properties,
                'side' => $side,
            ],
        ])->save();

        $this->deleteDuplicatedPrintFiles($card, $side, $media);

        return $media->refresh();
    }

    private function latestPrintFileForSide(Card $card, string $side): ?Media
    {
        $needle = $side === 'front' ? 'front' : 'back';

        return $card->getMedia(Card::MEDIA_PRINT_FILES)
            ->sortByDesc('id')
            ->first(fn (Media $media) => $media->getCustomProperty('side') === $side)
            ?? $card->getMedia(Card::MEDIA_PRINT_FILES)
                ->sortByDesc('id')
                ->first(fn (Media $media) => Str::contains(Str::lower($media->name.' '.$media->file_name), $needle));
    }

    private function deleteDuplicatedPrintFiles(Card $card, string $side, Media $currentMedia): void
    {
        $needle = $side === 'front' ? 'front' : 'back';

        $card->getMedia(Card::MEDIA_PRINT_FILES)
            ->filter(fn (Media $media) => $media->id !== $currentMedia->id)
            ->filter(fn (Media $media) => $media->getCustomProperty('side') === $side
                || Str::contains(Str::lower($media->name.' '.$media->file_name), $needle))
            ->each(fn (Media $media) => $media->delete());
    }
}

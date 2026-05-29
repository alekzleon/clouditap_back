<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TapCardDesign;
use App\Models\TapFaq;
use App\Models\TapHeroSetting;
use App\Models\TapPricingPlan;
use App\Models\TapReview;
use App\Models\TapSetting;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CloudiTapLandingController extends Controller
{
    use ApiResponse;

    public function public(): JsonResponse
    {
        return $this->success([
            'hero' => TapHeroSetting::query()->where('is_active', true)->latest()->first()?->payload(),
            'designs' => TapCardDesign::query()->where('is_active', true)->orderBy('sort_order')->get()->map->payload()->values(),
            'pricing' => TapPricingPlan::query()->where('is_active', true)->orderBy('sort_order')->get()->values(),
            'reviews' => TapReview::query()->where('is_active', true)->orderBy('sort_order')->get()->map->payload()->values(),
            'faqs' => TapFaq::query()->where('is_active', true)->orderBy('sort_order')->get()->values(),
            'settings' => TapSetting::query()->first()?->payload(),
        ], 'Landing CloudiTap obtenida correctamente.');
    }

    public function admin(): JsonResponse
    {
        return $this->success([
            'hero' => $this->hero()->payload(),
            'heroes' => TapHeroSetting::query()->latest()->get()->map->payload()->values(),
            'designs' => TapCardDesign::query()->orderBy('sort_order')->get()->map->payload()->values(),
            'pricing' => TapPricingPlan::query()->orderBy('sort_order')->get()->values(),
            'reviews' => TapReview::query()->orderBy('sort_order')->get()->map->payload()->values(),
            'faqs' => TapFaq::query()->orderBy('sort_order')->get()->values(),
            'settings' => $this->settings()->payload(),
        ], 'Administración CloudiTap obtenida correctamente.');
    }

    public function storeHero(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->heroRules(requireMedia: true));
        $validator->after(fn ($validator) => $this->validateHeroPayload($validator, $request));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $data = $validator->validated();
        $data['media_path'] = $request->file('media')->store('clouditap/hero', 'public');

        if ($request->hasFile('mobile_media')) {
            $data['mobile_media_path'] = $request->file('mobile_media')->store('clouditap/hero', 'public');
        }

        unset($data['media'], $data['mobile_media']);

        return $this->success(TapHeroSetting::create($data)->payload(), 'Banner creado correctamente.', 201);
    }

    public function updateHero(Request $request, ?TapHeroSetting $hero = null): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->heroRules(requireMedia: false));
        $validator->after(fn ($validator) => $this->validateHeroPayload($validator, $request));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $hero ??= $this->hero();
        $data = $validator->validated();

        if ($request->hasFile('media')) {
            $this->deletePublicFile($hero->media_path);
            $data['media_path'] = $request->file('media')->store('clouditap/hero', 'public');
        }

        if ($request->hasFile('mobile_media')) {
            $this->deletePublicFile($hero->mobile_media_path);
            $data['mobile_media_path'] = $request->file('mobile_media')->store('clouditap/hero', 'public');
        }

        unset($data['media'], $data['mobile_media']);
        $hero->update($data);

        return $this->success($hero->refresh()->payload(), 'Banner actualizado correctamente.');
    }

    public function destroyHero(TapHeroSetting $hero): JsonResponse
    {
        $this->deletePublicFile($hero->media_path);
        $this->deletePublicFile($hero->mobile_media_path);
        $hero->delete();

        return $this->success(null, 'Banner eliminado correctamente.');
    }

    public function storeDesign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->designRules(requiredImage: true));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $data = $validator->validated();
        $data['slug'] = $this->uniqueSlug(TapCardDesign::class, $data['name']);
        $data['image_path'] = $request->file('image')->store('clouditap/designs', 'public');
        unset($data['image']);

        $design = TapCardDesign::create($data);

        return $this->success($design->payload(), 'Diseño creado correctamente.', 201);
    }

    public function updateDesign(Request $request, TapCardDesign $design): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->designRules(requiredImage: false));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $data = $validator->validated();

        if ($request->filled('name') && $request->input('name') !== $design->name) {
            $data['slug'] = $this->uniqueSlug(TapCardDesign::class, $request->input('name'), $design->id);
        }

        if ($request->hasFile('image')) {
            $this->deletePublicFile($design->image_path);
            $data['image_path'] = $request->file('image')->store('clouditap/designs', 'public');
        }

        unset($data['image']);
        $design->update($data);

        return $this->success($design->refresh()->payload(), 'Diseño actualizado correctamente.');
    }

    public function destroyDesign(TapCardDesign $design): JsonResponse
    {
        $this->deletePublicFile($design->image_path);
        $design->delete();

        return $this->success(null, 'Diseño eliminado correctamente.');
    }

    public function storePricing(Request $request): JsonResponse
    {
        return $this->storeSimple($request, new TapPricingPlan, $this->pricingRules(), 'Plan creado correctamente.');
    }

    public function updatePricing(Request $request, TapPricingPlan $plan): JsonResponse
    {
        return $this->updateSimple($request, $plan, $this->pricingRules(), 'Plan actualizado correctamente.');
    }

    public function destroyPricing(TapPricingPlan $plan): JsonResponse
    {
        $plan->delete();

        return $this->success(null, 'Plan eliminado correctamente.');
    }

    public function storeReview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->reviewRules(requiredPhoto: false));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $data = $validator->validated();
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('clouditap/reviews', 'public');
        }
        unset($data['photo']);

        return $this->success(TapReview::create($data)->payload(), 'Reseña creada correctamente.', 201);
    }

    public function updateReview(Request $request, TapReview $review): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->reviewRules(requiredPhoto: false));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $data = $validator->validated();
        if ($request->hasFile('photo')) {
            $this->deletePublicFile($review->photo_path);
            $data['photo_path'] = $request->file('photo')->store('clouditap/reviews', 'public');
        }
        unset($data['photo']);
        $review->update($data);

        return $this->success($review->refresh()->payload(), 'Reseña actualizada correctamente.');
    }

    public function destroyReview(TapReview $review): JsonResponse
    {
        $this->deletePublicFile($review->photo_path);
        $review->delete();

        return $this->success(null, 'Reseña eliminada correctamente.');
    }

    public function storeFaq(Request $request): JsonResponse
    {
        return $this->storeSimple($request, new TapFaq, $this->faqRules(), 'Pregunta creada correctamente.');
    }

    public function updateFaq(Request $request, TapFaq $faq): JsonResponse
    {
        return $this->updateSimple($request, $faq, $this->faqRules(), 'Pregunta actualizada correctamente.');
    }

    public function destroyFaq(TapFaq $faq): JsonResponse
    {
        $faq->delete();

        return $this->success(null, 'Pregunta eliminada correctamente.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'footer_text' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'instagram_url' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'string', 'max:255'],
            'tiktok_url' => ['nullable', 'string', 'max:255'],
            'privacy_url' => ['nullable', 'string', 'max:255'],
            'terms_url' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            foreach (['instagram_url', 'facebook_url', 'tiktok_url', 'privacy_url', 'terms_url'] as $field) {
                $this->validateUrlOrPath($validator, $request->input($field), $field);
            }
        });

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $settings = $this->settings();
        $data = $validator->validated();
        if ($request->hasFile('logo')) {
            $this->deletePublicFile($settings->logo_path);
            $data['logo_path'] = $request->file('logo')->store('clouditap/settings', 'public');
        }

        unset($data['logo']);
        $settings->update($data);

        return $this->success($settings->refresh()->payload(), 'Configuración actualizada correctamente.');
    }

    private function hero(): TapHeroSetting
    {
        return TapHeroSetting::firstOrCreate([], [
            'title' => 'Tu tarjeta digital, siempre lista para conectar.',
            'subtitle' => 'Comparte tu perfil, redes, enlaces y contacto con un solo toque usando CloudiTap.',
            'media_type' => 'image',
            'button_one_text' => 'Quiero mi CloudiTap',
            'button_one_url' => '/register',
            'button_one_active' => true,
            'button_two_text' => 'Ver diseños',
            'button_two_url' => '#designs',
            'button_two_active' => true,
            'is_active' => true,
        ]);
    }

    private function settings(): TapSetting
    {
        return TapSetting::firstOrCreate([], [
            'footer_text' => 'Tarjetas digitales NFC para conectar con clientes, contactos y oportunidades en segundos.',
            'whatsapp' => '525555555555',
            'privacy_url' => '/privacy',
            'terms_url' => '/terms',
            'meta_title' => 'CloudiTap',
            'meta_description' => 'Tarjetas digitales NFC conectadas a perfiles públicos personalizados.',
        ]);
    }

    private function heroRules(bool $requireMedia): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string'],
            'media_type' => ['required', Rule::in(['image', 'video'])],
            'media' => [$requireMedia ? 'required' : 'nullable', 'file'],
            'mobile_media' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'button_one_text' => ['nullable', 'string', 'max:255'],
            'button_one_url' => ['nullable', 'string', 'max:255'],
            'button_one_active' => ['sometimes', 'boolean'],
            'button_two_text' => ['nullable', 'string', 'max:255'],
            'button_two_url' => ['nullable', 'string', 'max:255'],
            'button_two_active' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function validateHeroPayload($validator, Request $request): void
    {
        $this->validateUrlOrPath($validator, $request->input('button_one_url'), 'button_one_url');
        $this->validateUrlOrPath($validator, $request->input('button_two_url'), 'button_two_url');
        $this->validateActiveButton($validator, $request, 'button_one');
        $this->validateActiveButton($validator, $request, 'button_two');

        if (! $request->hasFile('media')) {
            return;
        }

        $file = $request->file('media');
        $allowed = $request->input('media_type') === 'video'
            ? ['mp4']
            : ['jpg', 'jpeg', 'png', 'webp'];

        if (! in_array(strtolower($file->getClientOriginalExtension()), $allowed, true)) {
            $validator->errors()->add('media', 'El archivo de fondo no tiene el formato permitido.');
        }
    }

    private function designRules(bool $requiredImage): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => [$requiredImage ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'category' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function pricingRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price_label' => ['required', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function reviewRules(bool $requiredPhoto): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'business' => ['nullable', 'string', 'max:255'],
            'comment' => ['required', 'string'],
            'photo' => [$requiredPhoto ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function faqRules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function storeSimple(Request $request, Model $model, array $rules, string $message): JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);
        $validator->after(fn ($validator) => $this->validateButtonUrlIfPresent($validator, $request));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $model = $model::create($validator->validated());

        return $this->success($model, $message, 201);
    }

    private function updateSimple(Request $request, Model $model, array $rules, string $message): JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);
        $validator->after(fn ($validator) => $this->validateButtonUrlIfPresent($validator, $request));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $model->update($validator->validated());

        return $this->success($model->refresh(), $message);
    }

    private function validateButtonUrlIfPresent($validator, Request $request): void
    {
        if ($request->has('button_url')) {
            $this->validateUrlOrPath($validator, $request->input('button_url'), 'button_url');
        }
    }

    private function validateActiveButton($validator, Request $request, string $prefix): void
    {
        if (! filter_var($request->boolean("{$prefix}_active"), FILTER_VALIDATE_BOOL)) {
            return;
        }

        if (! $request->filled("{$prefix}_text")) {
            $validator->errors()->add("{$prefix}_text", 'El texto del botón activo es obligatorio.');
        }

        if (! $request->filled("{$prefix}_url")) {
            $validator->errors()->add("{$prefix}_url", 'El link del botón activo es obligatorio.');
        }
    }

    private function validateUrlOrPath($validator, ?string $value, string $field): void
    {
        if (! $value) {
            return;
        }

        $isInternal = str_starts_with($value, '/') || str_starts_with($value, '#');
        $isUrl = filter_var($value, FILTER_VALIDATE_URL);

        if (! $isInternal && ! $isUrl) {
            $validator->errors()->add($field, 'Debe ser una URL válida o una ruta interna.');
        }
    }

    private function uniqueSlug(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $index = 2;

        while ($modelClass::where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }

    private function deletePublicFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}

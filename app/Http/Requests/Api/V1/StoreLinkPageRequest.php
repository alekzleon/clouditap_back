<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Plan;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreLinkPageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $plan = $this->user()?->plan instanceof Plan ? $this->user()->plan : Plan::Basic;

        return [
            'card_id' => ['nullable', 'integer', 'exists:cards,id', Rule::unique('link_pages', 'card_id')],
            'profile' => ['required', 'array'],
            'profile.name' => ['required', 'string', 'max:255'],
            'profile.subtitle' => ['nullable', 'string', 'max:255'],
            'profile.cover_position' => ['nullable', Rule::in(['center', 'top', 'bottom', 'left center', 'right center'])],
            'profile.footer' => ['nullable', 'string', 'max:255'],
            'theme' => ['nullable', 'array'],
            'theme.background' => ['nullable', 'hex_color'],
            'theme.card' => ['nullable', 'hex_color'],
            'theme.card_soft' => ['nullable', 'hex_color'],
            'theme.text' => ['nullable', 'hex_color'],
            'theme.muted' => ['nullable', 'hex_color'],
            'theme.primary' => ['nullable', 'hex_color'],
            'theme.radius' => ['nullable', 'integer', 'min:12', 'max:30'],
            'theme.social_style' => ['nullable', Rule::in(['lineal', 'solid', 'color'])],
            'socials' => ['nullable', 'array'],
            'socials.*.id' => ['nullable', 'string', 'max:80'],
            'socials.*.label' => ['nullable', 'string', 'max:80'],
            'socials.*.url' => ['nullable', 'url', 'max:255'],
            'socials.*.active' => ['nullable', 'boolean'],
            'socials.*.position' => ['nullable', 'integer', 'min:1'],
            'links' => ['nullable', 'array'],
            'links.*.id' => ['nullable'],
            'links.*.title' => ['nullable', 'string', 'max:120'],
            'links.*.subtitle' => ['nullable', 'string', 'max:180'],
            'links.*.url' => ['nullable', 'url', 'max:255'],
            'links.*.icon' => ['nullable', Rule::in(['agency', 'shop', 'user', 'web', 'chat', 'link', 'qr'])],
            'links.*.active' => ['nullable', 'boolean'],
            'links.*.accent' => ['nullable', 'boolean'],
            'links.*.position' => ['nullable', 'integer', 'min:1'],
            'banners' => ['nullable', 'array', 'max:'.$plan->bannerLimit()],
            'banners.*.id' => ['nullable'],
            'banners.*.badge' => ['nullable', 'string', 'max:40'],
            'banners.*.title' => ['nullable', 'string', 'max:120'],
            'banners.*.description' => ['nullable', 'string', 'max:255'],
            'banners.*.url' => ['nullable', 'url', 'max:255'],
            'banners.*.active' => ['nullable', 'boolean'],
            'banners.*.position' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new UpdateLinkPageRequest)->messages() + [
            'card_id.exists' => 'La tarjeta seleccionada no existe.',
            'card_id.unique' => 'Esta tarjeta ya tiene un diseño asignado.',
        ];
    }
}

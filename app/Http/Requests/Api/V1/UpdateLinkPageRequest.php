<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Plan;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateLinkPageRequest extends ApiFormRequest
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
        $linkPageId = $this->route('linkPage')?->id ?? $this->user()?->linkPages()->oldest()->value('id');
        $plan = $this->user()?->plan instanceof Plan ? $this->user()->plan : Plan::Basic;

        return [
            'card_id' => ['nullable', 'integer', 'exists:cards,id', Rule::unique('link_pages', 'card_id')->ignore($linkPageId)],
            'profile' => ['required', 'array'],
            'profile.name' => ['required', 'string', 'max:255'],
            'profile.subtitle' => ['nullable', 'string', 'max:255'],
            'profile.cover_position' => ['required', Rule::in(['center', 'top', 'bottom', 'left center', 'right center'])],
            'profile.footer' => ['nullable', 'string', 'max:255'],
            'theme' => ['required', 'array'],
            'theme.background' => ['required', 'hex_color'],
            'theme.card' => ['required', 'hex_color'],
            'theme.card_soft' => ['required', 'hex_color'],
            'theme.text' => ['required', 'hex_color'],
            'theme.muted' => ['required', 'hex_color'],
            'theme.primary' => ['required', 'hex_color'],
            'theme.radius' => ['required', 'integer', 'min:12', 'max:30'],
            'theme.social_style' => ['required', Rule::in(['lineal', 'solid', 'color'])],
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
        return [
            'card_id.exists' => 'La tarjeta seleccionada no existe.',
            'card_id.unique' => 'Esta tarjeta ya tiene un diseño asignado.',
            'profile.name.required' => 'El nombre del perfil es obligatorio.',
            'profile.cover_position.in' => 'La posición de la portada no es válida.',
            'theme.background.hex_color' => 'El color de fondo no es válido.',
            'theme.card.hex_color' => 'El color de tarjeta no es válido.',
            'theme.card_soft.hex_color' => 'El color suave de tarjeta no es válido.',
            'theme.text.hex_color' => 'El color de texto no es válido.',
            'theme.muted.hex_color' => 'El color secundario no es válido.',
            'theme.primary.hex_color' => 'El color principal no es válido.',
            'theme.radius.min' => 'El radio debe ser de al menos :min.',
            'theme.radius.max' => 'El radio no puede ser mayor a :max.',
            'theme.social_style.in' => 'El estilo social no es válido.',
            'socials.*.id.string' => 'El identificador social debe ser texto.',
            'socials.*.label.string' => 'La etiqueta social debe ser texto.',
            'socials.*.url.url' => 'La URL social debe ser válida.',
            'socials.*.active.boolean' => 'El estado del social debe ser verdadero o falso.',
            'socials.*.position.integer' => 'La posición del social debe ser un número entero.',
            'links.*.title.string' => 'El título del link debe ser texto.',
            'links.*.url.url' => 'La URL del link debe ser válida.',
            'links.*.icon.in' => 'El icono del link no es válido.',
            'links.*.active.boolean' => 'El estado del link debe ser verdadero o falso.',
            'links.*.accent.boolean' => 'El acento del link debe ser verdadero o falso.',
            'links.*.position.integer' => 'La posición del link debe ser un número entero.',
            'banners.max' => 'Tu plan permite un máximo de :max banners.',
            'banners.*.title.string' => 'El título del banner debe ser texto.',
            'banners.*.url.url' => 'La URL del banner debe ser válida.',
            'banners.*.active.boolean' => 'El estado del banner debe ser verdadero o falso.',
            'banners.*.position.integer' => 'La posición del banner debe ser un número entero.',
        ];
    }
}

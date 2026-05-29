<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TransactionalEmailService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        return $this->success([
            'profile' => $this->profilePayload($request->user()),
        ], 'Perfil obtenido correctamente.');
    }

    public function updateName(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede tener más de :max caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $request->user()->update($validator->validated());

        return $this->success([
            'profile' => $this->profilePayload($request->user()->refresh()),
        ], 'Nombre actualizado correctamente.');
    }

    public function updatePublicSlug(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:80',
                'alpha_dash:ascii',
                Rule::unique('users', 'slug')->ignore($request->user()->id),
            ],
        ], [
            'slug.required' => 'El slug público es obligatorio.',
            'slug.alpha_dash' => 'El slug público solo puede contener letras, números, guiones y guiones bajos.',
            'slug.unique' => 'Este slug público ya está en uso.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $request->user()->update([
            'slug' => Str::slug($validator->validated()['slug'], '-'),
        ]);

        return $this->success([
            'profile' => $this->profilePayload($request->user()->refresh()),
        ], 'Slug público actualizado correctamente.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La nueva contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $validated = $validator->validated();

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return $this->error('La contraseña actual no es correcta.', [
                'errors' => [
                    'current_password' => ['La contraseña actual no es correcta.'],
                ],
            ], 422);
        }

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        app(TransactionalEmailService::class)->sendPasswordChanged($request->user()->refresh());

        return $this->success(null, 'Contraseña actualizada correctamente.');
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'avatar.required' => 'La imagen de perfil es obligatoria.',
            'avatar.image' => 'El archivo debe ser una imagen.',
            'avatar.mimes' => 'La imagen debe ser jpg, jpeg, png o webp.',
            'avatar.max' => 'La imagen no puede pesar más de 4MB.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store("users/{$user->id}/avatar", 'public');

        $user->update([
            'avatar_path' => $path,
        ]);

        return $this->success([
            'profile' => $this->profilePayload($user->refresh()),
        ], 'Imagen de perfil actualizada correctamente.');
    }

    public function generateReferralCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->referral_code) {
            $user->update([
                'referral_code' => $this->uniqueReferralCode($user),
            ]);
        }

        return $this->success([
            'referral' => $this->referralPayload($user->refresh()),
        ], 'Código de recomendado generado correctamente.');
    }

    public function updateShippingAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->shippingAddressRules(), $this->shippingAddressMessages());

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $request->user()->update([
            'shipping_address' => $validator->validated()['shipping_address'],
        ]);

        return $this->success([
            'profile' => $this->profilePayload($request->user()->refresh()),
        ], 'Dirección de envío actualizada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'plan' => $user->plan->value,
            'public_slug' => $user->slug,
            'public_url' => url("/u/{$user->slug}"),
            'avatar' => [
                'path' => $user->avatar_path,
                'url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            ],
            'referral' => $this->referralPayload($user),
            'shipping_address' => $user->shipping_address,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function referralPayload(User $user): array
    {
        return [
            'code' => $user->referral_code,
            'share_url' => $user->referral_code ? url("/register?ref={$user->referral_code}") : null,
            'status' => $user->referral_code ? 'active' : 'not_generated',
        ];
    }

    private function uniqueReferralCode(User $user): string
    {
        $prefix = Str::upper(Str::substr(Str::slug($user->name, ''), 0, 4)) ?: 'TAP';

        do {
            $code = $prefix.Str::upper(Str::random(6));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingAddressRules(): array
    {
        return [
            'shipping_address' => ['required', 'array'],
            'shipping_address.recipient_name' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.street' => ['required', 'string', 'max:255'],
            'shipping_address.exterior_number' => ['required', 'string', 'max:30'],
            'shipping_address.interior_number' => ['nullable', 'string', 'max:30'],
            'shipping_address.neighborhood' => ['required', 'string', 'max:120'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.state' => ['required', 'string', 'max:120'],
            'shipping_address.postal_code' => ['required', 'string', 'max:10'],
            'shipping_address.country' => ['required', 'string', 'size:2'],
            'shipping_address.references' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function shippingAddressMessages(): array
    {
        return [
            'shipping_address.required' => 'La dirección de envío es obligatoria.',
            'shipping_address.recipient_name.required' => 'El nombre de quien recibe es obligatorio.',
            'shipping_address.phone.required' => 'El teléfono es obligatorio.',
            'shipping_address.street.required' => 'La calle es obligatoria.',
            'shipping_address.exterior_number.required' => 'El número exterior es obligatorio.',
            'shipping_address.neighborhood.required' => 'La colonia es obligatoria.',
            'shipping_address.city.required' => 'La ciudad es obligatoria.',
            'shipping_address.state.required' => 'El estado es obligatorio.',
            'shipping_address.postal_code.required' => 'El código postal es obligatorio.',
            'shipping_address.country.required' => 'El país es obligatorio.',
            'shipping_address.country.size' => 'El país debe enviarse en formato ISO de 2 letras, por ejemplo MX.',
        ];
    }
}

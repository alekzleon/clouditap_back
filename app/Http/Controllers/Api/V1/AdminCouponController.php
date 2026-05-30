<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCouponController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $coupons = Coupon::query()
            ->withCount(['assignedUsers', 'redemptions'])
            ->latest()
            ->get()
            ->map(fn (Coupon $coupon) => $this->payload($coupon))
            ->values();

        return $this->success($coupons, 'Cupones obtenidos correctamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = validator($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $validated = $validator->validated();
        $userIds = $validated['user_ids'] ?? [];
        unset($validated['user_ids']);

        $coupon = Coupon::create($validated);
        $coupon->assignedUsers()->sync($userIds);

        return $this->success($this->payload($coupon->refresh()->loadCount(['assignedUsers', 'redemptions'])), 'Cupón creado correctamente.', 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        $coupon->load(['assignedUsers:id,name,email'])->loadCount(['assignedUsers', 'redemptions']);

        return $this->success([
            ...$this->payload($coupon),
            'assigned_users' => $coupon->assignedUsers->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ], 'Cupón obtenido correctamente.');
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validator = validator($request->all(), $this->rules($coupon->id, true), $this->messages());

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $validated = $validator->validated();
        $userIds = $validated['user_ids'] ?? null;
        unset($validated['user_ids']);

        $coupon->update($validated);

        if ($userIds !== null) {
            $coupon->assignedUsers()->sync($userIds);
        }

        return $this->success($this->payload($coupon->refresh()->loadCount(['assignedUsers', 'redemptions'])), 'Cupón actualizado correctamente.');
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return $this->success(null, 'Cupón eliminado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $couponId = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:60', Rule::unique('coupons', 'code')->ignore($couponId)],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => [$required, Rule::in([Coupon::TYPE_FIXED, Coupon::TYPE_PERCENT])],
            'discount_value' => [$required, 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'code.required' => 'El código del cupón es obligatorio.',
            'code.unique' => 'Ya existe un cupón con ese código.',
            'discount_type.in' => 'El tipo de descuento debe ser fixed o percent.',
            'discount_value.min' => 'El descuento debe ser mayor a cero.',
            'ends_at.after_or_equal' => 'La fecha final debe ser posterior o igual a la inicial.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'description' => $coupon->description,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'currency' => $coupon->currency,
            'starts_at' => $coupon->starts_at,
            'ends_at' => $coupon->ends_at,
            'max_uses' => $coupon->max_uses,
            'max_uses_per_user' => $coupon->max_uses_per_user,
            'is_active' => $coupon->is_active,
            'assigned_users_count' => $coupon->assigned_users_count ?? null,
            'redemptions_count' => $coupon->redemptions_count ?? null,
            'created_at' => $coupon->created_at,
            'updated_at' => $coupon->updated_at,
        ];
    }
}

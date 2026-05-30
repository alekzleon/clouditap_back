<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPromotionController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $promotions = Promotion::query()
            ->latest()
            ->get()
            ->map(fn (Promotion $promotion) => $this->payload($promotion))
            ->values();

        return $this->success($promotions, 'Promociones obtenidas correctamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = validator($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $validated = $validator->validated();
        $validated['type'] = $validated['type'] ?? Promotion::TYPE_BULK_CARD_DISCOUNT;

        $promotion = Promotion::create($validated);

        return $this->success($this->payload($promotion), 'Promoción creada correctamente.', 201);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return $this->success($this->payload($promotion), 'Promoción obtenida correctamente.');
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $validator = validator($request->all(), $this->rules(true), $this->messages());

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $promotion->update($validator->validated());

        return $this->success($this->payload($promotion->refresh()), 'Promoción actualizada correctamente.');
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return $this->success(null, 'Promoción eliminada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'type' => ['sometimes', Rule::in([Promotion::TYPE_BULK_CARD_DISCOUNT])],
            'min_quantity' => [$required, 'integer', 'min:1'],
            'discount_type' => [$required, Rule::in([Promotion::TYPE_FIXED, Promotion::TYPE_PERCENT])],
            'discount_value' => [$required, 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => 'El nombre de la promoción es obligatorio.',
            'type.in' => 'Por ahora solo se permite bulk_card_discount.',
            'min_quantity.required' => 'La cantidad mínima de tarjetas es obligatoria.',
            'discount_type.in' => 'El tipo de descuento debe ser fixed o percent.',
            'ends_at.after_or_equal' => 'La fecha final debe ser posterior o igual a la inicial.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'type' => $promotion->type,
            'min_quantity' => $promotion->min_quantity,
            'discount_type' => $promotion->discount_type,
            'discount_value' => $promotion->discount_value,
            'currency' => $promotion->currency,
            'starts_at' => $promotion->starts_at,
            'ends_at' => $promotion->ends_at,
            'is_active' => $promotion->is_active,
            'created_at' => $promotion->created_at,
            'updated_at' => $promotion->updated_at,
        ];
    }
}

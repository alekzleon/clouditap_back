<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CourtesyGrant;
use App\Models\User;
use App\Models\UserCardCredit;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCourtesyController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $grants = CourtesyGrant::query()
            ->with(['user:id,name,email', 'admin:id,name,email'])
            ->latest()
            ->get()
            ->map(fn (CourtesyGrant $grant) => $this->payload($grant))
            ->values();

        return $this->success($grants, 'Cortesías obtenidas correctamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'quantity.required' => 'La cantidad de tarjetas es obligatoria.',
            'quantity.min' => 'La cortesía debe ser de al menos una tarjeta.',
            'quantity.max' => 'La cortesía no puede exceder 100 tarjetas por operación.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $validated = $validator->validated();

        $result = DB::transaction(function () use ($request, $validated): array {
            $grant = CourtesyGrant::create([
                'user_id' => $validated['user_id'],
                'admin_user_id' => $request->user()->id,
                'quantity' => $validated['quantity'],
                'reason' => $validated['reason'] ?? null,
            ]);

            $credits = $this->cardCreditsForUser((int) $validated['user_id']);
            $credits->increment('purchased', (int) $validated['quantity']);

            return [
                'grant' => $grant->load(['user:id,name,email', 'admin:id,name,email']),
                'credits' => $credits->refresh(),
            ];
        });

        return $this->success([
            'courtesy' => $this->payload($result['grant']),
            'card_credits' => $this->creditsPayload($result['credits']),
        ], 'Cortesía aplicada correctamente.', 201);
    }

    private function cardCreditsForUser(int $userId): UserCardCredit
    {
        return UserCardCredit::firstOrCreate(
            ['user_id' => $userId],
            [
                'purchased' => Card::where('user_id', $userId)->count(),
                'used' => Card::where('user_id', $userId)->count(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CourtesyGrant $grant): array
    {
        return [
            'id' => $grant->id,
            'quantity' => $grant->quantity,
            'reason' => $grant->reason,
            'user' => $grant->user ? [
                'id' => $grant->user->id,
                'name' => $grant->user->name,
                'email' => $grant->user->email,
            ] : null,
            'admin' => $grant->admin ? [
                'id' => $grant->admin->id,
                'name' => $grant->admin->name,
                'email' => $grant->admin->email,
            ] : null,
            'created_at' => $grant->created_at,
            'updated_at' => $grant->updated_at,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function creditsPayload(UserCardCredit $credits): array
    {
        return [
            'available' => $credits->available(),
            'used' => $credits->used,
            'purchased' => $credits->purchased,
        ];
    }
}

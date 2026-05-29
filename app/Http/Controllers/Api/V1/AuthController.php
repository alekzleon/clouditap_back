<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Services\TransactionalEmailService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede tener más de :max caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.string' => 'El correo electrónico debe ser texto.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.max' => 'El correo electrónico no puede tener más de :max caracteres.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser texto.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $validated = $validator->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'slug' => $this->uniqueUserSlug($validated['name']),
            'plan' => Plan::Basic,
            'password' => $validated['password'],
        ]);

        $user->cardCredit()->create([
            'purchased' => 0,
            'used' => 0,
        ]);

        $user->assignRole('client');

        app(TransactionalEmailService::class)->sendWelcome($user);

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Usuario registrado correctamente.', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.string' => 'El correo electrónico debe ser texto.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser texto.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $validated = $validator->validated();
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->error('Las credenciales no son válidas.', null, 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Sesión iniciada correctamente.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $email = $validator->validated()['email'];
        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->error('Ese correo no está en nuestro sistema.', [
                'errors' => [
                    'email' => ['Ese correo no está en nuestro sistema.'],
                ],
            ], 404);
        }

        $status = Password::broker()->sendResetLink(
            ['email' => $email],
            function (User $user, string $token): void {
                Mail::to($user->email)->send(new ResetPasswordMail(
                    $user,
                    $this->resetPasswordUrl($user, $token),
                ));
            }
        );

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->error('No se pudo enviar el correo de recuperación en este momento.', [
                'status' => $status,
            ], 429);
        }

        return $this->success(null, 'Te enviamos un correo para recuperar tu contraseña.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'token.required' => 'El token de recuperación es obligatorio.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La nueva contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $status = Password::broker()->reset(
            $validator->validated(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                app(TransactionalEmailService::class)->sendPasswordChanged($user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error($this->resetPasswordErrorMessage($status), [
                'status' => $status,
            ], 422);
        }

        return $this->success(null, 'Contraseña restablecida correctamente.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'user' => $request->user(),
            'roles' => $request->user()->getRoleNames(),
        ], 'Usuario autenticado obtenido correctamente.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(null, 'Sesión cerrada correctamente.');
    }

    private function uniqueUserSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'user';
        $slug = $baseSlug;
        $suffix = 2;

        while (User::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function resetPasswordUrl(User $user, string $token): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return $frontendUrl.'/reset-password?'.http_build_query([
            'email' => $user->email,
            'token' => $token,
        ]);
    }

    private function resetPasswordErrorMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_USER => 'Ese correo no está en nuestro sistema.',
            Password::INVALID_TOKEN => 'El enlace de recuperación no es válido o ya expiró.',
            Password::RESET_THROTTLED => 'Espera un momento antes de solicitar otro cambio de contraseña.',
            default => 'No se pudo restablecer la contraseña.',
        };
    }
}

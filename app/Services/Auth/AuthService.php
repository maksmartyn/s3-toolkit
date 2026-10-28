<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Domain\DTO\Auth\CredentialsData;
use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class AuthService
{
    public function __construct(private UserRepositoryInterface $users) {}

    /**
     * Проверка учётных данных для Fortify. Возвращает модель, потому что её ждёт guard.
     *
     * @throws ValidationException если пароль верный, но пользователь заблокирован
     */
    public function attempt(CredentialsData $credentials): ?User
    {
        $user = $this->users->findByEmail($credentials->email);

        // Хеш проверяется и для несуществующего email, чтобы время ответа не выдавало зарегистрированных пользователей
        $hash = $user->password ?? Hash::make($credentials->password);

        if (! Hash::check($credentials->password, $hash) || $user === null) {
            return null;
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('Your account is blocked.')]);
        }

        $this->users->touchLastLogin($user->id);

        return $user;
    }
}

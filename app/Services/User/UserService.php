<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Domain\DTO\Audit\AuditRecordData;
use App\Domain\DTO\User\TemporaryPasswordData;
use App\Domain\DTO\User\UserCreateData;
use App\Domain\DTO\User\UserData;
use App\Domain\DTO\User\UserFilterData;
use App\Domain\DTO\User\UserUpdateData;
use App\Domain\Enum\Audit\AuditAction;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Audit\AuditDetails;
use App\Services\Audit\AuditService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class UserService
{
    private const int TEMPORARY_PASSWORD_LENGTH = 16;

    public function __construct(
        private UserRepositoryInterface $users,
        private UserMapper $mapper,
        private AuditService $audit,
    ) {}

    /**
     * @return LengthAwarePaginator<int, UserData>
     */
    public function paginate(UserFilterData $filter): LengthAwarePaginator
    {
        return $this->users->paginateFiltered($filter)->through(fn (User $user) => $this->mapper->toData($user));
    }

    public function get(int $id): UserData
    {
        return $this->mapper->toData($this->users->findWithTrashedOrFail($id));
    }

    public function create(UserCreateData $data): TemporaryPasswordData
    {
        $this->ensureEmailAvailable($data->email);

        $password = $data->password ?? $this->generatePassword();

        return DB::transaction(function () use ($data, $password): TemporaryPasswordData {
            $user = $this->users->create([
                'name' => $data->name,
                'email' => Str::lower($data->email),
                'password' => $password,
                'password_change_required' => true,
            ]);

            $this->record(AuditAction::UserCreated, $user, AuditDetails::changes([], [
                'name' => $user->name,
                'email' => $user->email,
            ]));

            return new TemporaryPasswordData(['user' => $this->mapper->toData($user), 'password' => $password]);
        });
    }

    /**
     * Суперадмин создаётся только из консоли. Пароль, заданный вручную, менять при входе не требуется.
     */
    public function createSuperadmin(UserCreateData $data): TemporaryPasswordData
    {
        $this->ensureEmailAvailable($data->email);

        $password = $data->password ?? $this->generatePassword();

        return DB::transaction(function () use ($data, $password): TemporaryPasswordData {
            $user = $this->users->createSuperadmin(
                name: $data->name,
                email: $data->email,
                password: $password,
                passwordChangeRequired: $data->password === null,
            );

            $this->record(AuditAction::UserCreated, $user, [
                ...AuditDetails::changes([], ['name' => $user->name, 'email' => $user->email]),
                ...AuditDetails::flags(['superadmin' => true]),
            ]);

            return new TemporaryPasswordData(['user' => $this->mapper->toData($user), 'password' => $password]);
        });
    }

    public function update(int $id, UserUpdateData $data): UserData
    {
        $this->ensureEmailAvailable($data->email, $id);

        return DB::transaction(function () use ($id, $data): UserData {
            $before = $this->users->findOrFail($id);
            $after = [
                'name' => $data->name,
                'email' => Str::lower($data->email),
            ];
            $details = AuditDetails::changes(['name' => $before->name, 'email' => $before->email], $after);

            $user = $this->users->update($id, $after);

            if ($details !== []) {
                $this->record(AuditAction::UserUpdated, $user, $details);
            }

            return $this->mapper->toData($user);
        });
    }

    public function block(int $id): UserData
    {
        return DB::transaction(function () use ($id): UserData {
            $this->ensureNotLastSuperadmin($id);

            $user = $this->users->setActive($id, false);
            $this->users->rotateRememberToken($id);
            $this->record(AuditAction::UserBlocked, $user);

            return $this->mapper->toData($user);
        });
    }

    public function unblock(int $id): UserData
    {
        return DB::transaction(function () use ($id): UserData {
            $user = $this->users->setActive($id, true);
            $this->record(AuditAction::UserUnblocked, $user);

            return $this->mapper->toData($user);
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $this->ensureNotLastSuperadmin($id);

            $user = $this->users->findOrFail($id);
            $this->users->rotateRememberToken($id);
            $this->users->delete($id);
            $this->record(AuditAction::UserDeleted, $user);
        });
    }

    public function restore(int $id): UserData
    {
        $user = $this->users->findWithTrashedOrFail($id);

        if (! $user->trashed()) {
            return $this->mapper->toData($user);
        }

        $this->ensureEmailAvailable($user->email, $id);

        return DB::transaction(function () use ($id): UserData {
            $user = $this->users->restore($id);
            $this->record(AuditAction::UserRestored, $user);

            return $this->mapper->toData($user);
        });
    }

    /**
     * Новый временный пароль. Пользователь должен будет сменить его при следующем входе,
     * а его текущие сессии завершатся (см. AuthenticateSession).
     */
    public function resetPassword(int $id): TemporaryPasswordData
    {
        $password = $this->generatePassword();

        return DB::transaction(function () use ($id, $password): TemporaryPasswordData {
            $user = $this->users->setPassword($id, $password, changeRequired: true);
            $this->users->rotateRememberToken($id);
            $this->record(AuditAction::UserPasswordReset, $user, AuditDetails::flags(['temporaryPassword' => true]));

            return new TemporaryPasswordData(['user' => $this->mapper->toData($user), 'password' => $password]);
        });
    }

    /**
     * Смена собственного пароля, в том числе обязательная после выдачи временного.
     */
    public function changePassword(int $id, string $password): UserData
    {
        $user = $this->users->findOrFail($id);

        if (Hash::check($password, $user->password)) {
            throw new DomainException(__('The new password must be different from the current one.'));
        }

        return DB::transaction(function () use ($id, $password): UserData {
            $user = $this->users->setPassword($id, $password, changeRequired: false);
            $this->record(AuditAction::UserPasswordChanged, $user);

            return $this->mapper->toData($user);
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function record(AuditAction $action, User $user, array $details = []): void
    {
        $this->audit->record(new AuditRecordData([
            'action' => $action,
            'subjectId' => $user->id,
            'subjectLabel' => $user->name,
            'details' => $details,
        ]));
    }

    private function ensureEmailAvailable(string $email, ?int $exceptId = null): void
    {
        if ($this->users->emailTaken($email, $exceptId)) {
            throw new DomainException(__('A user with this email already exists.'));
        }
    }

    private function ensureNotLastSuperadmin(int $id): void
    {
        $user = $this->users->findOrFail($id);

        if ($user->is_superadmin && $user->is_active && $this->users->countActiveSuperadmins(exceptId: $id) === 0) {
            throw new DomainException(__('The last active superadmin cannot be blocked or deleted.'));
        }
    }

    private function generatePassword(): string
    {
        return Str::password(self::TEMPORARY_PASSWORD_LENGTH, symbols: false);
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\DTO\User\UserCreateData;
use App\Exceptions\DomainException;
use App\Services\User\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

final class CreateSuperadminCommand extends Command
{
    protected $signature = 'app:create-superadmin
        {--name= : Имя}
        {--email= : Email}
        {--generate-password : Сгенерировать временный пароль (потребуется сменить при входе)}';

    protected $description = 'Создать суперадмина';

    public function handle(UserService $users): int
    {
        $name = $this->option('name') ?: text('Имя', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $password = $this->option('generate-password')
            ? null
            : password('Пароль (пусто — сгенерировать временный)');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password ?: null],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['nullable', 'string', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        try {
            $result = $users->createSuperadmin(new UserCreateData($validator->validated()));
        } catch (DomainException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Суперадмин {$result->user->email} создан.");

        if ($result->user->passwordChangeRequired) {
            $this->components->warn("Временный пароль (сменить при первом входе): {$result->password}");
        }

        return self::SUCCESS;
    }
}

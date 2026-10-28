<?php

use App\Domain\DTO\Abstracts\DTO;
use App\Models\User;
use App\Repositories\Abstracts\BaseRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class BaseRepositoryTestUser extends User
{
    protected $table = 'users';

    /**
     * @return HasOne<User, $this>
     */
    public function itself(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id');
    }
}

/**
 * @extends BaseRepository<BaseRepositoryTestUser>
 */
final class BaseRepositoryTestUserRepository extends BaseRepository
{
    protected function model(): string
    {
        return BaseRepositoryTestUser::class;
    }
}

final class BaseRepositoryTestUserData extends DTO
{
    public string $name;

    public string $email;

    public ?string $password;
}

beforeEach(function () {
    $this->repository = new BaseRepositoryTestUserRepository;
});

it('creates and updates models from DTO using only initialized fields', function () {
    $user = $this->repository->create(new BaseRepositoryTestUserData([
        'name' => 'Ann',
        'email' => 'ann@example.com',
        'password' => 'secret-password',
    ]));

    $updated = $this->repository->update($user->id, new BaseRepositoryTestUserData(['name' => 'Anna']));

    expect($updated->name)->toBe('Anna')
        ->and($updated->email)->toBe('ann@example.com')
        ->and($updated->password)->toBe($user->password);
});

it('finds models', function () {
    $users = User::factory()->count(3)->create();

    expect($this->repository->find($users[0]->id)?->is($users[0]))->toBeTrue()
        ->and($this->repository->find(999_999))->toBeNull()
        ->and($this->repository->findOneWhere(['email' => $users[1]->email])?->is($users[1]))->toBeTrue()
        ->and($this->repository->findWhere(['email' => $users[2]->email]))->toHaveCount(1)
        ->and($this->repository->findWhereIn('id', [$users[0]->id, $users[1]->id]))->toHaveCount(2)
        ->and($this->repository->all())->toHaveCount(3)
        ->and($this->repository->exists(['email' => $users[0]->email]))->toBeTrue()
        ->and($this->repository->paginate(2)->total())->toBe(3);

    $this->repository->findOrFail(999_999);
})->throws(ModelNotFoundException::class);

it('deletes models', function () {
    $user = User::factory()->create();

    expect($this->repository->delete($user->id))->toBeTrue()
        ->and(User::query()->find($user->id))->toBeNull();
});

it('does not leak eager loads between calls', function () {
    $user = User::factory()->create();

    $withItself = $this->repository->with('itself');

    expect($withItself->find($user->id)?->relationLoaded('itself'))->toBeTrue()
        ->and($this->repository->find($user->id)?->relationLoaded('itself'))->toBeFalse();
});

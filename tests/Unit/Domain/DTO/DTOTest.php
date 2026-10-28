<?php

use App\Domain\DTO\Abstracts\DTO;
use Illuminate\Support\Collection;

enum DtoTestStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}

final class DtoTestMoney
{
    public function __construct(public readonly int $amount) {}
}

final class DtoTestChild extends DTO
{
    public string $name;

    public ?int $age;
}

final class DtoTestParent extends DTO
{
    public int $id;

    public ?string $title;

    public string $slug = 'default';

    public DtoTestStatus $status;

    public ?DtoTestMoney $price;

    public DtoTestChild $child;

    /** @var list<DtoTestStatus> */
    public array $statuses;

    /** @var list<DtoTestChild> */
    public array $children;

    /** @var list<string> */
    public array $upper;

    /** @var Collection<int, DtoTestMoney> */
    public Collection $prices;

    public string $email;

    protected array $casts = [
        'statuses' => DtoTestStatus::class,
        'children' => DtoTestChild::class,
        'upper' => 'strtoupper',
        'prices' => DtoTestMoney::class,
    ];

    protected function setEmailAttribute(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}

it('fills scalars, enums, value objects and nested DTO by property type', function () {
    $dto = new DtoTestParent([
        'id' => 1,
        'status' => 'active',
        'price' => 100,
        'child' => ['name' => 'Bob', 'age' => 3],
        'unknown' => 'ignored',
    ]);

    expect($dto->id)->toBe(1)
        ->and($dto->status)->toBe(DtoTestStatus::Active)
        ->and($dto->price)->toEqual(new DtoTestMoney(100))
        ->and($dto->child)->toBeInstanceOf(DtoTestChild::class)
        ->and($dto->child->name)->toBe('Bob');
});

it('keeps already constructed enums and objects', function () {
    $money = new DtoTestMoney(5);

    $dto = new DtoTestParent(['status' => DtoTestStatus::Blocked, 'price' => $money]);

    expect($dto->status)->toBe(DtoTestStatus::Blocked)
        ->and($dto->price)->toBe($money);
});

it('accepts explicit null for nullable properties', function () {
    $dto = new DtoTestParent(['title' => null, 'price' => null]);

    expect($dto->isInitialized('title'))->toBeTrue()
        ->and($dto->title)->toBeNull()
        ->and($dto->initialized())->toHaveKeys(['title', 'price']);
});

it('applies casts to scalars, arrays and collections', function () {
    $dto = new DtoTestParent([
        'statuses' => ['active', 'blocked'],
        'children' => [['name' => 'A'], ['name' => 'B']],
        'upper' => ['a', 'b'],
        'prices' => collect([1, 2]),
    ]);

    expect($dto->statuses)->toBe([DtoTestStatus::Active, DtoTestStatus::Blocked])
        ->and($dto->children[1]->name)->toBe('B')
        ->and($dto->upper)->toBe(['A', 'B'])
        ->and($dto->prices->all())->toEqual([new DtoTestMoney(1), new DtoTestMoney(2)]);
});

it('calls setters before casting', function () {
    expect((new DtoTestParent(['email' => '  John@Example.COM ']))->email)->toBe('john@example.com');
});

it('distinguishes initialized properties from missing ones', function () {
    $dto = new DtoTestParent(['id' => 1, 'child' => ['name' => 'A']]);

    expect($dto->initialized())->toBe(['id' => 1, 'slug' => 'default', 'child' => ['name' => 'A']])
        ->and($dto->isInitialized('title'))->toBeFalse()
        ->and($dto->isInitialized('missing'))->toBeFalse()
        ->and($dto->only(['id', 'title']))->toBe(['id' => 1])
        ->and($dto->only(['id', 'title'], initialized: false))->toBe(['id' => 1, 'title' => null]);
});

it('serializes recursively to array and json', function () {
    $dto = new DtoTestParent([
        'id' => 1,
        'status' => 'active',
        'statuses' => ['blocked'],
        'children' => [['name' => 'A', 'age' => 1]],
    ]);

    $array = $dto->toArray();

    expect($array['status'])->toBe('active')
        ->and($array['statuses'])->toBe(['blocked'])
        ->and($array['children'])->toBe([['name' => 'A', 'age' => 1]])
        ->and($array['title'])->toBeNull()
        ->and(json_decode($dto->toJson(), true))->toBe($array)
        ->and(json_encode($dto))->toBe($dto->toJson());
});

it('reflects later fills in isInitialized', function () {
    $dto = new DtoTestParent;

    expect($dto->isInitialized('id'))->toBeFalse();

    $dto->fill(['id' => 2]);

    expect($dto->isInitialized('id'))->toBeTrue();
});

it('creates modified copies with with()', function () {
    $dto = DtoTestParent::from(['id' => 1]);
    $copy = $dto->with(['id' => 2]);

    expect($dto->id)->toBe(1)->and($copy->id)->toBe(2);
});

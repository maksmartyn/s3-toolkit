<?php

declare(strict_types=1);

namespace App\Http\Controllers\Audit;

use App\Domain\Enum\Audit\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\AuditLogIndexRequest;
use App\Models\User;
use App\Services\Audit\AuditService;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(AuditLogIndexRequest $request): Response
    {
        $filter = $request->toData();

        return Inertia::render('audit-log/Index', [
            'entries' => $this->audit->paginate($filter),
            'filters' => [
                'search' => $filter->search,
                'action' => $filter->action?->value,
                'user_id' => $filter->subjectUserId,
                'date_from' => $filter->dateFrom?->format('Y-m-d'),
                'date_to' => $filter->dateTo?->format('Y-m-d'),
            ],
            'actions' => array_map(
                fn (AuditAction $action) => ['value' => $action->value, 'label' => $action->label()],
                AuditAction::cases(),
            ),
            'can' => ['viewUsers' => $request->authUser()->can('viewAny', User::class)],
        ]);
    }
}

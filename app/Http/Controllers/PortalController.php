<?php

namespace App\Http\Controllers;

use App\Models\Statement;
use App\Support\CurrentLabel;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What a payee sees: their own published statements, nothing else.
 */
class PortalController extends Controller
{
    public function __construct(private readonly CurrentLabel $currentLabel) {}

    public function index(): Response
    {
        return Inertia::render('portal/index', [
            'statements' => $this->visible()->with('period:id,name,starts_on,ends_on')->latest('id')->get()
                ->map(fn (Statement $statement): array => [
                    ...$statement->only(['id', 'name', 'balance', 'payable']),
                    'period' => $statement->period?->name,
                    'published_at' => $statement->published_at?->toDateString(),
                ]),
        ]);
    }

    public function show(int $statement): Response
    {
        $statement = $this->visible()->findOrFail($statement);

        return Inertia::render('statements/show', [
            'statement' => [
                ...$statement->only(['id', 'name', 'archived', 'revision_of_id', 'period_id', 'payee_id']),
                'published' => true,
                'created_at' => $statement->created_at->toDateTimeString(),
                'data' => $statement->data,
            ],
            'portal' => true,
        ]);
    }

    public function download(int $statement): StreamedResponse
    {
        return StatementController::csv($this->visible()->findOrFail($statement));
    }

    /**
     * @return Builder<Statement>
     */
    private function visible(): Builder
    {
        return Statement::query()
            ->where('archived', false)
            ->whereNotNull('published_at')
            ->when(
                ! $this->currentLabel->isAdmin(),
                fn (Builder $query) => $query->where('payee_id', $this->currentLabel->payeeId() ?? 0),
            );
    }
}

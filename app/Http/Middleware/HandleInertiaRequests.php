<?php

namespace App\Http\Middleware;

use App\Support\CurrentLabel;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'label' => fn () => $this->currentLabel($request),
            'labels' => fn () => $request->user()?->labels()->orderBy('name')->get(['labels.id', 'labels.name'])
                ->map(fn ($label): array => ['id' => $label->id, 'name' => $label->name])->all() ?? [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{id: int, name: string, currency: string, role: string|null}|null
     */
    private function currentLabel(Request $request): ?array
    {
        $current = app(CurrentLabel::class);
        $label = $current->get() ?? $request->user()?->currentLabel;

        return $label === null ? null : [
            'id' => $label->id,
            'name' => $label->name,
            'currency' => $label->currency,
            'role' => $current->role()?->value,
        ];
    }
}

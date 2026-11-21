<?php

namespace App\Http\Middleware;

use App\Enums\LabelRole;
use App\Enums\RoyaltyTool;
use App\Http\Controllers\Settings\LabelBrandingController;
use App\Support\CurrentLabel;
use App\Support\PayeeAccess;
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
            // Only the labels the user runs: the ones they are a payee of are reached through My statements.
            'labels' => fn () => $request->user()?->labels()->wherePivot('role', LabelRole::Admin->value)->orderBy('name')->get(['labels.id', 'labels.name'])
                ->map(fn ($label): array => ['id' => $label->id, 'name' => $label->name])->all() ?? [],
            'isPayee' => fn () => $request->user() !== null && (new PayeeAccess($request->user()))->isPayeeAnywhere(),
            'canCreateLabel' => fn () => $request->user()?->canCreateLabel() ?? false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{id: int, name: string, currency: string, role: string|null, logo_url: string|null, signet_url: string|null, services: list<string>, tools: array<string, bool>}|null
     */
    private function currentLabel(Request $request): ?array
    {
        $current = app(CurrentLabel::class);
        $label = $current->get();
        $role = $current->role()?->value;

        // Account pages such as the profile are not tied to a label, so it is looked up among the
        // user's own labels (never one they left): without it the settings would hide everything
        // that belongs to the label.
        if ($label === null && $request->user() !== null) {
            $label = $request->user()->labels()->whereKey($request->user()->current_label_id)->first();
            $role = $label?->pivot->role;
        }

        return $label === null ? null : [
            'id' => $label->id,
            'name' => $label->name,
            'currency' => $label->currency,
            'role' => $role,
            'logo_url' => LabelBrandingController::url($label, 'logo'),
            'signet_url' => LabelBrandingController::url($label, 'signet'),
            // Which services the label works with; what is built for the others is hidden.
            'services' => $label->services(),
            // Which of the mechanicals, PPD and reserves tools the label uses.
            'tools' => array_combine(
                array_map(fn (RoyaltyTool $tool): string => $tool->value, RoyaltyTool::cases()),
                array_map(fn (RoyaltyTool $tool): bool => $tool->enabledFor($label), RoyaltyTool::cases()),
            ),
        ];
    }
}

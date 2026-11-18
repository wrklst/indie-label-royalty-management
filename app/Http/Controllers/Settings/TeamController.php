<?php

namespace App\Http\Controllers\Settings;

use App\Enums\LabelRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\MemberRequest;
use App\Models\Payee;
use App\Models\User;
use App\Support\CurrentLabel;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(CurrentLabel $currentLabel): Response
    {
        $payees = Payee::query()->pluck('name', 'id');

        return Inertia::render('settings/team', [
            'members' => $currentLabel->get()->users()->orderBy('name')->get()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'payee_id' => $user->pivot->payee_id,
                'payee' => $payees[$user->pivot->payee_id] ?? null,
            ]),
            'payeeOptions' => Options::payees(),
        ]);
    }

    /**
     * Add a person to the label. Someone without an account gets one and
     * a password reset link to set their own password.
     */
    public function store(MemberRequest $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $label = $currentLabel->get();
        $email = Str::lower($request->validated('email'));
        $user = User::query()->where('email', $email)->first();

        if ($user && $label->users()->whereKey($user->id)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This person already has access to the label.')]);

            return back();
        }

        if (! $user) {
            $user = User::query()->create([
                'name' => $request->validated('name') ?? Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(32),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            Password::sendResetLink(['email' => $email]);
        }

        $label->users()->attach($user, $this->pivot($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was added to the label.', ['name' => $user->name])]);

        return back();
    }

    public function update(MemberRequest $request, User $user, CurrentLabel $currentLabel): RedirectResponse
    {
        $label = $currentLabel->get();
        abort_unless($label->users()->whereKey($user->id)->exists(), 404);

        if ($request->validated('role') !== LabelRole::Admin->value && $this->isLastAdmin($user, $currentLabel)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A label needs at least one admin.')]);

            return back();
        }

        $label->users()->updateExistingPivot($user->id, $this->pivot($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access updated.')]);

        return back();
    }

    public function destroy(User $user, CurrentLabel $currentLabel): RedirectResponse
    {
        if ($this->isLastAdmin($user, $currentLabel)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A label needs at least one admin.')]);

            return back();
        }

        $currentLabel->get()->users()->detach($user->id);

        if ($user->current_label_id === $currentLabel->id()) {
            $user->forceFill(['current_label_id' => null])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access removed.')]);

        return back();
    }

    /**
     * @return array{role: string, payee_id: int|null}
     */
    private function pivot(MemberRequest $request): array
    {
        $role = $request->validated('role');

        return [
            'role' => $role,
            'payee_id' => $role === LabelRole::Payee->value ? $request->validated('payee_id') : null,
        ];
    }

    private function isLastAdmin(User $user, CurrentLabel $currentLabel): bool
    {
        $admins = $currentLabel->get()->users()->wherePivot('role', LabelRole::Admin->value)->pluck('users.id');

        return $admins->count() === 1 && $admins->first() === $user->id;
    }
}

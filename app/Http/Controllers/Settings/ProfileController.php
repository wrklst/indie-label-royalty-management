<?php

namespace App\Http\Controllers\Settings;

use App\Enums\LabelRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Label;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $changedEmail = $request->user()->isDirty('email');
        $request->user()->save();

        if ($changedEmail) {
            // Confirming by this link keeps password, passkeys and two-factor. Signing in with a
            // code while the address is unconfirmed resets them, as for any unproven address.
            $request->user()->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $changedEmail
            ? __('Profile updated. Confirm the new address with the link we sent to it.')
            : __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $orphaned = $user->labels()->wherePivot('role', LabelRole::Admin->value)->get()
            ->first(fn (Label $label): bool => $label->users()->wherePivot('role', LabelRole::Admin->value)->where('users.id', '!=', $user->id)->doesntExist());

        if ($orphaned !== null) {
            throw ValidationException::withMessages([
                'password' => __('You are the only admin of :label. Make someone else an admin under Settings → Team first.', ['label' => $orphaned->name]),
            ]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

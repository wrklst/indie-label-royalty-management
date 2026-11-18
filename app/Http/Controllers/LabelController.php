<?php

namespace App\Http\Controllers;

use App\Enums\LabelRole;
use App\Models\Label;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LabelController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('labels/create', [
            'isFirst' => ! $request->user()->labels()->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
        ]);

        $base = Str::slug($validated['name']) ?: 'label';
        $slug = $base;

        while (Label::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        $label = Label::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'currency' => strtoupper($validated['currency']),
            'contact_email' => $request->user()->email,
        ]);

        $label->users()->attach($request->user(), ['role' => LabelRole::Admin->value]);
        $request->user()->forceFill(['current_label_id' => $label->id])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Label created.')]);

        return to_route('dashboard');
    }

    /**
     * Switch the label the user is working in.
     */
    public function switch(Request $request, Label $label): RedirectResponse
    {
        abort_unless($request->user()->labels()->whereKey($label->id)->exists(), 403);

        $request->user()->forceFill(['current_label_id' => $label->id])->save();

        return to_route('dashboard');
    }
}

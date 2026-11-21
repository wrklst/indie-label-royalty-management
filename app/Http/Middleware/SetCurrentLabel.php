<?php

namespace App\Http\Middleware;

use App\Enums\LabelRole;
use App\Support\CurrentLabel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentLabel
{
    public function __construct(private readonly CurrentLabel $currentLabel) {}

    /**
     * Resolve the label the user is working in and scope the request to it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $labels = $user->labels()->orderBy('name')->get();
        $label = $labels->firstWhere('id', $user->current_label_id) ?? $labels->first();

        // Someone who runs a label always works in one they run; the labels they are only a
        // payee of are reached through My statements, so they never become the working label.
        if ($label !== null && $label->pivot->role !== LabelRole::Admin->value) {
            $label = $labels->first(fn ($candidate): bool => $candidate->pivot->role === LabelRole::Admin->value) ?? $label;
        }

        if ($label === null) {
            return redirect()->route('labels.create');
        }

        if ($user->current_label_id !== $label->id) {
            $user->forceFill(['current_label_id' => $label->id])->save();
        }

        $this->currentLabel->set(
            $label,
            LabelRole::from($label->pivot->role),
            $label->pivot->payee_id,
        );

        return $next($request);
    }
}

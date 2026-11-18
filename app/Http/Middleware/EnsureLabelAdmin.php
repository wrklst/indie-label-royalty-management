<?php

namespace App\Http\Middleware;

use App\Support\CurrentLabel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLabelAdmin
{
    public function __construct(private readonly CurrentLabel $currentLabel) {}

    /**
     * Payee users only get their statement portal.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->currentLabel->isAdmin()) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect()->route('portal.index');
            }

            abort(403);
        }

        return $next($request);
    }
}

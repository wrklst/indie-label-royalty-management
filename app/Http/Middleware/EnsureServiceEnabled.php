<?php

namespace App\Http\Middleware;

use App\Support\CurrentLabel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 404 for what is built for a service the label does not work
 * with: `service:bandcamp`.
 */
class EnsureServiceEnabled
{
    public function handle(Request $request, Closure $next, string $service): Response
    {
        abort_unless(app(CurrentLabel::class)->get()?->usesService($service), 404);

        return $next($request);
    }
}

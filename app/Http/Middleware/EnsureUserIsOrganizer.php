<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null || ! $request->user()->is_organizer) {
            abort(403, 'Solo los organizadores pueden hacer esto.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\ActiveGroup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOrganizer
{
    public function __construct(private ActiveGroup $activeGroup) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null || ! $this->activeGroup->isOrganizer()) {
            abort(403, 'Solo los organizadores de este grupo pueden hacer esto.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    /**
     * Let only support staff (admin and super admin) through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isStaff()) {
            return response()->json([
                'message' => 'This action is only available to support staff.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

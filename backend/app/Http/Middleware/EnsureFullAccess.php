<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFullAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!$request->user()?->isFullAccess()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }
        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): mixed
    {
        if (!$request->user()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        foreach ($permissions as $permission) {
            if ($request->user()->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin untuk tindakan ini.'], 403);
    }
}

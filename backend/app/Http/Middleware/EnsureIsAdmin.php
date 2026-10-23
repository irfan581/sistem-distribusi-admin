<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan hanya User yang is_admin = true (atau role admin)
 * yang bisa mengakses endpoint /admin/*.
 *
 * Sales (model App\Models\Sales) tidak bisa masuk karena
 * mereka autentikasi lewat guard berbeda (tabel sales),
 * tapi middleware ini sebagai lapisan kedua untuk User biasa
 * yang punya token tapi bukan admin.
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Sales model tidak punya method isAdminUser() — tolak langsung
        if (!method_exists($user, 'isAdminUser') || !$user->isAdminUser()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Halaman ini hanya untuk admin.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSalesActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Sejak guard driver ditambahkan, token non-sales bisa sampai ke sini.
            // Tanpa penjagaan ini, $user->is_active pada model driver mengembalikan
            // null sehingga seluruh token driver ikut terhapus dan driver
            // ter-logout paksa tanpa sebab.
            if (!$user instanceof \App\Models\Sales) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun ini tidak berhak mengakses endpoint sales.',
                    'code'    => 'WRONG_ACCOUNT_TYPE',
                ], 403);
            }

            if (!$user->is_active) {
                $user->tokens()->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda telah dinonaktifkan. Silakan hubungi admin untuk aktivasi kembali.',
                    'code'    => 'ACCOUNT_INACTIVE' 
                ], 403);
            }
        }

        return $next($request);
    }
}
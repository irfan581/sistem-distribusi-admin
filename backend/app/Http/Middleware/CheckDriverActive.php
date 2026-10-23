<?php

namespace App\Http\Middleware;

use App\Models\drivers;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDriverActive
{
    /**
     * Pastikan pemegang token memang driver yang masih berhak memakai aplikasi.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Token sales atau admin tidak boleh menyentuh endpoint driver. Tokennya
        // tidak dicabut — akun itu sendiri tidak bersalah, hanya salah pintu.
        if (!$user instanceof drivers) {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini tidak berhak mengakses aplikasi driver.',
                'code'    => 'WRONG_ACCOUNT_TYPE',
            ], 403);
        }

        if (!$user->bolehLoginMobile()) {
            $user->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan. Silakan hubungi admin untuk aktivasi kembali.',
                'code'    => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}

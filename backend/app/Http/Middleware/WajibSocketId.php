<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class WajibSocketId
{
    // APK sales lama meminta auth channel sebelum tersambung ke Reverb; tanpa ini Pusher melempar TypeError (500).
    public function handle(Request $request, Closure $next): mixed
    {
        if (!$request->filled('socket_id') || !$request->filled('channel_name')) {
            return response()->json(['message' => 'socket_id dan channel_name wajib diisi.'], 422);
        }

        return $next($request);
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Daftarkan /broadcasting/auth dengan Bearer token (Sanctum).
        // Satu-satunya tempat registrasi — tidak ada di bootstrap/app.php.
        Broadcast::routes(['middleware' => ['auth:sanctum', \App\Http\Middleware\WajibSocketId::class]]);

        require base_path('routes/channels.php');
    }
}

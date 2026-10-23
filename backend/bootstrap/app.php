<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // broadcasting/auth dan channels.php didaftarkan di BroadcastServiceProvider
        // agar tidak double-register yang menyebabkan 403 pada private channel auth
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'check.sales.active'  => \App\Http\Middleware\CheckSalesActive::class,
            'check.driver.active' => \App\Http\Middleware\CheckDriverActive::class,
            'ensure.admin'       => \App\Http\Middleware\EnsureIsAdmin::class,
            'permission'         => \App\Http\Middleware\CheckPermission::class,
        ]);

        // Aplikasi API-first tanpa halaman login web. Cegah middleware Authenticate
        // memanggil route('login') untuk request non-JSON (mis. broadcasting/auth
        // Reverb) yang menyebabkan "Route [login] not defined". Dengan return null,
        // AuthenticationException dilempar bersih lalu ditangani jadi JSON 401.
        $middleware->redirectGuestsTo(fn () => null);

        // API pakai Bearer token — tidak butuh CSRF/stateful session
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Aplikasi ini API-first tanpa halaman login web, jadi AuthenticationException
        // SELALU dibalas JSON 401 — bukan hanya untuk api/*. Tanpa ini, request auth
        // non-JSON di luar prefix api/ (mis. broadcasting/auth private channel Reverb)
        // membuat Laravel mencoba redirect ke route('login') yang tidak ada
        // → error "Route [login] not defined".
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau sudah kadaluarsa. Silakan login ulang.',
            ], 401);
        });

        // Semua exception di API route dikembalikan sebagai JSON konsisten
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {

                // Sebagian exception SUDAH membawa jawabannya sendiri, dan
                // yang paling sering adalah pembatas laju: ia melempar
                // HttpResponseException berisi jawaban 429 yang rapi.
                //
                // HttpResponseException bukan turunan HttpException Symfony,
                // jadi tanpa cabang ini ia lolos sampai ke cabang terakhir dan
                // jadi 500 berpesan kosong — batas laju yang kena jadi
                // kelihatan seperti server rusak, dan sebabnya tidak kelihatan
                // sama sekali. Persis itu yang terjadi pada foto.
                if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                    return $e->getResponse();
                }

                if ($e instanceof ValidationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validasi gagal',
                        'errors'  => $e->errors(),
                    ], 422);
                }

                if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data tidak ditemukan.',
                    ], 404);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage() ?: 'HTTP Error',
                    ], $e->getStatusCode());
                }

                // Generic server error — jangan expose detail di production
                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'Terjadi kesalahan pada server. Silakan coba lagi.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 500);
            }
        });

    })->create();

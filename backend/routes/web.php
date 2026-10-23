<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// CORS preflight untuk Vue SPA
Route::get('/sanctum/csrf-cookie', function () {
    return response()->noContent();
});

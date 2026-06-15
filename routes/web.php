<?php

use Illuminate\Support\Facades\Route;

/*
 * Serve the React single-page application for every non-API path. API routes
 * live under /api (see routes/api.php) and the Swagger UI under
 * /api/documentation + /docs, all excluded here so the SPA never shadows them.
 */
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|docs|build|storage|up).*$')
    ->name('spa');

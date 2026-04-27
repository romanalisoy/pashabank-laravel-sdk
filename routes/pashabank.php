<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Romanalisoy\PashaBank\Http\Controllers\CallbackController;
use Romanalisoy\PashaBank\Http\Middleware\VerifyCallbackIp;

/** @var array<int, string> $middleware */
$middleware = (array) config('pashabank.callback.middleware', ['web']);
$middleware[] = VerifyCallbackIp::class;

$name = (string) config('pashabank.callback.name', 'pashabank.callback');

$successRoute = config('pashabank.callback.success_route');
$failureRoute = config('pashabank.callback.failure_route');

if (is_string($successRoute) && $successRoute !== '' && is_string($failureRoute) && $failureRoute !== '') {
    // Split-route mode: bank picks success vs failure URL itself, but the
    // controller still issues command=c to confirm the actual state.
    Route::middleware($middleware)
        ->match(['get', 'post'], $successRoute, CallbackController::class)
        ->name($name.'.success')
        ->defaults('_pashabank_outcome_hint', 'success');

    Route::middleware($middleware)
        ->match(['get', 'post'], $failureRoute, CallbackController::class)
        ->name($name.'.failure')
        ->defaults('_pashabank_outcome_hint', 'failure');
} else {
    // Single-route mode: one URL, controller decides outcome.
    $route = (string) config('pashabank.callback.route', '/pashabank/callback');

    Route::middleware($middleware)
        ->match(['get', 'post'], $route, CallbackController::class)
        ->name($name);
}

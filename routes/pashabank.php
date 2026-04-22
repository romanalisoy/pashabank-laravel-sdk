<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Romanalisoy\PashaBank\Http\Controllers\CallbackController;
use Romanalisoy\PashaBank\Http\Middleware\VerifyCallbackIp;

$route = (string) config('pashabank.callback.route', '/pashabank/callback');
$name = (string) config('pashabank.callback.name', 'pashabank.callback');

/** @var array<int, string> $middleware */
$middleware = (array) config('pashabank.callback.middleware', ['web']);
$middleware[] = VerifyCallbackIp::class;

Route::middleware($middleware)
    ->match(['get', 'post'], $route, CallbackController::class)
    ->name($name);

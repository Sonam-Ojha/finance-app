<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'email.verified' => \App\Http\Middleware\RequireVerifiedEmail::class,
        ]);
        // Admin › App control: maintenance mode on the website (admins pass),
        // and maintenance (503) / minimum app version (426) on the API.
        $middleware->web(append: [\App\Http\Middleware\WebMaintenance::class]);
        // Before `auth`, so signed-out visitors see the maintenance page too, not the sign-in page.
        $middleware->prependToPriorityList(\Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class, \App\Http\Middleware\WebMaintenance::class);
        $middleware->api(prepend: [\App\Http\Middleware\AppGate::class]);
        // Security: a blocked account is signed out of the website / gets 403
        // `account_blocked` from the API; a Google- or Apple-made account gives
        // its date of birth on the website before anything else.
        $middleware->web(append: [
            \App\Http\Middleware\RejectBlockedUsers::class,
            \App\Http\Middleware\RequireBirthday::class,
        ]);
        $middleware->api(append: [\App\Http\Middleware\RejectBlockedUsers::class . ':sanctum']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A form left open longer than the session (phones keep tabs for days)
        // fails its CSRF check. Instead of a bare "419 Page Expired", go back to
        // a fresh copy of the page and say why; signing out just lands on sign-in.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) return null;
            if ($request->routeIs('logout')) return redirect()->route('login');

            $message = 'That page was open for a long time and expired. Please try again.';

            return back(302, [], route('login'))
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password', 'credential']))
                ->with(auth()->check() ? 'error' : 'status', $message);
        });
    })->create();

<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;

it('sends security headers with a CSP nonce matching the inline script', function () {
    $response = $this->get('/login')->assertOk();

    $csp = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $csp, $nonce);

    expect($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse()
        ->and($response->getContent())->toContain('<script nonce="'.$nonce[1].'">');
});

it('sends HSTS over HTTPS', function () {
    $this->get('https://localhost/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('takes the client IP from X-Forwarded-For only for trusted proxies', function () {
    Route::get('/_ip', fn () => request()->ip());

    $forwarded = fn () => $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeader('X-Forwarded-For', '203.0.113.7')
        ->get('/_ip')
        ->getContent();

    expect($forwarded())->toBe('10.0.0.5');

    TrustProxies::at('10.0.0.5');

    try {
        expect($forwarded())->toBe('203.0.113.7');
    } finally {
        TrustProxies::flushState();
    }
});

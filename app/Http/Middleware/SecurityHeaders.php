<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Заголовки безопасности. CSP: скрипты — только свои и с nonce (встроенный скрипт темы в app.blade.php),
 * WebSocket — только к Reverb. Стили допускают inline: их ставят компоненты интерфейса.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure() && config('s3toolkit.security.hsts')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (config('s3toolkit.security.csp') && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->policy());
        }

        return $response;
    }

    private function policy(): string
    {
        $scripts = ["'self'", "'nonce-".Vite::cspNonce()."'"];
        $websocket = (array) config('s3toolkit.websocket');
        $connect = [
            "'self'",
            ($websocket['scheme'] === 'https' ? 'wss' : 'ws').'://'.$websocket['host'].':'.$websocket['port'],
            ...array_filter((array) config('s3toolkit.security.connect_src')),
        ];

        // Dev-сервер Vite: скрипты и HMR по WebSocket
        if (Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $scripts[] = $hot;
            $connect[] = $hot;
            $connect[] = preg_replace('#^http#', 'ws', $hot);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scripts),
            "style-src 'self' 'unsafe-inline'".(Vite::isRunningHot() ? ' '.rtrim((string) file_get_contents(public_path('hot'))) : ''),
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            'connect-src '.implode(' ', array_unique($connect)),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}

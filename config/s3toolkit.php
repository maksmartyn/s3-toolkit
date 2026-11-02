<?php

return [

    'storage' => [
        // Разрешить endpoint во внутренних сетях (10/8, 192.168/16, localhost и т.п.).
        // Нужно для MinIO в локальной сети; для публичного SaaS выключить, чтобы закрыть SSRF.
        'allow_private_endpoints' => (bool) env('STORAGE_ALLOW_PRIVATE_ENDPOINTS', true),

        'connect_timeout' => (float) env('STORAGE_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('STORAGE_TIMEOUT', 30),
    ],

    'access_keys' => [
        // Клиент MinIO для управления ключами MinIO (в Docker-образе — /usr/local/bin/mc)
        'mc_binary' => env('MC_BINARY', 'mc'),
        'mc_timeout' => (int) env('MC_TIMEOUT', 30),
    ],

    // Куда подключается браузер для WebSocket (Reverb). Читается при каждом запуске, а не при сборке фронтенда
    'websocket' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('VITE_REVERB_HOST', 'localhost'),
        'port' => (int) env('VITE_REVERB_PORT', 443),
        'scheme' => env('VITE_REVERB_SCHEME', 'https'),
    ],

    'security' => [
        'csp' => (bool) env('SECURITY_CSP', true),
        // Адреса прокси через запятую или «*»: от них принимаются X-Forwarded-For/Proto (IP клиента в журнале, https)
        'trusted_proxies' => env('TRUSTED_PROXIES'),
        // HSTS отправляется только по HTTPS
        'hsts' => (bool) env('SECURITY_HSTS', true),
        // Куда браузеру можно подключаться кроме своего origin: WebSocket Reverb
        // Дополнительные адреса для connect-src через запятую; WebSocket Reverb добавляется сам
        'connect_src' => array_filter(array_map(trim(...), explode(',', (string) env('CSP_CONNECT_SRC', '')))),
    ],

    'operations' => [
        // Сколько секунд работает один шаг фоновой операции, прежде чем поставить в очередь продолжение
        'step_seconds' => (float) env('OPERATION_STEP_SECONDS', 20),

        // Таймаут задачи: шаг плюс худший случай одного запроса к хранилищу (с повтором) плюс запас.
        // Должен быть меньше retry_after очереди (REDIS_QUEUE_RETRY_AFTER), иначе задачу запустят второй раз параллельно
        'job_timeout' => (int) env('OPERATION_JOB_TIMEOUT', 150),

        // Сколько раз подряд продолжать операцию после того, как шаг не уложился в таймаут
        'max_timeouts' => (int) env('OPERATION_MAX_TIMEOUTS', 3),

        // Активная операция без прогресса дольше этого считается прерванной (воркер упал или контейнер перезапущен)
        'stale_minutes' => (int) env('OPERATION_STALE_MINUTES', 30),
    ],

];

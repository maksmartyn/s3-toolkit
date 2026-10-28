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

    'operations' => [
        // Сколько секунд работает один шаг фоновой операции, прежде чем поставить в очередь продолжение
        'step_seconds' => (float) env('OPERATION_STEP_SECONDS', 20),
    ],

];

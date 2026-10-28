<?php

return [

    'storage' => [
        // Разрешить endpoint во внутренних сетях (10/8, 192.168/16, localhost и т.п.).
        // Нужно для MinIO в локальной сети; для публичного SaaS выключить, чтобы закрыть SSRF.
        'allow_private_endpoints' => (bool) env('STORAGE_ALLOW_PRIVATE_ENDPOINTS', true),

        'connect_timeout' => (float) env('STORAGE_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('STORAGE_TIMEOUT', 30),
    ],

];

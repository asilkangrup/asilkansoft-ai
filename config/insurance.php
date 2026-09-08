<?php

return [
    'open' => [
        'base_url' => env('OPEN_HIZLI_TEKLIF_BASE_URL', 'https://webservis.openyazilim.com'),
        'acente_kodu' => env('OPEN_HIZLI_TEKLIF_ACENTE_KODU'),
        'token' => env('OPEN_HIZLI_TEKLIF_TOKEN'),
        'timeout' => (int) env('OPEN_HIZLI_TEKLIF_TIMEOUT', 20),
    ],
];

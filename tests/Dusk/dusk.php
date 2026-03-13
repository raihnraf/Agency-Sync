<?php

use Laravel\Dusk\Browser;

return [
    'base_url' => env('DUSK_BASE_URL', 'http://localhost:8000'),
    'chrome_driver' => env('DUSK_CHROME_DRIVER', null),
];

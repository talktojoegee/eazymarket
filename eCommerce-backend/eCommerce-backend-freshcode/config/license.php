<?php

return [
    // License validation endpoint
    'endpoint' => env('LICENSE_ENDPOINT', 'https://validator.infinitietech.com/home/validator'),

    // How often to revalidate the license with the remote server (in minutes)
    'recheck_minutes' => (int) env('LICENSE_RECHECK_MINUTES', 720), // default 12 hours

    // HTTP timeouts (seconds). Short so an unreachable validator never hangs a page.
    'timeout' => (float) env('LICENSE_TIMEOUT', 5),
    'connect_timeout' => (float) env('LICENSE_CONNECT_TIMEOUT', 5),

    'unavailable_retry_minutes' => (int) env('LICENSE_UNAVAILABLE_RETRY_MINUTES', 60),

    'allow_offline_activation' => (bool) env('LICENSE_ALLOW_OFFLINE_ACTIVATION', true),

];

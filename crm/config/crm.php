<?php

return [
    // Comma-separated IPs or ranges allowed to open the CRM, e.g. "203.0.113.10,198.51.100.0/24". Empty = anyone with a login.
    'allowed_ips' => env('CRM_ALLOWED_IPS', ''),
];

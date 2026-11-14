<?php

return [
    // Comma-separated IPs or ranges allowed to open the CRM, e.g. "203.0.113.10,198.51.100.0/24". Empty = anyone with a login.
    'allowed_ips' => env('CRM_ALLOWED_IPS', ''),

    // A secret word written in .env before the first visit. The setup page asks for it, so nobody who
    // finds the address before you can make themselves admin. Not needed after setup.
    'setup_code' => env('CRM_SETUP_CODE', ''),
];

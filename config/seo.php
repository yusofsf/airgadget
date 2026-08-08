<?php

return [
    // All canonical signals must use one HTTPS hostname.
    'canonical_url' => rtrim(env('SEO_CANONICAL_URL', 'https://airgadget.ir'), '/'),
];

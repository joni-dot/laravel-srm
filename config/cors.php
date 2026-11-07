<?php

/*
 * The API serves other servers, not web pages on other sites, so no route allows cross-origin browser requests.
 * Laravel's default would allow any website to call api/* from a browser.
 */
return [

    'paths' => [],

    'allowed_methods' => [],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

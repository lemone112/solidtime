<?php

declare(strict_types=1);

return [
    'gotenberg' => [
        'url' => env('GOTENBERG_URL'),
        'basic_auth_username' => env('GOTENBERG_BASIC_AUTH_USERNAME'),
        'basic_auth_password' => env('GOTENBERG_BASIC_AUTH_PASSWORD'),
    ],

    'oidc' => [
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'redirect' => env('OIDC_REDIRECT_URI'),
        // Base URL of the LabPics ID OIDC provider.
        // Discovery document is fetched from: {host}/identity/.well-known/openid-configuration
        'host' => env('OIDC_HOST', 'https://auth.lab.pics'),
        // Optional: UUID of the default organization to auto-join on first SSO login.
        // When set, the user is added with the role mapped from the OIDC `role` claim.
        'default_org_id' => env('OIDC_DEFAULT_ORG_ID'),
    ],
];

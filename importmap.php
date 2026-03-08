<?php

return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'bootstrap' => [
        'path' => './assets/bootstrap.js',
    ],
    'haptics' => [
        'path' => './assets/haptics.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
    'alpinejs' => [
        'version' => '3.14.9',
    ],
    'web-haptics' => [
        'path' => './assets/vendor/web-haptics.js',
    ],
];

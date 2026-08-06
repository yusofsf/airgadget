<?php

test('storefront exposes installable pwa metadata', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('/manifest.webmanifest', false)
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee('theme-color', false);

    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->toHaveKey('name')
        ->toHaveKey('start_url', '/?source=pwa')
        ->toHaveKey('scope', '/')
        ->toHaveKey('display', 'standalone')
        ->and($manifest['icons'])->toHaveCount(4);
});

test('pwa service worker and required icons are present', function () {
    foreach ([
        'sw.js',
        'offline.html',
        'pwa/icon-192.png',
        'pwa/icon-512.png',
        'pwa/icon-maskable-192.png',
        'pwa/icon-maskable-512.png',
        'pwa/apple-touch-icon.png',
    ] as $asset) {
        expect(public_path($asset))->toBeFile();
    }

    expect(file_get_contents(public_path('sw.js')))
        ->toContain("caches.match('/offline.html')")
        ->toContain("request.method !== 'GET'");
});

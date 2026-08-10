<?php

test('responses include baseline browser security headers', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Content-Security-Policy');
});

test('production http requests are redirected to https', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('http://airgadget.test/login')
        ->assertRedirect('https://airgadget.test/login')
        ->assertStatus(301);
});

test('secure responses include hsts', function () {
    $this->get('https://airgadget.test/')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

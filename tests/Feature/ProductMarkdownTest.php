<?php

test('a product with a markdown profile is served as text/markdown', function () {
    $response = $this->get('/ultraschallgeraete/mobile-geraete/mindray-dp-30.md');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
    $response->assertSeeText('Mindray DP-30');
});

test('a product without a markdown profile returns 404', function () {
    $response = $this->get('/ultraschallgeraete/mobile-geraete/mindray-mu7.md');

    $response->assertNotFound();
});

test('an unknown ultraschallgeraete path returns 404', function () {
    $response = $this->get('/ultraschallgeraete/does-not-exist.md');

    $response->assertNotFound();
});

test('path traversal attempts are rejected', function () {
    $response = $this->get('/ultraschallgeraete/..%2f..%2f..%2f.env.md');

    $response->assertNotFound();
});

<?php

use Illuminate\Support\Facades\Storage;

it('reports a healthy API with every check passing', function () {
    Storage::fake('public');

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.status', 'ok')
        ->assertJsonPath('checks.cache.status', 'ok')
        ->assertJsonPath('checks.storage.status', 'ok')
        ->assertJsonStructure(['status', 'app', 'version', 'environment', 'time', 'checks' => ['database' => ['ms', 'categories', 'library_dishes']]]);
});

it('answers 503 when a dependency fails', function () {
    config(['handi.media_disk' => 'missing-disk']);

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.storage.status', 'fail')
        ->assertJsonPath('checks.database.status', 'ok');
});

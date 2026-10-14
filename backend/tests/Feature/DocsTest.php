<?php

use App\Models\User;

it('keeps the API docs for super admins outside local', function () {
    $this->get('/docs/api.json')->assertForbidden();

    $admin = User::factory()->create(['is_admin' => true]);
    $json = $this->actingAs($admin)->get('/docs/api.json')->assertOk()->json();

    expect($json['paths'])->toHaveKey('/v1/auth/login')
        ->and(count($json['paths']))->toBeGreaterThan(40);
});

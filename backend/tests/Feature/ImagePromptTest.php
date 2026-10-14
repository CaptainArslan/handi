<?php

use App\Filament\Pages\AiPhotoPrompt;
use App\Models\User;
use App\Support\ImagePrompt;
use Livewire\Livewire;

function promptAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('serves the default prompt publicly', function () {
    $res = $this->getJson('/api/v1/image-prompt')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.placeholder', '{{dish_name}}')
        ->assertJsonPath('data.ai_name', 'Gemini')
        ->assertJsonPath('data.ai_url', 'https://gemini.google.com/app');

    $template = $res->json('data.template');
    expect($template)->toContain('{{dish_name}}')
        ->and(json_decode(str_replace('{{dish_name}}', 'Nihari', $template), true))->toBeArray()
        ->and($res->json('data.intro'))->toContain('{{dish_name}}');
});

it('fills the dish name in safely', function () {
    $text = ImagePrompt::render('Aunt "Sara" Haleem');
    $json = substr($text, strpos($text, '{'));

    expect(json_decode($json, true)['metadata']['dish_name'])->toBe('Aunt "Sara" Haleem')
        ->and($text)->toStartWith('Create one square image of Aunt "Sara" Haleem');
});

it('lets an admin edit the prompt and the app sees it', function () {
    $template = json_encode(['dish' => '{{dish_name}}', 'style' => 'flat']);

    Livewire::actingAs(promptAdmin())
        ->test(AiPhotoPrompt::class)
        ->fillForm(['enabled' => false, 'intro' => 'Draw {{dish_name}}.', 'template' => $template, 'ai_name' => 'Gemini', 'ai_url' => 'https://gemini.google.com/app'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->getJson('/api/v1/image-prompt')
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.intro', 'Draw {{dish_name}}.')
        ->assertJsonPath('data.template', "{\n    \"dish\": \"{{dish_name}}\",\n    \"style\": \"flat\"\n}");
});

it('rejects a template that is not JSON or has no dish placeholder', function (string $template) {
    Livewire::actingAs(promptAdmin())
        ->test(AiPhotoPrompt::class)
        ->fillForm(['template' => $template])
        ->call('save')
        ->assertHasFormErrors(['template']);
})->with([
    'broken json' => '{"dish": "{{dish_name}}",}',
    'no placeholder' => '{"dish": "Nihari"}',
]);

it('resets to the default', function () {
    ImagePrompt::save(['enabled' => false, 'intro' => 'x {{dish_name}}', 'template' => '{"a":"{{dish_name}}"}', 'ai_name' => 'X', 'ai_url' => 'https://example.com']);

    Livewire::actingAs(promptAdmin())->test(AiPhotoPrompt::class)->callAction('reset');

    expect(ImagePrompt::get())->toMatchArray(['enabled' => true, 'ai_url' => 'https://gemini.google.com/app', 'template' => ImagePrompt::defaultTemplate()]);
});

it('renders the admin page', function () {
    $this->actingAs(promptAdmin())->get('/admin/ai-photo-prompt')->assertOk()->assertSee('AI photo prompt');
});

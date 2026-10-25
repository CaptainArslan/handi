<?php

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Mail\ContactMessageMail;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Models\User;
use App\Services\ContactService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
});

function contactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ali Khan',
        'email' => 'Ali@Example.com',
        'topic' => 'problem',
        'message' => 'The reminder did not arrive this morning.',
    ], $overrides);
}

it('sends a message from a signed-out visitor to the team', function () {
    $this->postJson('/api/v1/contact', contactPayload())
        ->assertCreated()
        ->assertJsonPath('message', 'Thanks! Your message was sent. We usually reply within one working day.');

    $m = ContactMessage::sole();
    expect($m->email)->toBe('ali@example.com')->and($m->user_id)->toBeNull()->and($m->mailed_at)->not->toBeNull();

    Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
        return $mail->hasTo(config('handi.contact.to'))
            && $mail->hasReplyTo('ali@example.com')
            && str_contains($mail->envelope()->subject, 'Something isn’t working')
            && str_contains($mail->render(), 'The reminder did not arrive this morning.');
    });
});

it('links the message to the account when signed in', function () {
    $user = appUser();

    $this->actingAs($user)->postJson('/api/v1/contact', contactPayload(['topic' => 'account', 'app_version' => '1.0.2', 'device' => 'Pixel 7 · Android 14']))
        ->assertCreated();

    $m = ContactMessage::sole();
    expect($m->user_id)->toBe($user->id)->and($m->device)->toBe('Pixel 7 · Android 14')->and($m->app_version)->toBe('1.0.2');
    $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'event' => 'contact.sent']);
});

it('accepts and emails an attachment up to 2 MB', function () {
    $file = UploadedFile::fake()->create('screenshot.png', 1500, 'image/png');

    $this->post('/api/v1/contact', contactPayload(['attachment' => $file]), ['Accept' => 'application/json'])->assertCreated();

    $m = ContactMessage::sole();
    expect($m->attachment_name)->toBe('screenshot.png')->and($m->attachment_size)->toBe(1500 * 1024);
    Storage::disk('local')->assertExists($m->attachment_path);
    Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $mail) => count($mail->attachments()) === 1);
});

it('rejects files over 2 MB and unsupported types', function () {
    $this->post('/api/v1/contact', contactPayload(['attachment' => UploadedFile::fake()->create('big.pdf', 2100, 'application/pdf')]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('errors.attachment.0', 'Files can be up to 2 MB.');

    $this->post('/api/v1/contact', contactPayload(['attachment' => UploadedFile::fake()->create('run.exe', 10, 'application/octet-stream')]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('attachment');

    expect(ContactMessage::count())->toBe(0);
    Mail::assertNothingSent();
});

it('rejects a disallowed file name even when its contents look like text', function () {
    $this->post('/api/v1/contact', contactPayload(['attachment' => UploadedFile::fake()->createWithContent('virus.exe', 'just text inside')]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('errors.attachment.0', 'Attach an image, PDF, TXT or Word file.');
    expect(ContactMessage::count())->toBe(0);
});

it('does not count failed attempts toward the hourly limit', function () {
    foreach (range(1, 8) as $i) {
        $this->postJson('/api/v1/contact', contactPayload(['message' => 'short']))->assertUnprocessable();
    }
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/contact', contactPayload())->assertCreated();
    }
    $this->postJson('/api/v1/contact', contactPayload())->assertTooManyRequests();
    expect(ContactMessage::count())->toBe(5);
});

it('validates the form', function () {
    $this->postJson('/api/v1/contact', ['topic' => 'nope', 'message' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'topic', 'message']);
});

it('drops honeypot spam without emailing', function () {
    $this->postJson('/api/v1/contact', contactPayload(['website' => 'http://spam.test']))->assertCreated();

    expect(ContactMessage::count())->toBe(0);
    Mail::assertNothingSent();
});

it('keeps the message when the email fails', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    $this->postJson('/api/v1/contact', contactPayload())->assertCreated();

    expect(ContactMessage::sole()->mailed_at)->toBeNull();
});

it('limits messages to 5 an hour', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/contact', contactPayload())->assertCreated();
    }
    $this->postJson('/api/v1/contact', contactPayload())->assertTooManyRequests();
});

it('renders the contact email in the Handi design', function () {
    $m = ContactMessage::create(contactPayload(['email' => 'ali@example.com', 'message' => "Line one\nLine <two>", 'device' => 'Android app']));
    $html = (new ContactMessageMail($m))->render();

    expect($html)->toContain('images/brand/handi-email-logo.png')->toContain('Ali Khan')->toContain('Line one<br />')
        ->toContain('Line &lt;two&gt;')->toContain('/admin/contact-messages/'.$m->id)->toContain('Reply as Handi team')->not->toContain('Reply to Ali');
});

it('shows messages in the admin panel and marks them read when opened', function () {
    $m = ContactMessage::create(contactPayload(['email' => 'ali@example.com']));
    $admin = User::factory()->create(['is_admin' => true, 'email' => 'boss@handi.test']);

    $this->actingAs($admin)->get('/admin/contact-messages')->assertOk()->assertSee('Ali Khan');
    $this->actingAs($admin)->get("/admin/contact-messages/{$m->id}")->assertOk()->assertSee('The reminder did not arrive this morning.');

    expect($m->fresh()->read_at)->not->toBeNull();
});

describe('admin inbox statuses', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['is_admin' => true, 'email' => 'boss@handi.test']);
        $this->actingAs($this->admin);
    });

    it('starts every message as pending', function () {
        $this->postJson('/api/v1/contact', contactPayload())->assertCreated();

        expect(ContactMessage::sole()->status)->toBe(ContactStatus::Pending);
    });

    it('shows status tabs with open messages first', function () {
        $pending = ContactMessage::create(contactPayload(['email' => 'a@example.com']));
        $done = ContactMessage::create(contactPayload(['email' => 'b@example.com', 'status' => 'resolved']));

        Livewire::test(ListContactMessages::class)
            ->assertCanSeeTableRecords([$pending])->assertCanNotSeeTableRecords([$done])
            ->set('activeTab', 'resolved')
            ->assertCanSeeTableRecords([$done])->assertCanNotSeeTableRecords([$pending]);
    });

    it('marks a message resolved and remembers who did it', function () {
        $m = ContactMessage::create(contactPayload(['email' => 'a@example.com']));

        Livewire::test(ViewContactMessage::class, ['record' => $m->getRouteKey()])
            ->callAction('resolve')->assertHasNoActionErrors();

        $m->refresh();
        expect($m->status)->toBe(ContactStatus::Resolved)
            ->and($m->resolved_by)->toBe($this->admin->id)->and($m->resolved_at)->not->toBeNull();
        $this->assertDatabaseHas('activity_logs', ['event' => 'admin.contact_status', 'user_id' => $this->admin->id]);
    });

    it('changes status with a team note and clears resolution on reopen', function () {
        $m = ContactMessage::create(contactPayload(['email' => 'a@example.com', 'status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $this->admin->id]));

        Livewire::test(ViewContactMessage::class, ['record' => $m->getRouteKey()])
            ->callAction('status', ['status' => 'waiting_on_user', 'admin_note' => 'Asked for a screenshot'])
            ->assertHasNoActionErrors();

        $m->refresh();
        expect($m->status)->toBe(ContactStatus::WaitingOnUser)->and($m->admin_note)->toBe('Asked for a screenshot')
            ->and($m->resolved_at)->toBeNull()->and($m->resolved_by)->toBeNull();
    });

    it('changes many messages at once', function () {
        $a = ContactMessage::create(contactPayload(['email' => 'a@example.com']));
        $b = ContactMessage::create(contactPayload(['email' => 'b@example.com']));

        Livewire::test(ListContactMessages::class)
            ->callTableBulkAction('mark_spam', [$a, $b]);

        expect(ContactMessage::where('status', 'spam')->count())->toBe(2);
    });
});

describe('website contact page', function () {
    it('shows the form with every topic and no session cookie', function () {
        $this->get('/contact')->assertOk()
            ->assertSee('Send us a message')->assertSee('Something isn’t working')->assertSee('name="attachment"', false)
            ->assertCookieMissing('laravel_session');
    });

    it('sends the message and redirects to the thank-you state', function () {
        $this->post('/contact', contactPayload(['attachment' => UploadedFile::fake()->create('photo.jpg', 300, 'image/jpeg')]))
            ->assertStatus(303)->assertRedirect(route('site.contact', ['sent' => 1]).'#form');

        $m = ContactMessage::sole();
        expect($m->user_id)->toBeNull()->and($m->attachment_name)->toBe('photo.jpg')->and($m->device)->toBe('Website');
        Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo(config('handi.contact.to')));

        $this->get('/contact?sent=1')->assertOk()->assertSee('Message sent');
    });

    it('shows errors on the page and keeps what was typed', function () {
        $this->post('/contact', ['name' => 'Ali Khan', 'email' => 'not-an-email', 'message' => 'short'])
            ->assertStatus(422)
            ->assertSee('Enter a valid email, like name@example.com.')
            ->assertSee('Choose what it’s about.')
            ->assertSee('Tell us a little more (at least 10 characters).')
            ->assertSee('value="Ali Khan"', false);

        expect(ContactMessage::count())->toBe(0);
    });

    it('rejects files over 2 MB on the website too', function () {
        $this->post('/contact', contactPayload(['attachment' => UploadedFile::fake()->create('big.pdf', 2100, 'application/pdf')]))
            ->assertStatus(422)->assertSee('Files can be up to 2 MB.');
    });

    it('shows a friendly page after too many messages', function () {
        foreach (range(1, 5) as $i) {
            $this->post('/contact', contactPayload())->assertStatus(303);
        }
        $this->post('/contact', contactPayload())->assertTooManyRequests()->assertSee('Slow down a little');
    });
});

describe('admin replies and every inbox action', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['is_admin' => true, 'email' => 'boss@handi.test', 'name' => 'Arslan']);
        $this->actingAs($this->admin);
        $this->msg = ContactMessage::create(contactPayload(['email' => 'ali@example.com', 'message' => "Reminder did not come.\nPlease check."]));
    });

    function viewPage(ContactMessage $m)
    {
        return Livewire::test(ViewContactMessage::class, ['record' => $m->getRouteKey()]);
    }

    it('sends a branded reply email, saves it and moves to waiting on user', function () {
        viewPage($this->msg)
            ->callAction('reply', ['subject' => 'Re: Your reminder', 'body' => "Hi Ali,\nPlease turn off battery saver.", 'then' => 'waiting_on_user'])
            ->assertHasNoActionErrors()->assertNotified('Reply sent to ali@example.com');

        $reply = $this->msg->replies()->sole();
        expect($reply->mailed_at)->not->toBeNull()->and($reply->user_id)->toBe($this->admin->id)
            ->and($this->msg->fresh()->status)->toBe(ContactStatus::WaitingOnUser);

        Mail::assertSent(ContactReplyMail::class, function ($mail) {
            $html = $mail->render();

            return $mail->hasTo('ali@example.com') && $mail->hasReplyTo(config('handi.contact.reply_to'))
                && $mail->envelope()->subject === 'Re: Your reminder'
                && str_contains($html, 'Hi Ali,') && str_contains($html, 'Please turn off battery saver.')
                && str_contains($html, 'Reminder did not come.') && str_contains($html, 'The Handi team') && ! str_contains($html, 'Arslan');
        });
        $this->assertDatabaseHas('activity_logs', ['event' => 'admin.contact_replied']);
    });

    it('can reply and resolve in one go, or keep the status', function () {
        viewPage($this->msg)->callAction('reply', ['subject' => 'Re', 'body' => 'Fixed now.', 'then' => 'resolved'])->assertHasNoActionErrors();
        expect($this->msg->fresh()->status)->toBe(ContactStatus::Resolved)->and($this->msg->fresh()->resolved_by)->toBe($this->admin->id);

        $other = ContactMessage::create(contactPayload(['email' => 'b@example.com', 'status' => 'in_progress']));
        viewPage($other)->callAction('reply', ['subject' => 'Re', 'body' => 'Looking into it.', 'then' => 'keep'])->assertHasNoActionErrors();
        expect($other->fresh()->status)->toBe(ContactStatus::InProgress)->and($other->replies()->count())->toBe(1);
    });

    it('requires a message to reply', function () {
        viewPage($this->msg)->callAction('reply', ['subject' => 'Re', 'body' => '', 'then' => 'keep'])->assertHasActionErrors(['body' => 'required']);
        expect($this->msg->replies()->count())->toBe(0);
        Mail::assertNotSent(ContactReplyMail::class);
    });

    it('keeps the reply and warns when the email fails, without changing the status', function () {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused'));

        viewPage($this->msg)->callAction('reply', ['subject' => 'Re', 'body' => 'Hello', 'then' => 'resolved'])->assertNotified('Reply saved but NOT emailed');

        $reply = $this->msg->replies()->sole();
        expect($reply->mailed_at)->toBeNull()->and($reply->mail_error)->toContain('Connection refused')
            ->and($this->msg->fresh()->status)->toBe(ContactStatus::Pending);
    });

    it('shows the reply history on the message page', function () {
        app(ContactService::class)->reply($this->msg, $this->admin, 'Re: hello', 'First answer from us', null);

        $this->get("/admin/contact-messages/{$this->msg->id}")->assertOk()->assertSee('First answer from us')->assertSee('Emailed')->assertSee('Arslan');
        $this->get('/admin/contact-messages')->assertOk()->assertSee('1 reply');
    });

    it('opens the email app only as a secondary option', function () {
        viewPage($this->msg)->assertActionHasUrl('mailApp', 'mailto:ali@example.com?subject='.rawurlencode('Re: Something isn’t working'));
    });

    it('hides reply on spam and lets you reopen', function () {
        viewPage($this->msg)->callAction('spam')->assertActionHidden('reply')->assertActionVisible('reopen')->callAction('reopen');
        expect($this->msg->fresh()->status)->toBe(ContactStatus::InProgress);
    });

    it('downloads the attachment', function () {
        $path = UploadedFile::fake()->create('shot.png', 10, 'image/png')->store('contact', 'local');
        $this->msg->update(['attachment_path' => $path, 'attachment_name' => 'shot.png', 'attachment_size' => 10240]);

        viewPage($this->msg)->assertActionVisible('download')->callAction('download')->assertFileDownloaded('shot.png');
    });

    it('deletes a message with its file and replies', function () {
        $path = UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')->store('contact', 'local');
        $this->msg->update(['attachment_path' => $path]);
        app(ContactService::class)->reply($this->msg, $this->admin, 'Re', 'Body', null);

        viewPage($this->msg)->callAction('delete');

        expect(ContactMessage::count())->toBe(0)->and(ContactReply::count())->toBe(0);
        Storage::disk('local')->assertMissing($path);
    });

    it('filters by unread, topic and attachment, and counts pending in the menu badge', function () {
        $read = ContactMessage::create(contactPayload(['email' => 'r@example.com', 'topic' => 'feedback', 'read_at' => now(), 'attachment_path' => 'contact/x.png', 'attachment_name' => 'x.png']));
        $list = Livewire::test(ListContactMessages::class);

        $list->filterTable('unread')->assertCanSeeTableRecords([$this->msg])->assertCanNotSeeTableRecords([$read]);
        $list->resetTableFilters()->filterTable('topic', 'feedback')->assertCanSeeTableRecords([$read])->assertCanNotSeeTableRecords([$this->msg]);
        $list->resetTableFilters()->filterTable('has_file')->assertCanSeeTableRecords([$read])->assertCanNotSeeTableRecords([$this->msg]);

        expect(ContactMessageResource::getNavigationBadge())->toBe('2');
    });

    it('searches by name, email and message text', function () {
        $other = ContactMessage::create(contactPayload(['name' => 'Zara', 'email' => 'zara@example.com', 'message' => 'Biryani suggestion please add']));

        Livewire::test(ListContactMessages::class)
            ->searchTable('Biryani')->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$this->msg])
            ->searchTable('ali@example')->assertCanSeeTableRecords([$this->msg])->assertCanNotSeeTableRecords([$other]);
    });

    it('blocks non-admins from the inbox', function () {
        $this->actingAs(appUser())->get('/admin/contact-messages')->assertForbidden();
    });
});

<?php

use App\Jobs\CreateInquiryInCas;
use App\Jobs\SendInquiryMails;
use App\Mail\ContactFormCompanyMail;
use App\Mail\ContactFormCustomerMail;
use App\Services\Cas\CasClient;
use App\Services\Cas\CasRequestFailedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.cas_genesis_world.host' => 'https://cas.example.test',
        'services.cas_genesis_world.username' => 'testuser',
        'services.cas_genesis_world.password' => 'secret',
        'services.cas_genesis_world.product_key' => 'test-product-key',
    ]);

    File::ensureDirectoryExists(storage_path('logs'));
    File::put(storage_path('logs/mail.log'), '');
    File::put(storage_path('logs/api.log'), '');
});

afterEach(function () {
    File::delete(storage_path('logs/mail.log'));
    File::delete(storage_path('logs/api.log'));
});

function validContactFormData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Dr. Max Mustermann',
        'email' => 'max@example.com',
        'telefon' => '0231123456',
        'plz' => '44269',
        'nachricht' => 'Ich interessiere mich für ein Gerät.',
        'praxis' => 'Praxis Mustermann',
        'fachgebiet' => 'Allgemeinmedizin / Hausarzt',
        'rueckruf' => 'ja',
        'rueckruf_datum' => now()->addDays(3)->format('Y-m-d'),
        'datenschutz' => 'ja',
    ], $overrides);
}

function inquiryJob(array $overrides = []): CreateInquiryInCas
{
    $data = array_merge([
        'name' => 'Dr. Max Mustermann',
        'email' => 'max@example.com',
        'telefon' => null,
        'plz' => '44269',
        'nachricht' => null,
        'praxis' => null,
        'fachgebiet' => null,
        'wantsCallback' => false,
        'rueckrufDatum' => null,
    ], $overrides);

    return new CreateInquiryInCas(...$data);
}

function mailJob(array $overrides = []): SendInquiryMails
{
    $data = array_merge([
        'name' => 'Dr. Max Mustermann',
        'email' => 'max@example.com',
        'telefon' => null,
        'plz' => '44269',
        'nachricht' => null,
        'praxis' => null,
        'fachgebiet' => null,
        'wantsCallback' => false,
        'rueckrufDatum' => null,
    ], $overrides);

    return new SendInquiryMails(...$data);
}

test('contact form requires name, email, plz and datenschutz consent', function () {
    Bus::fake();

    $response = $this->post(route('kontakt.store'), []);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['name', 'email', 'plz', 'datenschutz']);
    Bus::assertNothingDispatched();
});

test('valid submission dispatches CreateInquiryInCas and SendInquiryMails independently and redirects with a success flash', function () {
    Bus::fake();

    $response = $this->post(route('kontakt.store'), validContactFormData());

    $response->assertRedirect(route('kontakt'));
    $response->assertSessionHas('contactFormSubmitted', true);

    Bus::assertDispatched(CreateInquiryInCas::class, function (CreateInquiryInCas $job) {
        return $job->name === 'Dr. Max Mustermann'
            && $job->email === 'max@example.com'
            && $job->wantsCallback === true;
    });

    Bus::assertDispatched(SendInquiryMails::class, function (SendInquiryMails $job) {
        return $job->name === 'Dr. Max Mustermann'
            && $job->email === 'max@example.com'
            && $job->wantsCallback === true;
    });
});

test('CreateInquiryInCas creates the CAS record', function () {
    Http::fake([
        '*/v7.0/type/Inquiries*' => Http::response(['GGUID' => 'guid-123'], 200),
    ]);

    inquiryJob()->handle(app(CasClient::class));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v7.0/type/Inquiries')
            && $request['fields']['Name'] === 'Dr. Max Mustermann';
    });
});

test('CreateInquiryInCas does not fail when no GUID can be extracted from a successful response', function () {
    Http::fake([
        '*/v7.0/type/Inquiries*' => Http::response(['unexpected' => 'shape'], 200),
    ]);

    // The request itself succeeded - an unparsable GUID is only a logging
    // concern now that nothing downstream needs it, so handle() must not
    // throw or otherwise mark the job as failed.
    expect(fn () => inquiryJob()->handle(app(CasClient::class)))->not->toThrow(Throwable::class);
});

test('CreateInquiryInCas still reaches CAS when the configured host has no scheme', function () {
    config(['services.cas_genesis_world.host' => 'cas.example.test/genesisrest.svc']);

    Http::fake([
        'https://cas.example.test/genesisrest.svc/v7.0/type/Inquiries*' => Http::response(['GGUID' => 'guid-123'], 200),
    ]);

    inquiryJob()->handle(app(CasClient::class));

    Http::assertSent(fn ($request) => $request->url() === 'https://cas.example.test/genesisrest.svc/v7.0/type/Inquiries?tag-as-recently-used=false');
});

test('CreateInquiryInCas throws on a failed CAS request so the queue retries it', function () {
    Http::fake([
        '*/v7.0/type/Inquiries*' => Http::response('Service Unavailable', 503),
    ]);

    expect(fn () => inquiryJob()->handle(app(CasClient::class)))
        ->toThrow(CasRequestFailedException::class);
});

test('SendInquiryMails sends both mails and logs the submission', function () {
    Mail::fake();

    mailJob([
        'telefon' => '0231123456',
        'nachricht' => 'Testnachricht',
        'praxis' => 'Praxis Mustermann',
        'fachgebiet' => 'Allgemeinmedizin / Hausarzt',
        'wantsCallback' => true,
        'rueckrufDatum' => '2026-08-01',
    ])->handle();

    Mail::assertSent(ContactFormCompanyMail::class, function ($mail) {
        return $mail->hasTo((string) config('mail.from.address'))
            && $mail->hasReplyTo('max@example.com');
    });
    Mail::assertSent(ContactFormCustomerMail::class, function ($mail) {
        return $mail->hasTo('max@example.com')
            && $mail->name === 'Dr. Max Mustermann';
    });

    $logContent = file_get_contents(storage_path('logs/mail.log'));
    expect($logContent)
        ->toContain('Anfrage von Dr. Max Mustermann')
        ->toContain('Mailversand an max@example.com ✓');
});

test('SendInquiryMails failed() hook logs a failure line', function () {
    mailJob()->failed(new RuntimeException('SMTP down'));

    $logContent = file_get_contents(storage_path('logs/mail.log'));
    expect($logContent)->toContain('Mailversand an max@example.com ✗');
});

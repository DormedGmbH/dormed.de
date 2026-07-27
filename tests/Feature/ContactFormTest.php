<?php

use App\Jobs\CreateInquiryInCas;
use App\Jobs\SendInquiryMailsAndUpdateCasStatus;
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
        'services.cas_genesis_world.username' => 'testdb/testuser',
        'services.cas_genesis_world.password' => 'secret',
        'services.cas_genesis_world.product_key' => 'test-product-key',
    ]);

    File::ensureDirectoryExists(storage_path('logs'));
    File::put(storage_path('logs/contact-form.log'), '');
    File::put(storage_path('logs/api.log'), '');
});

afterEach(function () {
    File::delete(storage_path('logs/contact-form.log'));
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

test('contact form requires name, email, plz and datenschutz consent', function () {
    Bus::fake();

    $response = $this->post(route('kontakt.store'), []);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['name', 'email', 'plz', 'datenschutz']);
    Bus::assertNothingDispatched();
});

test('valid submission dispatches CreateInquiryInCas and redirects with a success flash', function () {
    Bus::fake();

    $response = $this->post(route('kontakt.store'), validContactFormData());

    $response->assertRedirect(route('kontakt'));
    $response->assertSessionHas('contactFormSubmitted', true);

    Bus::assertDispatched(CreateInquiryInCas::class, function (CreateInquiryInCas $job) {
        return $job->name === 'Dr. Max Mustermann'
            && $job->email === 'max@example.com'
            && $job->wantsCallback === true;
    });
});

test('CreateInquiryInCas creates the CAS record and dispatches the mail job with the returned GUID', function () {
    Bus::fake([SendInquiryMailsAndUpdateCasStatus::class]);

    Http::fake([
        '*/v7.0/type/Inquiries*' => Http::response(['GGUID' => 'guid-123'], 200),
    ]);

    inquiryJob()->handle(app(CasClient::class));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v7.0/type/Inquiries')
            && $request['Name'] === 'Dr. Max Mustermann';
    });

    Bus::assertDispatched(SendInquiryMailsAndUpdateCasStatus::class, function ($job) {
        return $job->guid === 'guid-123';
    });
});

test('CreateInquiryInCas fails permanently when no GUID can be extracted from a successful response', function () {
    Bus::fake([SendInquiryMailsAndUpdateCasStatus::class]);

    Http::fake([
        '*/v7.0/type/Inquiries*' => Http::response(['unexpected' => 'shape'], 200),
    ]);

    inquiryJob()->handle(app(CasClient::class));

    Bus::assertNotDispatched(SendInquiryMailsAndUpdateCasStatus::class);
});

test('CreateInquiryInCas throws on a failed CAS request so the queue retries it', function () {
    Http::fake([
        '*/v7.0/type/Inquiries*' => Http::response('Service Unavailable', 503),
    ]);

    expect(fn () => inquiryJob()->handle(app(CasClient::class)))
        ->toThrow(CasRequestFailedException::class);
});

test('SendInquiryMailsAndUpdateCasStatus sends both mails, logs the submission and updates CAS', function () {
    Mail::fake();
    Http::fake([
        '*/v7.0/type/Inquiries/guid-123*' => Http::response(['ok' => true], 200),
    ]);

    $job = new SendInquiryMailsAndUpdateCasStatus(
        guid: 'guid-123',
        name: 'Dr. Max Mustermann',
        email: 'max@example.com',
        telefon: '0231123456',
        plz: '44269',
        nachricht: 'Testnachricht',
        praxis: 'Praxis Mustermann',
        fachgebiet: 'Allgemeinmedizin / Hausarzt',
        wantsCallback: true,
        rueckrufDatum: '2026-08-01',
    );

    $job->handle(app(CasClient::class));

    Mail::assertSent(ContactFormCompanyMail::class, function ($mail) {
        return $mail->hasReplyTo('max@example.com');
    });
    Mail::assertSent(ContactFormCustomerMail::class, function ($mail) {
        return $mail->name === 'Dr. Max Mustermann';
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v7.0/type/Inquiries/guid-123')
            && $request->method() === 'PUT'
            && $request['MAIL_STATUS'] === 1;
    });

    $logContent = file_get_contents(storage_path('logs/contact-form.log'));
    expect($logContent)
        ->toContain('Anfrage von Dr. Max Mustermann')
        ->toContain('Mailversand an max@example.com ✓');
});

test('SendInquiryMailsAndUpdateCasStatus failed() hook logs a failure line and marks CAS mail status false', function () {
    Http::fake([
        '*/v7.0/type/Inquiries/guid-123*' => Http::response(['ok' => true], 200),
    ]);

    $job = new SendInquiryMailsAndUpdateCasStatus(
        guid: 'guid-123',
        name: 'Dr. Max Mustermann',
        email: 'max@example.com',
        telefon: null,
        plz: '44269',
        nachricht: null,
        praxis: null,
        fachgebiet: null,
        wantsCallback: false,
        rueckrufDatum: null,
    );

    $job->failed(new RuntimeException('SMTP down'));

    $logContent = file_get_contents(storage_path('logs/contact-form.log'));
    expect($logContent)->toContain('Mailversand an max@example.com ✗');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v7.0/type/Inquiries/guid-123')
            && $request->method() === 'PUT'
            && $request['MAIL_STATUS'] === 0;
    });
});

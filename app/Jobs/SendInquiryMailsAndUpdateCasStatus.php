<?php

namespace App\Jobs;

use App\Mail\ContactFormCompanyMail;
use App\Mail\ContactFormCustomerMail;
use App\Services\Cas\CasClient;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends both contact-form mails and records the outcome, both in
 * contact-form.log and back on the CRM record. Only dispatched by
 * CreateInquiryInCas after the CRM record already exists - never runs
 * (and so never sends mail) before that.
 *
 * Any mail failure lets the job retry as a whole (see retryUntil()) rather
 * than treating a single failed send as final - the accepted trade-off is
 * that a retry may re-send the company mail a second time if it already
 * went out but the customer mail then failed; a duplicate internal
 * notification is a much smaller problem than a lost inquiry.
 */
class SendInquiryMailsAndUpdateCasStatus implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $guid,
        public string $name,
        public string $email,
        public ?string $telefon,
        public string $plz,
        public ?string $nachricht,
        public ?string $praxis,
        public ?string $fachgebiet,
        public bool $wantsCallback,
        public ?string $rueckrufDatum,
    ) {}

    public function retryUntil(): DateTime
    {
        return now()->addHours(3);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 120, 300, 600];
    }

    public function handle(CasClient $client): void
    {
        Mail::send(new ContactFormCompanyMail(
            name: $this->name,
            email: $this->email,
            telefon: $this->telefon,
            plz: $this->plz,
            nachricht: $this->nachricht,
            praxis: $this->praxis,
            fachgebiet: $this->fachgebiet,
            wantsCallback: $this->wantsCallback,
            rueckrufDatum: $this->rueckrufDatum,
        ));

        Mail::send(new ContactFormCustomerMail(name: $this->name));

        $this->logSubmission(mailSucceeded: true);

        $client->updateDataObject('Inquiries', $this->guid, [
            'MAIL_STATUS' => true,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->logSubmission(mailSucceeded: false);

        Log::channel('api')->error('SendInquiryMailsAndUpdateCasStatus permanently failed.', [
            'guid' => $this->guid,
            'name' => $this->name,
            'exception' => $exception->getMessage(),
        ]);

        try {
            app(CasClient::class)->updateDataObject('Inquiries', $this->guid, [
                'MAIL_STATUS' => false,
            ]);
        } catch (Throwable) {
            // Best effort - we're already in the terminal failure path, and
            // the attempt itself is logged to api.log by the client.
        }
    }

    private function logSubmission(bool $mailSucceeded): void
    {
        $timestamp = now()->format('d.m.Y H:i:s');
        $status = $mailSucceeded ? '✓' : '✗';

        Log::channel('contact-form')->info(
            "[{$timestamp}] Anfrage von {$this->name} | Mailversand an {$this->email} {$status}"
        );
    }
}

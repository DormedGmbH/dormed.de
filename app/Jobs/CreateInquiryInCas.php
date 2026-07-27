<?php

namespace App\Jobs;

use App\Services\Cas\CasClient;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates the CRM record for a contact-form submission. Retried until
 * {@see retryUntil()} rather than a fixed attempt count, since the CAS
 * server has a nightly reboot window of roughly an hour and a submission
 * made right before it must still go through afterwards.
 *
 * No mail is sent until this succeeds - see SendInquiryMailsAndUpdateCasStatus,
 * which this job dispatches once it has a GUID.
 */
class CreateInquiryInCas implements ShouldQueue
{
    use Queueable;

    public function __construct(
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
        $guid = $client->createDataObject('Inquiries', [
            'Name' => $this->name,
            'MAIL' => $this->email,
            'PHONE' => $this->telefon,
            'ZIP' => $this->plz,
            'MESSAGE' => $this->nachricht,
            'COMPANY' => $this->praxis,
            'SPECIALTY' => $this->fachgebiet,
            'CALLBACK_REQUEST' => $this->wantsCallback,
            'CALLBACK_DATE' => $this->rueckrufDatum,
        ]);

        if ($guid === null) {
            // The request itself succeeded but the response shape didn't
            // match what we expected - retrying won't fix that, and would
            // just create duplicate records. CasClient already logged the
            // raw response to api.log for follow-up.
            $this->fail('CAS create succeeded but no GUID could be extracted from the response - see api.log.');

            return;
        }

        SendInquiryMailsAndUpdateCasStatus::dispatch(
            guid: $guid,
            name: $this->name,
            email: $this->email,
            telefon: $this->telefon,
            plz: $this->plz,
            nachricht: $this->nachricht,
            praxis: $this->praxis,
            fachgebiet: $this->fachgebiet,
            wantsCallback: $this->wantsCallback,
            rueckrufDatum: $this->rueckrufDatum,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('api')->error('CreateInquiryInCas permanently failed.', [
            'name' => $this->name,
            'email' => $this->email,
            'exception' => $exception->getMessage(),
        ]);
    }
}

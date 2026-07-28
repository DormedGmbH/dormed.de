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
 * Dispatched independently of SendInquiryMails - the two no longer share
 * any state (no GUID hand-off, no CAS status PUT), so nothing here blocks
 * or is blocked by the mail job.
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
        // The GUID is only used for logging (see CasClient::extractGuid) -
        // nothing downstream needs it anymore, so a response we can't parse
        // a GUID from still counts as success as long as the request itself
        // succeeded.
        $client->createDataObject('Inquiries', [
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

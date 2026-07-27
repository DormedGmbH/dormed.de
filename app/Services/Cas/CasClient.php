<?php

namespace App\Services\Cas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the CAS genesisWorld REST API (v7.0).
 *
 * Every request/response is logged to the `api` channel, since the API's own
 * OpenAPI documentation is incomplete for several endpoints (some payload/
 * response schemas are wrong or explicitly marked "currently undocumented")
 * - having the raw exchange on disk is what makes that workable in practice.
 */
class CasClient
{
    /**
     * Candidate field names for the GUID returned by a create call, tried in
     * order. "GGUID" matches the "dataObjectGGUID" naming used throughout
     * the API for existing records; the others are defensive fallbacks.
     *
     * @var list<string>
     */
    private const GUID_CANDIDATE_KEYS = ['GGUID', 'guid', 'Guid', 'id', 'Id'];

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withBasicAuth(
                (string) config('services.cas_genesis_world.username'),
                (string) config('services.cas_genesis_world.password'),
            )
            ->withHeaders([
                'X-CAS-PRODUCT-KEY' => (string) config('services.cas_genesis_world.product_key'),
            ])
            ->acceptJson()
            ->asJson();
    }

    /**
     * CAS_GENESIS_WORLD_HOST is meant to be entered with a scheme, but a
     * bare host/path (as configured once in production) silently breaks
     * every request with a Guzzle "URI must include a scheme and host"
     * error - defaulting to https here turns a config typo into a working
     * request instead of a hard failure.
     */
    private function baseUrl(): string
    {
        $host = rtrim((string) config('services.cas_genesis_world.host'), '/');

        return preg_match('#^https?://#i', $host) === 1 ? $host : "https://{$host}";
    }

    /**
     * Create a data object of the given type. Returns the new record's GUID,
     * or null if the call succeeded but no recognizable GUID field was found
     * in the response - a permanent schema mismatch, not something a retry
     * would fix. A failed HTTP call (network error, non-2xx) throws instead,
     * since that *is* worth retrying.
     *
     * @param  array<string, mixed>  $fields
     *
     * @throws CasRequestFailedException
     */
    public function createDataObject(string $dataObjectType, array $fields): ?string
    {
        // tag-as-recently-used is a required query param on this endpoint;
        // irrelevant for a background integration, so always false.
        $response = $this->client()
            ->post("/v7.0/type/{$dataObjectType}?tag-as-recently-used=false", $fields);

        $this->logExchange('POST', "/v7.0/type/{$dataObjectType}", $fields, $response);

        if ($response->failed()) {
            throw new CasRequestFailedException(
                "CAS create request for [{$dataObjectType}] failed with status {$response->status()}."
            );
        }

        return $this->extractGuid($response);
    }

    /**
     * Update an existing data object by GUID.
     *
     * @param  array<string, mixed>  $fields
     *
     * @throws CasRequestFailedException
     */
    public function updateDataObject(string $dataObjectType, string $guid, array $fields): void
    {
        $response = $this->client()->put("/v7.0/type/{$dataObjectType}/{$guid}", $fields);

        $this->logExchange('PUT', "/v7.0/type/{$dataObjectType}/{$guid}", $fields, $response);

        if ($response->failed()) {
            throw new CasRequestFailedException(
                "CAS update request for [{$dataObjectType}/{$guid}] failed with status {$response->status()}."
            );
        }
    }

    private function extractGuid(Response $response): ?string
    {
        $body = $response->json();

        if (! is_array($body)) {
            Log::channel('api')->warning('CAS create response was not a JSON object, cannot extract GUID.', [
                'body' => $response->body(),
            ]);

            return null;
        }

        foreach (self::GUID_CANDIDATE_KEYS as $key) {
            if (is_string($body[$key] ?? null) && $body[$key] !== '') {
                return $body[$key];
            }
        }

        Log::channel('api')->warning('CAS create response did not contain a recognizable GUID field.', [
            'triedKeys' => self::GUID_CANDIDATE_KEYS,
            'body' => $body,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logExchange(string $method, string $path, array $payload, Response $response): void
    {
        Log::channel('api')->info("CAS {$method} {$path}", [
            'request' => $payload,
            'status' => $response->status(),
            'response' => $response->json() ?? $response->body(),
        ]);
    }
}

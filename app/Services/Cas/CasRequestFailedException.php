<?php

namespace App\Services\Cas;

use RuntimeException;

/**
 * The CAS API request itself failed (network error, timeout, non-2xx
 * response) - a transient problem, safe and expected to be retried by the
 * calling job. Distinct from a successful-but-unparseable response, which
 * is a permanent schema mismatch and must never be retried blindly.
 */
class CasRequestFailedException extends RuntimeException {}

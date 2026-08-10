<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Raised when a supported provider webhook cannot be matched to a local payment.
 *
 * The event is kept in a failed state so the provider retries it and an administrator
 * can replay it once the local record exists.
 */
class UnresolvedWebhookPaymentException extends RuntimeException {}

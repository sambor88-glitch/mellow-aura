<?php

namespace App\Modules\Payments\Przelewy24;

use RuntimeException;

/**
 * Przelewy24 did not answer, or answered without what we asked for. The message is for Kasia's alert,
 * never for the customer.
 */
class Przelewy24Exception extends RuntimeException {}

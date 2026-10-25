<?php

namespace App\Modules\Shipping\Exceptions;

use RuntimeException;

/**
 * ShipX did not answer, or answered with an error, when asked about a parcel. Nothing was changed.
 */
class ShipXUnavailable extends RuntimeException {}

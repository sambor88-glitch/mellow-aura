<?php

namespace App\Modules\Shipping\Exceptions;

use RuntimeException;

/**
 * ShipX did not take the parcel or could not be reached. The message is a sentence for Kasia in the panel.
 */
class ShipmentRefused extends RuntimeException {}

<?php

namespace App\Modules\Content\Exceptions;

use RuntimeException;

/**
 * The feed could not be read. The message is shown to Kasia in the panel, so it says what to check.
 */
class InstagramFeedUnavailable extends RuntimeException {}

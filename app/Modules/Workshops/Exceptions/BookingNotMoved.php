<?php

namespace App\Modules\Workshops\Exceptions;

use RuntimeException;

/**
 * A move Actions\MoveBooking refuses. The message is the sentence Kasia reads in the panel; nothing of the move is saved.
 */
class BookingNotMoved extends RuntimeException {}

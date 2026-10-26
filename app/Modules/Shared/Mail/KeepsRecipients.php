<?php

namespace App\Modules\Shared\Mail;

/**
 * A mail that keeps its own recipients when staging sends every other mail to one inbox, such as a technical alert
 * meant for the person who looks after the shop.
 */
interface KeepsRecipients {}

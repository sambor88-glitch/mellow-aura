<?php

namespace App\Modules\Shared\Mail;

use Illuminate\Mail\Mailable;

/**
 * A customer mail with sentences Kasia rewrites in the panel. The module lists it in its provider's
 * $customerMails, and the panel shows it under „Maile”.
 */
interface HasEditableTexts
{
    public static function editableTexts(): EditableTexts;

    /**
     * The mail for made-up data, nothing saved, for the preview in the panel.
     */
    public static function sample(string $locale): Mailable;
}

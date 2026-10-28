<?php

namespace App\Modules\Shared\Mail;

/**
 * Which sentences of one customer mail Kasia rewrites in the panel („Maile”, MA-239). The layout, the amounts
 * and the order details stay in the template; an empty field sends the default text from `lang`.
 */
final readonly class EditableTexts
{
    /**
     * @param  string  $key  part of the setting name: mail_<key>_<part>
     * @param  string  $label  the mail as Kasia names it on the list
     * @param  string  $when  when the mail goes out, in her words
     * @param  string  $lang  where the defaults live, e.g. checkout::mail-texts.order_confirmed
     * @param  list<string>  $parts  subject, intro, outro — keys of MailTexts::PARTS
     * @param  array<string, string>  $placeholders  what she may type in braces, e.g. ['numer' => 'numer zamówienia']
     * @param  list<string>  $locales  languages the mail has a template in
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $when,
        public string $lang,
        public array $parts = ['subject', 'intro', 'outro'],
        public array $placeholders = [],
        public array $locales = ['pl'],
    ) {}
}

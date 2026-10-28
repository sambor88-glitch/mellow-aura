<?php

namespace App\Modules\Shared\Mail;

use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use Closure;
use Illuminate\Support\HtmlString;

/**
 * The sentences of customer mails that Kasia writes in the panel („Maile”, MA-239): per mail the subject, the
 * opening and the closing sentence, and one signature and footer for all of them. Each in the language the mail
 * is rendered in; an empty field gives the default from `lang`. Plain text only: whatever she types is escaped,
 * a line break stays a line break, and {numer}-style placeholders fill in from the mail's own data.
 */
class MailTexts
{
    /**
     * Each sentence of a mail with the most characters and lines it takes.
     *
     * @var array<string, array{int, int}>
     */
    public const PARTS = ['subject' => [120, 1], 'intro' => [400, 4], 'outro' => [300, 3]];

    /**
     * Shared by every customer mail.
     *
     * @var array<string, array{int, int}>
     */
    public const COMMON = ['signature' => [60, 2], 'footer' => [200, 3]];

    /** @var list<class-string<HasEditableTexts>> */
    private static array $mails = [];

    /**
     * Typed in the panel and not saved yet, for the preview: setting => value.
     *
     * @var array<string, ?string>
     */
    private array $typed = [];

    public function __construct(private Settings $settings) {}

    /**
     * @param  class-string<HasEditableTexts>  ...$mails
     */
    public static function register(string ...$mails): void
    {
        self::$mails = array_values(array_unique([...self::$mails, ...$mails]));
    }

    /**
     * @return array<string, EditableTexts> by key, in the order the modules registered them
     */
    public static function mails(): array
    {
        return collect(self::$mails)
            ->map(fn (string $mail) => $mail::editableTexts())
            ->keyBy('key')
            ->all();
    }

    /**
     * @return class-string<HasEditableTexts>|null
     */
    public static function mailClass(string $key): ?string
    {
        return collect(self::$mails)->first(fn (string $mail) => $mail::editableTexts()->key === $key);
    }

    /**
     * mail_order_confirmed_intro, mail_order_confirmed_intro_en; mail_signature without a mail.
     */
    public static function setting(string $part, string $locale, ?string $mail = null): string
    {
        return 'mail_'.($mail !== null ? $mail.'_' : '').$part.($locale === Locales::default() ? '' : '_'.$locale);
    }

    /**
     * The text an empty field sends, in the current language.
     */
    public function default(string $part, ?string $mail = null): string
    {
        if ($mail !== null) {
            return __(self::mails()[$mail]->lang.'.'.$part);
        }

        if ($part === 'footer') {
            $city = $this->settings->get('footer_city');

            return 'MellowAura'.($city ? ' · '.$city : '');
        }

        return __('shared::mail.'.$part);
    }

    /**
     * The sentence as Kasia wrote it, or the default, with placeholders still in braces.
     */
    public function raw(string $part, ?string $mail = null): string
    {
        $key = self::setting($part, app()->getLocale(), $mail);
        $value = array_key_exists($key, $this->typed) ? $this->typed[$key] : $this->settings->get($key);

        return filled($value) ? (string) $value : $this->default($part, $mail);
    }

    /**
     * For a subject or the plain-text part.
     *
     * @param  array<string, string>  $fill  placeholder => value
     */
    public function text(string $mail, string $part, array $fill = []): string
    {
        return strtr($this->raw($part, $mail), self::tokens($fill));
    }

    /**
     * For the HTML part: escaped, line breaks kept, filled-in values in bold.
     *
     * @param  array<string, string>  $fill  placeholder => value
     */
    public function html(string $mail, string $part, array $fill = []): HtmlString
    {
        $values = array_map(fn (string $value) => '<strong style="font-weight: 600;">'.e($value).'</strong>', self::tokens($fill));

        return new HtmlString(nl2br(strtr(e($this->raw($part, $mail)), $values), false));
    }

    /**
     * The signature or the footer for the HTML part: escaped, line breaks kept.
     */
    public function common(string $part): HtmlString
    {
        return new HtmlString(nl2br(e($this->raw($part)), false));
    }

    public function signature(): string
    {
        return $this->raw('signature');
    }

    public function footer(): string
    {
        return $this->raw('footer');
    }

    /**
     * Renders with the texts typed in the panel in place of the saved ones, without saving them.
     *
     * @template T
     *
     * @param  array<string, ?string>  $typed  setting => value
     * @param  Closure(): T  $render
     * @return T
     */
    public function previewing(array $typed, Closure $render): mixed
    {
        $this->typed = $typed;

        try {
            return $render();
        } finally {
            $this->typed = [];
        }
    }

    /**
     * @param  array<string, string>  $fill
     * @return array<string, string>
     */
    private static function tokens(array $fill): array
    {
        return collect($fill)->mapWithKeys(fn (string $value, string $name) => ['{'.$name.'}' => $value])->all();
    }
}

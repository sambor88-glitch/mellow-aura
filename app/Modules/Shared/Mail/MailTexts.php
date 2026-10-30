<?php

namespace App\Modules\Shared\Mail;

use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\SitePhoto;
use Closure;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

/**
 * The sentences of customer mails that Kasia writes in the panel („Maile”, MA-239): per mail the subject, the
 * opening and the closing sentence, and one signature and footer for all of them, the signature with an optional
 * picture above it (MA-287). Each in the language the mail
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

    /** The picture above the signature, one for every customer mail in every language (MA-287). */
    public const SIGNATURE_IMAGE = 'mail_signature_image';

    /** @var list<class-string<HasEditableTexts>> */
    private static array $mails = [];

    /**
     * Typed in the panel and not saved yet, for the preview: setting => value.
     *
     * @var array<string, ?string>
     */
    private array $typed = [];

    private bool $previewing = false;

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
     * The text an empty field sends, in the current language. A variant picks a default worded for another case,
     * e.g. `subject_many` for several vouchers; whatever Kasia writes herself goes out in every case.
     */
    public function default(string $part, ?string $mail = null, ?string $variant = null): string
    {
        if ($mail !== null) {
            $key = self::mails()[$mail]->lang.'.'.$part;

            return __($variant !== null && Lang::has($key.'_'.$variant) ? $key.'_'.$variant : $key);
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
    public function raw(string $part, ?string $mail = null, ?string $variant = null): string
    {
        $key = self::setting($part, app()->getLocale(), $mail);
        $value = array_key_exists($key, $this->typed) ? $this->typed[$key] : $this->settings->get($key);

        return filled($value) ? (string) $value : $this->default($part, $mail, $variant);
    }

    /**
     * For a subject or the plain-text part.
     *
     * @param  array<string, string>  $fill  placeholder => value
     */
    public function text(string $mail, string $part, array $fill = [], ?string $variant = null): string
    {
        return strtr($this->raw($part, $mail, $variant), self::tokens($fill));
    }

    /**
     * For the HTML part: escaped, line breaks kept, filled-in values in bold.
     *
     * @param  array<string, string>  $fill  placeholder => value
     */
    public function html(string $mail, string $part, array $fill = [], ?string $variant = null): HtmlString
    {
        $values = array_map(fn (string $value) => '<strong style="font-weight: 600;">'.e($value).'</strong>', self::tokens($fill));

        return new HtmlString(nl2br(strtr(e($this->raw($part, $mail, $variant)), $values), false));
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
     * The picture Kasia put above the signature, with the size it shows at in a mail. A missing file gives null,
     * so a mail goes out with the text signature alone instead of an empty frame.
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public function signatureImage(): ?array
    {
        $image = $this->settings->get(self::SIGNATURE_IMAGE);
        $url = is_array($image) ? SitePhoto::url($image['path'] ?? null) : null;

        return $url === null ? null : ['url' => $url, 'width' => (int) $image['width'], 'height' => (int) $image['height']];
    }

    /**
     * Whether a mail is being rendered for the panel's preview, so nothing should leave the shop because of it.
     */
    public function isPreviewing(): bool
    {
        return $this->previewing;
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
        $this->previewing = true;

        try {
            return $render();
        } finally {
            $this->typed = [];
            $this->previewing = false;
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

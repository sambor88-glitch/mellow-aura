<?php

namespace App\Modules\Gifts\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineType;
use App\Modules\Gifts\Support\GiftChoiceTerms;
use App\Modules\Localization\Support\Locales;
use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gift choice cards from the mug configurator (MA-235). The size and its price come from the panel, like a mug's;
 * a card whose size was removed there drops out of the cart. The names and dedication are optional, within the
 * voucher's limits. A card sent by e-mail needs the address and a day from today up to a year ahead.
 * Polish and złoty only: the English pages have no gift choice.
 */
class GiftChoiceLines implements LineType
{
    public function __construct(private MugOptions $options, private GiftChoiceTerms $terms, private Settings $settings) {}

    public function lines(array $rows): iterable
    {
        $sizes = $this->options->sizes()->keyBy('label');
        $photo = $this->options->photoUrl();
        $delivery = $this->terms->deliveryGross();

        foreach ($rows as $key => $row) {
            $size = $sizes->get($row['size'] ?? null);

            if ($size !== null) {
                yield $key => new GiftChoiceLine(
                    $key,
                    (int) $row['quantity'],
                    $size,
                    $delivery,
                    $row['recipient_name'] ?? null,
                    $row['sender_name'] ?? null,
                    $row['dedication'] ?? null,
                    $row['recipient_email'] ?? null,
                    $row['send_on'] ?? null,
                    $photo,
                );
            }
        }
    }

    public function fromRequest(Request $request): CartLine
    {
        abort_unless(Locales::current() === Locales::default(), 404);

        $sizes = $this->options->sizes();
        $text = fn (string $field) => is_string($value = $request->input($field)) ? $value : '';
        $nameMax = (int) $this->settings->get('voucher_recipient_name_max_chars', 40);

        // Browsers send a line break in a textarea as two characters; the counter on the page counts one.
        $request->merge([
            'recipient_name' => trim((string) preg_replace('/\s+/u', ' ', $text('recipient_name'))),
            'sender_name' => trim((string) preg_replace('/\s+/u', ' ', $text('sender_name'))),
            'dedication' => trim((string) preg_replace(["/\r\n?/", "/\n{3,}/"], ["\n", "\n\n"], $text('dedication'))),
            'recipient_email' => trim($text('recipient_email')),
        ]);

        $data = $request->validate([
            'size' => ['required', Rule::in($sizes->pluck('label')->all())],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
            'recipient_name' => ['nullable', 'string', 'max:'.$nameMax],
            'sender_name' => ['nullable', 'string', 'max:'.$nameMax],
            'dedication' => ['nullable', 'string', 'max:'.(int) $this->settings->get('voucher_dedication_max_chars', 180)],
            'delivery' => ['required', Rule::in(['email', 'print'])],
            'recipient_email' => ['exclude_unless:delivery,email', 'required', 'email', 'max:255'],
            'send_on' => ['exclude_unless:delivery,email', 'required', 'date_format:Y-m-d', 'after_or_equal:today',
                'before_or_equal:'.today()->addDays(GiftChoiceTerms::MAX_SEND_DAYS)->toDateString()],
        ], [
            'size.*' => __('mug-configurator::mug.validation.size'),
            'quantity.max' => CartLine::tooManyNotice(),
            'recipient_name.max' => __('gifts::choice.validation.recipient_name_max'),
            'sender_name.max' => __('gifts::choice.validation.sender_name_max'),
            'dedication.max' => __('gifts::choice.validation.dedication_max'),
            'delivery.*' => __('gifts::choice.validation.delivery'),
            'recipient_email.required' => __('gifts::choice.validation.recipient_email_required'),
            'recipient_email.*' => __('gifts::choice.validation.recipient_email'),
            'send_on.required' => __('gifts::choice.validation.send_on_required'),
            'send_on.*' => __('gifts::choice.validation.send_on'),
        ]);

        $size = $sizes->firstWhere('label', $data['size']);
        $details = [
            ($data['recipient_name'] ?? '') ?: null,
            ($data['sender_name'] ?? '') ?: null,
            ($data['dedication'] ?? '') ?: null,
            isset($data['recipient_email']) ? mb_strtolower($data['recipient_email']) : null,
            $data['send_on'] ?? null,
        ];

        return new GiftChoiceLine(
            GiftChoiceLine::keyFor($size['label'], $details),
            $details[3] !== null ? 1 : (int) ($data['quantity'] ?? 1),
            $size,
            $this->terms->deliveryGross(),
            ...$details,
            photoUrl: $this->options->photoUrl(),
        );
    }
}

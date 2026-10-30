<?php

namespace App\Modules\MugConfigurator\Support;

use Illuminate\Validation\ValidationException;

/**
 * The text stamped on a mug, checked on the server whatever the page let through: letters, digits and punctuation,
 * within the lines and characters from the panel. The configurator and the gift choice card (MA-235) check it here.
 */
class MugText
{
    /** Letters, digits, punctuation, maths and currency signs, spaces and line breaks — no emoji. */
    public const PATTERN = '/^[\p{L}\p{N}\p{P}\p{Sm}\p{Sc} \n]+$/u';

    public function __construct(private MugOptions $options) {}

    /**
     * Letters are stamped in capitals. Each line loses spaces at its ends and doubled spaces inside,
     * and empty lines go.
     */
    public static function tidy(mixed $text): string
    {
        if (! is_string($text)) {
            return '';
        }

        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn (string $line) => mb_strtoupper(trim((string) preg_replace('/[^\S\n]+/u', ' ', $line))))
            ->filter(fn (string $line) => $line !== '')
            ->join("\n");
    }

    /**
     * The rules for a tidied text, with their messages.
     *
     * @return array{0: list<string>, 1: array<string, string>}
     */
    public static function rules(string $field = 'text'): array
    {
        return [
            ['required', 'string', 'regex:'.self::PATTERN],
            [
                $field.'.required' => __('mug-configurator::mug.validation.text_required'),
                $field.'.regex' => __('mug-configurator::mug.validation.text_regex'),
            ],
        ];
    }

    /**
     * Checks that a tidied text fits the lines and characters from the panel.
     *
     * @throws ValidationException
     */
    public function fits(string $text, string $field = 'text'): void
    {
        $lines = explode("\n", $text);
        $maxLines = $this->options->maxLines();
        $maxChars = $this->options->maxCharsPerLine();

        if (count($lines) > $maxLines) {
            throw ValidationException::withMessages([$field => trans_choice('mug-configurator::mug.validation.too_many_lines', $maxLines)]);
        }

        if (collect($lines)->contains(fn (string $line) => mb_strlen($line) > $maxChars)) {
            throw ValidationException::withMessages([$field => __('mug-configurator::mug.validation.too_long', ['max' => $maxChars])]);
        }
    }
}

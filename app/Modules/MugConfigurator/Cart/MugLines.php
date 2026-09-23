<?php

namespace App\Modules\MugConfigurator\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineType;
use App\Modules\MugConfigurator\Support\MugOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mugs from the configurator. Prices come from the sizes in the panel; a line whose size or glaze was
 * removed there drops out of the cart. The text is checked here, whatever the page let through.
 */
class MugLines implements LineType
{
    public function __construct(private MugOptions $options) {}

    public function lines(array $rows): iterable
    {
        $sizes = $this->options->sizes()->keyBy('label');
        $glazes = $this->options->glazes()->keyBy('code');
        $photo = $this->options->photoUrl();

        foreach ($rows as $key => $row) {
            $size = $sizes->get($row['size'] ?? null);
            $glaze = $glazes->get($row['glaze'] ?? null);

            if ($size && $glaze && is_string($row['text'] ?? null) && $row['text'] !== '') {
                yield $key => new MugLine($key, (int) $row['quantity'], $row['text'], $size, $glaze, $photo);
            }
        }
    }

    public function fromRequest(Request $request): CartLine
    {
        $sizes = $this->options->sizes();
        $glazes = $this->options->glazes();
        $maxLines = $this->options->maxLines();
        $maxChars = $this->options->maxCharsPerLine();

        $request->merge(['text' => $this->tidy($request->input('text'))]);

        $data = $request->validate([
            'text' => ['required', 'string', 'regex:/^[\p{L}\p{N}\p{P}\p{Sm}\p{Sc} \n]+$/u'],
            'size' => ['required', Rule::in($sizes->pluck('label')->all())],
            'glaze' => ['required', Rule::in($glazes->pluck('code')->all())],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
        ], [
            'text.required' => 'Napisz, co mam wbić w glinę',
            'text.regex' => 'Wbiję litery, cyfry i znaki interpunkcyjne — bez emotek',
            'size.required' => 'Wybierz rozmiar kubka',
            'size.in' => 'Wybierz rozmiar kubka',
            'glaze.required' => 'Wybierz kolor wnętrza',
            'glaze.in' => 'Wybierz kolor wnętrza',
            'quantity.max' => CartLine::tooManyNotice(),
        ]);

        $lines = explode("\n", $data['text']);

        if (count($lines) > $maxLines) {
            throw ValidationException::withMessages(['text' => 'Zmieszczę najwyżej '.$maxLines.' '.($maxLines === 1 ? 'linię' : ($maxLines < 5 ? 'linie' : 'linii')).' napisu']);
        }

        if (collect($lines)->contains(fn (string $line) => mb_strlen($line) > $maxChars)) {
            throw ValidationException::withMessages(['text' => 'W jednej linii zmieszczę najwyżej '.$maxChars.' znaków — podziel napis na linie']);
        }

        $size = $sizes->firstWhere('label', $data['size']);
        $glaze = $glazes->firstWhere('code', $data['glaze']);

        return new MugLine(
            MugLine::keyFor($data['text'], $size['label'], $glaze['code']),
            (int) ($data['quantity'] ?? 1),
            $data['text'],
            $size,
            $glaze,
            $this->options->photoUrl(),
        );
    }

    /**
     * Letters are stamped in capitals. Each line loses spaces at its ends and doubled spaces inside,
     * and empty lines go.
     */
    private function tidy(mixed $text): string
    {
        if (! is_string($text)) {
            return '';
        }

        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn (string $line) => mb_strtoupper(trim((string) preg_replace('/[^\S\n]+/u', ' ', $line))))
            ->filter(fn (string $line) => $line !== '')
            ->join("\n");
    }
}

<?php

namespace App\Modules\MugConfigurator\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineType;
use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\MugConfigurator\Support\MugText;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mugs from the configurator. Prices come from the sizes in the panel; a line whose size or glaze was
 * removed there drops out of the cart. The text is checked here, whatever the page let through.
 */
class MugLines implements LineType
{
    public function __construct(private MugOptions $options, private MugText $text) {}

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
        [$textRules, $textMessages] = MugText::rules();

        $request->merge(['text' => MugText::tidy($request->input('text'))]);

        $data = $request->validate([
            'text' => $textRules,
            'size' => ['required', Rule::in($sizes->pluck('label')->all())],
            'glaze' => ['required', Rule::in($glazes->pluck('code')->all())],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
        ], [
            ...$textMessages,
            'size.required' => __('mug-configurator::mug.validation.size'),
            'size.in' => __('mug-configurator::mug.validation.size'),
            'glaze.required' => __('mug-configurator::mug.validation.glaze'),
            'glaze.in' => __('mug-configurator::mug.validation.glaze'),
            'quantity.max' => CartLine::tooManyNotice(),
        ]);

        $this->text->fits($data['text']);

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
}

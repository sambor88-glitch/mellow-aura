<?php

namespace App\Modules\Cart;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A kind of cart line, registered in LineTypes under the type name its rows carry.
 */
interface LineType
{
    /**
     * Lines for the session rows of this type, keyed as in the session. A row whose thing can no longer
     * be bought is left out; the cart itself shrinks a quantity above the line's limit.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return iterable<string, CartLine>
     */
    public function lines(array $rows): iterable;

    /**
     * The line an "add to cart" form asks for, with the quantity the customer wants.
     *
     * @throws ValidationException
     */
    public function fromRequest(Request $request): CartLine;
}

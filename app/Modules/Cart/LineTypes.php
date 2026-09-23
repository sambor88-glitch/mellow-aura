<?php

namespace App\Modules\Cart;

use Illuminate\Contracts\Container\Container;

/**
 * The kinds of lines the cart can hold. A module registers its kind in its service provider:
 *
 *     $this->callAfterResolving(LineTypes::class, fn (LineTypes $types) => $types->register(MugLine::TYPE, MugLines::class));
 */
class LineTypes
{
    /** @var array<string, class-string<LineType>> */
    private array $types = [];

    public function __construct(private Container $container) {}

    /**
     * @param  class-string<LineType>  $class
     */
    public function register(string $name, string $class): static
    {
        $this->types[$name] = $class;

        return $this;
    }

    public function get(string $name): ?LineType
    {
        return isset($this->types[$name]) ? $this->container->make($this->types[$name]) : null;
    }
}

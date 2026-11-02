<?php

namespace App\Modules\Admin\Support\Push;

/**
 * One notification for the panel's phones (MA-345). It shows on a locked screen, so it never names a customer:
 * the order number and the amount, the workshop day, the kind of idea — never a name, an e-mail or an address.
 *
 * $route is the panel screen the notification opens; only accounts that may open it get the notification,
 * so a helper hears about orders and nothing else. $tag lets a repeated notification replace the earlier one.
 */
final readonly class PanelNotice
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $route,
        public string $tag,
        public ?int $onlyUser = null,
    ) {}

    /**
     * What the worker on the phone reads (admin::app.worker).
     *
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function payload(): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'tag' => $this->tag];
    }
}

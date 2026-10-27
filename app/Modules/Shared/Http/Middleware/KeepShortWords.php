<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Polish typesetting on every page: a one-letter word (w, z, i, o, a, u) never ends a line, a number stays with
 * the word after it (10 sekund), a range stays whole (6–12 tygodni), a dash never starts a line and an arrow
 * never stands alone on one. Done once on the finished HTML, so texts from the panel, the language files and the database all get it.
 * Only text between tags changes; tags, attributes (also Alpine ones with „=>” inside), scripts, styles and form
 * fields stay as they are.
 */
class KeepShortWords
{
    private const NBSP = "\u{00A0}";

    private const WORD_JOINER = "\u{2060}";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // The panel is Kasia's tool, not the shop: its forms and tables stay exactly as typed.
        if (! config('app.keep_short_words') || $request->routeIs('admin.*')
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            || ! is_string($content = $response->getContent())) {
            return $response;
        }

        return $response->setContent(self::html($content));
    }

    public static function html(string $html): string
    {
        return preg_replace_callback(
            '/(<(script|style|textarea|pre|code)\b.*?<\/\2\s*>)|(<!--.*?-->)|(<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)|([^<]+)/is',
            fn (array $match) => isset($match[5]) && $match[5] !== '' ? self::text($match[5]) : $match[0],
            $html,
        ) ?? $html;
    }

    public static function text(string $text): string
    {
        // Twice, so „i w domu” keeps both short words with what follows.
        for ($pass = 0; $pass < 2; $pass++) {
            $text = preg_replace('/(^|[\s\x{00A0}(„"\x{201C}])([aiouwzAIOUWZ])[ \t\r\n]+/u', '$1$2'.self::NBSP, $text) ?? $text;
        }

        $text = preg_replace('/(\d)[ \t\r\n]+(?=\p{L})/u', '$1'.self::NBSP, $text) ?? $text;
        // Only the en dash of a range: a hyphen between digits belongs to codes such as MA-2026-0001 and stays as is.
        $text = preg_replace('/(\d)–(?=\d)/u', '$1'.self::WORD_JOINER.'–'.self::WORD_JOINER, $text) ?? $text;
        $text = preg_replace('/[ \t\r\n]+([→↗])/u', self::NBSP.'$1', $text) ?? $text;

        return preg_replace('/[ \t\r\n]+([—–])(?=\s)/u', self::NBSP.'$1', $text) ?? $text;
    }
}

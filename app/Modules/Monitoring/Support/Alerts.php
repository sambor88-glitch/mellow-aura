<?php

namespace App\Modules\Monitoring\Support;

use App\Modules\Monitoring\Mail\Alert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tells the person who looks after the shop that something broke. The same alert goes out at most once
 * an hour, so an error on every page view makes one mail, not hundreds. Sending never throws: an alert
 * about a mail provider that is down fails the same way, and then it only reaches the log.
 */
class Alerts
{
    public function send(string $key, string $subject, string $details): void
    {
        try {
            if (! Cache::add('monitoring:alert:'.sha1($key), true, now()->addMinutes((int) config('monitoring.repeat_after_minutes')))) {
                return;
            }

            Log::error('Monitoring alert: '.$subject);

            if (filled($address = config('monitoring.alert_email'))) {
                Mail::to($address)->send(new Alert($subject, $details));
            }
        } catch (Throwable $exception) {
            // Never report() here: the report would come straight back as another alert. The log may be what broke, too.
            rescue(fn () => Log::error('Monitoring alert not sent: '.$exception->getMessage()), report: false);
        }
    }

    /**
     * An error the site reported: what, where and during which request, with the shop's own files from the trace.
     */
    public function exception(Throwable $exception): void
    {
        $where = $this->relative($exception->getFile()).':'.$exception->getLine();

        $trace = collect($exception->getTrace())
            ->filter(fn (array $frame) => isset($frame['file']) && ! str_contains($frame['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR))
            ->take(8)
            ->map(fn (array $frame) => $this->relative($frame['file']).':'.($frame['line'] ?? '?'))
            ->implode("\n");

        $this->send(
            key: 'exception:'.$exception::class.':'.$where,
            subject: 'Błąd '.class_basename($exception).' w '.$where,
            details: implode("\n", array_filter([
                'Błąd: '.$exception::class,
                'Komunikat: '.Str::limit($exception->getMessage(), 500),
                'Miejsce: '.$where,
                'Przy: '.$this->during(),
                'Kiedy: '.now()->format('Y-m-d H:i:s'),
                $trace !== '' ? "\nPliki sklepu w śladzie błędu:\n".$trace : null,
                "\nPełny opis jest w storage/logs/laravel.log na serwerze.",
            ])),
        );
    }

    private function during(): string
    {
        if (app()->runningInConsole()) {
            return 'php '.implode(' ', array_slice($_SERVER['argv'] ?? ['artisan'], 0, 3));
        }

        return request()->method().' /'.ltrim(request()->path(), '/');
    }

    private function relative(string $path): string
    {
        return Str::after($path, base_path().DIRECTORY_SEPARATOR);
    }
}

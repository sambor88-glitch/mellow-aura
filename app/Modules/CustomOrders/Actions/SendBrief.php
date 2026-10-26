<?php

namespace App\Modules\CustomOrders\Actions;

use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Mail\BriefConfirmed;
use App\Modules\CustomOrders\Mail\BriefReceived;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

/**
 * Saves an idea from the custom order form with its photos, then mails Kasia and the customer.
 * The idea is saved first: a mail that can't be queued is reported, and the idea still waits for Kasia.
 */
class SendBrief
{
    /** Enough to see a glaze or a pattern; a whole set of six still fits in one mail to Kasia. */
    private const LONGEST_SIDE = 2400;

    public function __construct(private Settings $settings) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, quantity?: ?string, needed_by?: ?string, budget?: ?string, description: string}  $brief
     * @param  list<UploadedFile>  $photos
     */
    public function __invoke(BriefKind $kind, array $brief, array $photos): CustomOrderRequest
    {
        // The photos are converted first, so a photo that fails leaves no idea without its photos behind.
        $webps = array_map(fn (UploadedFile $photo) => $this->webp($photo), $photos);

        $request = DB::transaction(function () use ($kind, $brief, $webps) {
            $request = CustomOrderRequest::query()->create([
                'name' => trim($brief['name']),
                'email' => trim($brief['email']),
                'phone' => $this->optional($brief['phone'] ?? null),
                'kind' => $kind,
                'quantity' => $this->optional($brief['quantity'] ?? null),
                'needed_by' => $this->optional($brief['needed_by'] ?? null),
                'budget' => $this->optional($brief['budget'] ?? null),
                'description' => trim($brief['description']),
                'status' => CustomOrderStatus::New,
            ]);

            foreach ($webps as $index => $webp) {
                $request->addMedia($webp)
                    ->usingFileName('pomysl-'.$request->id.'-'.($index + 1).'.webp')
                    ->toMediaCollection('photos');
            }

            return $request;
        });

        try {
            Mail::to($this->settings->get('contact_email') ?: throw new RuntimeException('No contact e-mail in the panel for a custom order idea.'))
                ->send(new BriefReceived($request));
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            Mail::to($request->email, $request->name)->send(new BriefConfirmed($request));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $request;
    }

    private function optional(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }

    /**
     * Turned upright, scaled down and saved again as WebP, which also drops EXIF with the GPS position
     * of the customer's home.
     */
    private function webp(UploadedFile $photo): string
    {
        $webp = tempnam(sys_get_temp_dir(), 'brief');

        Image::useImageDriver('gd')
            ->loadFile($photo->getRealPath())
            ->fit(Fit::Max, self::LONGEST_SIDE, self::LONGEST_SIDE)
            ->quality(82)
            ->format('webp')
            ->save($webp);

        return $webp;
    }
}

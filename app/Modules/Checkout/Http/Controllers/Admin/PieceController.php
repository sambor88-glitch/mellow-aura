<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\Admin\PhotoRules;
use App\Modules\Checkout\Actions\SetPiecePhoto;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Checkout\Support\PieceCardPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * What only the studio knows about a piece — the firing date, the glaze, the plant, a few words from Kasia, its own photo
 * (MA-290) — and the card with the QR code to its page, printed for the parcel (MA-234). All only for a piece of a paid
 * order, like its certificate.
 */
class PieceController extends Controller
{
    private const PHOTO_MAX_KILOBYTES = 10 * 1024;

    // A bigger photo would not fit in PHP's memory while it is scaled down, as with product photos.
    private const PHOTO_MAX_SIDE = 8192;

    /**
     * What the page checks before sending the photo (photoPicker): one file, up to 10 MB.
     *
     * @return array{maxFiles: int, maxFileBytes: int, maxTotalBytes: int}
     */
    public static function photoLimits(): array
    {
        return [
            ...PhotoRules::limits(),
            'maxFiles' => 1,
            'maxFileBytes' => (int) min(self::PHOTO_MAX_KILOBYTES * 1024, UploadedFile::getMaxFilesize()),
        ];
    }

    public function update(Request $request, Order $order, OrderItem $item): RedirectResponse
    {
        $this->ensurePiece($order, $item);

        // Each item has its own form on the order page, so its errors go into its own bag.
        $validator = Validator::make($request->all(), [
            'fired_on' => ['nullable', 'date', 'before_or_equal:today'],
            'glaze' => ['nullable', 'string', 'max:120'],
            'plant' => ['nullable', 'string', 'max:120'],
            'maker_note' => ['nullable', 'string', 'max:500'],
        ], [
            'fired_on.date' => 'Wpisz datę, np. 12.10.2026',
            'fired_on.before_or_equal' => 'Wypał nie może być w przyszłości — sprawdź datę',
            'glaze.max' => 'Szkliwo zmieść w 120 znakach',
            'plant.max' => 'Roślinę zmieść w 120 znakach',
            'maker_note.max' => 'Kilka słów zmieść w 500 znakach — napisz krócej',
        ]);

        if ($validator->fails()) {
            return to_route('admin.orders.show', $order)->withErrors($validator, 'piece'.$item->id)->withInput()->withFragment('sztuka-'.$item->id);
        }

        $item->update(collect($validator->validated())->map(fn (?string $value) => filled($value) ? trim($value) : null)->all());

        return to_route('admin.orders.show', $order)
            ->with('panel_status', 'Zapisane — widać to na stronie sztuki i na certyfikacie.')
            ->withFragment('sztuka-'.$item->id);
    }

    public function storePhoto(Request $request, Order $order, OrderItem $item, SetPiecePhoto $setPhoto): RedirectResponse
    {
        $this->ensurePiece($order, $item);

        $notPhoto = 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP';
        $validator = Validator::make($request->all(), [
            'photo' => [
                'bail', 'required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::PHOTO_MAX_KILOBYTES,
                'dimensions:max_width='.self::PHOTO_MAX_SIDE.',max_height='.self::PHOTO_MAX_SIDE,
            ],
        ], [
            'photo.required' => 'Wybierz zdjęcie z telefonu albo komputera',
            'photo.uploaded' => 'Zdjęcie nie doszło — może jest za duże. Spróbuj mniejszego',
            'photo.file' => $notPhoto,
            'photo.mimetypes' => $notPhoto,
            'photo.max' => 'Zdjęcie jest za duże — do 10 MB',
            'photo.dimensions' => 'Zdjęcie jest za duże — zmniejsz je do '.self::PHOTO_MAX_SIDE.' px na dłuższym boku',
        ]);

        if ($validator->fails()) {
            return to_route('admin.orders.show', $order)->withErrors($validator, 'piecePhoto'.$item->id)->withFragment('sztuka-'.$item->id);
        }

        $replaced = $item->hasMedia('photo');
        $setPhoto($item, $request->file('photo'));

        return to_route('admin.orders.show', $order)
            ->with('panel_status', $replaced ? 'Zdjęcie zmienione — widać je na stronie sztuki.' : 'Zdjęcie dodane — widać je na stronie sztuki.')
            ->withFragment('sztuka-'.$item->id);
    }

    public function destroyPhoto(Order $order, OrderItem $item): RedirectResponse
    {
        $this->ensurePiece($order, $item);

        $item->clearMediaCollection('photo');

        return to_route('admin.orders.show', $order)
            ->with('panel_status', 'Zdjęcie usunięte ze strony sztuki.')
            ->withFragment('sztuka-'.$item->id);
    }

    public function card(Order $order, OrderItem $item, PieceCardPdf $pdf): Response
    {
        $this->ensurePiece($order, $item);

        return response($pdf->render($item->setRelation('order', $order)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.PieceCardPdf::filename($item).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ensurePiece(Order $order, OrderItem $item): void
    {
        abort_unless($item->order_id === $order->id && $order->payment_status === PaymentStatus::Paid && $item->getsCertificate(), 404);
    }
}

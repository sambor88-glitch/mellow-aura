<?php

namespace App\Modules\CustomOrders\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Http\Requests\Admin\UpdateCustomOrderRequestRequest;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * „Zamówienia indywidualne” in the panel: ideas from the form with the customer's photos, Kasia's price and note.
 * The list shows the work still to do, oldest first; one status at a time through the tabs. Shipped and declined ideas
 * leave it for their own tabs, newest first.
 */
class CustomOrderRequestController extends Controller
{
    /** How many ideas the „Wysłane” and „Odrzucone” tabs show. */
    private const CLOSED_SHOWN = 50;

    public function index(Request $request): View
    {
        $status = CustomOrderStatus::tryFrom((string) $request->query('status'));

        $requests = CustomOrderRequest::query()
            ->with('media')
            ->when($status !== null && ! in_array($status, CustomOrderStatus::open(), true),
                fn ($query) => $query->where('status', $status)->latest('updated_at')->limit(self::CLOSED_SHOWN),
                fn ($query) => $query->whereIn('status', $status ? [$status] : CustomOrderStatus::open())->orderBy('created_at')->orderBy('id'),
            )
            ->get();

        $counts = CustomOrderRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('custom-orders::admin.requests', [
            'current' => $status,
            'requests' => $requests,
            'counts' => $counts,
            'openCount' => collect(CustomOrderStatus::open())->sum(fn (CustomOrderStatus $open) => (int) ($counts[$open->value] ?? 0)),
        ]);
    }

    public function update(UpdateCustomOrderRequestRequest $request, CustomOrderRequest $customOrderRequest): RedirectResponse
    {
        $customOrderRequest->update($request->changes());

        return to_route('admin.custom-orders.index')
            ->withFragment('pomysl-'.$customOrderRequest->id)
            ->with('panel_status', 'Zapisane. '.$customOrderRequest->name.' — '.mb_strtolower($customOrderRequest->status->label()).'.');
    }

    public function photo(CustomOrderRequest $customOrderRequest, int $photo): StreamedResponse
    {
        $media = $customOrderRequest->getMedia('photos')->firstWhere('id', $photo) ?? abort(404);

        return Storage::disk($media->disk)->response($media->getPathRelativeToRoot(), $media->file_name, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=86400',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}

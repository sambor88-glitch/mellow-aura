<?php

namespace App\Modules\Firing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Firing\Enums\FiringRequestStatus;
use App\Modules\Firing\Http\Requests\Admin\UpdateFiringRequestRequest;
use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Firing\Support\LoadDays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * „Zgłoszenia do wypału” in the panel: batches from the form, grouped into the weekly load by the day they come in.
 * A batch without a day waits on top until Kasia gives it one. Picked-up batches leave the list for their own tab.
 */
class FiringRequestController extends Controller
{
    /** How many picked-up batches the „Odebrane” tab shows. */
    private const COLLECTED_SHOWN = 50;

    public function index(Request $request, LoadDays $loadDays): View
    {
        $collected = $request->query('widok') === 'odebrane';

        $requests = FiringRequest::query()
            ->when($collected,
                fn ($query) => $query->where('status', FiringRequestStatus::Collected)->latest('updated_at')->limit(self::COLLECTED_SHOWN),
                fn ($query) => $query->whereIn('status', FiringRequestStatus::open())->orderBy('created_at')->orderBy('id'),
            )
            ->get();

        return view('firing::admin.requests', [
            'collected' => $collected,
            'loads' => $collected ? collect() : $this->loads($requests),
            'requests' => $requests,
            'openCount' => $collected ? FiringRequest::query()->whereIn('status', FiringRequestStatus::open())->count() : $requests->count(),
            'loadDays' => $loadDays->upcoming(),
        ]);
    }

    public function update(UpdateFiringRequestRequest $request, FiringRequest $firingRequest): RedirectResponse
    {
        $firingRequest->update($request->validated());

        return to_route('admin.firing.requests.index')
            ->withFragment('zgloszenie-'.$firingRequest->id)
            ->with('panel_status', 'Zapisane. '.$firingRequest->name.' — '.mb_strtolower($firingRequest->status->label()).'.');
    }

    /**
     * Moves every open batch of one load day to the same status — the whole kiln goes in and comes out together.
     */
    public function updateLoad(Request $request, string $date): RedirectResponse
    {
        $validated = $request->validate(
            ['status' => ['required', Rule::enum(FiringRequestStatus::class)]],
            ['status.*' => 'Wybierz status z listy'],
        );

        $moved = FiringRequest::query()
            ->whereDate('drop_off_on', $date)
            ->whereIn('status', FiringRequestStatus::open())
            ->update(['status' => $validated['status']]);

        $status = FiringRequestStatus::from($validated['status']);

        return to_route('admin.firing.requests.index')
            ->withFragment('partia-'.$date)
            ->with('panel_status', $moved > 0
                ? 'Cała partia z '.Carbon::parse($date)->locale('pl')->translatedFormat('j F').' — '.mb_strtolower($status->label()).'.'
                : 'W tej partii nie ma już zgłoszeń do zmiany.');
    }

    /**
     * The open batches by load day: those without a day first, then the days in order.
     *
     * @param  Collection<int, FiringRequest>  $requests
     * @return Collection<int, array{date: ?string, label: string, requests: Collection<int, FiringRequest>, kilograms: ?string, quote: int, unpriced: int}>
     */
    private function loads(Collection $requests): Collection
    {
        return $requests
            ->groupBy(fn (FiringRequest $request) => $request->drop_off_on?->toDateString() ?? '')
            ->sortKeys()
            ->map(function (Collection $load, string $date) {
                $kilograms = $load->where('unit', 'kg')->sum(fn (FiringRequest $request) => (float) $request->quantity);

                return [
                    'date' => $date === '' ? null : $date,
                    'label' => $date === '' ? 'Bez terminu' : Carbon::parse($date)->locale('pl')->translatedFormat('l, j F'),
                    'requests' => $load,
                    'kilograms' => $kilograms > 0 ? rtrim(rtrim(number_format($kilograms, 2, ',', ' '), '0'), ',') : null,
                    'quote' => (int) $load->sum('quote'),
                    'unpriced' => $load->whereNull('quote')->count(),
                ];
            })
            ->values();
    }
}

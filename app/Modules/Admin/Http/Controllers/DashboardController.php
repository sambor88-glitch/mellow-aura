<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Support\PanelAccess;
use App\Modules\Catalog\Support\LastPieces;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Complaint;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\OrderWait;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Monitoring\Support\FailedMails;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * „Na dziś” (MA-250): what to send and what to answer, not a second menu. Tiles, each leading to its filtered list,
 * and under them everything that waits for Kasia, the oldest first. A complaint waits from the day it arrived and shows
 * its 14-day deadline (MA-261). „Ostatnie sztuki” counts what is about to leave the shop, from the Catalog (MA-343).
 * A helper gets only the orders.
 */
class DashboardController extends Controller
{
    /** The list under the tiles stops here; the tiles still count everything. */
    private const SHOWN = 20;

    public function __invoke(OrderWait $wait, FailedMails $failedMails, LastPieces $lastPieces): View
    {
        $paid = fn (OrderStatus $status) => Order::query()->where('payment_status', PaymentStatus::Paid)->where('status', $status);
        $toSend = $paid(OrderStatus::InProgress)->with('items')->orderBy('paid_at')->orderBy('id')->get();
        $problems = $paid(OrderStatus::Problem)->with('items')->orderBy('updated_at')->get();
        $ideas = $this->allowed('admin.custom-orders.index')
            ? CustomOrderRequest::query()->where('status', CustomOrderStatus::New)->orderBy('created_at')->orderBy('id')->get()
            : collect();
        $complaints = $this->allowed('admin.complaints.index')
            ? Complaint::query()->open()->orderBy('received_on')->orderBy('id')->get()
            : collect();
        $mails = $this->allowed('admin.failed-mails.index') ? $failedMails->all() : collect();
        $stock = $this->allowed('admin.products.index') ? $lastPieces->summary() : null;
        $oldest = $toSend->first();
        $late = $toSend->filter(fn (Order $order) => $wait->overdue($order))->count();

        $tiles = array_values(array_filter([
            [
                'value' => $toSend->count(), 'label' => 'do wysłania', 'route' => route('admin.orders.index'), 'go' => 'Pokaż listę',
                'hint' => match (true) {
                    $oldest === null => 'wszystko wysłane',
                    $late > 0 => 'najdłużej '.$wait->label($oldest).' · po terminie: '.$late,
                    default => 'najdłużej '.$wait->label($oldest),
                },
                'late' => $late > 0,
            ],
            $this->allowed('admin.custom-orders.index') ? [
                'value' => $ideas->count(), 'label' => 'nowe pomysły', 'route' => route('admin.custom-orders.index', ['status' => CustomOrderStatus::New->value]), 'go' => 'Wyceń',
                'hint' => 'zamówienia indywidualne do wyceny', 'late' => false,
            ] : null,
            $this->allowed('admin.complaints.index') ? [
                'value' => $complaints->count(), 'label' => 'reklamacje', 'route' => route('admin.complaints.index').'#czekaja', 'go' => 'Odpowiedz',
                'hint' => $complaints->isEmpty() ? 'żadna nie czeka na odpowiedź' : $complaints->first()->deadlineLabel(),
                'late' => (bool) $complaints->first()?->dueSoon(),
            ] : null,
            [
                'value' => $problems->count(), 'label' => 'problem', 'route' => route('admin.orders.index', ['status' => 'problem']), 'go' => 'Pokaż',
                'hint' => 'nadkomplet, brak sztuki, zwrot, paczka nie doszła', 'late' => false,
            ],
            $stock === null ? null : [
                'value' => $stock['soldOut'] + $stock['last'], 'label' => 'ostatnie sztuki',
                'route' => route('admin.products.index', ['pokaz' => 'ostatnie-sztuki']), 'go' => 'Co lepić',
                'hint' => $this->stockHint($stock), 'late' => false,
            ],
            $this->allowed('admin.failed-mails.index') ? [
                'value' => $mails->count(), 'label' => 'niewysłane maile', 'route' => route('admin.failed-mails.index'), 'go' => 'Wyślij jeszcze raz',
                'hint' => 'nie wyszły mimo kilku prób', 'late' => false,
            ] : null,
        ]));

        return view('admin::dashboard', [
            'tiles' => $tiles,
            'waiting' => $this->waiting($toSend, $problems, $ideas, $complaints, $mails, $wait),
            'today' => now()->locale('pl')->isoFormat('dddd, D MMMM'),
        ]);
    }

    /**
     * Everything that waits, the oldest first.
     *
     * @return Collection<int, array{since: CarbonInterface, age: string, kind: string, title: string, detail: string, status: ?array{string, string}, late: ?string, deadline: ?string, action: string, url: string, main: bool}>
     */
    private function waiting(Collection $toSend, Collection $problems, Collection $ideas, Collection $complaints, Collection $mails, OrderWait $wait): Collection
    {
        $items = fn (Order $order) => $order->items->map(fn ($item) => $item->product_name.($item->variant_label === '' ? '' : ' ('.$item->variant_label.')').' × '.$item->quantity)->join(', ');

        return collect()
            ->concat($toSend->map(fn (Order $order) => [
                'since' => $wait->since($order), 'kind' => ($wait->madeToOrder($order) ? 'do wysłania · na zamówienie · ' : 'do wysłania · ').$order->number, 'title' => $order->name, 'detail' => $items($order),
                'status' => [$order->status->label(), $order->status->chipClass()],
                'late' => $wait->overdue($order) ? 'Termin wysyłki minął '.$wait->deadline($order)->format('j.m') : null,
                'action' => 'Spakuj i wyślij', 'url' => route('admin.orders.show', $order), 'main' => true,
            ]))
            ->concat($problems->map(fn (Order $order) => [
                'since' => $order->updated_at, 'kind' => 'problem · '.$order->number, 'title' => $order->name, 'detail' => $order->problem_note ?: $items($order),
                'status' => [$order->status->label(), $order->status->chipClass()], 'late' => null,
                'action' => 'Szczegóły', 'url' => route('admin.orders.show', $order), 'main' => false,
            ]))
            ->concat($ideas->map(fn (CustomOrderRequest $idea) => [
                'since' => $idea->created_at, 'kind' => 'zamówienie indywidualne', 'title' => $idea->name.' · '.$idea->kind->label(), 'detail' => str($idea->description)->limit(90)->toString(),
                'status' => [CustomOrderStatus::New->label(), 'bg-rose text-ink'], 'late' => null,
                'action' => 'Wyceń', 'url' => route('admin.custom-orders.index').'#pomysl-'.$idea->id, 'main' => true,
            ]))
            ->concat($complaints->map(fn (Complaint $complaint) => [
                'since' => $complaint->received_on, 'kind' => 'reklamacja'.($complaint->order_number ? ' · '.$complaint->order_number : ''),
                'title' => $complaint->name, 'detail' => str($complaint->description)->limit(90)->toString(),
                'status' => null, 'late' => $complaint->dueSoon() ? Str::ucfirst($complaint->deadlineLabel()) : null,
                'deadline' => $complaint->dueSoon() ? null : Str::ucfirst($complaint->deadlineLabel()),
                'action' => 'Odpowiedz', 'url' => route('admin.complaints.index', ['reklamacja' => $complaint->id]).'#odpowiedz', 'main' => true,
            ]))
            ->concat($mails->map(fn (array $mail) => [
                'since' => $mail['failedAt'], 'kind' => 'niewysłany mail', 'title' => $mail['description'], 'detail' => implode(', ', $mail['to']),
                'status' => null, 'late' => null,
                'action' => 'Wyślij jeszcze raz', 'url' => route('admin.failed-mails.index'), 'main' => false,
            ]))
            ->sortBy(fn (array $row) => $row['since']->getTimestamp())
            ->take(self::SHOWN)
            ->map(fn (array $row) => ['deadline' => null, ...$row, 'age' => $this->age($row['since'])])
            ->values();
    }

    /**
     * „wyprzedane: 1 · ostatnia sztuka: 2 · czekają: 3” — sold out apart from the last pieces, and the people
     * waiting for them (MA-343).
     *
     * @param  array{soldOut: int, last: int, waiting: int, threshold: int}  $stock
     */
    private function stockHint(array $stock): string
    {
        if ($stock['soldOut'] + $stock['last'] === 0) {
            return 'nic się nie kończy';
        }

        return implode(' · ', array_filter([
            $stock['soldOut'] > 0 ? 'wyprzedane: '.$stock['soldOut'] : null,
            $stock['last'] > 0 ? ($stock['threshold'] === 1 ? 'ostatnia sztuka: ' : 'do '.$stock['threshold'].' szt.: ').$stock['last'] : null,
            $stock['waiting'] > 0 ? 'czekają: '.$stock['waiting'] : null,
        ]));
    }

    private function age(CarbonInterface $since): string
    {
        return match ($days = (int) $since->copy()->startOfDay()->diffInDays(today())) {
            0 => 'dziś',
            1 => '1 dzień',
            default => $days.' dni',
        };
    }

    private function allowed(string $route): bool
    {
        return PanelAccess::allows(auth()->user(), $route);
    }
}

<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Enums\ComplaintStatus;
use App\Modules\Checkout\Http\Requests\Admin\AnswerComplaintRequest;
use App\Modules\Checkout\Http\Requests\Admin\RecordComplaintRequest;
use App\Modules\Checkout\Mail\ComplaintAnswered;
use App\Modules\Checkout\Models\Complaint;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ComplaintLetter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * „Reklamacje”: Kasia saves a complaint on the day it arrives by e-mail and sees, oldest first, what waits and until
 * when (MA-261). Answering one fills in the form from it; she picks her decision, sees the letter the customer will
 * get and sends it by e-mail, which is a durable medium. The answer closes the complaint and stays on the list word
 * for word, also when the e-mail did not go out.
 */
class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $order = filled($number = $request->query('zamowienie'))
            ? Order::query()->where('number', mb_strtoupper((string) $number))->first()
            : null;

        // The complaint being answered: from its „Odpowiedz” link, or kept by the form across „Pokaż list”.
        $complaint = filled($id = $request->query('reklamacja', $request->old('complaint_id')))
            ? Complaint::query()->open()->find((int) $id)
            : null;

        return view('checkout::admin.complaints.index', [
            'order' => $order,
            'complaint' => $complaint,
            'open' => Complaint::query()->open()->with('order')->orderBy('received_on')->orderBy('id')->get(),
            'answers' => ComplaintAnswer::query()->with('order')->latest('id')->paginate(20),
        ]);
    }

    public function record(RecordComplaintRequest $request): RedirectResponse
    {
        $complaint = Complaint::query()->create([
            'order_id' => Order::query()->where('number', $request->validated('order_number'))->value('id'),
            'order_number' => $request->validated('order_number'),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'received_on' => $request->receivedOn(),
            'description' => $request->validated('description'),
        ]);

        return to_route('admin.complaints.index')->withFragment('czekaja')
            ->with('panel_status', 'Reklamacja zapisana. Odpowiedz do '.$complaint->deadline()->format('j.m.Y').'.');
    }

    public function store(AnswerComplaintRequest $request, ComplaintLetter $letter): RedirectResponse
    {
        $paragraphs = $letter->paragraphs($request->receivedOn(), $request->validated('order_number'), $request->decision(), $request->remedy(), $request->validated('details'), $request->mediation());

        if ($request->previewOnly()) {
            return to_route('admin.complaints.index')->withFragment('odpowiedz')->withInput()->with('complaint_letter', $paragraphs);
        }

        $complaint = filled($id = $request->validated('complaint_id')) ? Complaint::query()->find($id) : null;

        // The answer closes its complaint at once, also when the e-mail fails: the letter is ready to send by hand.
        $answer = DB::transaction(fn () => tap(ComplaintAnswer::query()->create([
            'complaint_id' => $complaint?->id,
            'order_id' => Order::query()->where('number', $request->validated('order_number'))->value('id'),
            'order_number' => $request->validated('order_number'),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'received_on' => $request->receivedOn(),
            'decision' => $request->decision(),
            'remedy' => $request->remedy(),
            'details' => $request->validated('details'),
            'mediation' => $request->mediation(),
            'letter' => implode("\n\n", $paragraphs),
        ]), fn () => $complaint?->update(['status' => ComplaintStatus::Answered])));

        // Sent at once, not through the queue: the answer has a deadline, so Kasia learns right here whether it went out.
        // A failed e-mail is reported and the letter stays on the list to send by hand.
        $emailed = rescue(function () use ($answer) {
            Mail::to(new Address($answer->email, $answer->name))->send(new ComplaintAnswered($answer));

            return true;
        }, false);

        if ($emailed) {
            $answer->update(['emailed_at' => now()]);
        }

        return to_route('admin.complaints.index')->with('panel_status', $emailed
            ? 'Odpowiedź wysłana do '.$answer->email.'. Kopia jest na liście.'
            : 'Odpowiedź zapisana, ale e-mail nie wyszedł. Skopiuj list z listy i wyślij go ze swojej skrzynki.');
    }
}

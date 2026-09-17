<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Http\Requests\Admin\AnswerComplaintRequest;
use App\Modules\Checkout\Mail\ComplaintAnswered;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ComplaintLetter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * „Reklamacje”: Kasia fills in her decision, sees the letter the customer will get and sends it by e-mail, which is
 * a durable medium. Every answer stays on the list word for word, also when the e-mail did not go out.
 */
class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $order = filled($number = $request->query('zamowienie'))
            ? Order::query()->where('number', mb_strtoupper((string) $number))->first()
            : null;

        return view('checkout::admin.complaints.index', [
            'order' => $order,
            'answers' => ComplaintAnswer::query()->with('order')->latest('id')->paginate(20),
        ]);
    }

    public function store(AnswerComplaintRequest $request, ComplaintLetter $letter): RedirectResponse
    {
        $paragraphs = $letter->paragraphs($request->receivedOn(), $request->validated('order_number'), $request->decision(), $request->remedy(), $request->validated('details'), $request->mediation());

        if ($request->previewOnly()) {
            return to_route('admin.complaints.index')->withFragment('odpowiedz')->withInput()->with('complaint_letter', $paragraphs);
        }

        $answer = ComplaintAnswer::query()->create([
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
        ]);

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

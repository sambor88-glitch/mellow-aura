<?php

namespace App\Modules\Monitoring\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Support\FailedMails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * „Niewysłane maile”: mails the queue gave up on. Sending again puts the same mail back in the queue,
 * so it goes out as it was written, with its attachments.
 */
class FailedMailController extends Controller
{
    public function index(FailedMails $mails): View
    {
        return view('monitoring::admin.failed-mails.index', ['mails' => $mails->all()]);
    }

    public function retry(string $id, FailedMails $mails): RedirectResponse
    {
        // A second click on the same button finds the mail already on its way.
        if (! $mails->has($id)) {
            return to_route('admin.failed-mails.index')->with('panel_status', 'Tego maila nie ma już na liście.');
        }

        Artisan::call('queue:retry', ['id' => [$id]]);

        return to_route('admin.failed-mails.index')->with('panel_status', 'Wysyłam jeszcze raz. Jeśli się nie uda, wróci na listę.');
    }

    public function retryAll(FailedMails $mails): RedirectResponse
    {
        if (($ids = $mails->all()->pluck('id'))->isNotEmpty()) {
            Artisan::call('queue:retry', ['id' => $ids->all()]);
        }

        return to_route('admin.failed-mails.index')->with('panel_status', 'Wysyłam wszystkie jeszcze raz.');
    }

    public function destroy(string $id, FailedMails $mails, FailedJobProviderInterface $failer): RedirectResponse
    {
        if (! $mails->has($id)) {
            return to_route('admin.failed-mails.index')->with('panel_status', 'Tego maila nie ma już na liście.');
        }

        $failer->forget($id);

        return to_route('admin.failed-mails.index')->with('panel_status', 'Usunięte z listy.');
    }
}

<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Content\Actions\AddServiceExample;
use App\Modules\Content\Http\Requests\Admin\StoreServiceExampleRequest;
use App\Modules\Content\Http\Requests\Admin\UpdateServiceExampleRequest;
use App\Modules\Content\Models\ServiceExample;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The „przed i po” pairs on a service page: add a pair, change its caption and descriptions, move it, delete it.
 */
class ServiceExampleController extends Controller
{
    public function store(StoreServiceExampleRequest $request, AddServiceExample $addExample): RedirectResponse
    {
        $example = $addExample($request->service(), $request->file('before'), $request->file('after'), $request->texts());

        return $this->back($example, 'Para dodana — widać ją na stronie');
    }

    public function update(UpdateServiceExampleRequest $request, ServiceExample $example): RedirectResponse
    {
        $example->update($request->texts());

        return $this->back($example, 'Opis zapisany');
    }

    public function move(Request $request, ServiceExample $example): RedirectResponse
    {
        $direction = $request->input('kierunek') === 'gora' ? -1 : 1;

        // The same renumbering as products: the pairs get 1, 2, 3… with these two swapped, so pairs saved with the same number still move.
        DB::transaction(function () use ($example, $direction) {
            $ids = ServiceExample::query()->where('service', $example->service)->ordered()->lockForUpdate()->pluck('id')->all();
            $from = array_search($example->id, $ids, true);

            if ($from === false || ! isset($ids[$from + $direction])) {
                return;
            }

            [$ids[$from], $ids[$from + $direction]] = [$ids[$from + $direction], $ids[$from]];

            foreach ($ids as $position => $id) {
                ServiceExample::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return $this->back($example, 'Kolejność zmieniona. Klienci już ją widzą.');
    }

    public function destroy(ServiceExample $example): RedirectResponse
    {
        $service = $example->service;
        $example->delete();

        return to_route('admin.services.edit', $service->slug())->withFragment('przed-i-po')->with('panel_status', 'Para usunięta ze strony');
    }

    private function back(ServiceExample $example, string $status): RedirectResponse
    {
        return to_route('admin.services.edit', $example->service->slug())->withFragment('para-'.$example->id)->with('panel_status', $status);
    }
}

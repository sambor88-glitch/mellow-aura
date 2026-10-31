<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\AttachProductModel;
use App\Modules\Catalog\Http\Requests\Admin\ModelRules;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The 3D model under each product in the panel (MA-310): add or replace, and delete.
 * With a model, the product page shows „Zobacz u siebie”.
 */
class ProductModelController extends Controller
{
    public function store(Request $request, Product $product, AttachProductModel $attachModel): RedirectResponse
    {
        $validator = Validator::make($request->all(), ModelRules::rules(), ModelRules::messages());

        if ($validator->fails()) {
            throw (new ValidationException($validator))->errorBag('model-'.$product->id)->redirectTo($this->backTo($product));
        }

        $replaced = $product->arModel() !== null;
        $attachModel($product, $request->file('model'));

        return redirect($this->backTo($product))->with('panel_status', $replaced ? 'Model 3D podmieniony' : 'Model 3D dodany');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->clearMediaCollection(Product::MODEL_COLLECTION);

        return redirect($this->backTo($product))->with('panel_status', 'Model 3D usunięty');
    }

    private function backTo(Product $product): string
    {
        return route('admin.products.index').'#produkt-'.$product->id;
    }
}

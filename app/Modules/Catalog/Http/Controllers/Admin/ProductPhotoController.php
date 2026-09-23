<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\AddProductPhotos;
use App\Modules\Catalog\Actions\MoveProductPhoto;
use App\Modules\Catalog\Http\Requests\Admin\PhotoRules;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The photo strip under each product in the panel: add, reorder and delete. Photo descriptions are saved
 * with the product form.
 */
class ProductPhotoController extends Controller
{
    public function store(Request $request, Product $product, AddProductPhotos $addPhotos): RedirectResponse
    {
        $validator = Validator::make($request->all(), PhotoRules::rules(required: true), PhotoRules::messages());

        if ($validator->fails()) {
            // Mistakes come back under the product they belong to.
            throw (new ValidationException($validator))->errorBag('zdjecia-'.$product->id)->redirectTo($this->backTo($product));
        }

        $photos = $request->file('photos');
        $addPhotos($product, $photos);

        return redirect($this->backTo($product))->with('panel_status', $this->addedLabel(count($photos)));
    }

    public function move(Request $request, Product $product, Media $media, MoveProductPhoto $movePhoto): RedirectResponse
    {
        $movePhoto($product, $media, $request->input('kierunek') === 'lewo' ? -1 : 1);

        return redirect($this->backTo($product));
    }

    public function destroy(Product $product, Media $media): RedirectResponse
    {
        $media->delete();

        return redirect($this->backTo($product))->with('panel_status', 'Zdjęcie usunięte');
    }

    private function backTo(Product $product): string
    {
        return route('admin.products.index').'#produkt-'.$product->id;
    }

    private function addedLabel(int $count): string
    {
        return match (true) {
            $count === 1 => 'Zdjęcie dodane',
            in_array($count % 10, [2, 3, 4], true) && ! in_array($count % 100, [12, 13, 14], true) => $count.' zdjęcia dodane',
            default => $count.' zdjęć dodanych',
        };
    }
}

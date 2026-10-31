<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\GlbFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The 3D model file of a published product (MA-310). Sent from here, not straight from storage, so it always goes
 * out as model/gltf-binary and can be cached for a long time: a new model gets a new id, so a new address.
 * Open without the staging password — Android's Scene Viewer fetches it itself, without the browser's login.
 */
class ProductModelController extends Controller
{
    public function __invoke(Media $media, string $name): StreamedResponse
    {
        $product = $media->model;

        abort_unless(
            $media->collection_name === Product::MODEL_COLLECTION && $name === $media->file_name && $product instanceof Product && $product->is_published,
            404,
        );

        $response = $media->toInlineResponse(request());
        $response->headers->add([
            'Content-Type' => GlbFile::MIME_TYPE,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        return $response;
    }
}

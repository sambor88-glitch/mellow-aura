<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\GlbFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Saves the 3D model sent from the panel (MA-310). A new one replaces the old: the collection holds a single file.
 * The type is set by hand, since a server reads a .glb file as plain bytes, and the file is sent with this type.
 */
class AttachProductModel
{
    public function __invoke(Product $product, UploadedFile $file): Media
    {
        $media = $product->addMedia($file)
            ->usingName($product->name)
            ->usingFileName(Str::slug($product->name).'-'.Str::lower(Str::random(6)).'.glb')
            ->toMediaCollection(Product::MODEL_COLLECTION);

        $media->forceFill(['mime_type' => GlbFile::MIME_TYPE])->save();

        return $media;
    }
}

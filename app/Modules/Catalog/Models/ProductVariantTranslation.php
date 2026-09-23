<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A variant's label in one language other than Polish (see Shared\Models\Concerns\HasTranslations). */
#[Fillable(['locale', 'label'])]
class ProductVariantTranslation extends Model {}

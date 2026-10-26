<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A product in one language other than Polish (see Shared\Models\Concerns\HasTranslations). */
#[Fillable(['locale', 'slug', 'name', 'description', 'seo_description', 'care_note', 'deviation', 'size_tolerance', 'safety_warnings'])]
class ProductTranslation extends Model {}

<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A category in one language other than Polish (see Shared\Models\Concerns\HasTranslations). */
#[Fillable(['locale', 'slug', 'name', 'seo_title', 'seo_description'])]
class CategoryTranslation extends Model {}

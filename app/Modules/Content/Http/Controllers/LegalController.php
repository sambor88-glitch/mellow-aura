<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Support\LegalDocument;
use Illuminate\View\View;

/**
 * /regulamin and /polityka-prywatnosci.
 */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('content::legal', [
            'document' => LegalDocument::terms(),
            'canonical' => route('content.terms'),
            'seoTitle' => 'Regulamin sklepu',
            'description' => 'Zasady zakupów w MellowAurze: zamówienia, płatności, dostawa, vouchery, warsztaty, odstąpienie od umowy i reklamacje.',
        ]);
    }

    public function privacy(): View
    {
        return view('content::legal', [
            'document' => LegalDocument::privacy(),
            'canonical' => route('content.privacy'),
            'seoTitle' => 'Polityka prywatności',
            'description' => 'Jakie dane zbieram w sklepie i na warsztatach, po co, jak długo je przechowuję, komu je przekazuję i jakie masz prawa.',
        ]);
    }
}

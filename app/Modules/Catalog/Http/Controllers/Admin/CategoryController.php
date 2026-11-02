<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\ArrangeCategories;
use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\DeleteCategory;
use App\Modules\Catalog\Actions\MoveCategory;
use App\Modules\Catalog\Actions\SaveCategoryTexts;
use App\Modules\Catalog\Http\Requests\Admin\SaveCategoryTextsRequest;
use App\Modules\Catalog\Http\Requests\Admin\SaveShopShortcutsRequest;
use App\Modules\Catalog\Http\Requests\Admin\StoreCategoryRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CategoryTree;
use App\Modules\Catalog\Support\ShopShortcuts;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Categories in the panel: the tree of shelves under Glina and Szycie, arranged by dragging (MA-351), what Google
 * shows for each shelf and the introduction above its products, plus the line under each shortcut card under the
 * shop's products.
 */
class CategoryController extends Controller
{
    public function index(Settings $settings): View
    {
        $tree = new CategoryTree(Category::query()->with('translations')->withCount(['products', 'alsoShownProducts'])->get());

        return view('catalog::admin.categories.index', [
            'tree' => $tree,
            // Every shelf in the order of the tree, the vouchers after Glina and Szycie: „Zapisz i następna” walks this.
            'categories' => $tree->inOrder(),
            'shortcutNotes' => collect(ShopShortcuts::ROUTES)->keys()->mapWithKeys(fn (string $label) => [$label => [
                'pl' => $settings->get(ShopShortcuts::noteKey($label, 'pl')),
                'en' => $settings->get(ShopShortcuts::noteKey($label, 'en')),
            ]])->all(),
        ]);
    }

    /** „Dodaj półkę”: it opens on its card, ready for its texts. */
    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): RedirectResponse
    {
        $category = $createCategory($request->parent(), $request->name(), $request->englishName());

        return to_route('admin.categories.index')
            ->withFragment('kategoria-'.$category->id)
            ->with('panel_status', $category->name.' — dodana w „'.$request->parent()->name.'”. Pokaże się w sklepie, gdy postawisz na niej produkt.');
    }

    /** „Zapisz i następna” saves and opens the next category in the list (MA-318). */
    public function update(SaveCategoryTextsRequest $request, Category $category, SaveCategoryTexts $saveTexts, MoveCategory $moveCategory): RedirectResponse
    {
        ['texts' => $texts, 'translations' => $translations] = $request->texts();

        DB::transaction(function () use ($request, $category, $texts, $translations, $saveTexts, $moveCategory) {
            $saveTexts($category, $texts, $translations);

            if ($parent = $request->parent()) {
                $moveCategory($category, $parent);
            }
        });

        $next = $request->filled('next') ? Category::query()->find($request->integer('next')) : null;

        return to_route('admin.categories.index')
            ->withFragment('kategoria-'.($next ?? $category)->id)
            ->with('panel_status', $next ? 'Zapisane. Teraz: '.$next->name.'.' : 'Zapisane. Google zobaczy zmianę przy następnej wizycie.');
    }

    /**
     * A drag in the tree sends the whole tree at once. From the panel's script it answers in JSON for the toast;
     * a tree changed in another window in the meantime gives 409 and the panel asks to refresh.
     */
    public function arrange(Request $request, ArrangeCategories $arrangeCategories): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'tree' => ['required', 'array'],
            'tree.*.id' => ['required', 'integer', 'distinct'],
            'tree.*.parent_id' => ['required', 'integer'],
        ]);

        try {
            $saved = $arrangeCategories(array_values($data['tree']));
            $status = $saved ? 200 : 409;
            $message = $saved
                ? 'Kolejność zapisana. Tak stoi teraz w sklepie.'
                : 'Półki zmieniły się w międzyczasie — odśwież stronę i przeciągnij jeszcze raz.';
        } catch (ValidationException $exception) {
            $status = 422;
            $message = collect($exception->errors())->flatten()->first();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return to_route('admin.categories.index')->with('panel_status', $message);
    }

    public function destroy(Category $category, DeleteCategory $deleteCategory): RedirectResponse
    {
        $blocker = DeleteCategory::blocker($category);
        $name = $category->name;

        if ($blocker !== null || ! $deleteCategory($category)) {
            return to_route('admin.categories.index')->withFragment('kategoria-'.$category->id)->with('panel_status', $blocker);
        }

        return to_route('admin.categories.index')->with('panel_status', $name.' — usunięta.');
    }

    public function shortcuts(SaveShopShortcutsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.categories.index')
            ->withFragment('skroty')
            ->with('panel_status', 'Podpisy zapisane. Sklep już je pokazuje.');
    }
}

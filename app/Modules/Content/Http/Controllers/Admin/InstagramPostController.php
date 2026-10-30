<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Content\Actions\AddInstagramPost;
use App\Modules\Content\Http\Requests\Admin\StoreInstagramPostRequest;
use App\Modules\Content\Models\InstagramPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Posts Kasia adds by hand to the Instagram section (MA-274): add one, move it, delete it.
 * The posts from the Behold feed are the feed's own and are not changed here.
 */
class InstagramPostController extends Controller
{
    public function store(StoreInstagramPostRequest $request, AddInstagramPost $addPost): RedirectResponse
    {
        $addPost($request->file('photo'), $request->permalink(), $request->alt());

        return $this->back('Post dodany. Widać go na stronie głównej, jeśli sekcja jest włączona.');
    }

    public function move(Request $request, InstagramPost $post): RedirectResponse
    {
        abort_unless($post->is_manual, 404);
        $direction = $request->input('kierunek') === 'gora' ? -1 : 1;

        // The posts get 1, 2, 3… with these two swapped, so posts saved with the same number still move.
        DB::transaction(function () use ($post, $direction) {
            $ids = InstagramPost::query()->manual()->ordered()->lockForUpdate()->pluck('id')->all();
            $from = array_search($post->id, $ids, true);

            if ($from === false || ! isset($ids[$from + $direction])) {
                return;
            }

            [$ids[$from], $ids[$from + $direction]] = [$ids[$from + $direction], $ids[$from]];

            foreach ($ids as $position => $id) {
                InstagramPost::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return $this->back('Kolejność zmieniona. Klienci już ją widzą.');
    }

    public function destroy(InstagramPost $post): RedirectResponse
    {
        abort_unless($post->is_manual, 404);

        Storage::disk('public')->delete($post->image_path);
        $post->delete();

        return $this->back('Post usunięty ze strony');
    }

    private function back(string $status): RedirectResponse
    {
        return to_route('admin.content.edit')->withFragment('instagram-reczne')->with('panel_status', $status);
    }
}

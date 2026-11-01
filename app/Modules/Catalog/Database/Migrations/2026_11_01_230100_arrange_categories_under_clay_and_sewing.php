<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The flat shop categories become a tree under Glina and Szycie (MA-351, Maciej's list of 1.11.2026).
 *
 * A category that keeps its products keeps its row, so the texts for Google and the introduction Kasia wrote stay:
 * „Kubki i filiżanki” becomes „Kubki”, „Jedwab” becomes „Szycie”, and so on. Its old addresses, Polish and English,
 * go on to the new one with a 301 (category_redirects). The rest of the tree is new and empty until Kasia adds products.
 * The products from a split category move to their own shelf; anything Kasia already moved elsewhere stays where it is.
 *
 * A fresh database has no categories yet, so nothing happens here: CatalogSeeder builds the same tree.
 * Vouchers stay outside the tree, at the top, with their own page.
 */
return new class extends Migration
{
    /** @var array<string, array{string, string, string, string}> top of the tree: slug => [name, English name, English slug, group] */
    private const ROOTS = [
        'glina' => ['Glina', 'Ceramics', 'ceramics', 'ceramics'],
        'szycie' => ['Szycie', 'Textiles', 'textiles', 'crafts'],
    ];

    /**
     * In shop order within each parent. „Migotki” has no English name yet: Kasia gives it one in the panel.
     *
     * @var array<string, array{string, ?string, ?string, string}> slug => [name, English name, English slug, parent slug]
     */
    private const CHILDREN = [
        'kubki-z-wlasnym-napisem' => ['Kubki z własnym napisem', 'Personalised mugs', 'personalised-mugs', 'glina'],
        'kubki' => ['Kubki', 'Mugs', 'mugs', 'glina'],
        'filizanki' => ['Filiżanki', 'Cups', 'cups', 'glina'],
        'patery' => ['Patery', 'Platters', 'platters', 'glina'],
        'miski' => ['Miski', 'Bowls', 'bowls', 'glina'],
        'talerze' => ['Talerze', 'Plates', 'plates', 'glina'],
        'wazony' => ['Wazony', 'Vases', 'vases', 'glina'],
        'bizuteria' => ['Biżuteria', 'Jewellery', 'jewellery', 'glina'],
        'kolczyki' => ['Kolczyki', 'Earrings', 'earrings', 'bizuteria'],
        'wisiorki' => ['Wisiorki', 'Pendants', 'pendants', 'bizuteria'],
        'pierscionki' => ['Pierścionki', 'Rings', 'rings', 'bizuteria'],
        'migotki' => ['Migotki', null, null, 'glina'],
        'kadzielnice' => ['Kadzielnice', 'Incense holders', 'incense-holders', 'glina'],
        'deski-do-krojenia' => ['Deski do krojenia', 'Chopping boards', 'chopping-boards', 'glina'],
        'zegary' => ['Zegary', 'Clocks', 'clocks', 'glina'],
        'oslonki-na-donice' => ['Osłonki na donice', 'Plant pot covers', 'plant-pot-covers', 'glina'],
        'maselnice' => ['Maselnice', 'Butter dishes', 'butter-dishes', 'glina'],
        'miski-dla-zwierzaka' => ['Miski dla zwierzaka', 'Pet bowls', 'pet-bowls', 'glina'],
        'rzezby' => ['Rzeźby', 'Sculptures', 'sculptures', 'glina'],
        'scrunchie' => ['Scrunchie', 'Scrunchies', 'scrunchies', 'szycie'],
        'opaski' => ['Opaski', 'Headbands', 'headbands', 'szycie'],
        'kosmetyczki' => ['Kosmetyczki', 'Makeup bags', 'makeup-bags', 'szycie'],
        'saszetki' => ['Saszetki', 'Pouches', 'pouches', 'szycie'],
        'maskotki' => ['Maskotki', 'Soft toys', 'soft-toys', 'szycie'],
        'kokardy' => ['Kokardy', 'Bows', 'bows', 'szycie'],
        'piorniki' => ['Piórniki', 'Pencil cases', 'pencil-cases', 'szycie'],
        'etui-na-klucze' => ['Etui na klucze', 'Key pouches', 'key-pouches', 'szycie'],
    ];

    /** @var array<string, string> old flat category => the place in the tree that takes over its row */
    private const RENAMED = [
        'jedwab' => 'szycie',
        'kubki-i-filizanki' => 'kubki',
        'talerze-i-miski' => 'talerze',
        'wazony-i-patery' => 'wazony',
        'kadzielnice-i-podstawki' => 'kadzielnice',
        'bizuteria' => 'bizuteria',
        'kosmetyczki-i-piorniki' => 'kosmetyczki',
    ];

    /** @var array<string, array{string, string, list<string>}> product => [its old category, its new one, also shown in] */
    private const MOVED = [
        'zestaw-4-filizanek' => ['kubki-i-filizanki', 'filizanki', []],
        // Kasia's quote, not the customer's: a mug, also shown beside the configurator.
        'kubki-z-cytatem' => ['kubki-i-filizanki', 'kubki', ['kubki-z-wlasnym-napisem']],
        'miski' => ['talerze-i-miski', 'miski', []],
        'patery' => ['wazony-i-patery', 'patery', []],
        // Maciej, 1.11.2026: with the jewellery, and still among the incense holders.
        'podstawki-pod-bizuterie' => ['kadzielnice-i-podstawki', 'bizuteria', ['kadzielnice']],
        'scrunchies' => ['jedwab', 'scrunchie', []],
        'jedwabne-opaski' => ['jedwab', 'opaski', []],
        'piorniki' => ['kosmetyczki-i-piorniki', 'piorniki', []],
    ];

    public function up(): void
    {
        $before = DB::table('categories')->whereIn('slug', array_keys(self::RENAMED))->pluck('id', 'slug')->map(fn ($id) => (int) $id);

        if ($before->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($before) {
            foreach (self::RENAMED as $from => $to) {
                if (isset($before[$from])) {
                    $this->rename($before[$from], $from, $to);
                }
            }

            $ids = [];
            $position = 0;

            foreach (self::ROOTS as $slug => [$name, $englishName, $englishSlug, $group]) {
                $ids[$slug] = $this->place($slug, $name, null, $group, ++$position);
                $this->name($ids[$slug], $englishName, $englishSlug);
            }

            // Vouchers and anything else outside the tree come after Glina and Szycie.
            DB::table('categories')->whereNull('parent_id')->whereNotIn('id', $ids)->orderBy('sort_order')->orderBy('id')->pluck('id')
                ->each(fn (int $id) => DB::table('categories')->where('id', $id)->update(['sort_order' => ++$position]));

            $positions = [];

            foreach (self::CHILDREN as $slug => [$name, $englishName, $englishSlug, $parent]) {
                $root = array_key_exists($parent, self::ROOTS) ? $parent : self::CHILDREN[$parent][3];
                $ids[$slug] = $this->place($slug, $name, $ids[$parent], self::ROOTS[$root][3], $positions[$parent] = ($positions[$parent] ?? 0) + 1);

                if ($englishName !== null) {
                    $this->name($ids[$slug], $englishName, $englishSlug);
                }
            }

            foreach (self::MOVED as $product => [$from, $to, $alsoIn]) {
                $row = DB::table('products')->where('slug', $product)->first(['id', 'category_id']);

                // Only a product still on its old shelf: one Kasia moved herself stays where she put it.
                if ($row === null || ! isset($before[$from]) || (int) $row->category_id !== (int) $before[$from]) {
                    continue;
                }

                DB::table('products')->where('id', $row->id)->update(['category_id' => $ids[$to]]);

                foreach ($alsoIn as $slug) {
                    DB::table('category_product')->insertOrIgnore(['category_id' => $ids[$slug], 'product_id' => $row->id]);
                }
            }
        });
    }

    /**
     * The tree cannot tell which flat category each product came from, so it stays: the old addresses still lead
     * to the right shelves.
     */
    public function down(): void {}

    /** The old row under its new name and address; both old addresses go on to it. */
    private function rename(int $id, string $from, string $to): void
    {
        $english = DB::table('category_translations')->where('category_id', $id)->where('locale', 'en')->first(['slug']);

        DB::table('categories')->where('id', $id)->update(['slug' => $to, 'name' => self::ROOTS[$to][0] ?? self::CHILDREN[$to][0], 'updated_at' => now()]);

        if ($from !== $to) {
            $this->redirect($id, 'pl', $from);
        }

        $englishSlug = self::ROOTS[$to][2] ?? self::CHILDREN[$to][2];

        if ($english !== null && $english->slug !== $englishSlug) {
            $this->redirect($id, 'en', $english->slug);
        }
    }

    private function redirect(int $id, string $locale, string $slug): void
    {
        DB::table('category_redirects')->insertOrIgnore([
            'category_id' => $id,
            'locale' => $locale,
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** A place in the tree, taking over the row with that address when there is one. */
    private function place(string $slug, string $name, ?int $parentId, string $group, int $position): int
    {
        $values = ['parent_id' => $parentId, 'group' => $group, 'sort_order' => $position, 'updated_at' => now()];
        $id = DB::table('categories')->where('slug', $slug)->value('id');

        if ($id !== null) {
            DB::table('categories')->where('id', $id)->update($values);

            return (int) $id;
        }

        return (int) DB::table('categories')->insertGetId([...$values, 'slug' => $slug, 'name' => $name, 'created_at' => now()]);
    }

    /** The English name and address, unless another category already has that address. */
    private function name(int $id, string $name, string $slug): void
    {
        if (DB::table('category_translations')->where('locale', 'en')->where('slug', $slug)->where('category_id', '!=', $id)->exists()) {
            return;
        }

        $row = DB::table('category_translations')->where('category_id', $id)->where('locale', 'en');

        if ($row->exists()) {
            $row->update(['name' => $name, 'slug' => $slug, 'updated_at' => now()]);
        } else {
            DB::table('category_translations')->insert(['category_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
};

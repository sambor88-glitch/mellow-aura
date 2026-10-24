<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The offer from the prototype (PRODUCTS, TYPE_OF, OCC, WHO in MellowAura.dc.html).
 *
 * Prices in grosze. Products are matched by slug, so re-running the seeder adds
 * what is missing and never touches products already edited in the panel.
 * Stock follows the prototype: 1 per stamped mug, 99 per voucher, 3 otherwise.
 */
class CatalogSeeder extends Seeder
{
    /**
     * CARE_CERAMIC in the prototype — the general rule a product's own note replaces.
     */
    private const CERAMIC_CARE_NOTE = 'Zmywarka tak, złoto tylko ręcznie';

    private const CERAMIC_CATEGORIES = [
        'kubki-i-filizanki', 'talerze-i-miski', 'wazony-i-patery', 'kadzielnice-i-podstawki', 'bizuteria',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $categories = $this->seedCategories();

            foreach ($this->products() as $position => $data) {
                $this->seedProduct($data, $categories[$data['category']], $position + 1);
            }
        });
    }

    /**
     * @return array<string, Category> keyed by slug
     */
    private function seedCategories(): array
    {
        $names = [
            'kubki-i-filizanki' => 'Kubki i filiżanki',
            'talerze-i-miski' => 'Talerze i miski',
            'wazony-i-patery' => 'Wazony i patery',
            'kadzielnice-i-podstawki' => 'Kadzielnice i podstawki',
            'bizuteria' => 'Biżuteria',
            'jedwab' => 'Jedwab',
            'kosmetyczki-i-piorniki' => 'Kosmetyczki i piórniki',
            'vouchery' => 'Vouchery',
        ];

        $categories = [];

        foreach (array_keys($names) as $position => $slug) {
            $category = Category::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $names[$slug], 'sort_order' => $position + 1],
            );

            // null means "never set"; the panel stores an emptied note as ''.
            if ($category->default_care_note === null && in_array($slug, self::CERAMIC_CATEGORIES, true)) {
                $category->update(['default_care_note' => self::CERAMIC_CARE_NOTE]);
            }

            $categories[$slug] = $category;
        }

        return $categories;
    }

    private function seedProduct(array $data, Category $category, int $sortOrder): void
    {
        if (Product::query()->where('slug', $data['slug'])->exists()) {
            return;
        }

        $product = Product::query()->create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'category_id' => $category->id,
            'description' => $data['description'] ?: null,
            'dimensions' => $data['dimensions'] ?? null,
            'is_published' => $data['published'] ?? true,
            'is_one_off' => $data['stamp'] ?? false,
            'stamp_enabled' => $data['stamp'] ?? false,
            'sort_order' => $sortOrder,
            'occasions' => $data['occasions'] ?? [],
            'recipients' => $data['recipients'] ?? [],
        ]);

        foreach ($data['variants'] as $position => [$label, $price, $compareAt]) {
            $product->variants()->create([
                'label' => $label,
                'price_gross' => $price,
                'compare_at_price' => $compareAt,
                'stock' => $data['stock'],
                'sort_order' => $position + 1,
            ]);
        }

        foreach ($data['photos'] ?? [] as $photo) {
            $path = base_path('zdjecia/'.$photo);

            if (is_file($path)) {
                $product->addMedia($path)->preservingOriginal()->toMediaCollection(Product::GALLERY);
            }
        }
    }

    /**
     * Variants: [label, price, compare-at price]. The prototype keeps one old
     * price per product; it goes on the variant that carries the headline price.
     */
    private function products(): array
    {
        return [
            [
                'slug' => 'wazony', 'name' => 'Wazony', 'category' => 'wazony-i-patery', 'stock' => 3,
                'variants' => [['Niski 16 cm', 23900, null], ['Średni 20 cm', 26900, null], ['Wysoki 24 cm', 29900, 31500]],
                'dimensions' => ['height' => 24, 'diameter' => 12, 'thickness' => 6],
                'description' => 'Ręcznie formowane wazony z ceramiki o organicznej, nieregularnej linii. Każdy egzemplarz to unikat — pełen drobnych asymetrii, które nadają mu charakter i duszę. Szkliwiony subtelnie, w odcieniach inspirowanych naturą: ziemią, wodą i światłem.',
                'photos' => ['wazon-rzezbiony.webp'],
                'occasions' => ['wedding', 'housewarming', 'mothers_day'], 'recipients' => ['for_her'],
            ],
            [
                'slug' => 'zestaw-4-filizanek', 'name' => 'Zestaw 4 filiżanek z talerzykami', 'category' => 'kubki-i-filizanki', 'stock' => 3,
                'variants' => [['Zestaw 2 szt.', 18900, null], ['Zestaw 4 szt.', 34900, null], ['Zestaw 6 szt.', 49900, null]],
                'description' => 'Zestaw czterech filiżanek z talerzykami, stworzony z myślą o chwilach, które chcemy celebrować — nawet tych codziennych. Każdy element jest ręcznie formowany, szkliwiony z czułością i nosi w sobie ślad dłoni, które go stworzyły.',
                'photos' => ['zestaw-filizanka-talerz.webp', 'zestaw-flatlay.webp'],
                'occasions' => ['wedding', 'housewarming', 'christmas'], 'recipients' => ['for_couple'],
            ],
            [
                'slug' => 'patery', 'name' => 'Patery', 'category' => 'wazony-i-patery', 'stock' => 3,
                'variants' => [['Mała 24 cm', 32900, null], ['Średnia 30 cm', 36900, null], ['Duża 36 cm', 39900, null]],
                'description' => 'Duże formy dekoracyjne na granicy naczynia i rzeźby. Patery powstają bez formy — z płata gliny, który sam decyduje, gdzie się wygnie. Czarne szkliwo wypalane na wysoką temperaturę daje głęboki, lustrzany połysk.',
                'photos' => ['patera-czarna-kolce.webp'],
                'occasions' => ['wedding', 'housewarming'], 'recipients' => ['for_couple'],
            ],
            [
                'slug' => 'talerze', 'name' => 'Talerze', 'category' => 'talerze-i-miski', 'stock' => 3,
                'variants' => [['Deserowy 18 cm', 11000, null], ['Obiadowy 24 cm', 14000, 15900], ['Serwisowy 30 cm', 18500, null]],
                'description' => 'Talerze z odciskiem prawdziwych roślin zebranych pod pracownią — baldachy, gałązki, liście. Roślina wypala się w piecu i zostawia po sobie rysunek, którego nie da się powtórzyć. Piaskowa glina z ciemnym nakrapianiem.',
                'photos' => ['talerz-niebieski-odcisk.webp', 'kasia-talerz-zloto.webp'],
                'occasions' => ['wedding', 'housewarming'], 'recipients' => ['for_couple'],
            ],
            [
                'slug' => 'bizuteria', 'name' => 'Biżuteria', 'category' => 'bizuteria', 'stock' => 3, 'published' => false,
                'variants' => [['Kolczyki', 12900, null], ['Broszka', 9900, null], ['Naszyjnik', 17900, null]],
                'description' => 'Ręcznie robiona biżuteria ceramiczna, która łączy w sobie subtelność formy i siłę natury. Lekka, organiczna, często szkliwiona delikatnymi pastelami lub złotem. Dla osób ceniących autentyczność i piękno niedoskonałości.',
                'occasions' => ['mothers_day', 'birthday', 'for_yourself'], 'recipients' => ['for_her'],
            ],
            [
                'slug' => 'kubki-z-cytatem', 'name' => 'Kubki z cytatem', 'category' => 'kubki-i-filizanki', 'stock' => 1, 'stamp' => true,
                'variants' => [['Królowa matka', 7900, 9900], ['Ochujeję', 7900, 9900], ['No ocipieje zaraz', 7900, 9900], ['Nie powinnam, ale…', 7900, 9900], ['Twój tekst', 7900, 9900]],
                'description' => 'Kubek, który mówi głośno to, co myślisz przed pierwszą kawą. Litery wbijam stemplem jedna po drugiej w surową glinę — „królowa matka", „ochujeję", „no ocipieje zaraz". Wnętrze szkliwione kolorem, na zewnątrz matowy piasek. Twój własny tekst do 22 znaków bez dopłaty.',
                'photos' => ['kubek-krolowa-matka.webp', 'kubek-ochujeje.webp', 'kubek-ocipieje.webp', 'kubek-nie-powinnam.webp'],
                'occasions' => ['birthday', 'for_yourself', 'christmas'], 'recipients' => ['for_her', 'for_him'],
            ],
            [
                'slug' => 'kubki-malowane', 'name' => 'Kubki malowane ręcznie', 'category' => 'kubki-i-filizanki', 'stock' => 3,
                'variants' => [['200 ml', 6900, null], ['300 ml', 7900, 9900], ['400 ml', 9500, null]],
                'dimensions' => ['height' => 8.5, 'diameter' => 9, 'capacity' => 300, 'thickness' => 5],
                'description' => 'Kubki formowane z ręki, o ściankach grubszych tam, gdzie trzyma je dłoń. Malowane pędzlem — każdy inaczej. Nadają się do zmywarki i do porannej kawy pitej wolniej niż zwykle.',
                'photos' => ['kubek-cappuccino.webp', 'zestaw-flatlay.webp'],
                'occasions' => ['birthday', 'housewarming', 'christmas'], 'recipients' => ['for_her', 'for_him'],
            ],
            [
                'slug' => 'kadzielnice', 'name' => 'Kadzielnice', 'category' => 'kadzielnice-i-podstawki', 'stock' => 3,
                'variants' => [['Pszczoły ze złotem', 9900, null]],
                'description' => 'Kadzielnica na patyczki, z misą łapiącą popiół i złoconym kołnierzem wypalanym w trzecim wypale. Drobne pszczoły odbijane stemplem — mały rytuał zapalania czegoś dla siebie o zmierzchu.',
                'photos' => ['kadzielnica-pszczoly.webp'],
                'occasions' => ['for_yourself', 'christmas', 'birthday'], 'recipients' => ['for_her', 'for_him'],
            ],
            [
                'slug' => 'podstawki-pod-bizuterie', 'name' => 'Podstawki pod biżuterię', 'category' => 'kadzielnice-i-podstawki', 'stock' => 3,
                'variants' => [['Mała', 7900, null], ['Średnia', 9900, null], ['Duża', 12900, null]],
                'description' => 'Małe muszle z gliny z perłą w środku, na której zawiesisz pierścionek na noc. Szkliwo pudrowe, tak samo jak tkanina, na której je fotografuję. Idealne na prezent, który nie zajmuje miejsca.',
                'photos' => ['podstawki-muszle-roz.webp'],
                'occasions' => ['mothers_day', 'birthday', 'for_yourself'], 'recipients' => ['for_her'],
            ],
            [
                'slug' => 'scrunchies', 'name' => 'Scrunchies w 3 rozmiarach', 'category' => 'jedwab', 'stock' => 3,
                'variants' => [['Mała', 5900, null], ['Średnia', 6900, null], ['Duża XL', 7900, null]],
                'dimensions' => ['circumference' => 18, 'width' => 5],
                'description' => 'Miękkie scrunchies uszyte z wyselekcjonowanych tkanin — jedwabiu, lnu i bawełny o naturalnym splocie. Każda gumka powstaje z uważnością, często z końcówek tkanin lub materiałów z drugiego obiegu, którym daję nowe życie.',
                'photos' => ['scrunchies-fiolet.webp', 'opaska-jedwab.webp'],
                'occasions' => ['for_yourself', 'birthday', 'mothers_day'], 'recipients' => ['for_her'],
            ],
            [
                'slug' => 'jedwabne-opaski', 'name' => 'Jedwabne opaski', 'category' => 'jedwab', 'stock' => 3,
                'variants' => [['Wąska 2 cm', 8900, null], ['Średnia 4 cm', 9900, null], ['Szeroka pikowana', 11900, null]],
                'description' => 'Opaski szyte na sztywnym kabłąku, obszyte jedwabiem z drugiego obiegu — wzory, których nie kupisz na metry. Każda jest jedna, bo tkaniny wystarcza dokładnie na jedną.',
                'photos' => ['opaska-jedwab.webp', 'scrunchies-fiolet.webp'],
                'occasions' => ['mothers_day', 'birthday', 'for_yourself'], 'recipients' => ['for_her'],
            ],
            [
                'slug' => 'voucher-lepienie', 'name' => 'Voucher na warsztat — lepienie z ręki', 'category' => 'vouchery', 'stock' => 99,
                'variants' => [['Dla jednej osoby', 22000, null], ['Dla dwóch osób', 42000, null], ['Dla trzech osób', 60000, null]],
                'description' => 'Dwie i pół godziny przy stole w mojej pracowni, glina, narzędzia i dwa wypały w cenie. Voucher dostajesz w PDF do wydruku albo wyślę go pocztą na ładnym papierze. Ważny rok — termin obdarowana osoba wybiera sama.',
                'photos' => ['zestaw-flatlay.webp'],
                'occasions' => ['birthday', 'christmas', 'mothers_day'], 'recipients' => ['for_her', 'for_him'],
            ],
            [
                'slug' => 'voucher-para', 'name' => 'Voucher — warsztat dla pary', 'category' => 'vouchery', 'stock' => 99,
                'variants' => [['PDF do wydruku', 39000, null], ['Wysyłka pocztą', 39000, null]],
                'description' => 'Trzy godziny, dwa stanowiska obok siebie i dwa kubki na koniec. Popularny prezent na rocznicę — i jedyny, po którym zostają rzeczy do codziennego użytku, a nie zdjęcia.',
                'photos' => ['kubek-cappuccino.webp'],
                'occasions' => ['anniversary', 'christmas', 'wedding'], 'recipients' => ['for_couple'],
            ],
            [
                'slug' => 'voucher-kwotowy', 'name' => 'Voucher kwotowy', 'category' => 'vouchery', 'stock' => 99,
                'variants' => [['150 zł', 15000, null], ['250 zł', 25000, null], ['400 zł', 40000, null]],
                'description' => 'Nie wiesz, czy wolą lepić, czy wybrać coś gotowego? Voucher kwotowy działa i na warsztaty, i na wszystko ze sklepu. Ważny rok, można łączyć z innymi.',
                'photos' => ['talerz-niebieski-odcisk.webp'],
                'occasions' => ['birthday', 'christmas', 'for_yourself'], 'recipients' => ['for_her', 'for_him', 'for_couple'],
            ],
            [
                'slug' => 'miski', 'name' => 'Miski', 'category' => 'talerze-i-miski', 'stock' => 3, 'published' => false,
                'variants' => [['Mała 12 cm', 8900, null], ['Średnia 16 cm', 11900, null], ['Duża 22 cm', 14900, null]],
                'description' => '',
            ],
            [
                'slug' => 'kosmetyczki', 'name' => 'Kosmetyczki', 'category' => 'kosmetyczki-i-piorniki', 'stock' => 3, 'published' => false,
                'variants' => [['Mała', 9900, null], ['Średnia', 12900, null], ['Duża', 15900, null]],
                'description' => '',
            ],
            [
                'slug' => 'piorniki', 'name' => 'Piórniki', 'category' => 'kosmetyczki-i-piorniki', 'stock' => 3, 'published' => false,
                'variants' => [['Mały płaski', 6900, null], ['Średni', 8900, null], ['Duży tubowy', 10900, null]],
                'description' => '',
            ],
        ];
    }
}

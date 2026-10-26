<?php

namespace App\Modules\Shared\Support;

use Closure;
use Illuminate\Support\Str;
use XMLWriter;

/**
 * The products Google Merchant Center fetches from /google-merchant.xml, which opens the free listings in Google
 * Shopping. Each module adds what it sells in its provider, so a new kind of product needs no change here.
 * The file is built on request, so a product published in the panel is in it at once.
 */
class MerchantFeed
{
    public const BRAND = 'MellowAura';

    /** @var list<Closure(): iterable<MerchantItem>> */
    private array $sources = [];

    /**
     * @param  callable(): iterable<MerchantItem>  $source
     */
    public function add(callable $source): static
    {
        $this->sources[] = $source(...);

        return $this;
    }

    /**
     * Only what Google can show: an item with a photo and a price.
     *
     * @return list<MerchantItem>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->sources as $source) {
            foreach ($source() as $item) {
                if (filled($item->image) && $item->price > 0) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * RSS 2.0 with Google's namespace, the format Merchant Center fetches on a schedule.
     */
    public function xml(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $xml->startElement('channel');
        $xml->writeElement('title', self::BRAND);
        $xml->writeElement('link', url('/'));
        $xml->writeElement('description', self::BRAND);

        foreach ($this->items() as $item) {
            $this->writeItem($xml, $item);
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function writeItem(XMLWriter $xml, MerchantItem $item): void
    {
        $xml->startElement('item');
        $this->write($xml, 'id', Str::limit($item->id, 50, ''));
        $this->write($xml, 'title', Str::limit($item->title, 150, ''));
        $this->write($xml, 'description', Str::limit(self::plain($item->description), 5000, ''));
        $this->write($xml, 'link', $item->link);
        $this->write($xml, 'image_link', $item->image);

        foreach (array_slice($item->additionalImages, 0, 10) as $image) {
            $this->write($xml, 'additional_image_link', $image);
        }

        $this->write($xml, 'availability', $item->available ? 'in_stock' : 'out_of_stock');
        $this->write($xml, 'price', self::amount($item->price));

        if ($item->salePrice !== null && $item->salePrice < $item->price) {
            $this->write($xml, 'sale_price', self::amount($item->salePrice));
        }

        $this->write($xml, 'brand', self::BRAND);
        // Handmade in the studio: no barcode and no manufacturer's part number exist.
        $this->write($xml, 'identifier_exists', 'no');
        $this->write($xml, 'condition', 'new');

        if ($item->googleCategory !== null) {
            $this->write($xml, 'google_product_category', (string) $item->googleCategory);
        }

        if ($item->productType !== null) {
            $this->write($xml, 'product_type', $item->productType);
        }

        if ($item->isBundle) {
            $this->write($xml, 'is_bundle', 'yes');
        }

        if (! $item->returnable) {
            $this->write($xml, 'return_policy_label', MerchantItem::NOT_RETURNABLE);
        }

        foreach ($item->delivery as $option) {
            $xml->startElement('g:shipping');
            $this->write($xml, 'country', 'PL');
            $this->write($xml, 'service', $option['label']);
            $this->write($xml, 'price', self::amount($option['price']));

            if ($option['handling'] !== null) {
                $this->write($xml, 'min_handling_time', (string) $option['handling'][0]);
                $this->write($xml, 'max_handling_time', (string) $option['handling'][1]);
            }

            if ($option['transit'] !== null) {
                $this->write($xml, 'min_transit_time', (string) $option['transit'][0]);
                $this->write($xml, 'max_transit_time', (string) $option['transit'][1]);
            }

            $xml->endElement();
        }

        $xml->endElement();
    }

    private function write(XMLWriter $xml, string $name, ?string $value): void
    {
        $xml->writeElement('g:'.$name, (string) $value);
    }

    private static function amount(int $grosze): string
    {
        return Money::decimal($grosze).' PLN';
    }

    /**
     * Google wants plain text: no tags, one space between words.
     */
    private static function plain(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    }
}

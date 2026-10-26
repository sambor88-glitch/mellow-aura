<?php

namespace App\Modules\Firing\Models;

use App\Modules\Firing\Enums\FiringRequestStatus;
use App\Modules\Shared\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A batch reported through the firing form. Kasia works through them in the panel, grouped by the day they come in.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?string $phone
 * @property ?string $service_code
 * @property ?string $service_label
 * @property ?string $unit
 * @property ?string $quantity
 * @property ?int $unit_price
 * @property ?int $quote
 * @property ?Carbon $drop_off_on
 * @property ?string $message
 * @property FiringRequestStatus $status
 */
class FiringRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'quote' => 'integer',
            'drop_off_on' => 'date',
            'status' => FiringRequestStatus::class,
        ];
    }

    /**
     * „2,5 kg”, „3 szt.” or null, as the mails show the amount.
     */
    public function amount(): ?string
    {
        if ($this->quantity === null) {
            return null;
        }

        $number = rtrim(rtrim(str_replace('.', ',', (string) $this->quantity), '0'), ',');

        return $number.' '.($this->unit === 'kg' ? 'kg' : 'szt.');
    }

    /**
     * The lines both mails list, label => value; an empty one is left out.
     *
     * @return array<string, string>
     */
    public function summary(): array
    {
        return array_filter([
            'Wypał' => $this->service_label ?? 'Nie wiem, doradź',
            'Ile' => $this->amount(),
            'Cena z cennika' => $this->unit_price !== null && $this->quantity !== null
                ? Money::format($this->unit_price).($this->unit === 'kg' ? ' / kg' : ' / szt.')
                : null,
            'Przywiezienie' => $this->drop_off_on?->locale('pl')->translatedFormat('l, j F Y'),
            'Wycena' => $this->quote !== null ? Money::format($this->quote) : 'po obejrzeniu prac',
        ], fn (?string $value) => $value !== null);
    }
}

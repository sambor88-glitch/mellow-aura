<?php

namespace App\Modules\Shared\Http\Requests\Concerns;

/**
 * A list edited in rows: every row has a „usuń” box, a few empty rows at the end take new entries,
 * and the ↑ ↓ buttons send the whole form with `move=<row>:up|down`, so nothing typed is lost.
 * Validation runs on the rows as sent, so each error stays next to its row.
 */
trait EditsListRows
{
    public function moved(): bool
    {
        return $this->move() !== null;
    }

    /**
     * The rows to save: moved when an arrow was pressed, without removed rows and rows whose first
     * field is empty, trimmed, with an empty field saved as null.
     *
     * @param  list<string>  $fields  the first one names the row, e.g. the question
     * @return list<array<string, ?string>>
     */
    protected function listRows(string $name, array $fields): array
    {
        $rows = array_values(array_filter((array) $this->input($name, []), 'is_array'));

        if ($move = $this->move()) {
            [$from, $to] = $move;

            if (isset($rows[$from], $rows[$to])) {
                [$rows[$from], $rows[$to]] = [$rows[$to], $rows[$from]];
            }
        }

        return collect($rows)
            ->reject(fn (array $row) => (bool) ($row['remove'] ?? false))
            ->map(fn (array $row) => collect($fields)->mapWithKeys(fn (string $field) => [
                $field => filled($value = $row[$field] ?? null) ? str_replace("\r\n", "\n", trim((string) $value)) : null,
            ])->all())
            ->filter(fn (array $row) => $row[$fields[0]] !== null)
            ->values()
            ->all();
    }

    /**
     * @return array{int, int}|null
     */
    private function move(): ?array
    {
        if (! preg_match('/^(\d+):(up|down)$/', (string) $this->input('move'), $match)) {
            return null;
        }

        return [(int) $match[1], $match[2] === 'up' ? (int) $match[1] - 1 : (int) $match[1] + 1];
    }
}

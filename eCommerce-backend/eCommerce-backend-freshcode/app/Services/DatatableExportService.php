<?php

namespace App\Services;

use App\Models\DatatableExport;
use App\Support\CsvSafe;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatatableExportService
{
    public function __construct(private DatatableExportRegistry $registry) {}

    public function count(string $module, string $panel, object $user, array $parameters, string $scope): int
    {
        if ($scope === 'current_page') {
            $total = $this->registry->query($module, $panel, $user, $parameters, $scope)->count();
            if ($this->registry->definition($module, $panel)['current_page_expands'] ?? false) {
                return $total;
            }
            $remaining = max(0, $total - max(0, (int) ($parameters['start'] ?? 0)));

            return min($remaining, max(1, min(1000, (int) ($parameters['length'] ?? 10))));
        }

        $query = $this->registry->query($module, $panel, $user, $parameters, $scope);

        return $query->count();
    }

    public function generate(DatatableExport $export): DatatableExport
    {
        if ($export->format !== 'csv') {
            throw new RuntimeException('Unsupported export format.');
        }

        $user = $export->user;
        if (! $user) {
            throw new RuntimeException('Export owner no longer exists.');
        }

        $this->registry->authorize($user, $export->module, $export->panel);
        $definition = $this->registry->definition($export->module, $export->panel);
        $headings = array_map(fn (string $column) => $definition['columns'][$column], $export->columns);
        $extension = $export->format;
        $filename = $export->module.'-'.$export->created_at->format('Ymd-His').'.'.$extension;
        $relativePath = 'datatable-exports/'.$export->user_id.'/'.$export->id.'/'.$filename;
        Storage::disk($export->disk)->makeDirectory(dirname($relativePath));
        $path = Storage::disk($export->disk)->path($relativePath);
        $query = $this->scopedQuery($export->module, $export->panel, $user, $export->parameters, $export->scope);

        $export->update([
            'status' => 'processing',
            'processed_rows' => 0,
            'path' => $relativePath,
            'filename' => $filename,
            'expires_at' => now()->addHours((int) config('datatable-exports.expires_after_hours', 24)),
        ]);

        $this->writeCsv($path, $headings, $query, $export);

        $export->update([
            'status' => 'ready',
            'path' => $relativePath,
            'filename' => $filename,
            'processed_rows' => $export->total_rows,
            'completed_at' => now(),
            'expires_at' => now()->addHours((int) config('datatable-exports.expires_after_hours', 24)),
        ]);

        return $export->refresh();
    }

    private function scopedQuery(string $module, string $panel, object $user, array $parameters, string $scope): Builder
    {
        $query = $this->registry->query($module, $panel, $user, $parameters, $scope);

        if ($scope === 'current_page' && ! ($this->registry->definition($module, $panel)['current_page_expands'] ?? false)) {
            $query->offset(max(0, (int) ($parameters['start'] ?? 0)))
                ->limit(max(1, min(1000, (int) ($parameters['length'] ?? 10))));
        }

        return $query;
    }

    private function writeCsv(string $path, array $headings, Builder $query, DatatableExport $export): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to create CSV export.');
        }
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headings);
        $this->eachRow($query, $export, function (array $row) use ($handle): void {
            fputcsv($handle, CsvSafe::row($row));
        });
        fclose($handle);
    }

    private function eachRow(Builder $query, DatatableExport $export, callable $callback): void
    {
        $processed = 0;
        foreach ($query->lazy(200) as $record) {
            $mapped = $this->registry->row($export->module, $export->panel, $record, $export->user);
            $row = array_map(fn (string $column) => $this->normalize($mapped[$column] ?? null), $export->columns);
            $callback($row);
            $processed++;
            if ($processed % 200 === 0) {
                $export->update(['processed_rows' => $processed]);
            }
        }
    }

    private function normalize(mixed $value): string|int|float|null
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if (is_string($value)) {
            return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if ($value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        return (string) $value;
    }
}

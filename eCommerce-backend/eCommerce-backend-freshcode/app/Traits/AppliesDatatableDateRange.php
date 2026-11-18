<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesDatatableDateRange
{
    protected function applyDatatableDateRange(Builder $query, Request $request, string $column = 'created_at', ?string $relation = null): void
    {
        $from = $this->datatableDate($request->input('date_from'), false);
        $to = $this->datatableDate($request->input('date_to'), true);
        if (! $from && ! $to) {
            return;
        }
        $apply = function (Builder $builder) use ($column, $from, $to): void {
            if ($from) {
                $builder->where($column, '>=', $from);
            }
            if ($to) {
                $builder->where($column, '<=', $to);
            }
        };
        $relation ? $query->whereHas($relation, $apply) : $apply($query);
    }

    private function datatableDate(mixed $value, bool $end): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value, config('app.timezone'));

            return $end ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}

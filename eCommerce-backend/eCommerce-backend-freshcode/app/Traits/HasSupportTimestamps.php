<?php

namespace App\Traits;

use App\Support\SupportDateTime;
use Illuminate\Support\Carbon;

trait HasSupportTimestamps
{
    protected function asDateTime($value)
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d+)?$/', $value)) {
            return Carbon::parse($value, SupportDateTime::storageTimezone())->setTimezone(config('app.timezone'));
        }

        return parent::asDateTime($value);
    }

    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }

        $date = is_string($value)
            ? Carbon::parse($value, config('app.timezone'))
            : parent::asDateTime($value);

        return SupportDateTime::forStorage($date)->format($this->getDateFormat());
    }
}

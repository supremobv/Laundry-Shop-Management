<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $guarded = ['id'];

    public static function current(): self
    {
        return app('business.settings');
    }

    public function money(int|float|null $cents): string
    {
        return $this->currency.' '.number_format(($cents ?? 0) / 100, 2);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    public const DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES = 20;
    public const DEFAULT_CHECK_IN_MAX_LATE_MINUTES = 40;

    protected $fillable = [
        'latitude',
        'longitude',
        'radius_meters',
        'check_in_time',
        'check_in_late_tolerance_minutes',
        'check_in_max_late_minutes',
        'check_out_time',
    ];

    protected $casts = [
        'radius_meters' => 'integer',
        'check_in_late_tolerance_minutes' => 'integer',
        'check_in_max_late_minutes' => 'integer',
    ];

    public function checkInLateToleranceMinutes(): int
    {
        return (int) ($this->check_in_late_tolerance_minutes ?? self::DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES);
    }

    public function checkInMaxLateMinutes(): int
    {
        return (int) ($this->check_in_max_late_minutes ?? self::DEFAULT_CHECK_IN_MAX_LATE_MINUTES);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutsideDuty extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'duty_date',
        'planned_start',
        'planned_end',
        'location_name',
        'latitude',
        'longitude',
        'requested_radius_meters',
        'approved_radius_meters',
        'reason',
        'request_photo',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'attended_at',
        'attendance_photo',
        'attendance_location',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'attended_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function effectiveRadiusMeters(): int
    {
        return (int) ($this->approved_radius_meters ?? $this->requested_radius_meters);
    }
}

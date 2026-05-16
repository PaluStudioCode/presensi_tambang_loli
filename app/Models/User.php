<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_number',
        'full_name',
        'department',
        'address',
        'email',
        'password',
        'role',
        'profile_photo',
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function overtimes()
    {
        return $this->hasMany(Overtime::class);
    }

    public function approvedOvertimes()
    {
        return $this->hasMany(Overtime::class, 'approved_by');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function approvedLeaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    public function outsideDuties()
    {
        return $this->hasMany(OutsideDuty::class);
    }

    public function approvedOutsideDuties()
    {
        return $this->hasMany(OutsideDuty::class, 'approved_by');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
    ];
}

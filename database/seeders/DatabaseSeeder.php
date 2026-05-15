<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Setting;
use App\Models\Attendance;
use App\Models\Overtime;
use Faker\Factory as Faker;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // 1. Create Default Setting (Palu, Sulawesi Tengah)
        Setting::create([
            'latitude' => '-0.891700',
            'longitude' => '119.870700',
            'radius_meters' => 100,
            'check_in_time' => '08:00:00',
            'check_in_late_tolerance_minutes' => 20,
            'check_in_max_late_minutes' => 40,
            'check_out_time' => '17:00:00',
        ]);

        // 2. Create 1 Admin
        $admin = User::create([
            'id_number' => '1234567890',
            'full_name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'Admin'
        ]);

        // 3. Create 1 Karyawan (Employee)
        $employees = [
            User::create([
                'id_number' => $faker->unique()->nik(),
                'full_name' => "agus karyawan",
                'email' => "karyawan@example.com",
                'password' => Hash::make('password'),
                'role' => 'Employee'
            ]),
        ];
    }
    }

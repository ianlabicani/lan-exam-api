<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentRole = Role::where('name', 'student')->first();
        $students = [];

        // Create students for each year and section
        foreach (range(1, 4) as $year) {
            foreach (range('a', 'g') as $section) {
                $student = User::create([
                    'name' => "Student {$year}".strtoupper($section),
                    'email' => "student{$year}{$section}@mail.com",
                    'password' => Hash::make('password'),
                    'year' => (string) $year,
                    'section' => $section,
                ]);
                $student->roles()->attach($studentRole);
                $students[] = $student;
            }
        }

        $teacher = User::create([
            'name' => 'Teacher',
            'email' => 'teacher@mail.com',
            'password' => Hash::make('password'),
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@mail.com',
            'password' => Hash::make('password'),
        ]);

        $teacher->roles()->attach(Role::where('name', 'teacher')->first());
        $admin->roles()->attach(Role::where('name', 'admin')->first());
    }
}

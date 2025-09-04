<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teacher = User::create([
            'name' => 'John Teacher',
            'email' => 'teacher@example.com',
            'password' => Hash::make('password'),
        ]);
        $teacher->roles()->attach(Role::where('name', 'teacher')->first());

        // Student user
        $students = [];
        foreach (range('a', 'g') as $section) {
            $student = User::create([
                'name' => 'Student ' . strtoupper($section),
                'email' => "student{$section}@example.com",
                'password' => Hash::make('password'),
                'year' => '1',
                'section' => $section,
            ]);
            $student->roles()->attach(Role::where('name', 'student')->first());
            $students[] = $student;
        }

        $student = User::create([
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'year' => '1',
            'section' => 'a',
        ]);
        $student->roles()->attach(Role::where('name', 'student')->first());
    }
}

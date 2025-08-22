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
            'password' => Hash::make('password'), // change in production
        ]);
        $teacher->roles()->attach(Role::where('name', 'teacher')->first());

        $student = User::create([
            'name' => 'Jane Student',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
        ]);
        $student->roles()->attach(Role::where('name', 'student')->first());

    }
}

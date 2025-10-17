<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'student'],
            ['name' => 'teacher'],
            ['name' => 'admin'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}

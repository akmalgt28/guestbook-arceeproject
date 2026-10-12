<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Super Admin', 'email' => 'admin@guestbook.test', 'password' => 'Admin12345!', 'role' => 'Super Admin'],
            ['name' => 'Event Admin', 'email' => 'event.admin@guestbook.test', 'password' => 'Event12345!', 'role' => 'Event Admin'],
            ['name' => 'QR Scanner', 'email' => 'scanner@guestbook.test', 'password' => 'Scanner12345!', 'role' => 'QR Scanner'],
        ];
        foreach ($users as $data) {
            $user = User::query()->firstOrCreate(['email' => $data['email']], ['name' => $data['name'], 'password' => $data['password']]);
            $user->update(['name' => $data['name']]);
            $user->syncRoles([$data['role']]);
        }
    }
}

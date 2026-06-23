<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'User One',
            'email' => 'user1@test.com',
            'password' => Hash::make('12345678'),
            'wallet_balance' => 70000,
        ]);
        
        $faker = Faker::create();
        $usersData = [];

        for ($i = 1; $i <= 20; $i++) {
            $usersData[] = [
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'password' => Hash::make('password'),
                'wallet_balance' => rand(5000, 200000),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($usersData, 50) as $chunk) {
            User::insert($chunk);
        }
    }
}
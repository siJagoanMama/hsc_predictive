<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Agent;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::updateOrCreate(
            ['email' => 'haydarAdmin@example.com'],
            [
                'name' => 'HaydarAdmin',
                'role' => UserRole::Admin,
                'password' => Hash::make('haydar123'),
            ]
        );

        // Create Super Admin User
        $superAdmin = User::updateOrCreate(
            ['email' => 'haydarSuperAdmin@example.com'],
            [
                'name' => 'Haydar SuperAdmin',
                'role' => UserRole::SuperAdmin,
                'password' => Hash::make('haydar123'),
            ]
        );

        // Create sample agents
        $agents = [
            ['name' => 'Agent 1', 'extension' => '101', 'email' => 'agent1@example.com'],
            ['name' => 'Agent 2', 'extension' => '102', 'email' => 'agent2@example.com'],
            ['name' => 'Agent 3', 'extension' => '103', 'email' => 'agent3@example.com'],
        ];

        foreach ($agents as $agentData) {
            $user = User::updateOrCreate(
                ['email' => $agentData['email']],
                [
                    'name' => $agentData['name'],
                    'role' => UserRole::Agent,
                    'password' => Hash::make('password'),
                ]
            );

            $agent = Agent::updateOrCreate(
                ['extension' => $agentData['extension']],
                [
                    'user_id' => $user->id,
                    'name' => $agentData['name'],
                    'status' => 'idle',
                ]
            );

            $user->update(['agent_id' => $agent->id]);
        }

        $this->call([
            CallerIdSeeder::class,
        ]);
    }
}
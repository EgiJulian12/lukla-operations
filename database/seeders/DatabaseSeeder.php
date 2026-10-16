<?php

namespace Database\Seeders;

use App\Enums\ClientType;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Urutan penting: peran harus ada sebelum akun demo ditetapkan ke peran.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $this->seedInternalUsers();
        $this->seedTailorUsers();
        $this->seedClientUsers();

        $this->call(ApprovedSampleDemoSeeder::class);
    }

    /**
     * Satu akun demo per peran internal (7 peran). Semua memakai password
     * "password" dari UserFactory dan punya akses ke panel /admin.
     */
    private function seedInternalUsers(): void
    {
        foreach (UserRole::internal() as $role) {
            $user = User::factory()->create([
                'name' => $role->getLabel(),
                'email' => $role->value.'@lukla.test',
            ]);

            $user->assignRole($role->value);
        }
    }

    /**
     * 3 Akun penjahit tambahan untuk pengujian antrean dan penugasan perakitan.
     */
    private function seedTailorUsers(): void
    {
        $tailors = [
            ['name' => 'Penjahit 1 (Agus)', 'email' => 'penjahit1@lukla.test'],
            ['name' => 'Penjahit 2 (Bambang)', 'email' => 'penjahit2@lukla.test'],
            ['name' => 'Penjahit 3 (Citra)', 'email' => 'penjahit3@lukla.test'],
        ];

        foreach ($tailors as $data) {
            $user = User::factory()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => true,
            ]);

            $user->assignRole(UserRole::TAILOR_OPERATOR->value);
        }
    }

    /**
     * Klien maklon demo beserta akun portal-nya. Akun ini terikat ke satu
     * client_id sehingga hanya bisa melihat data klien tersebut (FR-025, NFR-004).
     */
    private function seedClientUsers(): void
    {
        $client = Client::query()->firstOrCreate(
            ['code' => 'MAK-DEMO'],
            [
                'name' => 'PT Maklon Demo',
                'type' => ClientType::MAKLON,
                'email' => 'kontak@maklon-demo.test',
            ],
        );

        $user = User::factory()->create([
            'client_id' => $client->id,
            'name' => 'Klien Maklon Demo',
            'email' => 'klien@lukla.test',
        ]);

        $user->assignRole(UserRole::CLIENT_MAKLON->value);
    }
}

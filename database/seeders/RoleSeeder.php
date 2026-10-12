<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menyemai delapan peran operasional (RBAC matrix) dari App\Enums\UserRole.
 *
 * Nama role di tabel `roles` memakai istilah operasional Indonesia
 * (owner_eksekutif, tim_sample, gudang, ...) sehingga sama dengan nilai enum
 * yang dipakai di seluruh pemeriksaan otorisasi aplikasi.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value);
        }
    }
}

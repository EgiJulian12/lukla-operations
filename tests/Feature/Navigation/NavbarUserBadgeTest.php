<?php

namespace Tests\Feature\Navigation;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarUserBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_panel_topbar_displays_user_name_and_role_badge(): void
    {
        $owner = User::factory()->create([
            'name' => 'Pak Budi Handoko',
        ]);
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $this->actingAs($owner)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Pak Budi Handoko')
            ->assertSee('Owner');
    }

    public function test_different_internal_roles_render_correct_badge_labels(): void
    {
        $productionLead = User::factory()->create(['name' => 'Mandor Joko']);
        $productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->actingAs($productionLead)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Mandor Joko')
            ->assertSee('Kepala Produksi');

        $qc = User::factory()->create(['name' => 'QC Rina']);
        $qc->assignRole(UserRole::QC_INSPECTOR->value);

        $this->actingAs($qc)
            ->get('/admin')
            ->assertOk()
            ->assertSee('QC Rina')
            ->assertSee('QC Inspector');

        $warehouse = User::factory()->create(['name' => 'Gudang Agus']);
        $warehouse->assignRole(UserRole::WAREHOUSE->value);

        $this->actingAs($warehouse)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Gudang Agus')
            ->assertSee('Gudang');
    }

    public function test_portal_panel_topbar_displays_client_user_name_and_company(): void
    {
        $client = Client::factory()->create([
            'name' => 'CV Busana Megah',
            'code' => 'BM-01',
        ]);

        $clientUser = User::factory()->create([
            'client_id' => $client->id,
            'name' => 'Ibu Maya',
        ]);
        $clientUser->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->actingAs($clientUser)
            ->get('/portal/'.$client->code)
            ->assertOk()
            ->assertSee('Ibu Maya')
            ->assertSee('Klien Maklon')
            ->assertSee('CV Busana Megah');
    }

    public function test_guest_access_does_not_break_view(): void
    {
        $this->get('/admin/login')
            ->assertOk();
    }

    public function test_global_search_is_disabled_on_admin_and_portal_topbars(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $this->actingAs($owner)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('fi-global-search');

        $client = Client::factory()->create();
        $clientUser = User::factory()->create(['client_id' => $client->id]);
        $clientUser->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->actingAs($clientUser)
            ->get('/portal/'.$client->code)
            ->assertOk()
            ->assertDontSee('fi-global-search');
    }
}

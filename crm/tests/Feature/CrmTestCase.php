<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class CrmTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $repA;
    protected User $repB;
    protected User $otherTeamRep;

    protected function setUp(): void
    {
        parent::setUp();
        CompanySetting::forget();
        CompanySetting::create([
            'company_name' => 'Test Pharma Pvt Ltd',
            'office_start' => '09:30', 'office_end' => '18:30', 'lunch_start' => '13:30', 'lunch_end' => '14:00',
            'late_grace_minutes' => 15, 'full_day_minutes' => 480, 'half_day_minutes' => 240, 'weekly_off' => '0',
        ]);
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Owner']);
        $this->manager = User::factory()->create(['role' => 'manager', 'name' => 'Manager West']);
        $this->repA = User::factory()->create(['role' => 'rep', 'name' => 'Amit', 'manager_id' => $this->manager->id]);
        $this->repB = User::factory()->create(['role' => 'rep', 'name' => 'Bhavna', 'manager_id' => $this->manager->id]);
        $other = User::factory()->create(['role' => 'manager', 'name' => 'Manager East']);
        $this->otherTeamRep = User::factory()->create(['role' => 'rep', 'name' => 'Chetan', 'manager_id' => $other->id]);
    }

    protected function tearDown(): void
    {
        CompanySetting::forget();
        parent::tearDown();
    }
}

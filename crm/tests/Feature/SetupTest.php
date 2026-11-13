<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_goes_to_setup_and_company_sets_own_name(): void
    {
        CompanySetting::forget();
        $this->get('/login')->assertRedirect('/setup');
        $this->post('/setup', [
            'company_name' => 'Any Pharma Ltd', 'office_start' => '09:00', 'office_end' => '18:00', 'lunch_start' => '13:00', 'lunch_end' => '13:30',
            'admin_name' => 'Boss', 'admin_email' => 'Boss@Example.com', 'password' => 'secret12345', 'password_confirmation' => 'secret12345',
        ])->assertRedirect('/');
        $this->assertSame('admin', User::first()->role);
        $this->assertSame('boss@example.com', User::first()->email);
        CompanySetting::forget();
        $this->get('/')->assertSee('Any Pharma Ltd');
        $this->post('/logout');
        CompanySetting::forget();
        $this->get('/setup')->assertRedirect('/login');
        CompanySetting::forget();
    }
}

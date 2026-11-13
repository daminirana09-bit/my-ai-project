<?php

namespace Tests\Feature;

class LanguageTest extends CrmTestCase
{
    public function test_each_user_sees_own_language(): void
    {
        $this->repA->update(['locale' => 'hi']);
        $this->repB->update(['locale' => 'hing']);
        $this->actingAs($this->repA->fresh())->get('/leads')->assertSee('लीड्स')->assertSee('पंच इन');
        $this->actingAs($this->repB->fresh())->get('/leads')->assertSee('Nayi lead')->assertSee('Sab');
        $this->actingAs($this->admin)->get('/leads')->assertSee('New lead');
    }

    public function test_user_can_switch_language(): void
    {
        $this->actingAs($this->repA)->post('/profile/language', ['locale' => 'hi']);
        $this->assertSame('hi', $this->repA->fresh()->locale);
        $this->actingAs($this->repA)->post('/profile/language', ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }
}

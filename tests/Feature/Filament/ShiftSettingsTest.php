<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ShiftSettings\Pages\ManageShiftSettings;
use App\Models\ShiftSetting;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_record_starts_from_current_settings(): void
    {
        $this->actingAs(User::factory()->create());
        ShiftSetting::create(['effective_from' => '2026-09-01', 'second_shift_courses' => [2, 3], 'second_fourth_pair_enabled' => false]);

        Livewire::test(ManageShiftSettings::class)
            ->assertSee('2, 3')
            ->mountAction(CreateAction::class)
            ->assertSchemaStateSet(['second_shift_courses' => [2, 3], 'second_shift_enabled' => true, 'second_fourth_pair_enabled' => false], 'mountedActionSchema0')
            ->fillForm(['effective_from' => '2026-11-23', 'second_shift_enabled' => false])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $latest = ShiftSetting::newest();
        $this->assertSame([2, 3], $latest->secondShiftCourses());
        $this->assertFalse($latest->second_shift_enabled);
        $this->assertFalse($latest->second_fourth_pair_enabled);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Profile\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Models\User;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_last_active_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($admin->fresh());
    }

    public function test_an_admin_can_delete_their_account_while_another_remains(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertNull($admin->fresh());
    }

    public function test_deleting_an_account_removes_its_avatar_from_storage(): void
    {
        Storage::fake('rustfs');
        Storage::disk('rustfs')->put('avatars/me.png', 'png');

        $user = User::factory()->create(['avatar' => 'avatars/me.png']);

        $this->actingAs($user)->delete('/profile', ['password' => 'password']);

        Storage::disk('rustfs')->assertMissing('avatars/me.png');
    }
}

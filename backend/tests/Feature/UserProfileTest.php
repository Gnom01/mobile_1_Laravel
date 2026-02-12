<?php

namespace Tests\Feature;

use App\Models\CrmUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    // Removing RefreshDatabase for now as it might cause issues with the existing DB setup 
    // and I don't want to wipe the user's data if they haven't configured a test DB.
    // use RefreshDatabase;

    /**
     * Test that an unauthenticated user cannot access the profile.
     */
    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/user/profile');

        $response->assertStatus(401);
    }

    /**
     * Test that an authenticated user can access their profile.
     */
    public function test_authenticated_user_can_access_profile(): void
    {
        // Find an existing user or create one manually if possible
        // Since I cannot run migrations easily to setup a clean state, 
        // I will attempt to use a factory if it exists, or just mock the auth.
        
        $user = CrmUser::factory()->create([
            'FirstName' => 'Jan',
            'LastName' => 'Kowalski',
            'Email' => 'jan@example.com',
            'Phone' => '123456789',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/user/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'FirstName' => 'Jan',
                    'LastName' => 'Kowalski',
                    'Email' => 'jan@example.com',
                ],
            ]);
    }
}

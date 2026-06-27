<?php

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DefaultPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(DefaultPermissionsSeeder::class);
});

test('guest public navigation keeps cart and auth actions visible for mobile', function () {
    $this->withSession(['cart.items' => [1001 => 2, 1002 => 1]])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('mobile-header-actions', false)
        ->assertSee('aria-label="Cart"', false)
        ->assertSee('>3</span>', false)
        ->assertSee('Login')
        ->assertSee('Register')
        ->assertDontSee('customer-avatar-button', false);
});

test('customer public navigation uses avatar dropdown instead of name trigger', function () {
    $customer = User::query()->create([
        'name' => 'Uma Grace',
        'email' => 'uma@example.com',
        'password' => 'password',
        'role' => 'customer',
        'permission_id' => Permission::query()->where('name', 'customer')->value('id'),
        'status' => 'active',
    ]);

    $this->actingAs($customer)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('customer-avatar-button', false)
        ->assertSee('UG')
        ->assertSee('Uma Grace')
        ->assertSee('Dashboard')
        ->assertSee('My Repairs')
        ->assertSee('My Orders')
        ->assertSee('Logout')
        ->assertDontSee('>Login</a>', false)
        ->assertDontSee('>Register</a>', false)
        ->assertDontSee('admin.dashboard', false);
});

test('admin navigation stays separate from public customer cart and avatar controls', function () {
    $admin = User::query()->create([
        'name' => 'Admin User',
        'email' => 'admin-navigation@example.com',
        'password' => 'password',
        'role' => 'admin',
        'permission_id' => Permission::query()->where('name', 'admin')->value('id'),
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('cart-action', false)
        ->assertDontSee('mobile-header-actions', false)
        ->assertDontSee('customer-avatar-button', false);

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('cart-action', false)
        ->assertDontSee('customer-avatar-button', false)
        ->assertDontSee('admin.dashboard', false);
});

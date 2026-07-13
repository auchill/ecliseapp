<?php

use App\Models\Permission;
use App\Models\Product;
use App\Models\User;

function permissionId(string $name): int
{
    return Permission::query()->where('name', $name)->where('status', 'active')->value('id');
}

test('admin login only accepts active admin users', function () {
    $customer = User::query()->create([
        'name' => 'Customer User',
        'email' => 'customer-login@example.com',
        'password' => 'password',
        'role' => 'customer',
        'permission_id' => permissionId('customer'),
        'status' => 'active',
    ]);

    $admin = User::query()->create([
        'name' => 'Admin User',
        'email' => 'admin-login@example.com',
        'password' => 'password',
        'role' => 'admin',
        'permission_id' => permissionId('admin'),
        'status' => 'active',
    ]);

    $this->post(route('admin.login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
});

test('customer login rejects admin and inactive customer users', function () {
    $admin = User::query()->create([
        'name' => 'Shop Admin',
        'email' => 'shop-admin@example.com',
        'password' => 'password',
        'role' => 'admin',
        'permission_id' => permissionId('admin'),
        'status' => 'active',
    ]);

    $inactiveCustomer = User::query()->create([
        'name' => 'Inactive Customer',
        'email' => 'inactive-customer@example.com',
        'password' => 'password',
        'role' => 'customer',
        'permission_id' => permissionId('customer'),
        'status' => 'inactive',
    ]);

    $customer = User::query()->create([
        'name' => 'Active Customer',
        'email' => 'active-customer@example.com',
        'password' => 'password',
        'role' => 'customer',
        'permission_id' => permissionId('customer'),
        'status' => 'active',
    ]);

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => $inactiveCustomer->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($customer);
});

test('admin users are blocked from cart checkout and customer repair booking routes', function () {
    $admin = User::query()->create([
        'name' => 'Blocked Admin',
        'email' => 'blocked-admin@example.com',
        'password' => 'password',
        'role' => 'admin',
        'permission_id' => permissionId('admin'),
        'status' => 'active',
    ]);

    $product = Product::query()->create([
        'name' => 'Blocked Product',
        'slug' => 'blocked-product',
        'sku' => 'BLOCKED-1',
        'regular_price' => 100,
        'quantity' => 2,
        'is_active' => true,
    ]);

    $this->actingAs($admin)->get(route('cart.index'))->assertRedirect(route('admin.dashboard'));
    $this->actingAs($admin)->post(route('cart.store', $product), ['quantity' => 1])->assertRedirect(route('admin.dashboard'));
    $this->actingAs($admin)->get(route('checkout.show'))->assertRedirect(route('admin.dashboard'));
    $this->actingAs($admin)->get(route('repairs.create'))->assertRedirect(route('admin.dashboard'));
});

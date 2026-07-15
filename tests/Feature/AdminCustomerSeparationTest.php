<?php

use App\Models\Permission;
use App\Models\Product;
use App\Models\Repair;
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

test('admin repairs sidebar includes repairs child link and highlights repair routes', function () {
    $admin = User::query()->create([
        'name' => 'Sidebar Admin',
        'email' => 'sidebar-admin@example.com',
        'password' => 'password',
        'role' => 'admin',
        'permission_id' => permissionId('admin'),
        'status' => 'active',
    ]);
    $customer = User::query()->create([
        'name' => 'Sidebar Customer',
        'email' => 'sidebar-customer@example.com',
        'password' => 'password',
        'role' => 'customer',
        'permission_id' => permissionId('customer'),
        'status' => 'active',
    ])->customer()->create([
        'full_name' => 'Sidebar Customer',
        'email' => 'sidebar-customer@example.com',
        'customer_since' => now(),
        'status' => 'active',
    ]);

    $repair = Repair::query()->create([
        'customer_id' => $customer->id,
        'repair_number' => 'REP-SIDEBAR-1',
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone',
        'issue_category' => 'Screen',
        'issue_description' => 'Cracked display',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total_amount' => 0,
        'amount_paid' => 0,
        'balance_due' => 0,
        'status' => 'diagnosis_in_progress',
        'repair_status' => 'diagnosis_in_progress',
        'payment_status' => 'unpaid',
        'fulfillment_method' => 'pickup',
        'repair_total' => 0,
    ]);

    $activeSidebarLink = 'class="admin-menu-link active" href="'.route('admin.repairs.index').'"';

    $this->actingAs($admin)
        ->get(route('admin.repairs.index'))
        ->assertOk()
        ->assertSee($activeSidebarLink, false);

    $this->actingAs($admin)
        ->get(route('admin.repairs.show', $repair))
        ->assertOk()
        ->assertSee($activeSidebarLink, false);
});

<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Repair;
use App\Models\User;

/**
 * Book Repair, Track Repair and Track Order must never surface one customer's records to
 * another. Repair and order numbers are sequential, so a reference alone is not a secret.
 */
function isolationUser(string $email, string $role = 'customer'): User
{
    return User::query()->create([
        'name' => 'Isolation '.ucfirst($role),
        'email' => $email,
        'password' => 'password',
        'role' => $role,
        'status' => 'active',
    ]);
}

function isolationRepair(User $user, string $number): Repair
{
    return Repair::query()->create([
        'customer_id' => Customer::forUser($user)->id,
        'repair_number' => $number,
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 15',
        'issue_category' => 'Screen',
        'issue_description' => 'Cracked screen',
        'subtotal' => 100,
        'tax_amount' => 13,
        'total_amount' => 113,
        'amount_paid' => 0,
        'balance_due' => 113,
        'status' => 'awaiting_customer_payment',
        'repair_status' => 'awaiting_customer_payment',
        'payment_status' => 'unpaid',
        'fulfillment_method' => 'pickup',
        'pickup_or_shipping_option' => 'pickup',
        'repair_total' => 113,
        'currency' => 'cad',
    ]);
}

function isolationOrder(User $user, string $number): Order
{
    return Order::query()->create([
        'customer_id' => Customer::forUser($user)->id,
        'order_number' => $number,
        'subtotal' => 100,
        'tax' => 13,
        'total' => 113,
        'status' => 'Paid',
        'payment_status' => 'paid',
        'fulfillment_method' => 'pickup',
        'currency' => 'cad',
    ]);
}

// ---------------------------------------------------------------------------
// Track Repair
// ---------------------------------------------------------------------------

test('a signed in customer cannot track another customers repair', function () {
    $owner = isolationUser('isolation-owner@example.com');
    $other = isolationUser('isolation-other@example.com');
    Customer::forUser($owner)->update(['email' => 'isolation-owner@example.com', 'phone' => '416-555-1000']);
    $repair = isolationRepair($owner, 'ECL-REP-2026-9000001');

    // Even with the exact repair number.
    $this->actingAs($other)
        ->post(route('repairs.track.submit'), [
            'repair_number' => $repair->repair_number,
        ])
        ->assertSessionHasErrors('repair_number');
});

test('a signed in customer can track their own repair', function () {
    $owner = isolationUser('isolation-self@example.com');
    $repair = isolationRepair($owner, 'ECL-REP-2026-9000002');

    $this->actingAs($owner)
        ->post(route('repairs.track.submit'), ['repair_number' => $repair->repair_number])
        ->assertOk()
        ->assertSee($repair->repair_number);
});

test('a guest is sent to login and cannot reach repair tracking', function () {
    $owner = isolationUser('isolation-guest-target@example.com');
    $repair = isolationRepair($owner, 'ECL-REP-2026-9000003');

    $this->get(route('repairs.track'))->assertRedirect(route('login'));

    // A bare repair number previously returned the customer's name, address and payment state.
    $this->post(route('repairs.track.submit'), ['repair_number' => $repair->repair_number])
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Track Order
// ---------------------------------------------------------------------------

test('a signed in customer cannot track another customers order', function () {
    $owner = isolationUser('isolation-order-owner@example.com');
    $other = isolationUser('isolation-order-other@example.com');
    Customer::forUser($owner)->update(['email' => 'isolation-order-owner@example.com']);
    $order = isolationOrder($owner, 'ECL-ORD-2026-9000001');

    $this->actingAs($other)
        ->post(route('orders.track.result'), [
            'order_number' => $order->order_number,
        ])
        ->assertSessionHasErrors('order_number');
});

test('a signed in customer can track their own order', function () {
    $owner = isolationUser('isolation-order-self@example.com');
    $order = isolationOrder($owner, 'ECL-ORD-2026-9000002');

    $this->actingAs($owner)
        ->post(route('orders.track.result'), ['order_number' => $order->order_number])
        ->assertOk()
        ->assertSee($order->order_number);
});

test('a guest is sent to login and cannot reach order tracking', function () {
    $owner = isolationUser('isolation-order-guest@example.com');
    $order = isolationOrder($owner, 'ECL-ORD-2026-9000003');

    $this->get(route('orders.track'))->assertRedirect(route('login'));

    $this->post(route('orders.track.result'), ['order_number' => $order->order_number])
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Book Repair completion lookup
// ---------------------------------------------------------------------------

test('the book repair lookup cannot confirm another customers repair number', function () {
    $owner = isolationUser('isolation-book-owner@example.com');
    $other = isolationUser('isolation-book-other@example.com');
    $repair = isolationRepair($owner, 'ECL-REP-2026-9000006');

    // A mismatched lookup must be indistinguishable from a number that does not exist.
    $this->actingAs($other)
        ->post(route('repairs.store'), ['repair_number' => $repair->repair_number])
        ->assertSessionHasErrors('repair_number');

    $this->actingAs($other)
        ->post(route('repairs.store'), ['repair_number' => 'ECL-REP-2026-0000000'])
        ->assertSessionHasErrors('repair_number');
});

test('a guest is sent to login and cannot reach book repair', function () {
    $owner = isolationUser('isolation-book-guest@example.com');
    $repair = isolationRepair($owner, 'ECL-REP-2026-9000009');

    $this->get(route('repairs.create'))->assertRedirect(route('login'));

    $this->post(route('repairs.store'), ['repair_number' => $repair->repair_number])
        ->assertRedirect(route('login'));
});

test('the book repair lookup still finds the owning customers repair', function () {
    $owner = isolationUser('isolation-book-self@example.com');
    $repair = isolationRepair($owner, 'ECL-REP-2026-9000007');

    $this->actingAs($owner)
        ->post(route('repairs.store'), ['repair_number' => $repair->repair_number])
        ->assertRedirect(route('repairs.complete', $repair->repair_number));
});

// ---------------------------------------------------------------------------
// Admin
// ---------------------------------------------------------------------------

test('an admin can still look up any customer record', function () {
    $owner = isolationUser('isolation-admin-target@example.com');
    $admin = isolationUser('isolation-admin@example.com', 'admin');
    $repair = isolationRepair($owner, 'ECL-REP-2026-9000008');
    $order = isolationOrder($owner, 'ECL-ORD-2026-9000004');

    $this->actingAs($admin)
        ->post(route('repairs.track.submit'), ['repair_number' => $repair->repair_number])
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('orders.track.result'), ['order_number' => $order->order_number])
        ->assertOk();
});

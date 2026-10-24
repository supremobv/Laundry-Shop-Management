<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\Supply;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LaundryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'must_change_password' => false]);
    }

    private function service(): Service
    {
        return Service::create(['name' => 'Wash & fold', 'unit' => 'kg', 'price_cents' => 6500, 'turnaround_hours' => 24, 'active' => true]);
    }

    private function payload(?Service $service = null): array
    {
        $customer = Customer::create(['name' => 'Test Customer', 'phone' => '09170001111']);

        return ['customer_id' => $customer->id, 'due_at' => now()->addDay()->format('Y-m-d\TH:i'), 'priority' => 'normal', 'discount' => '0', 'items' => [['service_id' => ($service ?? $this->service())->id, 'quantity' => '2.50']]];
    }

    private function order(User $user, array $extra = []): Order
    {
        return app(OrderService::class)->create(array_replace($this->payload(), $extra), $user->id);
    }

    private function pay(User $user, Order $order, string $amount = '162.50', ?string $key = null)
    {
        return $this->actingAs($user)->post(route('orders.payment', $order), ['amount' => $amount, 'method' => 'cash', 'idempotency_key' => $key ?? Str::uuid()->toString()]);
    }

    public function test_guest_cannot_read_shop_data(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->get('/login')->assertOk()->assertSee('Welcome back.');
    }

    public function test_every_main_page_renders_with_real_data(): void
    {
        $user = $this->user();
        $order = $this->order($user);
        $this->pay($user, $order);
        foreach (['/', '/orders', '/orders/create', '/orders/'.$order->id, '/orders/'.$order->id.'/receipt', '/customers', '/customers/create', '/customers/'.$order->customer_id, '/customers/'.$order->customer_id.'/edit', '/services', '/services/create', '/services/'.$order->items->first()->service_id.'/edit', '/inventory', '/expenses', '/reports', '/staff', '/staff/create', '/staff/'.$user->id.'/edit', '/settings', '/audit-log', '/profile', '/help', '/business-logo'] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/orders/'.$order->id)->assertOk()->assertJsonPath('id', $order->id);
        $this->getJson('/api/v1/customers')->assertOk();
    }

    public function test_roles_are_enforced_for_reads_and_mutations(): void
    {
        $operator = $this->user('operator');
        $order = $this->order($operator);
        foreach (['/settings', '/staff', '/reports', '/expenses', '/services', '/customers', '/audit-log', '/orders/create'] as $path) {
            $this->actingAs($operator)->get($path)->assertForbidden();
        }
        $this->actingAs($operator)->post('/customers', ['name' => 'Bad', 'phone' => '123'])->assertForbidden();
        $this->pay($operator, $order)->assertForbidden();
        $this->actingAs($operator)->patch(route('orders.status', $order), ['status' => 'cancelled', 'note' => 'Not allowed'])->assertForbidden();
        $this->actingAs($operator)->get('/orders')->assertOk();
        $this->actingAs($operator)->get('/inventory')->assertOk();
        $cashier = $this->user('cashier');
        $this->actingAs($cashier)->put('/settings', [])->assertForbidden();
        $this->actingAs($cashier)->post('/staff', [])->assertForbidden();
        $manager = $this->user('manager');
        $this->actingAs($manager)->get('/reports')->assertOk();
        $this->actingAs($manager)->get('/staff')->assertForbidden();
    }

    public function test_inactive_users_and_temporary_passwords_are_blocked(): void
    {
        $user = $this->user();
        $user->update(['must_change_password' => true]);
        $this->actingAs($user)->get('/')->assertRedirect('/profile');
        $this->put('/profile/password', ['current_password' => 'wrong', 'password' => 'StrongReplacement!123', 'password_confirmation' => 'StrongReplacement!123'])->assertSessionHasErrors('current_password');
        $user->update(['active' => false]);
        $this->actingAs($user)->get('/orders')->assertRedirect('/login');
    }

    public function test_login_rotates_session_and_is_throttled(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_change_clears_temporary_password_requirement(): void
    {
        $u = $this->user();
        $u->update(['must_change_password' => true]);
        $this->actingAs($u)->put('/profile/password', ['current_password' => 'password', 'password' => 'NewPersonalPassword!123', 'password_confirmation' => 'NewPersonalPassword!123'])->assertRedirect('/');
        $this->assertFalse($u->fresh()->must_change_password);
        $this->assertTrue(Hash::check('NewPersonalPassword!123', $u->fresh()->password));
    }

    public function test_csrf_rejects_mutations_without_a_valid_token(): void
    {
        $this->app->bind(ValidateCsrfToken::class, EnforcedCsrf::class);
        $u = $this->user();
        $data = $this->payload();
        $this->actingAs($u)->post('/orders', $data)->assertStatus(419);
        $this->withSession(['_token' => 'valid-test-token'])->post('/orders', [...$data, '_token' => 'valid-test-token'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_order_totals_use_catalog_and_exact_rounding(): void
    {
        $user = $this->user();
        BusinessSetting::current()->update(['tax_basis_points' => 1200]);
        $data = $this->payload();
        $data['discount'] = '10.00';
        $data['total_cents'] = 1;
        $data['items'][0]['price_cents'] = 1;
        $this->actingAs($user)->post('/orders', $data)->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame(16250, $order->subtotal_cents);
        $this->assertSame(1830, $order->tax_cents);
        $this->assertSame(17080, $order->total_cents);
        $catalog = Service::first();
        $catalog->update(['price_cents' => 100]);
        $this->assertSame(6500, $order->items->first()->unit_price_cents);
        $this->assertSame(17080, $order->fresh()->total_cents);
    }

    public function test_invalid_orders_do_not_create_partial_records(): void
    {
        $user = $this->user();
        $data = $this->payload();
        $data['discount'] = '999999';
        $this->actingAs($user)->post('/orders', $data)->assertSessionHasErrors('discount');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $data['discount'] = '0';
        $data['items'][0]['quantity'] = '-1';
        $this->post('/orders', $data)->assertSessionHasErrors('items.0.quantity');
        $data['items'][0]['quantity'] = '1';
        Service::first()->update(['active' => false]);
        $this->post('/orders', $data)->assertSessionHasErrors('items');
    }

    public function test_payments_are_idempotent_and_cannot_exceed_balance(): void
    {
        $user = $this->user();
        $order = $this->order($user);
        $key = Str::uuid()->toString();
        $this->pay($user, $order, '50.00', $key)->assertSessionHasNoErrors();
        $this->pay($user, $order, '50.00', $key)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(11250, $order->fresh()->balance_cents);
        $this->pay($user, $order, '500.00')->assertSessionHasErrors('amount');
        $this->pay($user, $order, '60.00', $key)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_workflow_requires_valid_stages_and_full_payment_before_pickup(): void
    {
        $user = $this->user();
        $order = $this->order($user);
        $this->actingAs($user)->patch(route('orders.status', $order), ['status' => 'ready'])->assertSessionHasErrors('status');
        foreach (['washing', 'drying', 'folding', 'ready'] as $stage) {
            $this->patch(route('orders.status', $order), ['status' => $stage])->assertSessionHasNoErrors();
        }
        $this->patch(route('orders.status', $order), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->pay($user, $order)->assertSessionHasNoErrors();
        $this->patch(route('orders.status', $order), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertDatabaseCount('order_events', 6);
    }

    public function test_refunds_preserve_payments_and_allow_cancellation(): void
    {
        $user = $this->user();
        $order = $this->order($user);
        $this->pay($user, $order);
        $payment = $order->payments()->first();
        $this->patch(route('orders.status', $order), ['status' => 'cancelled', 'note' => 'Customer cancelled'])->assertSessionHasErrors('status');
        $this->actingAs($this->user('cashier'))->post(route('orders.refund', [$order, $payment]), ['reason' => 'Returned cash'])->assertForbidden();
        $this->actingAs($user)->post(route('orders.refund', [$order, $payment]), ['reason' => 'Returned cash to customer'])->assertSessionHasNoErrors();
        $this->post(route('orders.refund', [$order, $payment]), ['reason' => 'Duplicate refund'])->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('refunds', 1);
        $this->assertSame(0, $order->fresh()->paid_cents);
        $this->patch(route('orders.status', $order), ['status' => 'cancelled', 'note' => 'Customer cancelled after refund'])->assertSessionHasNoErrors();
        $this->assertSame(0, $order->fresh()->balance_cents);
        $this->pay($user, $order)->assertSessionHasErrors('amount');
    }

    public function test_cross_order_refund_is_rejected(): void
    {
        $u = $this->user();
        $a = $this->order($u);
        $b = $this->order($u);
        $this->pay($u, $a);
        $this->post(route('orders.refund', [$b, $a->payments()->first()]), ['reason' => 'Wrong order'])->assertNotFound();
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_customer_archive_and_restore_preserve_history(): void
    {
        $u = $this->user();
        $o = $this->order($u);
        $customer = $o->customer;
        $this->actingAs($u)->delete(route('customers.destroy', $customer))->assertSessionHasErrors('customer');
        $this->patch(route('orders.status', $o), ['status' => 'cancelled', 'note' => 'Mistaken intake'])->assertSessionHasNoErrors();
        $this->delete(route('customers.destroy', $customer))->assertRedirect('/customers');
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->get('/customers?archived=1')->assertOk()->assertSee('Restore profile');
        $this->get(route('orders.show', $o))->assertOk()->assertSee($customer->name);
        $this->post(route('customers.restore', $customer))->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_manager_can_create_customers_services_and_expenses(): void
    {
        $u = $this->user('manager');
        $this->actingAs($u)->post('/customers', ['name' => 'New Regular', 'phone' => '09175550000', 'notes' => 'Unscented'])->assertSessionHasNoErrors()->assertRedirect('/customers');
        $this->post('/services', ['name' => 'Shoe care', 'unit' => 'pair', 'price' => '120.50', 'turnaround_hours' => 48, 'active' => 1])->assertSessionHasNoErrors()->assertRedirect('/services');
        $this->assertDatabaseHas('services', ['name' => 'Shoe care', 'price_cents' => 12050]);
        $this->post('/expenses', ['description' => 'Water bill', 'category' => 'utilities', 'amount' => '250.75', 'spent_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', ['description' => 'Water bill', 'amount_cents' => 25075]);
        $this->get('/reports')->assertOk()->assertSee('250.75');
        $this->get('/orders/create?customer_q=New%20Regular')->assertOk()->assertSee('New Regular');
    }

    public function test_staff_creation_reset_and_deactivation_revoke_sessions(): void
    {
        $u = $this->user();
        $password = 'TemporaryStaffPass!123';
        $this->actingAs($u)->post('/staff', ['name' => 'Staff Member', 'email' => 'staff@example.test', 'role' => 'cashier', 'active' => 1, 'password' => $password, 'password_confirmation' => $password])->assertSessionHasNoErrors();
        $staff = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertTrue($staff->must_change_password);
        DB::table('sessions')->insert(['id' => 'revoked-staff-session', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);
        $this->put(route('staff.update', $staff), ['name' => $staff->name, 'email' => $staff->email, 'role' => 'operator', 'active' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'revoked-staff-session']);
        $this->assertFalse($staff->fresh()->active);
    }

    public function test_completed_order_cancellation_requires_full_refund(): void
    {
        $u = $this->user();
        $o = $this->order($u);
        $this->pay($u, $o);
        foreach (['washing', 'drying', 'folding', 'ready', 'completed'] as $stage) {
            $this->patch(route('orders.status', $o), ['status' => $stage])->assertSessionHasNoErrors();
        }
        $this->patch(route('orders.status', $o), ['status' => 'cancelled', 'note' => 'Customer requested refund'])->assertSessionHasErrors('status');
        $this->post(route('orders.refund', [$o, $o->payments()->first()]), ['reason' => 'Full funds returned to customer'])->assertSessionHasNoErrors();
        $this->patch(route('orders.status', $o), ['status' => 'cancelled', 'note' => 'Closed after full refund'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $o->fresh()->status);
        $this->get('/reports')->assertOk();
    }

    public function test_stock_cannot_be_negative_and_every_adjustment_is_recorded(): void
    {
        $u = $this->user();
        $s = Supply::create(['name' => 'Detergent', 'unit' => 'liters', 'quantity' => '2.50', 'reorder_level' => 1]);
        $this->actingAs($u)->post(route('inventory.adjust', $s), ['quantity' => '-3', 'reason' => 'Used during shift', 'reorder_level' => 1])->assertSessionHasErrors('quantity');
        $this->assertEquals('2.50', $s->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->post(route('inventory.adjust', $s), ['quantity' => '-0.25', 'reason' => 'Used during shift', 'reorder_level' => 1])->assertSessionHasNoErrors();
        $this->assertEquals('2.25', $s->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_admin_cannot_remove_own_access(): void
    {
        $u = $this->user();
        $this->actingAs($u)->put(route('staff.update', $u), ['name' => $u->name, 'email' => $u->email, 'role' => 'operator', 'active' => 1])->assertSessionHasErrors('role');
        $this->assertSame('admin', $u->fresh()->role);
    }

    public function test_settings_upload_is_validated_and_history_currency_is_locked(): void
    {
        Storage::fake('local');
        $u = $this->user();
        $data = ['name' => 'My Laundry', 'tagline' => 'Fresh daily', 'currency' => 'PHP', 'timezone' => 'Asia/Manila', 'tax_percent' => '5', 'receipt_footer' => 'Thank you'];
        $this->actingAs($u)->put('/settings', [...$data, 'logo' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->put('/settings', [...$data, 'logo' => UploadedFile::fake()->image('logo.png', 100, 100)])->assertSessionHasNoErrors();
        $path = BusinessSetting::current()->fresh()->logo_path;
        Storage::disk('local')->assertExists($path);
        $this->get('/business-logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->order($u);
        $this->put('/settings', [...$data, 'currency' => 'USD'])->assertSessionHasErrors('currency');
    }

    public function test_output_is_escaped_and_security_headers_are_present(): void
    {
        $u = $this->user();
        Customer::create(['name' => '<script>alert(1)</script>', 'phone' => '123']);
        $this->actingAs($u)->get('/customers')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_unpaid_order_list_totals_do_not_trigger_extra_queries(): void
    {
        $u = $this->user();
        $this->order($u);
        $orders = Order::withSum('payments', 'amount_cents')->withSum('refunds', 'amount_cents')->get();
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ($orders as $o) {
            $this->assertSame(0, $o->paid_cents);
            $this->assertSame(16250, $o->balance_cents);
        }
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_reports_export_refunds_and_escape_formula_injection(): void
    {
        $u = $this->user();
        $o = $this->order($u);
        $this->actingAs($u)->post(route('orders.payment', $o), ['amount' => '162.50', 'method' => 'cash', 'reference' => '=HYPERLINK("evil")', 'idempotency_key' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->post(route('orders.refund', [$o, $o->payments()->first()]), ['reason' => 'Returned to customer'])->assertSessionHasNoErrors();
        $response = $this->get('/reports/export');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'-162.50", $csv);
        $this->get('/reports')->assertOk()->assertSee('Refund ledger');
    }
}

class EnforcedCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}

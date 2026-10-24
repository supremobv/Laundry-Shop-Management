<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Service;
use App\Models\Supply;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo data cannot be installed in production.');
        }
        if (Order::exists() || Customer::exists()) {
            throw new \RuntimeException('Demo data requires an empty shop. Existing data was not changed.');
        }
        BusinessSetting::current()->update(['phone' => '+63 917 555 0142', 'email' => 'hello@freshfold.test', 'address' => '24 Garden Street, Quezon City, Philippines']);
        $user = User::where('role', 'admin')->firstOrFail();
        $names = ['Sofia Reyes', 'Liam Santos', 'Olivia Garcia', 'Noah Cruz', 'Isabella Tan', 'Ethan Lim', 'Mia Flores', 'Lucas Mendoza', 'Ava Ramos', 'Elijah Lopez', 'Charlotte Diaz', 'Amelia Torres'];
        $customers = collect();
        foreach ($names as $i => $name) {
            $customers->push(Customer::create(['name' => $name, 'phone' => '+63 917 555 '.str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT), 'email' => Str::slug($name, '.').'@example.test', 'address' => ($i + 10).' Sample Street, Quezon City', 'notes' => $i === 0 ? 'Prefers unscented detergent.' : null]));
        }
        $service = app(OrderService::class);
        $catalog = Service::where('active', true)->get();
        $statuses = ['washing', 'drying', 'folding', 'ready', 'received', 'ready', 'washing', 'completed', 'completed', 'completed', 'completed', 'received', 'drying', 'folding', 'completed', 'received', 'completed', 'ready'];
        foreach ($statuses as $i => $status) {
            $order = $service->create(['customer_id' => $customers[$i % $customers->count()]->id, 'due_at' => now()->addHours(($i - 2) * 3 + 1)->toDateTimeString(), 'priority' => $i % 5 === 0 ? 'express' : 'normal', 'rack' => 'Rack '.chr(65 + $i % 4).' · Bag '.($i + 1), 'notes' => $i % 3 === 0 ? 'Separate whites. Check pockets before washing.' : null, 'discount' => '0', 'items' => [['service_id' => $catalog[$i % $catalog->count()]->id, 'quantity' => (string) (3 + $i % 6)]]], $user->id);
            $date = now()->subDays($i % 7)->setTime(9 + $i % 8, 15);
            $order->update(['created_at' => $date]);
            if ($i % 3 !== 1 || $status === 'completed') {
                $amount = $i % 4 === 0 && $status !== 'completed' ? intdiv($order->total_cents, 2) : $order->total_cents;
                $service->pay($order, ['amount' => number_format($amount / 100, 2, '.', ''), 'method' => $i % 3 === 0 ? 'e_wallet' : 'cash', 'idempotency_key' => Str::uuid()->toString()], $user->id);
                $order->payments()->update(['created_at' => $date]);
            }
            foreach (['washing', 'drying', 'folding', 'ready', 'completed'] as $stage) {
                if ($status === 'received') {
                    break;
                }
                $service->transition($order, $stage, $user->id, 'Sample workflow event.');
                if ($stage === $status) {
                    break;
                }
            }
        }
        foreach ([['Liquid detergent', 'liters', 12, 5], ['Fabric conditioner', 'liters', 3, 5], ['Laundry bags', 'pieces', 80, 20], ['Garment tags', 'pieces', 18, 25]] as [$name,$unit,$qty,$level]) {
            $s = Supply::create(['name' => $name, 'unit' => $unit, 'quantity' => $qty, 'reorder_level' => $level]);
            $s->movements()->create(['user_id' => $user->id, 'quantity' => $qty, 'reason' => 'Demo opening stock']);
        }
        foreach ([['Electricity', 'utilities', 250000], ['Detergent delivery', 'supplies', 85000], ['Equipment cleaning', 'maintenance', 45000]] as [$desc,$category,$amount]) {
            Expense::create(['description' => $desc, 'category' => $category, 'amount_cents' => $amount, 'spent_on' => today()->subDays(2), 'created_by' => $user->id]);
        }
    }
}

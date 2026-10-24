<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(array $data, int $userId): Order
    {
        return DB::transaction(function () use ($data, $userId) {
            $lines = [];
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $service = Service::where('active', true)->find($item['service_id']);
                if (! $service) {
                    throw ValidationException::withMessages(['items' => 'One of the selected services is no longer available. Please refresh and try again.']);
                }
                $total = Money::line($service->price_cents, (string) $item['quantity']);
                $subtotal += $total;
                if ($subtotal > 1000000000) {
                    throw ValidationException::withMessages(['items' => 'The order total must not exceed 10 million before tax. Split this load into separate orders.']);
                }
                $lines[] = ['service_id' => $service->id, 'name' => $service->name, 'unit' => $service->unit, 'quantity' => $item['quantity'], 'unit_price_cents' => $service->price_cents, 'total_cents' => $total];
            }
            $discount = Money::cents($data['discount'] ?? '0');
            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['discount' => 'The discount cannot exceed the subtotal.']);
            }
            $taxRate = BusinessSetting::current()->tax_basis_points;
            $tax = intdiv(($subtotal - $discount) * $taxRate + 5000, 10000);
            $order = Order::create([
                'number' => 'LF-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'customer_id' => $data['customer_id'], 'created_by' => $userId,
                'status' => 'received', 'priority' => $data['priority'], 'due_at' => $data['due_at'],
                'notes' => $data['notes'] ?? null, 'rack' => $data['rack'] ?? null,
                'subtotal_cents' => $subtotal, 'discount_cents' => $discount,
                'tax_basis_points' => $taxRate, 'tax_cents' => $tax, 'total_cents' => $subtotal - $discount + $tax,
            ]);
            $order->items()->createMany($lines);
            $order->events()->create(['user_id' => $userId, 'to_status' => 'received', 'note' => 'Order received at the counter.']);
            AuditLog::record('order.created', $order->number, ['total_cents' => $order->total_cents]);

            return $order;
        });
    }

    public function transition(Order $order, string $status, int $userId, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $status, $userId, $note) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            $next = ['received' => 'washing', 'washing' => 'drying', 'drying' => 'folding', 'folding' => 'ready', 'ready' => 'completed'];
            if ($status === 'cancelled') {
                $netPaid = (int) $locked->payments()->sum('amount_cents') - (int) $locked->refunds()->sum('refunds.amount_cents');
                if ($locked->status === 'cancelled' || $netPaid > 0 || ($locked->status === 'completed' && ! $locked->refunds()->exists())) {
                    throw ValidationException::withMessages(['status' => 'Refund all payments before cancelling. Completed orders can only be cancelled after a refund.']);
                }
            } elseif (($next[$locked->status] ?? null) !== $status) {
                throw ValidationException::withMessages(['status' => 'This order changed or the selected stage is invalid. Refresh and try again.']);
            }
            if ($status === 'completed' && (int) $locked->payments()->sum('amount_cents') - (int) $locked->refunds()->sum('refunds.amount_cents') < $locked->total_cents) {
                throw ValidationException::withMessages(['status' => 'Collect the remaining balance before completing pickup.']);
            }
            $previous = $locked->status;
            $locked->update(['status' => $status, 'completed_at' => $status === 'completed' ? now() : null, 'cancellation_reason' => $status === 'cancelled' ? $note : null]);
            $locked->events()->create(['user_id' => $userId, 'from_status' => $previous, 'to_status' => $status, 'note' => $note]);
            AuditLog::record('order.status_changed', $locked->number, ['from' => $previous, 'to' => $status, 'note' => $note]);
        });
    }

    public function pay(Order $order, array $data, int $userId): void
    {
        DB::transaction(function () use ($order, $data, $userId) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            $existing = Payment::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ($existing->order_id !== $locked->id || $existing->amount_cents !== Money::cents($data['amount']) || $existing->method !== $data['method']) {
                    throw ValidationException::withMessages(['amount' => 'This payment reference has already been used. Refresh the page.']);
                }

                return;
            }
            $amount = Money::cents($data['amount']);
            $balance = $locked->total_cents - ((int) $locked->payments()->sum('amount_cents') - (int) $locked->refunds()->sum('refunds.amount_cents'));
            if ($locked->status === 'cancelled' || $amount <= 0 || $amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'Enter a payment greater than zero and no more than the outstanding balance.']);
            }
            $payment = $locked->payments()->create(['received_by' => $userId, 'amount_cents' => $amount, 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'idempotency_key' => $data['idempotency_key']]);
            AuditLog::record('payment.received', $locked->number, ['payment_id' => $payment->id, 'amount_cents' => $amount, 'method' => $data['method']]);
        });
    }

    public function refund(Order $order, Payment $payment, string $reason, int $userId): void
    {
        DB::transaction(function () use ($order, $payment, $reason, $userId) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless($payment->order_id === $locked->id, 404);
            if ($payment->refund()->exists()) {
                throw ValidationException::withMessages(['refund' => 'This payment has already been refunded.']);
            }
            $refund = $payment->refund()->create(['user_id' => $userId, 'amount_cents' => $payment->amount_cents, 'reason' => $reason]);
            AuditLog::record('payment.refunded', $locked->number, ['refund_id' => $refund->id, 'payment_id' => $payment->id, 'amount_cents' => $payment->amount_cents, 'reason' => $reason]);
        });
    }
}

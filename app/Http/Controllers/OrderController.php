<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        $filters = $r->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(array_keys(Order::STATUSES))], 'overdue' => 'nullable|boolean']);
        $orders = Order::with('customer')->withSum('payments', 'amount_cents')->withSum('refunds', 'amount_cents')
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($r->boolean('overdue'), fn ($q) => $q->where('due_at', '<', now())->whereNotIn('status', ['completed', 'cancelled', 'ready']))
            ->latest()->paginate(15)->withQueryString();
        if ($r->routeIs('api.*')) {
            return response()->json($orders);
        }

        return view('orders.index', compact('orders'));
    }

    public function create(Request $r)
    {
        $r->validate(['customer_q' => 'nullable|string|max:100']);
        $customers = Customer::when($r->customer_q, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))->orderBy('name')->limit(100)->get(['id', 'name', 'phone']);
        if (old('customer_id') && ! $customers->contains('id', old('customer_id'))) {
            $selected = Customer::find(old('customer_id'), ['id', 'name', 'phone']);
            if ($selected) {
                $customers->prepend($selected);
            }
        }

        return view('orders.create', ['customers' => $customers, 'services' => Service::where('active', true)->orderBy('name')->get()]);
    }

    public function store(Request $r, OrderService $service)
    {
        $data = $r->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'due_at' => 'required|date|after:now|before:'.now()->addYear()->toDateTimeString(),
            'priority' => ['required', Rule::in(['normal', 'express'])], 'rack' => 'nullable|string|max:50', 'notes' => 'nullable|string|max:2000',
            'discount' => 'nullable|numeric|min:0|max:1000000|decimal:0,2',
            'items' => 'required|array|min:1|max:20', 'items.*.service_id' => 'required|integer|exists:services,id',
            'items.*.quantity' => 'required|numeric|min:0.01|max:1000|decimal:0,2',
        ]);
        $order = $service->create($data, $r->user()->id);

        return redirect()->route('orders.show', $order)->with('success', 'Order created. You can now collect a payment or start processing.');
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'items', 'payments.receiver', 'payments.refund.user', 'refunds', 'events.user', 'creator']);
        if (request()->routeIs('api.*')) {
            return response()->json($order);
        }

        return view('orders.show', compact('order'));
    }

    public function status(Request $r, Order $order, OrderService $service)
    {
        $data = $r->validate(['status' => ['required', Rule::in(array_keys(Order::STATUSES))], 'note' => 'nullable|string|max:1000']);
        if ($data['status'] === 'cancelled') {
            abort_unless($r->user()->hasPermission('orders.cancel'), 403);
            $r->validate(['note' => 'required|string|min:5|max:1000']);
        }
        $service->transition($order, $data['status'], $r->user()->id, $data['note'] ?? null);

        return back()->with('success', 'Order stage updated.');
    }

    public function payment(Request $r, Order $order, OrderService $service)
    {
        $data = $r->validate(['amount' => 'required|numeric|min:0.01|max:10000000|decimal:0,2', 'method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'e_wallet'])], 'reference' => 'nullable|string|max:150', 'idempotency_key' => 'required|uuid']);
        $service->pay($order, $data, $r->user()->id);

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function refund(Request $r, Order $order, Payment $payment, OrderService $service)
    {
        $data = $r->validate(['reason' => 'required|string|min:5|max:255']);
        $service->refund($order, $payment, $data['reason'], $r->user()->id);

        return back()->with('success', 'Full payment refund recorded. If the order is being cancelled, refund every payment, then cancel the order.');
    }

    public function receipt(Order $order)
    {
        $order->load('customer', 'items', 'payments.receiver', 'payments.refund', 'refunds');

        return view('orders.receipt', compact('order'));
    }
}

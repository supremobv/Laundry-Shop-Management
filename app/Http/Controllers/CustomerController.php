<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'archived' => 'nullable|boolean']);
        $customers = Customer::withCount('orders')->when($r->boolean('archived'), fn ($q) => $q->onlyTrashed())->when($r->q, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))->orderBy('name')->paginate(15)->withQueryString();
        if ($r->routeIs('api.*')) {
            return response()->json($customers);
        }

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(Request $r)
    {
        $customer = Customer::create($this->validated($r));
        AuditLog::record('customer.created', (string) $customer->id);

        return redirect()->route('customers.index')->with('success', 'Customer added.');
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', compact('customer'));
    }

    public function update(Request $r, Customer $customer)
    {
        $customer->update($this->validated($r));
        AuditLog::record('customer.updated', (string) $customer->id);

        return redirect()->route('customers.index')->with('success', 'Customer updated.');
    }

    public function show(Customer $customer)
    {
        $orders = $customer->orders()->withSum('payments', 'amount_cents')->withSum('refunds', 'amount_cents')->latest()->paginate(10);

        return view('customers.show', compact('customer', 'orders'));
    }

    public function destroy(Customer $customer)
    {
        if ($customer->orders()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            return back()->withErrors(['customer' => 'Complete or cancel this customer’s active orders before archiving.']);
        }
        $customer->delete();
        AuditLog::record('customer.archived', (string) $customer->id);

        return redirect()->route('customers.index')->with('success', 'Customer archived. Historical orders are retained.');
    }

    public function restore(Customer $customer)
    {
        abort_unless($customer->trashed(), 404);
        $customer->restore();
        AuditLog::record('customer.restored', (string) $customer->id);

        return back()->with('success', 'Customer restored to active profiles.');
    }

    private function validated(Request $r): array
    {
        return $r->validate(['name' => 'required|string|max:150', 'phone' => 'required|string|max:40', 'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:500', 'notes' => 'nullable|string|max:2000']);
    }
}

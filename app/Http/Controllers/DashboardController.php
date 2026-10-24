<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Supply;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $counts = Order::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $orders = Order::with('customer')->withSum('payments', 'amount_cents')->withSum('refunds', 'amount_cents')->whereNotIn('status', ['completed', 'cancelled'])->orderBy('due_at')->limit(7)->get();
        $revenue = Payment::where('created_at', '>=', today())->where('created_at', '<', today()->addDay())->sum('amount_cents')
            - Refund::where('created_at', '>=', today())->where('created_at', '<', today()->addDay())->sum('amount_cents');
        $weekly = collect(range(6, 0))->map(function ($days) {
            $start = today()->subDays($days);

            return ['label' => $start->format('D'), 'amount' => Payment::where('created_at', '>=', $start)->where('created_at', '<', $start->copy()->addDay())->sum('amount_cents')];
        });
        $lowStock = Supply::where('active', true)->whereColumn('quantity', '<=', 'reorder_level')->count();
        $customers = Customer::count();

        return view('dashboard', compact('counts', 'orders', 'revenue', 'weekly', 'lowStock', 'customers'));
    }
}

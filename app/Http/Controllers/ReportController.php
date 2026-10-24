<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index(Request $r)
    {
        [$from,$to] = $this->dates($r);
        $payments = Payment::with('order', 'receiver')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $collected = (clone $payments)->sum('amount_cents');
        $refunds = Refund::with('payment.order', 'user')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $refunded = (clone $refunds)->sum('amount_cents');
        $refunds = $refunds->latest()->limit(100)->get();
        $methods = (clone $payments)->selectRaw('method, SUM(amount_cents) as total')->groupBy('method')->get();
        $expenses = Expense::where('spent_on', '>=', $from)->where('spent_on', '<', Carbon::parse($to)->addDay()->toDateString())->sum('amount_cents');
        $booked = Order::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->where('status', '!=', 'cancelled')->sum('total_cents');
        $outstanding = Order::where('status', '!=', 'cancelled')->sum('total_cents') - Payment::sum('amount_cents') + Refund::sum('amount_cents');
        $payments = $payments->latest()->paginate(20)->withQueryString();

        return view('reports.index', compact('from', 'to', 'collected', 'refunded', 'refunds', 'methods', 'expenses', 'booked', 'outstanding', 'payments'));
    }

    public function export(Request $r)
    {
        [$from,$to] = $this->dates($r);
        AuditLog::record('report.exported', null, compact('from', 'to'));

        return response()->streamDownload(function () use ($from, $to) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Payment ID', 'Order', 'Date', 'Amount', 'Method', 'Reference', 'Received by']);
            foreach (Payment::with('order', 'receiver')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->orderBy('id')->lazyById(200) as $p) {
                fputcsv($file, array_map(fn ($v) => preg_match('/^[\s]*[=+@\-]/u', (string) $v) ? "'".$v : $v, [$p->id, $p->order->number, $p->created_at->toDateTimeString(), number_format($p->amount_cents / 100, 2, '.', ''), $p->method, $p->reference, $p->receiver->name]));
            }
            foreach (Refund::with('payment.order', 'user')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->orderBy('id')->lazyById(200) as $refund) {
                fputcsv($file, array_map(fn ($v) => preg_match('/^[\s]*[=+@\-]/u', (string) $v) ? "'".$v : $v, ['R-'.$refund->id, $refund->payment->order->number, $refund->created_at->toDateTimeString(), number_format(-$refund->amount_cents / 100, 2, '.', ''), 'refund', $refund->reason, $refund->user->name]));
            }
            fclose($file);
        }, 'payments-'.$from.'-'.$to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function dates(Request $r): array
    {
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $r->input('from') ?: today()->startOfMonth()->toDateString();
        $to = $r->input('to') ?: today()->toDateString();
        if ($from > $to || Carbon::parse($from)->diffInDays($to) >= 366) {
            throw ValidationException::withMessages(['from' => 'Choose a date range of up to 366 days, with the start before the end.']);
        }

        return [$from, $to];
    }
}

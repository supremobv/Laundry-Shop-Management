<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\StockMovement;
use App\Models\Supply;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationsController extends Controller
{
    public function expenses()
    {
        return view('operations.expenses', ['expenses' => Expense::orderByDesc('spent_on')->latest()->paginate(15)]);
    }

    public function expenseStore(Request $r)
    {
        $data = $r->validate(['description' => 'required|string|max:200', 'category' => 'required|in:utilities,rent,supplies,payroll,maintenance,other', 'amount' => 'required|numeric|min:0.01|max:10000000|decimal:0,2', 'spent_on' => 'required|date|before_or_equal:today']);
        $data['amount_cents'] = Money::cents($data['amount']);
        unset($data['amount']);
        DB::transaction(function () use ($data, $r) {
            $e = Expense::create([...$data, 'created_by' => $r->user()->id]);
            AuditLog::record('expense.created', (string) $e->id, ['amount_cents' => $e->amount_cents]);
        });

        return back()->with('success', 'Expense recorded.');
    }

    public function inventory()
    {
        return view('operations.inventory', ['supplies' => Supply::orderBy('name')->paginate(20), 'movements' => StockMovement::with('supply', 'user')->latest()->limit(10)->get()]);
    }

    public function supplyStore(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:150', 'unit' => 'required|string|max:20', 'reorder_level' => 'required|numeric|min:0|max:100000|decimal:0,2']);
        $s = Supply::create($data);
        AuditLog::record('supply.created', (string) $s->id);

        return back()->with('success', 'Supply added. Use Adjust stock to record an opening balance.');
    }

    public function stock(Request $r, Supply $supply)
    {
        $data = $r->validate(['quantity' => 'required|numeric|not_in:0|min:-100000|max:100000|decimal:0,2', 'reason' => 'required|string|min:3|max:200', 'reorder_level' => 'required|numeric|min:0|max:100000|decimal:0,2']);
        DB::transaction(function () use ($supply, $data, $r) {
            $s = Supply::lockForUpdate()->findOrFail($supply->id);
            $quantity = bcadd((string) $s->quantity, (string) $data['quantity'], 2);
            if (bccomp($quantity, '0', 2) < 0 || bccomp($quantity, '100000000', 2) > 0) {
                throw ValidationException::withMessages(['quantity' => 'Stock cannot be negative or exceed 100 million units.']);
            }
            $s->update(['quantity' => $quantity, 'reorder_level' => $data['reorder_level']]);
            $s->movements()->create(['user_id' => $r->user()->id, 'quantity' => $data['quantity'], 'reason' => $data['reason']]);
            AuditLog::record('stock.adjusted', (string) $s->id, $data);
        });

        return back()->with('success', 'Stock adjustment recorded.');
    }
}

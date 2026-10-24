<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index()
    {
        return view('services.index', ['services' => Service::orderBy('name')->paginate(15)]);
    }

    public function create()
    {
        return view('services.form', ['service' => new Service(['active' => true, 'turnaround_hours' => 24, 'unit' => 'kg'])]);
    }

    public function store(Request $r)
    {
        $service = Service::create($this->validated($r));
        AuditLog::record('service.created', (string) $service->id);

        return redirect()->route('services.index')->with('success', 'Service added.');
    }

    public function edit(Service $service)
    {
        return view('services.form', compact('service'));
    }

    public function update(Request $r, Service $service)
    {
        $service->update($this->validated($r));
        AuditLog::record('service.updated', (string) $service->id);

        return redirect()->route('services.index')->with('success', 'Service updated. Existing order prices are preserved.');
    }

    private function validated(Request $r): array
    {
        $data = $r->validate(['name' => 'required|string|max:150', 'unit' => ['required', Rule::in(['kg', 'piece', 'load', 'pair'])], 'price' => 'required|numeric|min:0.01|max:100000|decimal:0,2', 'turnaround_hours' => 'required|integer|min:1|max:720', 'description' => 'nullable|string|max:1000', 'active' => 'required|boolean']);
        $data['price_cents'] = Money::cents($data['price']);
        unset($data['price']);

        return $data;
    }
}

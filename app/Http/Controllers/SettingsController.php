<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings.edit');
    }

    public function update(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:150', 'tagline' => 'nullable|string|max:200', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:500', 'currency' => ['required', Rule::in(['PHP', 'USD', 'SGD', 'EUR', 'GBP', 'AUD', 'MYR', 'IDR'])], 'timezone' => 'required|timezone', 'tax_percent' => 'required|numeric|min:0|max:30|decimal:0,2', 'receipt_footer' => 'required|string|max:250', 'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=2048,max_height=2048', 'remove_logo' => 'nullable|boolean']);
        $settings = BusinessSetting::current();
        $data['tagline'] = $data['tagline'] ?? '';
        if ($settings->currency !== $data['currency'] && Order::exists()) {
            throw ValidationException::withMessages(['currency' => 'Currency cannot change after the first order because historical amounts use this currency.']);
        }
        if ($settings->timezone !== $data['timezone'] && Order::exists()) {
            throw ValidationException::withMessages(['timezone' => 'Timezone cannot change after the first order because existing pickup times use this timezone.']);
        }
        $data['tax_basis_points'] = Money::cents($data['tax_percent']);
        unset($data['tax_percent'],$data['logo'],$data['remove_logo']);
        $oldLogo = $settings->logo_path;
        if ($r->boolean('remove_logo')) {
            $data['logo_path'] = null;
        }
        if ($r->hasFile('logo')) {
            $image = imagecreatefromstring(file_get_contents($r->file('logo')->getRealPath()));
            if (! $image) {
                throw ValidationException::withMessages(['logo' => 'This image could not be read. Choose another PNG, JPEG, or WebP image.']);
            }
            imagesavealpha($image, true);
            ob_start();
            imagepng($image);
            $bytes = ob_get_clean();
            imagedestroy($image);
            $data['logo_path'] = 'branding/'.Str::uuid().'.png';
            Storage::disk('local')->put($data['logo_path'], $bytes);
        }
        DB::transaction(function () use ($settings, $data) {
            $settings->update($data);
            AuditLog::record('business.updated', null, ['fields' => array_keys($data)]);
        });
        if ($oldLogo && array_key_exists('logo_path', $data) && $oldLogo !== $data['logo_path']) {
            Storage::disk('local')->delete($oldLogo);
        }

        return back()->with('success', 'Business settings saved.');
    }

    public function logo()
    {
        $path = BusinessSetting::current()->logo_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return response()->file(public_path('images/logo.svg'));
        }

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'image/png']);
    }
}

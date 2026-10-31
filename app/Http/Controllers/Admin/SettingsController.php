<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', [
            'siteName' => Setting::get('site_name', config('app.name')),
            'logoPath' => Setting::get('logo_path'),
            'registrationMode' => \App\Support\Registration::mode(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => 'required|string|max:60',
            // Keine SVG-Dateien: die können Skripte enthalten
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:1024|dimensions:max_width=2000,max_height=2000',
            'remove_logo' => 'nullable|boolean',
            'registration_mode' => 'nullable|in:' . implode(',', array_keys(\App\Support\Registration::MODES)),
        ], [
            'logo.mimes' => 'Erlaubt sind PNG, JPG oder WebP (kein SVG).',
            'logo.max' => 'Das Logo darf höchstens 1 MB groß sein.',
            'logo.dimensions' => 'Das Logo darf höchstens 2000 x 2000 Pixel groß sein.',
        ]);

        Setting::put('site_name', trim($data['site_name']));
        if (!empty($data['registration_mode'])) {
            Setting::put('registration_mode', $data['registration_mode']);
        }

        $old = Setting::get('logo_path');
        if ($request->hasFile('logo')) {
            $ext = $request->file('logo')->extension(); // aus dem echten Dateiinhalt, nicht aus dem Dateinamen
            $path = $request->file('logo')->storeAs('branding', 'logo-' . time() . '.' . $ext, 'public');
            Setting::put('logo_path', $path);
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        } elseif ($request->boolean('remove_logo') && $old) {
            Storage::disk('public')->delete($old);
            Setting::put('logo_path', null);
        }

        return redirect()->route('admin.settings')->with('status', 'Einstellungen gespeichert.');
    }
}

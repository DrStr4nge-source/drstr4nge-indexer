<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\RuntimeSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Process\Process;

/** Admin: Zugangsdaten (Usenet, TMDB, Mail, Zahlung, Sicherung) ohne .env bearbeiten. */
class CredentialsController extends Controller
{
    public const GROUPS = [
        'usenet' => 'Usenet-Zugang', 'tmdb' => 'TMDB (Cover und Filminfos)', 'mail' => 'Mail-Versand und Warnungen',
        'payment' => 'Zahlung (NOWPayments)', 'backup' => 'Sicherung',
    ];

    public function edit()
    {
        $fields = [];
        foreach (RuntimeSettings::FIELDS as $key => [$path, $type, $group, $label]) {
            $admin = Setting::get($key);
            $fields[$group][$key] = [
                'label' => $label, 'type' => $type,
                'value' => $type === 'secret' ? '' : config($path),
                'from_admin' => $admin !== null,
                'has_secret' => $type === 'secret' && filled(config($path)),
                'env' => RuntimeSettings::$env[$path] ?? null,
            ];
        }
        return view('admin.credentials', [
            'fields' => $fields,
            'groups' => self::GROUPS,
            'rclone' => (bool) trim((string) @shell_exec('command -v rclone')),
            'gdrive' => is_file(RuntimeSettings::rcloneConfig()),
            'gdriveFolder' => str_contains((string) config('indexer.backup.rclone_remote'), ':') ? explode(':', config('indexer.backup.rclone_remote'), 2)[1] : 'drstr4nge-backups',
            'backupAt' => Cache::get('backup.last_at'),
            'backupError' => Cache::get('backup.error'),
            'backupRunning' => ($m = Cache::get('backup.manual_started')) && (int) Cache::get('backup.last_at', 0) < $m && !Cache::get('backup.error'),
        ]);
    }

    public function update(Request $request, string $group)
    {
        abort_unless(isset(self::GROUPS[$group]), 404);
        $rules = $messages = [];
        foreach (RuntimeSettings::FIELDS as $key => [$path, $type, $g, $label]) {
            if ($g !== $group) continue;
            $rules[$key] = match (true) {
                $type === 'host' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
                $type === 'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                str_starts_with($type, 'int:') => ['nullable', 'integer', 'min:' . explode(':', $type)[1], 'max:' . explode(':', $type)[2]],
                $type === 'bool' => ['nullable', 'boolean'],
                $type === 'email' => ['nullable', 'email', 'max:255'],
                $type === 'emails' => ['nullable', 'string', 'max:500', function ($a, $v, $fail) {
                    foreach (array_filter(array_map('trim', explode(',', (string) $v))) as $e) {
                        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) $fail("Keine gültige Adresse: $e");
                    }
                }],
                str_starts_with($type, 'in:') => ['nullable', $type],
                default => ['nullable', 'string', 'max:500'],
            };
            if ($type === 'host') $messages["$key.regex"] = 'Nur Servername oder IP, ohne Port und ohne "://".';
        }
        $data = $request->validate($rules, $messages);

        foreach (RuntimeSettings::FIELDS as $key => [$path, $type, $g]) {
            if ($g !== $group) continue;
            if ($type === 'secret') {
                if ($request->boolean("remove_$key")) {
                    Setting::put($key, null); // wieder .env
                } elseif (filled($data[$key] ?? null)) {
                    Setting::put($key, Crypt::encryptString((string) $data[$key]));
                }
                continue;
            }
            $v = $type === 'bool' ? ($request->boolean($key) ? '1' : '0') : trim((string) ($data[$key] ?? ''));
            $env = RuntimeSettings::$env[$path] ?? null;
            $envStr = is_bool($env) ? ($env ? '1' : '0') : (string) $env;
            // Gleich wie .env oder leer: Admin-Wert löschen, dann gilt die .env
            Setting::put($key, ($v === '' || $v === $envStr) ? null : $v);
        }
        return redirect()->to(route('admin.credentials') . "#$group")->with('status', self::GROUPS[$group] . ': gespeichert.');
    }

    public function test(string $what)
    {
        RuntimeSettings::apply(); // frisch gespeicherte Werte
        if ($what === 'usenet') {
            Artisan::call('indexer:test-nntp');
            return $this->back('usenet', 'Verbindungstest Usenet:', trim(Artisan::output()));
        }
        if ($what === 'mail') {
            Artisan::call('indexer:health', ['--test' => true]);
            return $this->back('mail', 'Test-Mail:', trim(Artisan::output()));
        }
        if ($what === 'gdrive') {
            $p = $this->rclone(['lsd', 'gdrive:']);
            return $this->back('backup', $p->isSuccessful() ? 'Google Drive erreichbar.' : 'Google Drive nicht erreichbar: ' . trim($p->getErrorOutput()));
        }
        abort(404);
    }

    /** Token von "rclone authorize drive" speichern und rclone.conf schreiben */
    public function gdrive(Request $request)
    {
        if ($request->boolean('remove')) {
            @unlink(RuntimeSettings::rcloneConfig());
            Setting::put('backup_rclone_remote', null);
            return $this->back('backup', 'Google Drive getrennt.');
        }
        $data = $request->validate([
            'token' => 'required|string|max:5000',
            'folder' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\- \/]+$/'],
        ], ['folder.regex' => 'Ordnername: Buchstaben, Ziffern, Leerzeichen, . _ - /']);
        // Ausgabe von rclone authorize enthält den Token zwischen Pfeilen oder als reines JSON
        $raw = $data['token'];
        if (preg_match('/\{.*\}/s', $raw, $m)) $raw = $m[0];
        $tok = json_decode($raw, true);
        if (!is_array($tok) || empty($tok['access_token']) || empty($tok['refresh_token'])) {
            return back()->withInput()->withErrors(['token' => 'Das ist kein gültiger Token. Den kompletten Text zwischen ---> und <--- einfügen (beginnt mit {"access_token").']);
        }
        $conf = RuntimeSettings::rcloneConfig();
        @mkdir(dirname($conf), 0700, true);
        file_put_contents($conf, "[gdrive]\ntype = drive\nscope = drive\ntoken = " . json_encode($tok, JSON_UNESCAPED_SLASHES) . "\n");
        chmod($conf, 0600);
        $folder = trim($data['folder'], " /");
        Setting::put('backup_rclone_remote', "gdrive:$folder");

        $p = $this->rclone(['mkdir', "gdrive:$folder"]);
        if (!$p->isSuccessful()) {
            return $this->back('backup', 'Token gespeichert, aber Google Drive antwortet nicht: ' . trim($p->getErrorOutput()));
        }
        return $this->back('backup', "Google Drive verbunden. Sicherungen landen im Ordner \"$folder\".");
    }

    /** Sicherung im Hintergrund starten */
    public function backupNow()
    {
        Cache::put('backup.manual_started', time(), 3600);
        Cache::forget('backup.error');
        $cmd = sprintf('nohup %s %s indexer:backup > %s 2>&1 &', escapeshellarg($this->phpCli()),
            escapeshellarg(base_path('artisan')), escapeshellarg(storage_path('logs/backup-manuell.log')));
        Process::fromShellCommandline($cmd, base_path())->run();
        return $this->back('backup', 'Sicherung läuft im Hintergrund. In ein paar Minuten die Seite neu laden.');
    }

    private function phpCli(): string
    {
        // Unter PHP-FPM zeigt PHP_BINARY auf php-fpm, für Befehle braucht es php (CLI)
        $bin = PHP_BINARY;
        return str_contains(basename($bin), 'fpm') ? (trim((string) @shell_exec('command -v php')) ?: 'php') : $bin;
    }

    private function rclone(array $args): Process
    {
        $p = new Process(array_merge(['rclone', '--config', RuntimeSettings::rcloneConfig()], $args));
        $p->setTimeout(60);
        $p->run();
        return $p;
    }

    private function back(string $anchor, string $msg, ?string $output = null)
    {
        return redirect()->to(route('admin.credentials') . "#$anchor")->with('status', $msg)->with('test_output', $output);
    }
}

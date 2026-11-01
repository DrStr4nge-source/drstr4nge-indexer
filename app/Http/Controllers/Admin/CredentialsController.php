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
            'gdrive' => isset(self::sections()['gdrive']),
            'mega' => isset(self::sections()['mega']),
            'megaUser' => preg_match('/^user = (.+)$/m', self::sections()['mega'] ?? '', $mu) ? $mu[1] : '',
            'target' => strtok((string) config('indexer.backup.rclone_remote'), ':') ?: null,
            'megaFolder' => \App\Models\Setting::get('rclone_folder_mega') ?: 'drstr4nge-backups',
            'gdriveFolder' => \App\Models\Setting::get('rclone_folder_gdrive') ?: (str_starts_with((string) config('indexer.backup.rclone_remote'), 'gdrive:') ? explode(':', config('indexer.backup.rclone_remote'), 2)[1] : 'drstr4nge-backups'),
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
        if ($what === 'mega') {
            $p = $this->rclone(['about', 'mega:']);
            return $this->back('backup', $p->isSuccessful() ? 'MEGA erreichbar. ' . preg_replace('/\s+/', ' ', trim($p->getOutput())) : 'MEGA nicht erreichbar: ' . trim($p->getErrorOutput()));
        }
        abort(404);
    }

    /** Token von "rclone authorize drive" speichern und rclone.conf schreiben */
    public function gdrive(Request $request)
    {
        if ($request->boolean('remove')) {
            self::writeSection('gdrive', null);
            self::dropTarget('gdrive');
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
        self::writeSection('gdrive', "type = drive\nscope = drive\ntoken = " . json_encode($tok, JSON_UNESCAPED_SLASHES));
        $folder = trim($data['folder'], " /");
        self::rememberFolder();
        Setting::put('backup_rclone_remote', "gdrive:$folder");
        Setting::put('rclone_folder_gdrive', $folder);

        $p = $this->rclone(['mkdir', "gdrive:$folder"]);
        if (!$p->isSuccessful()) {
            return $this->back('backup', 'Token gespeichert, aber Google Drive antwortet nicht: ' . trim($p->getErrorOutput()));
        }
        return $this->back('backup', "Google Drive verbunden. Sicherungen landen im Ordner \"$folder\".");
    }

    /** MEGA (mega.nz) mit E-Mail und Passwort verbinden. Das Passwort steht nur verschleiert (rclone obscure) in der rclone.conf. */
    public function mega(Request $request)
    {
        if ($request->boolean('remove')) {
            self::writeSection('mega', null);
            self::dropTarget('mega');
            return $this->back('backup', 'MEGA getrennt.');
        }
        $data = $request->validate([
            'mega_user' => 'required|email|max:255',
            'mega_pass' => 'required|string|max:500',
            'mega_folder' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\- \/]+$/'],
        ], ['mega_folder.regex' => 'Ordnername: Buchstaben, Ziffern, Leerzeichen, . _ - /']);
        // Das Ubuntu-Paket von rclone hat kein MEGA
        $bk = new Process(['rclone', 'help', 'backends']);
        $bk->run();
        if (!preg_match('/^\s*mega\b/m', $bk->getOutput())) {
            return back()->withInput($request->except('mega_pass'))->withErrors(['mega_pass' => 'Dein rclone kann kein MEGA (das Ubuntu-Paket hat es nicht). Einmal das Update einspielen, das installiert rclone von rclone.org: '
                . 'cd /tmp && rm -rf dsi && git clone -q https://github.com/DrStr4nge-source/drstr4nge-indexer.git dsi && sudo bash dsi/update.sh']);
        }
        // Passwort über stdin, nicht als Argument (wäre sonst in "ps" sichtbar)
        $ob = new Process(['rclone', 'obscure', '-']);
        $ob->setInput($data['mega_pass']);
        $ob->setTimeout(30);
        $ob->run();
        $obscured = trim($ob->getOutput());
        if (!$ob->isSuccessful() || $obscured === '' || str_contains($obscured, "\n")) {
            return $this->back('backup', 'rclone konnte das Passwort nicht verarbeiten: ' . trim($ob->getErrorOutput()));
        }
        $old = self::sections()['mega'] ?? null;
        self::writeSection('mega', "type = mega\nuser = " . trim($data['mega_user']) . "\npass = $obscured\nhard_delete = true");
        $folder = trim($data['mega_folder'], ' /');
        $p = $this->rclone(['mkdir', "mega:$folder"]);
        if (!$p->isSuccessful()) {
            self::writeSection('mega', $old); // alte (funktionierende) Verbindung behalten
            $err = trim($p->getErrorOutput());
            return back()->withInput($request->except('mega_pass'))->withErrors(['mega_pass' => 'Anmeldung bei MEGA fehlgeschlagen. E-Mail und Passwort prüfen (Zwei-Faktor-Anmeldung bei MEGA muss aus sein). '
                . ($err !== '' ? 'rclone: ' . mb_substr($err, -300) : '')]);
        }
        self::rememberFolder();
        Setting::put('backup_rclone_remote', "mega:$folder");
        Setting::put('rclone_folder_mega', $folder);
        return $this->back('backup', "MEGA verbunden. Sicherungen landen im Ordner \"$folder\".");
    }

    /** Bereits verbundenes Ziel (gdrive oder mega) als Sicherungsziel wählen */
    public function target(Request $request, string $remote)
    {
        abort_unless(isset(self::sections()[$remote]), 404);
        self::rememberFolder();
        $folder = Setting::get("rclone_folder_$remote") ?: 'drstr4nge-backups';
        Setting::put('backup_rclone_remote', "$remote:$folder");
        return $this->back('backup', 'Sicherungsziel: ' . ($remote === 'mega' ? 'MEGA' : 'Google Drive') . '.');
    }

    /** Ordner des bisherigen Ziels merken, bevor das Ziel wechselt (ältere Installationen haben ihn nur in backup_rclone_remote) */
    private static function rememberFolder(): void
    {
        if (preg_match('/^(gdrive|mega):(.+)$/', (string) config('indexer.backup.rclone_remote'), $m) && !Setting::get("rclone_folder_{$m[1]}")) {
            Setting::put("rclone_folder_{$m[1]}", $m[2]);
        }
    }

    /** Abschnitte der rclone.conf: [name => Inhalt ohne Kopfzeile] */
    private static function sections(): array
    {
        $out = [];
        $name = null;
        foreach (preg_split('/\R/', (string) @file_get_contents(RuntimeSettings::rcloneConfig())) as $line) {
            if (preg_match('/^\[(.+)\]\s*$/', $line, $m)) {
                $name = $m[1];
                $out[$name] = '';
            } elseif ($name !== null && trim($line) !== '') {
                $out[$name] .= ($out[$name] === '' ? '' : "\n") . $line;
            }
        }
        return $out;
    }

    /** Einen Abschnitt setzen (null = entfernen), die anderen bleiben erhalten */
    private static function writeSection(string $name, ?string $body): void
    {
        $all = self::sections();
        if ($body === null) unset($all[$name]); else $all[$name] = $body;
        $conf = RuntimeSettings::rcloneConfig();
        if (!$all) {
            @unlink($conf);
            return;
        }
        @mkdir(dirname($conf), 0700, true);
        file_put_contents($conf, implode("\n", array_map(fn ($n, $b) => "[$n]\n$b\n", array_keys($all), $all)));
        chmod($conf, 0600);
    }

    /** War das getrennte Konto das Sicherungsziel, gibt es keins mehr (bzw. das andere verbundene) */
    private static function dropTarget(string $remote): void
    {
        if (str_starts_with((string) config('indexer.backup.rclone_remote'), "$remote:")) {
            Setting::put('backup_rclone_remote', null);
        }
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

<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * Sicherung: Datenbank (gzip), .env und Logo in eine .tar-Datei.
 * Alte Sicherungen werden nach BACKUP_KEEP_DAYS gelöscht. Optional Kopie per rsync auf einen zweiten Server
 * und/oder per rclone in die Cloud (Google Drive, Dropbox, OneDrive, S3 ...).
 */
class Backup extends Command
{
    protected $signature = 'indexer:backup {--no-remote : nicht auf zweiten Server oder in die Cloud kopieren}';
    protected $description = 'Datenbank und Einstellungen sichern';

    public function handle(): int
    {
        $c = config('indexer.backup');
        if (!$c['enabled']) {
            $this->info('Sicherung ist aus (BACKUP_ENABLED=false).');
            return self::SUCCESS;
        }
        $dir = rtrim($c['path'], '/');
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            return $this->fail2("Ordner $dir lässt sich nicht anlegen");
        }
        @chmod($dir, 0700);
        $stamp = date('Ymd-His');
        $work = "$dir/.tmp-$stamp";
        mkdir($work, 0700);
        $target = "$dir/drstr4nge-backup-$stamp.tar";
        for ($n = 2; file_exists($target); $n++) { // nie eine vorhandene Sicherung überschreiben
            $target = "$dir/drstr4nge-backup-$stamp-$n.tar";
        }

        try {
            // 1) Datenbank
            $db = config('database.connections.' . config('database.default'));
            $cnf = "$work/my.cnf"; // Passwort nicht auf der Kommandozeile (wäre in "ps" sichtbar)
            file_put_contents($cnf, "[client]\nuser=\"" . addcslashes((string) $db['username'], '"\\') . "\"\npassword=\"" . addcslashes((string) $db['password'], '"\\') . "\"\n"
                . (!empty($db['unix_socket']) ? "socket=\"{$db['unix_socket']}\"\n" : "host=\"{$db['host']}\"\nport={$db['port']}\n"));
            chmod($cnf, 0600);
            $dump = trim((string) shell_exec('command -v mariadb-dump || command -v mysqldump'));
            if ($dump === '') {
                throw new \RuntimeException('mariadb-dump/mysqldump nicht gefunden');
            }
            $this->runShell(sprintf('%s --defaults-extra-file=%s --single-transaction --quick --max-allowed-packet=1G --routines --no-tablespaces %s | gzip -6 > %s',
                escapeshellarg($dump), escapeshellarg($cnf), escapeshellarg($db['database']), escapeshellarg("$work/datenbank.sql.gz")), 3600);
            unlink($cnf);

            // 2) .env (enthält APP_KEY, ohne den gespeicherte Passwörter und 2FA nicht lesbar sind) und Logo
            copy(base_path('.env'), "$work/env");
            chmod("$work/env", 0600);
            $parts = ['datenbank.sql.gz', 'env'];
            if (is_dir(storage_path('app/public/branding'))) {
                $this->runShell(sprintf('cp -a %s %s', escapeshellarg(storage_path('app/public/branding')), escapeshellarg("$work/branding")));
                $parts[] = 'branding';
            }
            $this->runShell(sprintf('tar -cf %s -C %s %s', escapeshellarg($target), escapeshellarg($work), implode(' ', array_map('escapeshellarg', $parts))));
            chmod($target, 0600);
        } catch (\Throwable $e) {
            @unlink($target);
            return $this->fail2($e->getMessage());
        } finally {
            $this->runShell('rm -rf ' . escapeshellarg($work), 60, false);
        }

        $size = filesize($target);
        $this->info(sprintf('Sicherung: %s (%s)', $target, \App\Support\Format::bytes($size)));

        // 3) Alte Sicherungen löschen
        $deleted = 0;
        foreach (glob("$dir/drstr4nge-backup-*.tar") ?: [] as $f) {
            if ($f !== $target && filemtime($f) < time() - max(1, (int) $c['keep_days']) * 86400) {
                @unlink($f) && $deleted++;
            }
        }
        if ($deleted) {
            $this->info("$deleted alte Sicherung(en) gelöscht.");
        }

        // 4) Zweiter Server
        $remoteError = null;
        if (!empty($c['remote']) && !$this->option('no-remote')) {
            try {
                $this->runShell(sprintf('rsync -a --timeout=120 -e %s %s %s', escapeshellarg('ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new'),
                    escapeshellarg($target), escapeshellarg($c['remote'])), 7200);
                $this->info('Auf zweiten Server kopiert: ' . $c['remote']);
            } catch (\Throwable $e) {
                $remoteError = 'Kopie auf zweiten Server fehlgeschlagen: ' . $e->getMessage();
                $this->error($remoteError);
            }
        }

        // 5) Cloud (Google Drive usw.) über rclone, z.B. BACKUP_RCLONE_REMOTE=gdrive:drstr4nge-backups
        if (!empty($c['rclone_remote']) && !$this->option('no-remote')) {
            try {
                $rc = 'rclone' . (!empty($c['rclone_config']) ? ' --config ' . escapeshellarg($c['rclone_config']) : '');
                $this->runShell(sprintf('%s copy %s %s', $rc, escapeshellarg($target), escapeshellarg($c['rclone_remote'])), 7200);
                $this->info('In die Cloud kopiert: ' . $c['rclone_remote']);
                // Dort ebenfalls nur die letzten BACKUP_KEEP_DAYS Tage behalten
                // --drive-use-trash=false: endgültig löschen, sonst belegt der Drive-Papierkorb noch 30 Tage Platz (bei anderen Zielen ohne Wirkung)
                $this->runShell(sprintf('%s delete %s --min-age %dd --include %s --drive-use-trash=false', $rc, escapeshellarg($c['rclone_remote']),
                    max(1, (int) $c['keep_days']), escapeshellarg('drstr4nge-backup-*.tar')), 600, false);
            } catch (\Throwable $e) {
                $msg = 'Kopie in die Cloud fehlgeschlagen: ' . $e->getMessage();
                $remoteError = $remoteError ? "$remoteError; $msg" : $msg;
                $this->error($msg);
            }
        }

        Cache::forever('backup.last_at', time());
        Cache::forever('backup.last_size', $size);
        $remoteError ? Cache::forever('backup.error', $remoteError) : Cache::forget('backup.error');
        $remoteError ? \Illuminate\Support\Facades\Log::error('Sicherung: ' . $remoteError) : \Illuminate\Support\Facades\Log::info('Sicherung erstellt: ' . basename($target) . ' (' . \App\Support\Format::bytes($size) . ')');
        return $remoteError ? self::FAILURE : self::SUCCESS;
    }

    private function runShell(string $cmd, int $timeout = 600, bool $check = true): void
    {
        // Ausdrücklich bash: /bin/sh ist auf Ubuntu dash und kennt pipefail nicht
        $p = new Process(['bash', '-c', 'set -o pipefail; ' . $cmd]);
        $p->setTimeout($timeout);
        $p->run();
        if ($check && !$p->isSuccessful()) {
            throw new \RuntimeException(trim($p->getErrorOutput()) ?: 'Befehl fehlgeschlagen (Code ' . $p->getExitCode() . ')');
        }
    }

    private function fail2(string $msg): int
    {
        Cache::forever('backup.error', $msg);
        \Illuminate\Support\Facades\Log::error('Sicherung fehlgeschlagen: ' . $msg);
        $this->error('Sicherung fehlgeschlagen: ' . $msg);
        return self::FAILURE;
    }
}

<?php
namespace App\Console\Commands;

use App\Services\Pre\IrcClient;
use App\Services\Pre\IrcReload;
use App\Services\Pre\IrcSettings;
use App\Services\Pre\PreParser;
use App\Services\Pre\PreStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Liest Pre-Ansagen aus IRC-Kanälen mit. Läuft dauerhaft (systemd-Dienst drstr4nge-pre). */
class PreIrc extends Command
{
    protected $signature = 'indexer:pre-irc {--debug : jede Kanalzeile anzeigen}';
    protected $description = 'Pre-Daten aus IRC mitlesen (läuft dauerhaft)';

    public function handle(): int
    {
        $wait = 30;
        $notice = null;
        while (true) {
            $c = IrcSettings::get();
            $token = IrcSettings::reloadToken();
            if (!$c['enabled'] || empty($c['nick']) || empty($c['server']) || empty($c['channels'])) {
                // Nicht beenden: Sobald im Admin eingerichtet oder eingeschaltet wird, geht es von selbst los
                $why = !$c['enabled'] ? 'IRC-Scraper ist im Admin ausgeschaltet.' : 'IRC-Scraper nicht eingerichtet: Nick, Server und Kanäle im Admin unter PreDB oder in der .env eintragen.';
                if ($notice !== $why) {
                    $this->warn($this->now() . " $why Warte auf Änderung.");
                    $notice = $why;
                }
                $this->sleepUntilReload($token, 30);
                continue;
            }
            $notice = null;
            $irc = new IrcClient($c, fn ($m) => $this->line($this->now() . " $m"));
            try {
                $this->line($this->now() . " Verbinde mit {$c['server']}:{$c['port']}" . ($c['tls'] ? ' (TLS)' : '') . " als {$c['nick']}");
                $irc->connect();
                $this->info($this->now() . ' Angemeldet, betrete ' . implode(', ', $c['channels']));
                $irc->join($c['channels']);
                Cache::forever('pre_irc.connected_at', time());
                Cache::forget('pre_irc.error');
                $wait = 30;
                $irc->listen(
                    fn ($from, $channel, $text) => $this->handleLine($c, $from, $channel, $text),
                    300,
                    function () use ($token) {
                        if (IrcSettings::reloadToken() !== $token) {
                            throw new IrcReload('Einstellungen geändert, verbinde neu');
                        }
                    }
                );
            } catch (IrcReload $e) {
                $this->info($this->now() . ' ' . $e->getMessage());
                $irc->close();
                Cache::forget('pre_irc.connected_at');
                continue; // sofort mit neuen Werten
            } catch (\RuntimeException $e) {
                $this->error($this->now() . ' ' . $e->getMessage() . ", neuer Versuch in {$wait} s");
                Cache::forever('pre_irc.error', $e->getMessage());
            } finally {
                $irc->close();
            }
            Cache::forget('pre_irc.connected_at');
            if ($this->sleepUntilReload($token, $wait)) {
                $wait = 30;
                continue;
            }
            $wait = min($wait * 2, 600);
        }
    }

    /** Schläft bis zu $seconds, bricht früher ab, wenn im Admin gespeichert wurde. true = es wurde gespeichert. */
    private function sleepUntilReload(?float $token, int $seconds): bool
    {
        for ($i = 0; $i < $seconds; $i += 5) {
            sleep(min(5, $seconds - $i));
            if (IrcSettings::reloadToken() !== $token) {
                return true;
            }
        }
        return false;
    }

    private function handleLine(array $c, string $from, string $channel, string $text): void
    {
        if ($this->option('debug')) {
            $this->line("[$channel] <$from> " . PreParser::stripFormatting($text));
        }
        $pre = PreParser::parse($text);
        if (!$pre) {
            return;
        }
        if (!empty($c['category_ignore']) && $pre['category'] && @preg_match($c['category_ignore'], $pre['category'])) {
            return;
        }
        if (!empty($c['title_ignore']) && @preg_match($c['title_ignore'], $pre['title'])) {
            return;
        }
        try {
            [$row, $new] = PreStore::save($pre);
        } catch (\Throwable $e) {
            $this->error($this->now() . ' Speichern fehlgeschlagen: ' . $e->getMessage());
            return;
        }
        Cache::forever('pre_irc.last_at', time());
        $nuke = $pre['nuked'] !== null ? ' [' . $row->nukeLabel() . ($pre['nuke_reason'] ? ': ' . $pre['nuke_reason'] : '') . ']' : '';
        $this->line(sprintf('%s %s %s%s%s', $this->now(), $new ? 'NEU' : 'UPD', $pre['title'], $pre['category'] ? " ({$pre['category']})" : '', $nuke));
    }

    private function now(): string
    {
        return '[' . date('Y-m-d H:i:s') . ']';
    }
}

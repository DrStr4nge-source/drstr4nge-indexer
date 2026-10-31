<?php
// DrStr4nge Indexer: Zeitplan. Wird aus routes/console.php eingebunden und kommt mit jedem Update mit.
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('indexer:crawl')->everyTenMinutes()->withoutOverlapping(120)
    ->after(fn () => Cache::forever('schedule.last.crawl', time()));
Schedule::command('indexer:inspect')->everyFifteenMinutes()->withoutOverlapping(120)
    ->after(fn () => Cache::forever('schedule.last.inspect', time()));
Schedule::command('indexer:metadata')->everyThirtyMinutes()->withoutOverlapping(120)
    ->after(fn () => Cache::forever('schedule.last.metadata', time()));
Schedule::command('indexer:tvmaze')->hourly()->withoutOverlapping(120)
    ->after(fn () => Cache::forever('schedule.last.tvmaze', time()));
Schedule::command('indexer:pre-fetch')->everyFiveMinutes()->withoutOverlapping(30)
    ->after(fn () => Cache::forever('schedule.last.pre-fetch', time()));
Schedule::command('indexer:pre-deobfuscate')->everyThirtyMinutes()->withoutOverlapping(60)
    ->after(fn () => Cache::forever('schedule.last.pre-deobfuscate', time()));
Schedule::command('indexer:recategorize --if-changed')->everyTenMinutes()->withoutOverlapping(120)
    ->after(fn () => Cache::forever('schedule.last.recategorize', time()));
Schedule::command('indexer:cleanup')->dailyAt('04:00')->timezone(config('indexer.timezone'))
    ->after(fn () => Cache::forever('schedule.last.cleanup', time()));
Schedule::command('indexer:backup')->dailyAt(config('indexer.backup.time', '03:30'))->timezone(config('indexer.timezone'))->withoutOverlapping(180)
    ->after(fn () => Cache::forever('schedule.last.backup', time()));
Schedule::command('indexer:health')->everyFifteenMinutes()->withoutOverlapping(30)
    ->after(fn () => Cache::forever('schedule.last.health', time()));
if (config('indexer.manticore.enabled')) {
    Schedule::command('indexer:search-index')->weeklyOn(1, '05:00')->timezone(config('indexer.timezone'))
    ->after(fn () => Cache::forever('schedule.last.search-index', time()));
}

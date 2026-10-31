<?php
namespace Tests\Unit;

use App\Services\Classifier;
use PHPUnit\Framework\TestCase;

class ClassifierTest extends TestCase
{
    public function test_ok(): void
    {
        $this->assertSame('ok', Classifier::status('Show.S01E01', ['a.rar', 'a.par2'], 5_000_000, false, 1_048_576));
    }

    public function test_blacklist_and_words(): void
    {
        $this->assertSame('spam', Classifier::status('Show.S01E01', ['a.rar'], 5_000_000, true));
        $this->assertSame('spam', Classifier::status('Cheap VIAGRA here', ['a.rar'], 5_000_000, false, 0, ['viagra']));
    }

    public function test_executables_only_is_spam(): void
    {
        $this->assertSame('spam', Classifier::status('Movie.2020.1080p', ['movie.exe', 'x.nfo'], 5_000_000));
        $this->assertSame('ok', Classifier::status('Game', ['setup.exe', 'data.rar'], 5_000_000));
    }

    public function test_too_small_is_spam(): void
    {
        $this->assertSame('spam', Classifier::status('Tiny', ['a.rar'], 1000, false, 1_048_576));
    }

    public function test_password_names(): void
    {
        $this->assertSame('passworded', Classifier::status('Show.S01E01 password protected', ['a.rar'], 5_000_000));
        $this->assertSame('passworded', Classifier::status('Show.S01E01 {{secret}}', ['a.rar'], 5_000_000));
        $this->assertSame('ok', Classifier::status('Bypass.Surgery.Documentary', ['a.mkv'], 5_000_000));
    }
}

<?php
namespace Tests\Unit;

use App\Services\Obfuscation;
use PHPUnit\Framework\TestCase;

class ObfuscationTest extends TestCase
{
    public function test_detects_random_names(): void
    {
        foreach (['ab12cd34ef56ab78cd90', '2f9a7c1b0d4e8f63a5b7c9d1e3f50a2b', 'Xk29fJq81LmZp03vBn.part01'] as $n) {
            $this->assertTrue(Obfuscation::looks($n), $n);
        }
    }

    public function test_keeps_normal_names(): void
    {
        foreach (['Show.Name.S01E02.1080p.WEB-GRP', 'short', 'Some Movie 2020', 'Artist_-_Album_2020'] as $n) {
            $this->assertFalse(Obfuscation::looks($n), $n);
        }
    }
}

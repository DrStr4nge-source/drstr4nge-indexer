<?php
namespace Tests\Unit;

use App\Services\CoverImage;
use PHPUnit\Framework\TestCase;

class CoverImageTest extends TestCase
{
    private function jpeg(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 40, 40));
        ob_start();
        imagejpeg($im);
        return (string) ob_get_clean();
    }

    public function test_converts_and_shrinks_to_webp(): void
    {
        if (!CoverImage::supported()) {
            $this->markTestSkipped('GD ohne WebP');
        }
        $webp = CoverImage::toWebp($this->jpeg(600, 900), 342, 80);
        $this->assertSame('RIFF', substr($webp, 0, 4));
        $this->assertSame('WEBP', substr($webp, 8, 4));
        $size = getimagesizefromstring($webp);
        $this->assertSame(342, $size[0]);
        $this->assertSame(513, $size[1]);
    }

    public function test_small_image_is_not_enlarged(): void
    {
        if (!CoverImage::supported()) {
            $this->markTestSkipped('GD ohne WebP');
        }
        $this->assertSame(200, getimagesizefromstring(CoverImage::toWebp($this->jpeg(200, 300)))[0]);
    }

    public function test_garbage_returns_null(): void
    {
        $this->assertNull(CoverImage::toWebp('das ist kein bild'));
        $this->assertNull(CoverImage::toWebp(''));
    }
}

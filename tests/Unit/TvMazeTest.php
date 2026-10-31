<?php

namespace Tests\Unit;

use App\Services\TvMaze;
use PHPUnit\Framework\TestCase;

class TvMazeTest extends TestCase
{
    public function test_ids_uebernehmen(): void
    {
        $r = TvMaze::map(['id' => 82, 'name' => 'Game of Thrones', 'premiered' => '2011-04-17', 'externals' => ['tvrage' => 24493, 'thetvdb' => 121361, 'imdb' => 'tt0944947']]);
        $this->assertSame(['tvmaze_id' => 82, 'tvdb_id' => 121361, 'imdb_id' => 'tt0944947', 'year' => 2011, 'title' => 'Game of Thrones'], $r);
        $this->assertNull(TvMaze::map(['id' => 1, 'externals' => ['imdb' => null]])['imdb_id']);
    }

    public function test_namenssuche_nur_bei_klarem_treffer(): void
    {
        $s = ['name' => 'Dark', 'premiered' => '2017-12-01'];
        $this->assertTrue(TvMaze::sameShow($s, 'Dark', 2017));
        $this->assertFalse(TvMaze::sameShow($s, 'Dark', 2010));          // anderes Jahr
        $this->assertFalse(TvMaze::sameShow($s, 'Dark Matter', 2017));   // anderer Titel
        $this->assertTrue(TvMaze::sameShow(['name' => 'Haus des Geldes', 'premiered' => '2017-05-02'], 'Haus des Geldes', null));
    }
}

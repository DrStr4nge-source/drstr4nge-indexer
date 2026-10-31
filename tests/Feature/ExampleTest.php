<?php
namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Ersetzt den Laravel-Beispieltest: die Startseite leitet zur Übersicht weiter. */
    public function test_home_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_pages_require_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/browse')->assertRedirect('/login');
    }
}

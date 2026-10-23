<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Halaman utama backend sengaja dialihkan ke /admin (routes/web.php). */
    public function test_halaman_utama_dialihkan_ke_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}

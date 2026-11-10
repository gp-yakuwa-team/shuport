<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    /**
     * トップページ (開発環境の動作確認画面) が表示されることを確認する。
     */
    public function test_welcome_page_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('shuport');
    }
}

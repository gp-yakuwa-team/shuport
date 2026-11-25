<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    /**
     * トップページがログイン画面（No.1）へリダイレクトされることを確認する。
     */
    public function test_top_page_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    /**
     * DB 疎通確認画面（開発用）が /dev-check で表示されることを確認する。
     */
    public function test_dev_check_page_returns_a_successful_response(): void
    {
        $response = $this->get('/dev-check');

        $response->assertStatus(200);
        $response->assertSee('shuport');
    }
}

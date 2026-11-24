<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class DeleteEndpointsTest extends TestCase
{
    /**
     * API-41（企業削除）の受け口があり、未認証では 401 になる（404 ではない）。
     */
    public function test_company_delete_endpoint_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/companies/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(401);
    }

    /**
     * API-42（タスク削除）の受け口があり、未認証では 401 になる（404 ではない）。
     */
    public function test_task_delete_endpoint_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/tasks/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(401);
    }
}

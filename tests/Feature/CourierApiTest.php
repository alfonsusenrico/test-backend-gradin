<?php

namespace Tests\Feature;

use App\Models\Courier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CourierApiTest extends TestCase
{
    use RefreshDatabase;

    private int $phoneSeed = 8100000000;

    private function logStep(string $message): void
    {
        fwrite(STDOUT, $message.PHP_EOL);
    }

    private function logSection(string $title): void
    {
        $this->logStep(PHP_EOL.'=== '.$title.' ===');
    }

    private function logData(string $label, array $data): void
    {
        if (array_key_exists('trace', $data)) {
            unset($data['trace'], $data['file'], $data['line'], $data['exception']);
        }

        $this->logStep($label.PHP_EOL.json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function logIndexResponse(string $label, TestResponse $response): void
    {
        $this->logStep($label);
        $this->logData('Meta:', (array) $response->json('meta'));

        $items = $response->json('data') ?? [];
        $summary = array_map(static fn (array $item) => [
            'id' => $item['id'] ?? null,
            'name' => $item['name'] ?? null,
            'level' => $item['level'] ?? null,
            'registered_at' => $item['registered_at'] ?? null,
        ], $items);

        $this->logData('Items:', $summary);
    }

    private function makeCourier(array $overrides = []): Courier
    {
        $this->phoneSeed++;

        return Courier::create(array_merge([
            'name' => 'Courier '.$this->phoneSeed,
            'phone' => (string) $this->phoneSeed,
            'email' => null,
            'level' => 2,
            'status' => 'active',
            'registered_at' => now(),
        ], $overrides));
    }

    public function test_index_paginates_results(): void
    {
        $this->logSection('Index Endpoint Tests');
        $this->logStep('Preparing data for pagination test.');
        for ($i = 0; $i < 25; $i++) {
            $this->makeCourier(['name' => 'Courier '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $response = $this->getJson('/api/couriers?per_page=10');
        $response->assertOk();

        $this->logIndexResponse('Index response for pagination (GET /api/couriers?per_page=10).', $response);

        $response->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.count', 10)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 10)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.total_pages', 3);
    }

    public function test_index_default_sort_by_name(): void
    {
        $this->logSection('Index Endpoint Tests');
        $this->makeCourier(['name' => 'Charlie']);
        $this->makeCourier(['name' => 'Alpha']);
        $this->makeCourier(['name' => 'Bravo']);

        $response = $this->getJson('/api/couriers?per_page=10');
        $response->assertOk();

        $this->logIndexResponse('Index response default sort (GET /api/couriers).', $response);

        $response->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.1.name', 'Bravo')
            ->assertJsonPath('data.2.name', 'Charlie');
    }

    public function test_index_sort_by_registered_at_override(): void
    {
        $this->logSection('Index Endpoint Tests');
        $this->makeCourier([
            'name' => 'Oldest',
            'registered_at' => now()->subDays(3),
        ]);
        $this->makeCourier([
            'name' => 'Middle',
            'registered_at' => now()->subDays(2),
        ]);
        $this->makeCourier([
            'name' => 'Newest',
            'registered_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/couriers?sort=registered_at&direction=desc&per_page=10');
        $response->assertOk();

        $this->logIndexResponse(
            'Index response sort registered_at desc (GET /api/couriers?sort=registered_at&direction=desc).',
            $response
        );

        $response->assertJsonPath('data.0.name', 'Newest')
            ->assertJsonPath('data.1.name', 'Middle')
            ->assertJsonPath('data.2.name', 'Oldest');
    }

    public function test_index_search_matches_all_terms(): void
    {
        $this->logSection('Index Endpoint Tests');
        $this->makeCourier(['name' => 'Budiono Hadi Agung']);
        $this->makeCourier(['name' => 'Budiono Hadi']);
        $this->makeCourier(['name' => 'Agung Pratama']);

        $response = $this->getJson('/api/couriers?search=budi+agung&per_page=10');
        $response->assertOk();

        $this->logIndexResponse('Index response search (GET /api/couriers?search=budi+agung).', $response);

        $response->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.name', 'Budiono Hadi Agung');
    }

    public function test_index_filter_level_two_or_three(): void
    {
        $this->logSection('Index Endpoint Tests');
        $this->makeCourier(['name' => 'Level 1', 'level' => 1]);
        $this->makeCourier(['name' => 'Level 2', 'level' => 2]);
        $this->makeCourier(['name' => 'Level 3', 'level' => 3]);
        $this->makeCourier(['name' => 'Level 4', 'level' => 4]);

        $response = $this->getJson('/api/couriers?level=2,3&per_page=10');
        $response->assertOk();

        $this->logIndexResponse('Index response filter level (GET /api/couriers?level=2,3).', $response);

        $levels = array_map(
            static fn (array $item) => $item['level'],
            $response->json('data') ?? []
        );

        $this->assertCount(2, $levels);
        foreach ($levels as $level) {
            $this->assertTrue(in_array($level, [2, 3], true));
        }
    }

    public function test_store_creates_courier(): void
    {
        $this->logSection('Store Endpoint Tests');
        $payload = [
            'name' => 'Budi Agung',
            'phone' => '08123456789',
            'email' => 'budi@example.com',
            'level' => 3,
            'status' => 'active',
            'registered_at' => now()->toDateTimeString(),
        ];

        $this->logStep('Running create dummy courier data (POST /api/couriers).');
        $this->logData('Create payload:', $payload);
        $response = $this->postJson('/api/couriers', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Budi Agung',
                'level' => 3,
                'status' => 'active',
            ]);

        $createdId = (int) $response->json('id');
        $this->logStep('Created data ID: '.$createdId);
        $this->logStep('Running show data after create (GET /api/couriers/'.$createdId.').');
        $showResponse = $this->getJson('/api/couriers/'.$createdId);
        $showResponse->assertOk();
        $this->logData('Show response:', $showResponse->json());
        $this->assertDatabaseHas('couriers', [
            'name' => 'Budi Agung',
            'phone' => '08123456789',
            'email' => 'budi@example.com',
            'level' => 3,
            'status' => 'active',
        ]);
    }

    public function test_store_requires_name_and_level(): void
    {
        $this->logSection('Store Endpoint Tests');
        $this->logStep('Running create with empty payload (POST /api/couriers).');
        $response = $this->postJson('/api/couriers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'level']);

        $this->logData('Validation errors:', $response->json());
    }

    public function test_update_updates_courier(): void
    {
        $this->logSection('Update Endpoint Tests');
        $courier = Courier::create([
            'name' => 'Sari Utami',
            'phone' => '0811111111',
            'email' => 'sari@example.com',
            'level' => 2,
            'status' => 'active',
            'registered_at' => now()->subDay(),
        ]);

        $payload = [
            'name' => 'Sari Utami Updated',
            'phone' => '0822222222',
            'email' => 'sari.updated@example.com',
            'level' => 3,
            'status' => 'inactive',
            'registered_at' => now()->toDateTimeString(),
        ];

        $this->logStep('Running show data before update (GET /api/couriers/'.$courier->id.').');
        $beforeResponse = $this->getJson('/api/couriers/'.$courier->id);
        $beforeResponse->assertOk();
        $this->logData('Before update data:', $beforeResponse->json());
        $this->logData('Update payload:', $payload);
        $this->logStep('Running update data (PUT /api/couriers/'.$courier->id.').');
        $response = $this->putJson('/api/couriers/'.$courier->id, $payload);

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $courier->id,
                'name' => 'Sari Utami Updated',
                'level' => 3,
                'status' => 'inactive',
            ]);

        $this->logStep('Running show data after update (GET /api/couriers/'.$courier->id.').');
        $afterResponse = $this->getJson('/api/couriers/'.$courier->id);
        $afterResponse->assertOk();
        $this->logData('After update data:', $afterResponse->json());
        $this->logStep('Updated data ID: '.$courier->id);
        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'name' => 'Sari Utami Updated',
            'phone' => '0822222222',
            'email' => 'sari.updated@example.com',
            'level' => 3,
            'status' => 'inactive',
        ]);
    }

    public function test_destroy_deletes_courier(): void
    {
        $this->logSection('Destroy Endpoint Tests');
        $courier = Courier::create([
            'name' => 'Doni Pratama',
            'phone' => '0833333333',
            'email' => 'doni@example.com',
            'level' => 2,
            'status' => 'active',
            'registered_at' => now()->subDays(2),
        ]);

        $this->logStep('Running show data (GET /api/couriers/'.$courier->id.').');
        $showResponse = $this->getJson('/api/couriers/'.$courier->id);
        $showResponse->assertOk();
        $this->logData('Show response:', $showResponse->json());

        $this->logStep('Running destroy data (DELETE /api/couriers/'.$courier->id.').');
        $this->deleteJson('/api/couriers/'.$courier->id)->assertNoContent();

        $this->logStep('Running show after delete (GET /api/couriers/'.$courier->id.').');
        $deletedShow = $this->getJson('/api/couriers/'.$courier->id);
        $deletedShow->assertNotFound();
        $this->logData('Show after delete response:', $deletedShow->json());

        $this->logStep('Verified data removed from database (ID: '.$courier->id.').');
        $this->assertDatabaseMissing('couriers', [
            'id' => $courier->id,
        ]);
    }

    public function test_rate_limit_exceeded_returns_429(): void
    {
        $this->logSection('Rate Limit Tests');
        $this->makeCourier(['name' => 'Rate Limit Courier']);

        $this->logStep('Running 6 rapid requests to trigger rate limit (GET /api/couriers).');
        for ($i = 1; $i <= 5; $i++) {
            $this->getJson('/api/couriers')->assertOk();
        }

        $response = $this->getJson('/api/couriers');
        $response->assertStatus(429);

        $this->logData('Rate limit response:', $response->json());
    }
}

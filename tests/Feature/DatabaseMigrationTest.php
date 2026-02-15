<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('metrics'),
            'Metrics table does not exist'
        );
    }

    public function test_metrics_table_has_correct_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('metrics', [
                'id', 'name', 'type', 'created_at', 'updated_at'
            ]),
            'Metrics table is missing expected columns'
        );
    }

    public function test_metric_points_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('metric_points'),
            'Metric points table does not exist'
        );
    }

    public function test_metric_points_table_has_correct_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('metric_points', [
                'id', 'metric_id', 'value', 'recorded_at', 'created_at'
            ]),
            'Metric points table is missing expected columns'
        );
    }

    public function test_api_keys_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('api_keys'),
            'API keys table does not exist'
        );
    }

    public function test_api_keys_table_has_correct_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('api_keys', [
                'id', 'name', 'key', 'rate_limit', 'created_at', 'updated_at'
            ]),
            'API keys table is missing expected columns'
        );
    }

    public function test_foreign_key_constraint_exists(): void
    {
        // Test that we can't insert a metric_point without a valid metric_id
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        \DB::table('metric_points')->insert([
            'metric_id' => 99999,
            'value' => 100,
            'recorded_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_can_insert_valid_data(): void
    {
        $metricId = \DB::table('metrics')->insertGetId([
            'name' => 'test.metric',
            'type' => 'counter',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pointId = \DB::table('metric_points')->insertGetId([
            'metric_id' => $metricId,
            'value' => 42.5,
            'recorded_at' => now(),
            'created_at' => now(),
        ]);

        $apiKeyId = \DB::table('api_keys')->insertGetId([
            'name' => 'Test Key',
            'key' => hash('sha256', 'test-key'),
            'rate_limit' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('metrics', ['id' => $metricId]);
        $this->assertDatabaseHas('metric_points', ['id' => $pointId]);
        $this->assertDatabaseHas('api_keys', ['id' => $apiKeyId]);
    }

    public function test_cascade_delete_works(): void
    {
        $metricId = \DB::table('metrics')->insertGetId([
            'name' => 'test.cascade',
            'type' => 'value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('metric_points')->insert([
            'metric_id' => $metricId,
            'value' => 100,
            'recorded_at' => now(),
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('metric_points', ['metric_id' => $metricId]);

        \DB::table('metrics')->where('id', $metricId)->delete();

        $this->assertDatabaseMissing('metric_points', ['metric_id' => $metricId]);
    }
}

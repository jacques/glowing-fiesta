# Testing Guide

## Overview

This document outlines the testing strategy and requirements for Glowing Fiesta. The application requires comprehensive testing at unit, feature, and load testing levels.

## Testing Stack

- **Framework:** PHPUnit (Laravel's default)
- **Factory:** Laravel Factories for test data
- **Database:** In-memory SQLite for fast tests
- **HTTP Testing:** Laravel HTTP testing utilities
- **Load Testing:** Apache Bench or K6

## Test Environment Setup

### Installation

```bash
# Install dev dependencies (if not already installed)
composer install

# Copy test environment file
cp .env.testing.example .env.testing
```

### Configuration

`.env.testing` should use SQLite in-memory database:

```bash
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
QUEUE_CONNECTION=sync
CACHE_DRIVER=array
SESSION_DRIVER=array
```

## Running Tests

### Run All Tests

```bash
# Run complete test suite
php artisan test

# With coverage report
php artisan test --coverage

# Parallel testing (faster)
php artisan test --parallel
```

### Run Specific Test Suites

```bash
# Unit tests only
php artisan test --testsuite=Unit

# Feature tests only
php artisan test --testsuite=Feature

# Specific test file
php artisan test tests/Feature/MetricIngestionTest.php

# Specific test method
php artisan test --filter testCounterMetricCanBeCreated
```

## Test Structure

```
tests/
├── Unit/
│   ├── Models/
│   │   ├── MetricTest.php
│   │   └── MetricPointTest.php
│   ├── Services/
│   │   ├── MetricIngestionServiceTest.php
│   │   └── MetricAggregationServiceTest.php
│   └── Validation/
│       └── MetricValidationTest.php
├── Feature/
│   ├── Api/
│   │   ├── CounterEndpointTest.php
│   │   ├── ValueEndpointTest.php
│   │   ├── BatchEndpointTest.php
│   │   └── AggregationEndpointTest.php
│   ├── Authentication/
│   │   └── ApiKeyAuthenticationTest.php
│   └── Jobs/
│       └── RetentionCleanupTest.php
└── LoadTests/
    └── ingestion_load_test.js
```

## Unit Tests

### Metric Creation Logic

**File:** `tests/Unit/Models/MetricTest.php`

**Test Cases:**
- Metric can be created with valid data
- Metric name is unique
- Metric type is immutable after creation
- Counter and value types are properly distinguished

**Example:**

```php
<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Metric;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_can_be_created()
    {
        $metric = Metric::create([
            'name' => 'test.metric',
            'type' => 'counter',
        ]);

        $this->assertDatabaseHas('metrics', [
            'name' => 'test.metric',
            'type' => 'counter',
        ]);
    }

    public function test_metric_name_must_be_unique()
    {
        Metric::create(['name' => 'test.metric', 'type' => 'counter']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Metric::create(['name' => 'test.metric', 'type' => 'value']);
    }

    public function test_metric_type_cannot_be_changed()
    {
        $metric = Metric::create(['name' => 'test.metric', 'type' => 'counter']);

        $this->expectException(\Exception::class);
        $metric->update(['type' => 'value']);
    }
}
```

### Type Enforcement

**File:** `tests/Unit/Services/MetricIngestionServiceTest.php`

**Test Cases:**
- Reject counter value when metric is type 'value'
- Reject value metric when metric is type 'counter'
- Type consistency is enforced across requests

### Aggregation Math Correctness

**File:** `tests/Unit/Services/MetricAggregationServiceTest.php`

**Test Cases:**
- Sum aggregation is correct
- Average aggregation is correct
- Min/Max aggregations are correct
- Count aggregation is correct
- Time bucket grouping is accurate
- Empty results handled properly

**Example:**

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Metric;
use App\Models\MetricPoint;
use App\Services\MetricAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MetricAggregationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sum_aggregation_is_correct()
    {
        $metric = Metric::factory()->create(['type' => 'counter']);
        
        MetricPoint::create([
            'metric_id' => $metric->id,
            'value' => 10,
            'recorded_at' => now()->startOfHour(),
        ]);
        
        MetricPoint::create([
            'metric_id' => $metric->id,
            'value' => 20,
            'recorded_at' => now()->startOfHour(),
        ]);

        $service = new MetricAggregationService();
        $result = $service->aggregate($metric, now()->subHour(), now(), 'hour', 'sum');

        $this->assertEquals(30, $result[0]['value']);
    }

    public function test_average_aggregation_is_correct()
    {
        $metric = Metric::factory()->create(['type' => 'value']);
        
        MetricPoint::create([
            'metric_id' => $metric->id,
            'value' => 100,
            'recorded_at' => now(),
        ]);
        
        MetricPoint::create([
            'metric_id' => $metric->id,
            'value' => 200,
            'recorded_at' => now(),
        ]);

        $service = new MetricAggregationService();
        $result = $service->aggregate($metric, now()->subHour(), now(), 'hour', 'avg');

        $this->assertEquals(150, $result[0]['value']);
    }
}
```

## Feature Tests

### API Key Enforcement

**File:** `tests/Feature/Authentication/ApiKeyAuthenticationTest.php`

**Test Cases:**
- Requests without API key are rejected (401)
- Requests with invalid API key are rejected (401)
- Requests with valid API key are accepted
- Rate limiting is enforced per API key

**Example:**

```php
<?php

namespace Tests\Feature\Authentication;

use Tests\TestCase;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiKeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_api_key_is_rejected()
    {
        $response = $this->postJson('/api/metrics/counter', [
            'metric' => 'test.metric',
            'value' => 1,
        ]);

        $response->assertStatus(401)
                 ->assertJson(['code' => 'UNAUTHORIZED']);
    }

    public function test_request_with_invalid_api_key_is_rejected()
    {
        $response = $this->withHeader('X-API-Key', 'invalid-key')
                         ->postJson('/api/metrics/counter', [
                             'metric' => 'test.metric',
                             'value' => 1,
                         ]);

        $response->assertStatus(401);
    }

    public function test_request_with_valid_api_key_is_accepted()
    {
        $apiKey = ApiKey::factory()->create();

        $response = $this->withHeader('X-API-Key', $apiKey->key)
                         ->postJson('/api/metrics/counter', [
                             'metric' => 'test.metric',
                             'value' => 1,
                         ]);

        $response->assertStatus(201);
    }
}
```

### Batch Ingestion

**File:** `tests/Feature/Api/BatchEndpointTest.php`

**Test Cases:**
- Batch accepts up to 1000 points
- Batch rejects more than 1000 points
- Entire batch is rejected if any validation fails
- Mixed counter and value types in same batch
- Successful batch returns correct response

**Example:**

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BatchEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_accepts_multiple_metrics()
    {
        $apiKey = ApiKey::factory()->create();

        $response = $this->withHeader('X-API-Key', $apiKey->key)
                         ->postJson('/api/metrics/batch', [
                             'points' => [
                                 [
                                     'metric' => 'metric1',
                                     'type' => 'counter',
                                     'value' => 1
                                 ],
                                 [
                                     'metric' => 'metric2',
                                     'type' => 'value',
                                     'value' => 99.5
                                 ],
                             ]
                         ]);

        $response->assertStatus(201)
                 ->assertJson(['processed' => 2]);

        $this->assertDatabaseCount('metric_points', 2);
    }

    public function test_batch_rejects_more_than_1000_points()
    {
        $apiKey = ApiKey::factory()->create();
        
        $points = [];
        for ($i = 0; $i < 1001; $i++) {
            $points[] = [
                'metric' => "metric{$i}",
                'type' => 'counter',
                'value' => 1
            ];
        }

        $response = $this->withHeader('X-API-Key', $apiKey->key)
                         ->postJson('/api/metrics/batch', [
                             'points' => $points
                         ]);

        $response->assertStatus(400)
                 ->assertJson(['code' => 'VALIDATION_ERROR']);
    }

    public function test_entire_batch_rejected_on_validation_failure()
    {
        $apiKey = ApiKey::factory()->create();

        $response = $this->withHeader('X-API-Key', $apiKey->key)
                         ->postJson('/api/metrics/batch', [
                             'points' => [
                                 ['metric' => 'valid', 'type' => 'counter', 'value' => 1],
                                 ['metric' => '', 'type' => 'counter', 'value' => 1], // Invalid
                             ]
                         ]);

        $response->assertStatus(400);
        $this->assertDatabaseCount('metric_points', 0); // No points saved
    }
}
```

### Time Bucket Grouping Accuracy

**File:** `tests/Feature/Api/AggregationEndpointTest.php`

**Test Cases:**
- Hour buckets group correctly
- Day buckets group correctly
- Minute buckets group correctly
- Data spanning multiple buckets is correctly separated
- Empty buckets are handled properly

**Example:**

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\ApiKey;
use App\Models\Metric;
use App\Models\MetricPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AggregationEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_hourly_buckets_group_correctly()
    {
        $apiKey = ApiKey::factory()->create();
        $metric = Metric::factory()->create(['type' => 'counter']);

        // Create points in different hours
        MetricPoint::create([
            'metric_id' => $metric->id,
            'value' => 10,
            'recorded_at' => '2024-02-15 10:15:00',
        ]);

        MetricPoint::create([
            'metric_id' => $metric->id,
            'value' => 20,
            'recorded_at' => '2024-02-15 10:45:00',
        ]);

        MetricPoint::create([
            'metric_id' => $metric->id,
            'value' => 30,
            'recorded_at' => '2024-02-15 11:15:00',
        ]);

        $response = $this->withHeader('X-API-Key', $apiKey->key)
                         ->getJson("/api/metrics/{$metric->name}?" . http_build_query([
                             'from' => '2024-02-15T10:00:00Z',
                             'to' => '2024-02-15T12:00:00Z',
                             'interval' => 'hour',
                             'aggregation' => 'sum',
                         ]));

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertEquals(30, $data[0]['value']); // 10 + 20 in hour 10
        $this->assertEquals(30, $data[1]['value']); // 30 in hour 11
    }
}
```

## Integration Tests

### Retention Job

**Test Cases:**
- Old data points are deleted
- Recent data points are preserved
- Deletion respects configured retention period

### Queue Processing

**Test Cases:**
- Metric ingestion jobs are queued
- Jobs process successfully
- Failed jobs are retried

## Load Testing

### Setup K6

```bash
# Install K6
curl https://github.com/grafana/k6/releases/download/v0.45.0/k6-v0.45.0-linux-amd64.tar.gz -L | tar xvz
sudo mv k6-v0.45.0-linux-amd64/k6 /usr/local/bin/
```

### Load Test Script

**File:** `tests/LoadTests/ingestion_load_test.js`

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '2m', target: 100 }, // Ramp up to 100 users
        { duration: '5m', target: 100 }, // Stay at 100 users
        { duration: '2m', target: 200 }, // Ramp up to 200 users
        { duration: '5m', target: 200 }, // Stay at 200 users
        { duration: '2m', target: 0 },   // Ramp down
    ],
    thresholds: {
        http_req_duration: ['p(95)<200'], // 95% of requests under 200ms
        http_req_failed: ['rate<0.01'],   // Less than 1% errors
    },
};

const BASE_URL = 'http://localhost:8000';
const API_KEY = 'your-test-api-key';

export default function () {
    // Test counter endpoint
    let counterRes = http.post(
        `${BASE_URL}/api/metrics/counter`,
        JSON.stringify({
            metric: `load.test.counter.${__VU}`,
            value: 1,
        }),
        {
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': API_KEY,
            },
        }
    );

    check(counterRes, {
        'counter status is 201': (r) => r.status === 201,
        'counter response time < 200ms': (r) => r.timings.duration < 200,
    });

    // Test value endpoint
    let valueRes = http.post(
        `${BASE_URL}/api/metrics/value`,
        JSON.stringify({
            metric: `load.test.value.${__VU}`,
            value: Math.random() * 100,
        }),
        {
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': API_KEY,
            },
        }
    );

    check(valueRes, {
        'value status is 201': (r) => r.status === 201,
        'value response time < 200ms': (r) => r.timings.duration < 200,
    });

    // Test batch endpoint
    let batchRes = http.post(
        `${BASE_URL}/api/metrics/batch`,
        JSON.stringify({
            points: [
                { metric: `load.test.batch1.${__VU}`, type: 'counter', value: 1 },
                { metric: `load.test.batch2.${__VU}`, type: 'value', value: 50.5 },
                { metric: `load.test.batch3.${__VU}`, type: 'counter', value: 3 },
            ],
        }),
        {
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': API_KEY,
            },
        }
    );

    check(batchRes, {
        'batch status is 201': (r) => r.status === 201,
        'batch response time < 200ms': (r) => r.timings.duration < 200,
    });

    sleep(1);
}
```

### Running Load Tests

```bash
# Run load test
k6 run tests/LoadTests/ingestion_load_test.js

# Run with custom duration
k6 run --duration 10m tests/LoadTests/ingestion_load_test.js

# Generate HTML report
k6 run --out json=test_results.json tests/LoadTests/ingestion_load_test.js
```

### Expected Results

**Target Performance:**
- **Throughput:** 10,000 writes/minute sustained
- **p95 Latency:** < 200ms
- **Error Rate:** < 1%
- **Success Rate:** > 99%

## Test Data Factories

### Metric Factory

**File:** `database/factories/MetricFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Metric;
use Illuminate\Database\Eloquent\Factories\Factory;

class MetricFactory extends Factory
{
    protected $model = Metric::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word . '.' . $this->faker->word,
            'type' => $this->faker->randomElement(['counter', 'value']),
        ];
    }

    public function counter()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'counter',
        ]);
    }

    public function value()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'value',
        ]);
    }
}
```

### API Key Factory

**File:** `database/factories/ApiKeyFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company . ' API Key',
            'key' => hash('sha256', Str::random(64)),
            'rate_limit' => 1000,
        ];
    }
}
```

## Continuous Integration

### GitHub Actions Example

**File:** `.github/workflows/tests.yml`

```yaml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

      redis:
        image: redis:6
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: pdo, mysql, redis

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run tests
        run: php artisan test --parallel

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        if: matrix.php-version == '8.2'
```

## Testing Checklist

Before each release:

- [ ] All unit tests pass
- [ ] All feature tests pass
- [ ] Load tests meet performance targets
- [ ] Code coverage > 80%
- [ ] Security vulnerabilities scanned
- [ ] Manual API testing completed
- [ ] Edge cases validated
- [ ] Error handling verified
- [ ] Documentation updated

## Best Practices

1. **Write Tests First:** TDD approach for new features
2. **Keep Tests Fast:** Use in-memory SQLite for unit/feature tests
3. **Isolate Tests:** Each test should be independent
4. **Use Factories:** Generate test data with factories
5. **Test Edge Cases:** Null values, empty arrays, boundary conditions
6. **Mock External Services:** Don't make real API calls in tests
7. **Clear Test Names:** Test name should describe what it tests
8. **Clean Database:** Use RefreshDatabase trait

## Troubleshooting

### Tests Failing Locally

```bash
# Clear cache
php artisan config:clear
php artisan cache:clear

# Recreate test database
php artisan migrate:fresh --env=testing

# Run single test with verbose output
php artisan test --filter testName -vvv
```

### Memory Issues

```bash
# Increase PHP memory limit
php -d memory_limit=512M artisan test
```

### Slow Tests

```bash
# Run tests in parallel
php artisan test --parallel

# Profile slow tests
php artisan test --profile
```

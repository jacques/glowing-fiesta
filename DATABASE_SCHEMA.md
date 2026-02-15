# Database Schema Implementation Summary

## Overview
This document provides a summary of the database schema implementation for the Glowing Fiesta metrics service.

## Implemented Tables

### 1. Metrics Table
**Purpose**: Stores metric definitions

**Schema**:
```sql
CREATE TABLE metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('counter', 'value') NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY unique_name (name),
    INDEX idx_type (type)
);
```

**Columns**:
- `id`: Auto-incrementing primary key
- `name`: Unique metric name (e.g., "api.requests", "db.query_time")
- `type`: Metric type - either "counter" (incremental) or "value" (arbitrary numeric)
- `created_at`, `updated_at`: Laravel timestamps

**Indexes**:
- Primary key on `id`
- Unique constraint on `name`
- Index on `type` for filtering by metric type

### 2. Metric Points Table
**Purpose**: Stores individual metric data points

**Schema**:
```sql
CREATE TABLE metric_points (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric_id BIGINT UNSIGNED NOT NULL,
    value DECIMAL(20,4) NOT NULL,
    recorded_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (metric_id) REFERENCES metrics(id) ON DELETE CASCADE,
    INDEX idx_metric_recorded (metric_id, recorded_at),
    INDEX idx_recorded_at (recorded_at)
);
```

**Columns**:
- `id`: Auto-incrementing primary key
- `metric_id`: Foreign key to metrics table
- `value`: Decimal value with high precision (20 digits, 4 decimal places)
- `recorded_at`: Timestamp when the metric was recorded
- `created_at`: Timestamp when the record was created in the database

**Indexes**:
- Primary key on `id`
- Compound index on `(metric_id, recorded_at)` for efficient time-series queries
- Index on `recorded_at` for time-based filtering and retention cleanup

**Foreign Keys**:
- `metric_id` references `metrics(id)` with CASCADE on delete

### 3. API Keys Table
**Purpose**: Stores API keys for authentication and rate limiting

**Schema**:
```sql
CREATE TABLE api_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    key VARCHAR(255) NOT NULL,
    rate_limit INT UNSIGNED NOT NULL DEFAULT 1000,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY unique_key (key),
    INDEX idx_name (name)
);
```

**Columns**:
- `id`: Auto-incrementing primary key
- `name`: Human-readable name for the API key
- `key`: The actual API key (should be hashed)
- `rate_limit`: Maximum requests per minute (default: 1000)
- `created_at`, `updated_at`: Laravel timestamps

**Indexes**:
- Primary key on `id`
- Unique constraint on `key`
- Index on `name` for lookups

## Migration Files

### Location
All migration files are located in `database/migrations/`

### Files Created
1. `2026_02_15_124529_create_metrics_table.php`
2. `2026_02_15_124533_create_metric_points_table.php`
3. `2026_02_15_124537_create_api_keys_table.php`

## Seeders

### ApiKeySeeder
**Location**: `database/seeders/ApiKeySeeder.php`

**Purpose**: Creates a sample API key for development environments only

**Features**:
- Only runs in non-production environments
- Creates one development API key with 10,000 requests/minute limit
- Key is hashed using SHA-256
- Provides informative output

**Usage**:
```bash
php artisan db:seed --class=ApiKeySeeder
```

## Testing

### Test Suite
Comprehensive test suite in `tests/Feature/DatabaseMigrationTest.php`

### Tests Include
1. ✓ Metrics table exists
2. ✓ Metrics table has correct columns
3. ✓ Metric points table exists
4. ✓ Metric points table has correct columns
5. ✓ API keys table exists
6. ✓ API keys table has correct columns
7. ✓ Foreign key constraint exists
8. ✓ Can insert valid data
9. ✓ Cascade delete works

### Running Tests
```bash
# Run all tests
php artisan test

# Run only migration tests
php artisan test --filter=DatabaseMigrationTest
```

## Database Commands

### Fresh Migration
```bash
php artisan migrate:fresh
```

### Run Migrations
```bash
php artisan migrate
```

### Rollback Migrations
```bash
# Rollback the last 3 migrations
php artisan migrate:rollback --step=3

# Rollback all migrations
php artisan migrate:rollback
```

### Seed Database
```bash
# Seed all
php artisan db:seed

# Seed specific seeder
php artisan db:seed --class=ApiKeySeeder
```

### Database Information
```bash
# Show database overview
php artisan db:show

# Show specific table structure
php artisan db:table metrics
php artisan db:table metric_points
php artisan db:table api_keys
```

## Design Decisions

### 1. Decimal Precision
- Chose `DECIMAL(20,4)` for metric values
- Provides high precision for various metric types
- Avoids floating-point precision issues
- Supports very large and very small numbers

### 2. Compound Index
- `(metric_id, recorded_at)` index on metric_points
- Optimized for common query pattern: "get points for a metric in a time range"
- Enables efficient time-series aggregations

### 3. Cascade Delete
- Metric points are deleted when parent metric is deleted
- Maintains referential integrity
- Prevents orphaned records

### 4. Separate recorded_at and created_at
- `recorded_at`: When the metric actually occurred
- `created_at`: When it was inserted into the database
- Allows historical data ingestion
- Useful for debugging and auditing

### 5. Rate Limit Default
- Default of 1000 requests/minute
- Reasonable for most use cases
- Configurable per key

### 6. Security Considerations
- API keys should be hashed (e.g., using bcrypt or SHA-256)
- Seeder only runs in non-production environments
- Unique constraints prevent duplicate keys

## Acceptance Criteria Status

✅ **All acceptance criteria met:**

- [x] Migrations create all three tables
- [x] Indexes on metric_points (metric_id, recorded_at)
- [x] Foreign key constraints properly configured
- [x] Up and down migrations tested
- [x] Seeder for sample API key (development only)

## Additional Deliverables

✅ **Business Requirements Document (BRD)**: `BRD.md`
✅ **Product Requirements Document (PRD)**: `PRD.md`
✅ **Comprehensive Test Suite**: `tests/Feature/DatabaseMigrationTest.php`

## Database Compatibility

The migrations are compatible with:
- MySQL 8.0+ (production)
- SQLite 3.x (development/testing)
- PostgreSQL 12+ (with minor adjustments)
- MariaDB 10.5+

## Next Steps

For full metrics service implementation, the following components are needed:
1. Eloquent models for metrics, metric_points, and api_keys
2. API endpoints for ingestion (counter, value, batch)
3. API endpoints for aggregation queries
4. Middleware for API key authentication
5. Rate limiting implementation
6. Queue workers for async processing
7. Retention policy cleanup job
8. Additional testing and documentation

See `PRD.md` and `BRD.md` for detailed requirements.

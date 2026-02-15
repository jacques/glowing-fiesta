# Architecture Documentation

## System Overview

Glowing Fiesta is an internal metrics service inspired by StatHat, designed to provide:
- High write throughput for metrics ingestion
- Deterministic aggregation for time-series data
- Simple operational footprint
- Horizontal scalability at the application layer
- Minimal external dependencies

**Technology Stack:**
- **Backend Framework:** Laravel (PHP 8.2)
- **Database:** MySQL 8.0
- **Queue System:** Redis (preferred) or Database queue
- **Web Server:** PHP-FPM with Nginx/Apache

## Architecture Components

### 1. Application Layer

The application layer is built on Laravel and is completely stateless, enabling horizontal scaling.

**Key Characteristics:**
- Stateless API application
- RESTful HTTP endpoints
- Rate limiting per API key
- Horizontally scalable
- Queue-based async processing

**Request Flow:**
```
Client → HTTP API → Validation → Queue (optional) → DB Write
Client → Aggregation API → Query Builder → Aggregated Response
```

### 2. Persistence Layer

MySQL 8.0 provides the persistence layer with a write-optimized schema.

**Key Characteristics:**
- Write-optimized table structures
- Time-bucketed indexes for efficient reads
- Optional date-based partitioning for large datasets
- Foreign key constraints for data integrity

**Schema Design:**
- `metrics` table: Stores metric definitions
- `metric_points` table: Stores time-series data points
- `api_keys` table: Stores authentication keys
- `metric_rollups_hourly` table: (Phase 2) Pre-aggregated data

### 3. Queue Layer

Laravel Queue system decouples ingestion from write pressure.

**Purpose:**
- Decouple HTTP response latency from database write operations
- Enable batch inserts for improved throughput
- Provide backpressure handling during traffic spikes

**Options:**
- **Recommended:** Redis queue driver
- **MVP Acceptable:** Database queue driver
- **Production:** Consider Laravel Horizon for monitoring

## Data Model

### metrics Table

Stores the definition of each metric.

```sql
CREATE TABLE metrics (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) UNIQUE NOT NULL,
    type ENUM('counter','value') NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY idx_name (name),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields:**
- `id`: Primary key
- `name`: Unique metric name (max 191 chars for utf8mb4 compatibility)
- `type`: Either 'counter' (incrementing) or 'value' (arbitrary numeric)
- `created_at`, `updated_at`: Standard Laravel timestamps

**Constraints:**
- Metric type is immutable once created
- Metric names must be unique
- Names are case-sensitive

### metric_points Table

Stores individual metric data points.

```sql
CREATE TABLE metric_points (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    metric_id BIGINT UNSIGNED NOT NULL,
    value DOUBLE NOT NULL,
    recorded_at DATETIME(6) NOT NULL,
    created_at TIMESTAMP NULL,
    
    INDEX idx_metric_time (metric_id, recorded_at),
    INDEX idx_recorded_at (recorded_at),
    
    FOREIGN KEY (metric_id) REFERENCES metrics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields:**
- `id`: Primary key
- `metric_id`: Foreign key to metrics table
- `value`: Numeric value (stored as DOUBLE for flexibility)
- `recorded_at`: Timestamp with microsecond precision
- `created_at`: When the record was inserted

**Indexes:**
- `idx_metric_time`: Composite index for time-range queries
- `idx_recorded_at`: Index for retention cleanup queries

### api_keys Table

Stores API authentication keys.

```sql
CREATE TABLE api_keys (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    key CHAR(64) UNIQUE NOT NULL,
    rate_limit INT NOT NULL DEFAULT 1000,
    created_at TIMESTAMP NULL,
    
    UNIQUE KEY idx_key (key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields:**
- `id`: Primary key
- `name`: Human-readable name for the key
- `key`: SHA-256 hashed API key
- `rate_limit`: Requests per minute allowed
- `created_at`: When the key was created

**Security:**
- Keys are stored as SHA-256 hashes
- Raw keys are never logged or stored

### metric_rollups_hourly Table (Phase 2)

Pre-aggregated hourly rollups for improved query performance.

```sql
CREATE TABLE metric_rollups_hourly (
    metric_id BIGINT UNSIGNED NOT NULL,
    bucket_start DATETIME NOT NULL,
    count BIGINT NOT NULL,
    sum DOUBLE NOT NULL,
    min DOUBLE NOT NULL,
    max DOUBLE NOT NULL,
    avg DOUBLE NOT NULL,
    
    PRIMARY KEY (metric_id, bucket_start),
    
    FOREIGN KEY (metric_id) REFERENCES metrics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Performance Optimization

### Write Optimization

**Strategies:**
1. **Batch Inserts:** Use `DB::insert()` for bulk operations
2. **Queue Processing:** Process writes asynchronously
3. **Disable Eloquent Events:** Use query builder for high-throughput writes
4. **Avoid N+1 Queries:** Use eager loading and bulk operations

**Expected Performance:**
- 5,000 - 20,000 writes/second on mid-tier hardware with batching
- Sub-200ms p95 latency for ingestion API

### Read Optimization

**Strategies:**
1. **Indexed Queries:** Leverage composite indexes
2. **Generated Columns:** For frequently accessed computed values
3. **Query Result Caching:** Cache aggregation results
4. **Read Replicas:** Optional for heavy read loads

**Generated Column Example:**
```sql
ALTER TABLE metric_points
ADD COLUMN bucket_hour DATETIME
GENERATED ALWAYS AS (DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00')) STORED,
ADD INDEX idx_bucket_hour (metric_id, bucket_hour);
```

### Partitioning Strategy

For large datasets, consider range partitioning by month:

```sql
ALTER TABLE metric_points
PARTITION BY RANGE (TO_DAYS(recorded_at)) (
    PARTITION p202401 VALUES LESS THAN (TO_DAYS('2024-02-01')),
    PARTITION p202402 VALUES LESS THAN (TO_DAYS('2024-03-01')),
    -- Add partitions as needed
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

**Benefits:**
- Faster range scans within partitions
- Efficient retention deletion via `DROP PARTITION`
- Reduced index size per partition

## Scalability Considerations

### Horizontal Scaling

The application is designed to scale horizontally:

1. **Stateless Application:** No session state stored in app servers
2. **Load Balancing:** Use standard HTTP load balancers
3. **Database Connection Pooling:** Configure appropriate pool sizes
4. **Queue Workers:** Scale worker count based on queue depth

### Database Scaling

1. **Vertical Scaling:** Start here - upgrade CPU, RAM, storage
2. **Read Replicas:** For read-heavy aggregation workloads
3. **Partitioning:** For very large datasets (100M+ rows)
4. **Sharding:** (Future) Shard by metric_id if needed

## High Availability

**Single Point of Failure Mitigation:**
- Database replication (primary-replica setup)
- Multiple application server instances
- Redis Sentinel for queue reliability
- Load balancer redundancy

## Monitoring and Observability

The service should emit its own metrics:

**Key Metrics:**
- `request_duration`: API response times
- `ingestion_failures`: Failed write operations
- `db_write_latency`: Database write performance
- `aggregation_latency`: Query performance
- `queue_depth`: Pending jobs in queue
- `retention_deleted`: Records cleaned by retention job

## Security Architecture

**Defense Layers:**
1. **Transport Security:** HTTPS required for all endpoints
2. **Authentication:** API key validation middleware
3. **Rate Limiting:** Per-key request throttling
4. **Input Validation:** Strict validation on all inputs
5. **SQL Injection Protection:** Parameterized queries only
6. **Request Size Limits:** Enforce maximum payload sizes

**Key Security Principles:**
- Never log API keys
- Use prepared statements exclusively
- Validate timestamp bounds (±7 days skew)
- Enforce metric type immutability

## Deployment Architecture

**Typical Production Setup:**
```
Internet → HTTPS Load Balancer → App Servers (N instances)
                                    ↓
                            Redis Queue ← Queue Workers (M instances)
                                    ↓
                            MySQL Primary ← Replica (optional)
```

**Container Deployment:**
- Stateless containers enable easy scaling
- Health check endpoints for load balancer
- Graceful shutdown handling for queue workers

## Future Enhancements

**Phase 2 Considerations:**
- Pre-aggregated rollup tables
- Real-time streaming capabilities
- Alerting system
- Multi-tenant RBAC
- Tag-based metric filtering
- Downsampling pipelines
- Metric metadata and descriptions

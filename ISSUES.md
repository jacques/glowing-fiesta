# Implementation Issues

This document outlines the individual GitHub issues that should be created to track the MVP implementation of Glowing Fiesta.

## Database & Schema Issues

### Issue #1: Database Schema Setup
**Title:** [MVP] Create database migrations for metrics, metric_points, and api_keys tables

**Description:**
Create Laravel migrations for the core database schema:
- `metrics` table with name and type fields
- `metric_points` table with metric_id, value, and recorded_at fields
- `api_keys` table with name, key, and rate_limit fields
- All necessary indexes and foreign keys

**Acceptance Criteria:**
- [ ] Migrations create all three tables
- [ ] Indexes on metric_points (metric_id, recorded_at)
- [ ] Foreign key constraints properly configured
- [ ] Up and down migrations tested
- [ ] Seeder for sample API key (development only)

**Labels:** MVP, database, high-priority

---

### Issue #2: Eloquent Models
**Title:** [MVP] Create Eloquent models for Metric, MetricPoint, and ApiKey

**Description:**
Implement Laravel Eloquent models with proper relationships and attribute casting.

**Acceptance Criteria:**
- [ ] Metric model with fillable attributes
- [ ] MetricPoint model with fillable attributes
- [ ] ApiKey model with key hashing
- [ ] Proper relationships defined
- [ ] Type casting for datetime and numeric fields
- [ ] Model factories for testing

**Labels:** MVP, models, high-priority

---

## Authentication Issues

### Issue #3: API Key Authentication Middleware
**Title:** [MVP] Implement API key authentication middleware

**Description:**
Create middleware to validate API keys on all protected routes.

**Acceptance Criteria:**
- [ ] Middleware validates X-API-Key header
- [ ] Returns 401 for missing or invalid keys
- [ ] Stores authenticated API key in request context
- [ ] Tests for authentication success and failure

**Labels:** MVP, authentication, security, high-priority

---

### Issue #4: Rate Limiting
**Title:** [MVP] Implement per-API-key rate limiting

**Description:**
Configure rate limiting based on API key rate_limit field.

**Acceptance Criteria:**
- [ ] Rate limiter checks per-key limits
- [ ] Returns 429 when limit exceeded
- [ ] Includes retry-after header
- [ ] Rate limit headers (X-RateLimit-*) included in responses
- [ ] Tests for rate limiting behavior

**Labels:** MVP, authentication, performance, medium-priority

---

### Issue #5: API Key Management Commands
**Title:** [MVP] Create Artisan commands for API key management

**Description:**
Implement commands to create, list, and revoke API keys.

**Acceptance Criteria:**
- [ ] `php artisan make:api-key {name} --rate-limit=N` command
- [ ] `php artisan list:api-keys` command
- [ ] `php artisan revoke:api-key {id}` command
- [ ] Keys are hashed before storage
- [ ] Raw key shown only once during creation

**Labels:** MVP, authentication, medium-priority

---

## Ingestion API Issues

### Issue #6: Counter Metric Endpoint
**Title:** [MVP] Implement POST /api/metrics/counter endpoint

**Description:**
Create endpoint for recording counter metrics.

**Acceptance Criteria:**
- [ ] Accepts metric name and optional value
- [ ] Defaults value to 1 if omitted
- [ ] Auto-creates metric if it doesn't exist
- [ ] Enforces type consistency
- [ ] Returns 201 with recorded data
- [ ] Returns 409 on type conflict
- [ ] Validation tests
- [ ] Feature tests for endpoint

**Labels:** MVP, api, ingestion, high-priority

---

### Issue #7: Value Metric Endpoint
**Title:** [MVP] Implement POST /api/metrics/value endpoint

**Description:**
Create endpoint for recording value metrics (arbitrary numeric values).

**Acceptance Criteria:**
- [ ] Accepts metric name and required value
- [ ] Auto-creates metric if it doesn't exist
- [ ] Enforces type consistency
- [ ] Returns 201 with recorded data
- [ ] Returns 409 on type conflict
- [ ] Validation tests
- [ ] Feature tests for endpoint

**Labels:** MVP, api, ingestion, high-priority

---

### Issue #8: Batch Ingestion Endpoint
**Title:** [MVP] Implement POST /api/metrics/batch endpoint

**Description:**
Create endpoint for bulk metric ingestion (up to 1000 points).

**Acceptance Criteria:**
- [ ] Accepts array of metric points
- [ ] Validates max 1000 points per request
- [ ] Atomic operation (all or nothing)
- [ ] Efficient bulk insert
- [ ] Returns count of processed points
- [ ] Validation for each point in batch
- [ ] Feature tests for batch scenarios

**Labels:** MVP, api, ingestion, performance, high-priority

---

### Issue #9: Timestamp Validation
**Title:** [MVP] Implement timestamp validation (±7 days skew)

**Description:**
Add validation to ensure timestamps are within acceptable range.

**Acceptance Criteria:**
- [ ] Rejects timestamps older than 7 days
- [ ] Rejects timestamps more than 7 days in future
- [ ] Defaults to current time if omitted
- [ ] Supports both Unix timestamps and ISO8601
- [ ] Clear error messages for invalid timestamps
- [ ] Unit tests for edge cases

**Labels:** MVP, validation, medium-priority

---

## Aggregation API Issues

### Issue #10: Aggregation Query Endpoint
**Title:** [MVP] Implement GET /api/metrics/{metric} endpoint

**Description:**
Create endpoint for querying aggregated metric data.

**Acceptance Criteria:**
- [ ] Accepts from/to date parameters
- [ ] Supports interval: minute, hour, day
- [ ] Supports aggregation: sum, avg, min, max, count
- [ ] Returns bucketed time-series data
- [ ] Returns 404 for non-existent metrics
- [ ] Efficient SQL queries with proper indexes
- [ ] Feature tests for aggregation accuracy

**Labels:** MVP, api, aggregation, high-priority

---

### Issue #11: Aggregation Service
**Title:** [MVP] Create MetricAggregationService for query logic

**Description:**
Implement service class to handle aggregation query building and execution.

**Acceptance Criteria:**
- [ ] Builds efficient SQL queries for each interval type
- [ ] Properly groups by time buckets
- [ ] Handles empty results gracefully
- [ ] Returns consistent data format
- [ ] Unit tests for aggregation math
- [ ] Performance tests for large datasets

**Labels:** MVP, service, aggregation, high-priority

---

### Issue #12: Time Bucket Optimization
**Title:** [MVP] Add generated column for bucket_hour optimization

**Description:**
Add generated column to metric_points for improved query performance.

**Acceptance Criteria:**
- [ ] Migration adds bucket_hour generated column
- [ ] Column indexed with metric_id
- [ ] Queries updated to use generated column
- [ ] Performance improvement measured
- [ ] Rollback tested

**Labels:** MVP, database, performance, medium-priority

---

## Queue Configuration Issues

### Issue #13: Queue Configuration
**Title:** [MVP] Configure Laravel Queue for async metric processing

**Description:**
Set up queue system for asynchronous metric ingestion.

**Acceptance Criteria:**
- [ ] Redis queue driver configured
- [ ] Fallback to database queue for development
- [ ] Queue worker supervisor configuration documented
- [ ] Failed job handling configured
- [ ] Batch insert job implemented
- [ ] Job tests

**Labels:** MVP, queue, performance, high-priority

---

### Issue #14: Batch Insert Job
**Title:** [MVP] Create ProcessMetricBatch job for bulk inserts

**Description:**
Implement queue job to process batches of metrics efficiently.

**Acceptance Criteria:**
- [ ] Job accepts array of metric points
- [ ] Uses DB::insert() for bulk operation
- [ ] Handles failures gracefully
- [ ] Retries on transient errors
- [ ] Logs processing metrics
- [ ] Unit tests for job logic

**Labels:** MVP, queue, jobs, high-priority

---

## Retention Policy Issues

### Issue #15: Retention Cleanup Command
**Title:** [MVP] Create retention cleanup scheduled job

**Description:**
Implement scheduled command to delete old metric points.

**Acceptance Criteria:**
- [ ] Command deletes points older than configured retention period
- [ ] Default retention: 90 days
- [ ] Configurable via environment variable
- [ ] Runs daily via Laravel scheduler
- [ ] Logs number of records deleted
- [ ] Tests for cleanup logic

**Labels:** MVP, maintenance, jobs, medium-priority

---

### Issue #16: Retention Configuration
**Title:** [MVP] Add retention policy configuration

**Description:**
Add configuration options for data retention.

**Acceptance Criteria:**
- [ ] RETENTION_DAYS environment variable
- [ ] Configuration file with defaults
- [ ] Validation for reasonable values
- [ ] Documentation in DEPLOYMENT.md

**Labels:** MVP, configuration, low-priority

---

## Testing Infrastructure Issues

### Issue #17: Unit Test Suite
**Title:** [MVP] Create comprehensive unit tests

**Description:**
Implement unit tests for models, services, and business logic.

**Acceptance Criteria:**
- [ ] Metric model tests
- [ ] MetricPoint model tests
- [ ] MetricIngestionService tests
- [ ] MetricAggregationService tests
- [ ] Validation tests
- [ ] 80%+ code coverage

**Labels:** MVP, testing, high-priority

---

### Issue #18: Feature Test Suite
**Title:** [MVP] Create comprehensive feature tests

**Description:**
Implement API endpoint integration tests.

**Acceptance Criteria:**
- [ ] Counter endpoint tests
- [ ] Value endpoint tests
- [ ] Batch endpoint tests
- [ ] Aggregation endpoint tests
- [ ] Authentication tests
- [ ] Rate limiting tests

**Labels:** MVP, testing, high-priority

---

### Issue #19: Load Testing Setup
**Title:** [MVP] Create load testing scripts and baseline

**Description:**
Set up load testing infrastructure and establish performance baselines.

**Acceptance Criteria:**
- [ ] K6 or Apache Bench scripts
- [ ] Test scenarios for ingestion endpoints
- [ ] Target: 10k writes/minute
- [ ] p95 latency < 200ms
- [ ] Documented in TESTING.md

**Labels:** MVP, testing, performance, medium-priority

---

## Security Issues

### Issue #20: Security Hardening
**Title:** [MVP] Implement security best practices

**Description:**
Ensure all security requirements are met.

**Acceptance Criteria:**
- [ ] HTTPS enforced (production)
- [ ] API keys never logged
- [ ] SQL injection protection verified
- [ ] Request size limits enforced
- [ ] Security headers configured
- [ ] CORS policy configured (if needed)
- [ ] Security audit checklist completed

**Labels:** MVP, security, high-priority

---

### Issue #21: Input Validation
**Title:** [MVP] Implement comprehensive input validation

**Description:**
Add validation for all API endpoints.

**Acceptance Criteria:**
- [ ] Metric name validation (max 191 chars)
- [ ] Value validation (numeric, reasonable bounds)
- [ ] Timestamp validation (±7 days)
- [ ] Batch size validation (max 1000)
- [ ] Consistent error response format
- [ ] Tests for edge cases

**Labels:** MVP, validation, security, high-priority

---

## Documentation Issues

### Issue #22: API Documentation Examples
**Title:** [MVP] Add code examples to API.md

**Description:**
Expand API documentation with client library examples.

**Acceptance Criteria:**
- [ ] PHP examples for all endpoints
- [ ] JavaScript examples
- [ ] Python examples
- [ ] cURL examples
- [ ] Error handling examples

**Labels:** MVP, documentation, low-priority

---

### Issue #23: Deployment Runbook
**Title:** [MVP] Create deployment runbook

**Description:**
Document step-by-step deployment process.

**Acceptance Criteria:**
- [ ] Initial deployment steps
- [ ] Update/rollback procedures
- [ ] Database migration process
- [ ] Queue worker management
- [ ] Troubleshooting guide
- [ ] Checklist for production readiness

**Labels:** MVP, documentation, medium-priority

---

## Performance Optimization Issues

### Issue #24: Database Connection Pooling
**Title:** [MVP] Optimize database connection pooling

**Description:**
Configure optimal database connection pool settings.

**Acceptance Criteria:**
- [ ] Connection pool sizing documented
- [ ] Timeout settings optimized
- [ ] Persistent connections configured (if appropriate)
- [ ] Connection monitoring setup
- [ ] Documented in DEPLOYMENT.md

**Labels:** MVP, performance, database, low-priority

---

### Issue #25: Query Optimization
**Title:** [MVP] Optimize aggregation queries

**Description:**
Ensure aggregation queries are using indexes efficiently.

**Acceptance Criteria:**
- [ ] EXPLAIN queries analyzed
- [ ] Indexes verified
- [ ] Query execution time measured
- [ ] Slow query log configured
- [ ] Optimization documented

**Labels:** MVP, performance, database, medium-priority

---

## Observability Issues

### Issue #26: Logging Configuration
**Title:** [MVP] Configure structured logging

**Description:**
Set up logging for debugging and monitoring.

**Acceptance Criteria:**
- [ ] Structured log format (JSON)
- [ ] Log levels configured appropriately
- [ ] Request logging (excluding API keys)
- [ ] Error logging with context
- [ ] Log rotation configured

**Labels:** MVP, observability, medium-priority

---

### Issue #27: Service Metrics
**Title:** [MVP] Implement self-monitoring metrics

**Description:**
Configure the service to emit its own operational metrics.

**Acceptance Criteria:**
- [ ] request_duration metric
- [ ] ingestion_failures metric
- [ ] db_write_latency metric
- [ ] aggregation_latency metric
- [ ] queue_depth metric
- [ ] retention_deleted metric

**Labels:** MVP, observability, medium-priority

---

## Priority Summary

### Critical Path (Must Complete for MVP)
1. Database Schema Setup (#1)
2. Eloquent Models (#2)
3. API Key Authentication Middleware (#3)
4. Counter Metric Endpoint (#6)
5. Value Metric Endpoint (#7)
6. Batch Ingestion Endpoint (#8)
7. Aggregation Query Endpoint (#10)
8. Aggregation Service (#11)
9. Queue Configuration (#13)
10. Batch Insert Job (#14)
11. Unit Test Suite (#17)
12. Feature Test Suite (#18)
13. Security Hardening (#20)
14. Input Validation (#21)

### High Priority (Important for MVP)
- Rate Limiting (#4)
- API Key Management Commands (#5)
- Retention Cleanup Command (#15)
- Load Testing Setup (#19)

### Medium Priority (Nice to Have)
- Timestamp Validation (#9)
- Time Bucket Optimization (#12)
- Retention Configuration (#16)
- Deployment Runbook (#23)
- Query Optimization (#25)
- Logging Configuration (#26)
- Service Metrics (#27)

### Low Priority (Can Be Deferred)
- Database Connection Pooling (#24)
- API Documentation Examples (#22)

## Issue Creation Instructions

When creating issues from this document:

1. Copy the title and description to a new GitHub issue
2. Add the labels specified
3. Set milestone to "MVP v1.0"
4. Assign to appropriate team member
5. Link related issues in the description
6. Update the project board

## Dependencies

Some issues have dependencies:
- #2 depends on #1 (models need migrations)
- #6, #7, #8 depend on #2, #3 (endpoints need models and auth)
- #10, #11 depend on #2 (aggregation needs models)
- #14 depends on #13 (batch job needs queue config)
- #17, #18 depend on all implementation issues

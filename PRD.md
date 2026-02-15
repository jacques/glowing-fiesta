# Product Requirements Document (PRD)
## Glowing Fiesta - Internal Metrics Service

**Version:** 1.0  
**Date:** February 15, 2026  
**Product Owner:** Engineering Team  
**Status:** Approved

---

## 1. Product Overview

### 1.1 Vision
Glowing Fiesta will be the centralized metrics collection and analysis platform for our organization, enabling every team to instrument their applications and make data-driven decisions with confidence.

### 1.2 Product Description
An internal-only, high-performance metrics service built on Laravel that provides:
- Fast and reliable metrics ingestion
- Flexible time-series aggregation
- Simple REST API interface
- Robust authentication and rate limiting

### 1.3 Target Users
- Backend developers instrumenting applications
- DevOps engineers monitoring infrastructure
- Data analysts building reports
- Product managers tracking business KPIs

---

## 2. Product Goals

### 2.1 Primary Goals
1. **Simplicity**: Developers can integrate in < 15 minutes
2. **Performance**: Handle 10,000+ writes/minute without degradation
3. **Reliability**: 99.9% uptime with no data loss
4. **Flexibility**: Support multiple metric types and aggregations

### 2.2 Non-Goals (Out of Scope)
- Real-time alerting (use existing monitoring tools)
- Custom visualizations/dashboards (use existing BI tools)
- External/public API access
- Historical data migration from other systems
- Multi-tenancy for external customers

---

## 3. User Stories & Use Cases

### 3.1 Epic 1: Metrics Ingestion

#### Story 1.1: Counter Metric
**As a** backend developer  
**I want to** increment a counter metric with a single API call  
**So that** I can track events like page views, errors, or orders

**Acceptance Criteria:**
- POST /api/metrics/counter endpoint available
- Default increment of 1 if no value provided
- Optional custom increment value
- Returns 201 on success
- Response time < 200ms (P95)

**Example:**
```bash
curl -X POST https://metrics.internal/api/metrics/counter \
  -H "X-API-Key: abc123" \
  -d '{"name": "api.requests", "value": 1}'
```

#### Story 1.2: Value Metric
**As a** backend developer  
**I want to** record arbitrary numeric values  
**So that** I can track measurements like response times or queue sizes

**Acceptance Criteria:**
- POST /api/metrics/value endpoint available
- Required numeric value field
- Optional timestamp (defaults to current time)
- Returns 201 on success
- Validates value is numeric

#### Story 1.3: Batch Ingestion
**As a** system with high metric volume  
**I want to** submit multiple metrics in one request  
**So that** I can reduce network overhead and improve efficiency

**Acceptance Criteria:**
- POST /api/metrics/batch endpoint available
- Accepts array of up to 1000 metrics
- Partial success handling (report which failed)
- Returns 201 with summary of accepted/rejected
- Transaction support for data integrity

### 3.2 Epic 2: Authentication & Security

#### Story 2.1: API Key Authentication
**As a** security-conscious organization  
**I want to** require API keys for all requests  
**So that** only authorized services can submit metrics

**Acceptance Criteria:**
- X-API-Key header required on all ingestion endpoints
- Returns 401 if key missing or invalid
- Keys stored securely (hashed)
- Key management interface for admins

#### Story 2.2: Rate Limiting
**As a** platform operator  
**I want to** limit requests per API key  
**So that** no single service can overwhelm the system

**Acceptance Criteria:**
- Configurable rate limit per API key
- Returns 429 when limit exceeded
- Response includes Retry-After header
- Limits reset every minute
- Redis-based for distributed systems

### 3.3 Epic 3: Data Aggregation

#### Story 3.1: Time-Series Query
**As a** data analyst  
**I want to** query aggregated metrics over time periods  
**So that** I can understand trends and patterns

**Acceptance Criteria:**
- GET /api/metrics/aggregate endpoint
- Support for sum, count, avg, min, max operations
- Time windows: 1m, 5m, 15m, 1h, 1d
- Date range filtering
- Multiple metrics in single query
- Returns JSON with timestamps and values

**Example:**
```bash
curl -X GET "https://metrics.internal/api/metrics/aggregate?\
name=api.requests&\
operation=sum&\
window=1h&\
start=2026-02-14T00:00:00Z&\
end=2026-02-15T00:00:00Z" \
  -H "X-API-Key: abc123"
```

#### Story 3.2: Multi-Metric Query
**As a** dashboard builder  
**I want to** retrieve multiple metrics in one request  
**So that** I can build comprehensive views efficiently

**Acceptance Criteria:**
- Accept comma-separated metric names
- Single time range applies to all metrics
- Response groups data by metric name
- Same aggregation applied to all metrics

### 3.4 Epic 4: Data Management

#### Story 4.1: Retention Policy
**As a** system administrator  
**I want to** automatically delete old metric data  
**So that** storage costs remain manageable

**Acceptance Criteria:**
- Configurable retention period (default 90 days)
- Automated cleanup job runs daily
- Soft delete with grace period
- Logs cleanup actions
- Configuration via environment variables

---

## 4. Functional Specifications

### 4.1 API Endpoints

#### 4.1.1 Ingestion Endpoints

**POST /api/metrics/counter**
```json
Request:
{
  "name": "string (required)",
  "value": "number (optional, default: 1)",
  "recorded_at": "ISO 8601 timestamp (optional)"
}

Response (201):
{
  "success": true,
  "message": "Counter metric recorded"
}
```

**POST /api/metrics/value**
```json
Request:
{
  "name": "string (required)",
  "value": "number (required)",
  "recorded_at": "ISO 8601 timestamp (optional)"
}

Response (201):
{
  "success": true,
  "message": "Value metric recorded"
}
```

**POST /api/metrics/batch**
```json
Request:
{
  "metrics": [
    {
      "name": "string (required)",
      "value": "number (required)",
      "type": "counter|value",
      "recorded_at": "ISO 8601 timestamp (optional)"
    }
  ]
}

Response (201):
{
  "success": true,
  "accepted": 100,
  "rejected": 0,
  "errors": []
}
```

#### 4.1.2 Aggregation Endpoints

**GET /api/metrics/aggregate**
```
Query Parameters:
- name: string (required) - metric name(s), comma-separated
- operation: sum|count|avg|min|max (required)
- window: 1m|5m|15m|1h|1d (required)
- start: ISO 8601 timestamp (required)
- end: ISO 8601 timestamp (required)

Response (200):
{
  "metric": "api.requests",
  "operation": "sum",
  "window": "1h",
  "data": [
    {
      "timestamp": "2026-02-15T00:00:00Z",
      "value": 1500
    },
    {
      "timestamp": "2026-02-15T01:00:00Z",
      "value": 1450
    }
  ]
}
```

### 4.2 Database Schema

#### 4.2.1 Metrics Table
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

#### 4.2.2 Metric Points Table
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

#### 4.2.3 API Keys Table
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

### 4.3 Data Types & Validation

#### 4.3.1 Metric Names
- Format: alphanumeric with dots, underscores, hyphens
- Max length: 255 characters
- Case-sensitive
- Examples: `api.requests`, `db_query_time`, `order-count`

#### 4.3.2 Metric Values
- Type: Decimal (20,4) for precision
- Range: -9999999999999999.9999 to 9999999999999999.9999
- Counters: typically non-negative, but not enforced
- Values: any numeric value

#### 4.3.3 Timestamps
- Format: ISO 8601 (YYYY-MM-DDTHH:mm:ssZ)
- Timezone: UTC
- Default: Current server time
- Historic data: Up to 7 days in the past
- Future data: Rejected

---

## 5. Non-Functional Requirements

### 5.1 Performance Requirements

| Metric | Target | Measurement |
|--------|--------|-------------|
| Write throughput | 10,000/min sustained | Load test |
| Read throughput | 1,000/min | Load test |
| Write latency (P95) | < 200ms | APM |
| Read latency (P95) | < 500ms | APM |
| Error rate | < 1% | Monitoring |
| Queue processing | < 30s lag | Monitoring |

### 5.2 Scalability Requirements
- Support 100+ API keys
- Handle 10+ concurrent services
- Store 100M+ data points
- Database should support partitioning
- Redis for distributed caching

### 5.3 Security Requirements
- HTTPS only (TLS 1.2+)
- API keys: 32+ character random strings
- Keys hashed in database (bcrypt)
- Rate limiting: Redis-backed
- Input validation on all endpoints
- SQL injection prevention
- XSS prevention (though API-only)
- CORS restrictions (internal only)

### 5.4 Reliability Requirements
- 99.9% uptime SLA
- Graceful degradation (queue buffering)
- No data loss for 200/201 responses
- Database transactions for consistency
- Health check endpoint
- Automatic retry for transient failures

### 5.5 Maintainability Requirements
- Unit test coverage > 80%
- Integration tests for all endpoints
- Load tests for performance validation
- Clear error messages
- Structured logging
- Code follows Laravel conventions
- Comprehensive API documentation

---

## 6. User Interface

### 6.1 API Response Format
All responses follow consistent JSON structure:

**Success Response:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Human-readable message"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": { ... }
  }
}
```

### 6.2 HTTP Status Codes
- 200 OK: Successful GET request
- 201 Created: Successful POST request
- 400 Bad Request: Invalid input
- 401 Unauthorized: Missing or invalid API key
- 429 Too Many Requests: Rate limit exceeded
- 500 Internal Server Error: Server-side error
- 503 Service Unavailable: System overloaded

---

## 7. Technical Specifications

### 7.1 Technology Stack
- **Framework**: Laravel 11.x
- **Language**: PHP 8.2+
- **Database**: MySQL 8.0
- **Cache/Queue**: Redis 6.0+
- **Web Server**: Nginx
- **Testing**: PHPUnit, Pest

### 7.2 System Architecture
```
┌──────────┐     HTTPS      ┌─────────┐
│  Client  │ ───────────────▶│  Nginx  │
└──────────┘                 └────┬────┘
                                  │
                            ┌─────▼──────┐
                            │  Laravel   │
                            │  App       │
                            └─────┬──────┘
                                  │
                    ┌─────────────┼─────────────┐
                    │             │             │
              ┌─────▼────┐  ┌────▼─────┐ ┌────▼─────┐
              │  MySQL   │  │  Redis   │ │  Redis   │
              │ (Primary)│  │  (Cache) │ │ (Queue)  │
              └──────────┘  └──────────┘ └──────────┘
```

### 7.3 Configuration
Environment variables:
- `DB_CONNECTION=mysql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
- `QUEUE_CONNECTION=redis`
- `METRICS_RETENTION_DAYS=90`
- `METRICS_RATE_LIMIT_DEFAULT=1000`

---

## 8. Testing Strategy

### 8.1 Unit Tests
- Model validation and relationships
- Service layer business logic
- Utility functions
- Target: >80% code coverage

### 8.2 Integration Tests
- API endpoint functionality
- Database operations
- Queue processing
- Authentication and authorization

### 8.3 Load Tests
- 10,000 writes/minute sustained
- Concurrent read/write operations
- Rate limiting behavior
- Queue performance under load
- Tool: K6 or Apache JMeter

### 8.4 Security Tests
- API authentication bypass attempts
- SQL injection tests
- Rate limit enforcement
- Input validation edge cases

---

## 9. Launch Plan

### 9.1 Phase 1: Alpha (Internal Team)
- Deploy to staging environment
- Engineering team integration
- Bug fixes and refinement
- Performance tuning
- Duration: 1 week

### 9.2 Phase 2: Beta (Early Adopters)
- 2-3 volunteer teams
- Real-world usage patterns
- Feedback collection
- Documentation updates
- Duration: 1 week

### 9.3 Phase 3: General Availability
- All teams invited
- Training sessions conducted
- Migration guides provided
- Support channels established
- Ongoing monitoring

---

## 10. Success Metrics

### 10.1 Adoption Metrics
- Number of teams onboarded
- Number of unique metrics
- Daily request volume
- API key utilization

### 10.2 Performance Metrics
- Average/P95/P99 latency
- Throughput (writes/reads per minute)
- Error rate
- Queue lag time

### 10.3 Business Metrics
- Cost savings vs. external services
- Developer satisfaction score
- Time to integrate (target: <15 min)
- Uptime percentage

---

## 11. Roadmap & Future Enhancements

### 11.1 Post-MVP Features (Not in Scope)
- Real-time streaming API
- Built-in alerting and notifications
- Advanced analytics (percentiles, histograms)
- Data export functionality
- Admin web UI for key management
- Multi-tenancy support
- GraphQL API
- Metric metadata and tagging

### 11.2 Optimization Opportunities
- Database partitioning by time
- Read replicas for aggregation queries
- Columnar storage for historical data
- Compression for old data
- Advanced caching strategies

---

## 12. Open Questions

1. Should we support metric metadata/tags in MVP?
2. What's the exact retention period requirement?
3. Do we need versioned API endpoints from start?
4. Should admin API key management be included in MVP?
5. What level of query complexity should we support?

---

## 13. Appendices

### Appendix A: API Examples

See full API documentation in `API.md`

### Appendix B: Database Schema

See detailed schema in database migrations

### Appendix C: Glossary

- **Counter**: A metric that only increases (e.g., requests, errors)
- **Value**: A metric that can be any number (e.g., temperature, response time)
- **Aggregation**: Computing statistics over time windows
- **Time Window**: Fixed time period for grouping data (e.g., 1 hour)
- **Rate Limit**: Maximum requests allowed per time period

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-02-15 | Engineering Team | Initial version |

---

**Approvals:**

- [ ] Product Manager
- [ ] Engineering Lead  
- [ ] Architecture Review
- [ ] Security Review

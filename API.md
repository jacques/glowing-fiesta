# API Documentation

## Overview

The Glowing Fiesta API provides endpoints for ingesting metrics and querying aggregated time-series data.

**Base URL:** `https://api.example.com`

**Authentication:** All requests require an API key sent via the `X-API-Key` header.

**Rate Limiting:** Rate limits are enforced per API key (default: 1000 requests/minute).

## Authentication

### API Key Header

All requests must include the API key in the header:

```
X-API-Key: your-api-key-here
```

**Example:**
```bash
curl -H "X-API-Key: abc123..." https://api.example.com/api/metrics/counter
```

### Error Response (401 Unauthorized)

```json
{
  "error": "Invalid or missing API key",
  "code": "UNAUTHORIZED"
}
```

## Ingestion Endpoints

### POST /api/metrics/counter

Records a counter metric increment.

**Use Case:** Event counting, request counting, error counting.

**Behavior:**
- Auto-creates metric if it doesn't exist
- Enforces type consistency (rejects if metric exists as 'value' type)
- Defaults value to 1 if omitted
- Defaults timestamp to current server time

**Request Body:**

```json
{
  "metric": "api.requests.total",
  "value": 1,
  "timestamp": 1676472000
}
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| metric | string | Yes | Metric name (max 191 characters) |
| value | integer | No | Increment amount (default: 1, min: 1) |
| timestamp | integer | No | Unix timestamp (defaults to now) |

**Validation Rules:**
- `metric`: required, string, max:191
- `value`: optional, integer, min:1
- `timestamp`: optional, integer, must be within ±7 days of current time

**Success Response (201 Created):**

```json
{
  "success": true,
  "metric": "api.requests.total",
  "value": 1,
  "recorded_at": "2024-02-15T10:30:00.000000Z"
}
```

**Error Responses:**

**400 Bad Request** (Validation Error):
```json
{
  "error": "Validation failed",
  "code": "VALIDATION_ERROR",
  "details": {
    "metric": ["The metric field is required."]
  }
}
```

**409 Conflict** (Type Mismatch):
```json
{
  "error": "Metric 'api.requests.total' already exists with type 'value'",
  "code": "METRIC_TYPE_CONFLICT"
}
```

**Example:**

```bash
curl -X POST https://api.example.com/api/metrics/counter \
  -H "X-API-Key: your-key" \
  -H "Content-Type: application/json" \
  -d '{
    "metric": "user.signups",
    "value": 1
  }'
```

---

### POST /api/metrics/value

Records a value metric (arbitrary numeric values).

**Use Case:** Response times, memory usage, queue depth, temperatures.

**Behavior:**
- Auto-creates metric if it doesn't exist
- Enforces type consistency
- Timestamp defaults to current server time

**Request Body:**

```json
{
  "metric": "api.response_time_ms",
  "value": 145.67,
  "timestamp": 1676472000
}
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| metric | string | Yes | Metric name (max 191 characters) |
| value | numeric | Yes | Numeric value (integer or float) |
| timestamp | integer | No | Unix timestamp (defaults to now) |

**Validation Rules:**
- `metric`: required, string, max:191
- `value`: required, numeric
- `timestamp`: optional, integer, must be within ±7 days of current time

**Success Response (201 Created):**

```json
{
  "success": true,
  "metric": "api.response_time_ms",
  "value": 145.67,
  "recorded_at": "2024-02-15T10:30:00.000000Z"
}
```

**Error Responses:**

Same as counter endpoint (400, 409 status codes).

**Example:**

```bash
curl -X POST https://api.example.com/api/metrics/value \
  -H "X-API-Key: your-key" \
  -H "Content-Type: application/json" \
  -d '{
    "metric": "server.cpu_usage_percent",
    "value": 67.5
  }'
```

---

### POST /api/metrics/batch

Records multiple metrics in a single request.

**Use Case:** Bulk metric ingestion, reducing HTTP overhead.

**Behavior:**
- Processes up to 1000 points per request
- Entire batch is rejected if any validation fails (atomic operation)
- More efficient than individual requests

**Request Body:**

```json
{
  "points": [
    {
      "metric": "api.requests.total",
      "type": "counter",
      "value": 1
    },
    {
      "metric": "api.response_time_ms",
      "type": "value",
      "value": 145.67
    },
    {
      "metric": "cache.hits",
      "type": "counter",
      "value": 5,
      "timestamp": 1676472000
    }
  ]
}
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| points | array | Yes | Array of metric points (max 1000) |
| points[].metric | string | Yes | Metric name (max 191 characters) |
| points[].type | enum | Yes | 'counter' or 'value' |
| points[].value | numeric | Yes* | Numeric value (*optional for counters, defaults to 1) |
| points[].timestamp | integer | No | Unix timestamp (defaults to now) |

**Validation Rules:**
- `points`: required, array, max:1000
- Each point follows the same validation as individual endpoints

**Success Response (201 Created):**

```json
{
  "success": true,
  "processed": 3,
  "points": [
    {
      "metric": "api.requests.total",
      "recorded_at": "2024-02-15T10:30:00.000000Z"
    },
    {
      "metric": "api.response_time_ms",
      "recorded_at": "2024-02-15T10:30:00.000000Z"
    },
    {
      "metric": "cache.hits",
      "recorded_at": "2024-02-15T10:30:00.000000Z"
    }
  ]
}
```

**Error Responses:**

**400 Bad Request** (Validation Error):
```json
{
  "error": "Validation failed",
  "code": "VALIDATION_ERROR",
  "details": {
    "points.0.metric": ["The metric field is required."],
    "points": ["The points array may not contain more than 1000 items."]
  }
}
```

**Example:**

```bash
curl -X POST https://api.example.com/api/metrics/batch \
  -H "X-API-Key: your-key" \
  -H "Content-Type: application/json" \
  -d '{
    "points": [
      {"metric": "requests", "type": "counter", "value": 1},
      {"metric": "latency", "type": "value", "value": 250.5}
    ]
  }'
```

---

## Aggregation Endpoints

### GET /api/metrics/{metric}

Retrieves aggregated metric data over a time range.

**Use Case:** Dashboards, reports, analytics.

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| {metric} | string | Yes | Metric name |

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| from | string | Yes | Start time (ISO8601 or Unix timestamp) |
| to | string | Yes | End time (ISO8601 or Unix timestamp) |
| interval | enum | No | Time bucket: 'minute', 'hour', 'day' (default: hour) |
| aggregation | enum | No | Aggregation type: 'sum', 'avg', 'min', 'max', 'count' (default: sum) |

**Example Request:**

```bash
curl -X GET "https://api.example.com/api/metrics/api.requests.total?from=2024-02-01T00:00:00Z&to=2024-02-15T23:59:59Z&interval=hour&aggregation=sum" \
  -H "X-API-Key: your-key"
```

**Success Response (200 OK):**

```json
{
  "metric": "api.requests.total",
  "type": "counter",
  "interval": "hour",
  "aggregation": "sum",
  "from": "2024-02-01T00:00:00Z",
  "to": "2024-02-15T23:59:59Z",
  "data": [
    {
      "bucket": "2024-02-01T00:00:00Z",
      "value": 1250
    },
    {
      "bucket": "2024-02-01T01:00:00Z",
      "value": 1180
    },
    {
      "bucket": "2024-02-01T02:00:00Z",
      "value": 980
    }
  ],
  "summary": {
    "total_points": 150000,
    "buckets": 336
  }
}
```

**Aggregation Types:**

| Type | Description | Best For |
|------|-------------|----------|
| sum | Sum of all values | Counters, totals |
| avg | Average of all values | Latencies, percentages |
| min | Minimum value | Finding lowest points |
| max | Maximum value | Finding peaks |
| count | Number of data points | Event frequency |

**Interval Options:**

| Interval | Format | Example Bucket |
|----------|--------|----------------|
| minute | YYYY-MM-DD HH:MM:00 | 2024-02-15 10:30:00 |
| hour | YYYY-MM-DD HH:00:00 | 2024-02-15 10:00:00 |
| day | YYYY-MM-DD 00:00:00 | 2024-02-15 00:00:00 |

**Time Format:**

The `from` and `to` parameters accept:
- **ISO8601:** `2024-02-15T10:30:00Z`
- **Unix Timestamp:** `1676472000`

**Error Responses:**

**400 Bad Request** (Invalid Parameters):
```json
{
  "error": "Invalid time range",
  "code": "INVALID_PARAMETERS",
  "details": {
    "from": ["The from field must be a valid date."]
  }
}
```

**404 Not Found** (Metric Not Found):
```json
{
  "error": "Metric 'unknown.metric' not found",
  "code": "METRIC_NOT_FOUND"
}
```

---

## Error Handling

All error responses follow a consistent format:

```json
{
  "error": "Human-readable error message",
  "code": "ERROR_CODE",
  "details": {}
}
```

### HTTP Status Codes

| Status | Description | When Used |
|--------|-------------|-----------|
| 200 | OK | Successful GET request |
| 201 | Created | Successful metric ingestion |
| 400 | Bad Request | Validation errors, invalid parameters |
| 401 | Unauthorized | Missing or invalid API key |
| 409 | Conflict | Metric type conflict |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Unexpected server error |

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| UNAUTHORIZED | 401 | Invalid or missing API key |
| VALIDATION_ERROR | 400 | Request validation failed |
| METRIC_TYPE_CONFLICT | 409 | Type mismatch for existing metric |
| METRIC_NOT_FOUND | 404 | Metric does not exist |
| RATE_LIMIT_EXCEEDED | 429 | Too many requests |
| INVALID_PARAMETERS | 400 | Invalid query parameters |
| SERVER_ERROR | 500 | Internal server error |

### Rate Limiting

When rate limited, the response includes retry information:

**Response (429 Too Many Requests):**

```json
{
  "error": "Rate limit exceeded",
  "code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 60
}
```

**Headers:**
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1676472060
Retry-After: 60
```

---

## Best Practices

### Efficient Ingestion

1. **Use Batch Endpoint:** Reduce HTTP overhead by batching up to 1000 points
2. **Send Timestamps:** Include explicit timestamps for accurate time-series data
3. **Handle Errors:** Implement retry logic with exponential backoff
4. **Monitor Rate Limits:** Watch the `X-RateLimit-*` headers

### Querying Data

1. **Limit Time Ranges:** Query smaller time ranges for better performance
2. **Choose Appropriate Intervals:** Use coarser intervals (hour, day) for longer ranges
3. **Cache Results:** Cache aggregation results on the client side
4. **Use Specific Aggregations:** Request only the aggregation types you need

### Naming Conventions

**Recommended Metric Naming:**
- Use dot notation: `service.component.metric`
- Lowercase with underscores: `api.response_time_ms`
- Be consistent and descriptive

**Examples:**
- `api.requests.total` - Total API requests
- `api.response_time_ms` - Response time in milliseconds
- `db.queries.count` - Database query count
- `cache.hit_ratio` - Cache hit ratio percentage

---

## Client Libraries

### PHP Example

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.example.com',
    'headers' => [
        'X-API-Key' => 'your-api-key',
        'Content-Type' => 'application/json',
    ]
]);

// Send counter
$response = $client->post('/api/metrics/counter', [
    'json' => [
        'metric' => 'user.signups',
        'value' => 1
    ]
]);

// Send batch
$response = $client->post('/api/metrics/batch', [
    'json' => [
        'points' => [
            ['metric' => 'requests', 'type' => 'counter', 'value' => 1],
            ['metric' => 'latency', 'type' => 'value', 'value' => 150.5]
        ]
    ]
]);

// Query data
$response = $client->get('/api/metrics/user.signups', [
    'query' => [
        'from' => '2024-02-01T00:00:00Z',
        'to' => '2024-02-15T23:59:59Z',
        'interval' => 'day',
        'aggregation' => 'sum'
    ]
]);

$data = json_decode($response->getBody(), true);
```

### JavaScript Example

```javascript
const axios = require('axios');

const client = axios.create({
  baseURL: 'https://api.example.com',
  headers: {
    'X-API-Key': 'your-api-key',
    'Content-Type': 'application/json'
  }
});

// Send counter
await client.post('/api/metrics/counter', {
  metric: 'page.views',
  value: 1
});

// Send batch
await client.post('/api/metrics/batch', {
  points: [
    { metric: 'requests', type: 'counter', value: 1 },
    { metric: 'latency', type: 'value', value: 150.5 }
  ]
});

// Query data
const response = await client.get('/api/metrics/page.views', {
  params: {
    from: '2024-02-01T00:00:00Z',
    to: '2024-02-15T23:59:59Z',
    interval: 'hour',
    aggregation: 'sum'
  }
});

console.log(response.data);
```

### Python Example

```python
import requests

headers = {
    'X-API-Key': 'your-api-key',
    'Content-Type': 'application/json'
}

base_url = 'https://api.example.com'

# Send counter
response = requests.post(
    f'{base_url}/api/metrics/counter',
    headers=headers,
    json={
        'metric': 'api.errors',
        'value': 1
    }
)

# Send batch
response = requests.post(
    f'{base_url}/api/metrics/batch',
    headers=headers,
    json={
        'points': [
            {'metric': 'requests', 'type': 'counter', 'value': 1},
            {'metric': 'latency', 'type': 'value', 'value': 150.5}
        ]
    }
)

# Query data
response = requests.get(
    f'{base_url}/api/metrics/api.errors',
    headers=headers,
    params={
        'from': '2024-02-01T00:00:00Z',
        'to': '2024-02-15T23:59:59Z',
        'interval': 'hour',
        'aggregation': 'count'
    }
)

data = response.json()
```

---

## API Changelog

### v1.0.0 (Initial Release)
- Counter metric endpoint
- Value metric endpoint
- Batch ingestion endpoint
- Aggregation query endpoint
- API key authentication
- Rate limiting

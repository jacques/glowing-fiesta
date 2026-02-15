# Glowing Fiesta

**Internal Metrics Service** - A high-performance metrics ingestion and aggregation API inspired by StatHat.

## Overview

Glowing Fiesta is a lightweight, write-optimized metrics collection service designed for internal use. It provides simple HTTP APIs for recording time-series metrics and querying aggregated data.

**Technology Stack:**
- Laravel (PHP 8.2)
- MySQL 8.0
- Redis (Queue & Cache)

## Key Features

- **High Write Throughput:** Optimized for 5k-20k writes/second
- **Flexible Metrics:** Support for counters and arbitrary value metrics
- **Time-Series Aggregation:** Built-in aggregation (sum, avg, min, max, count) with configurable time buckets
- **Batch Ingestion:** Reduce overhead with bulk metric uploads (up to 1000 points)
- **API Key Authentication:** Secure, per-key rate limiting
- **Horizontal Scalability:** Stateless application layer
- **Automatic Retention:** Configurable data retention with automated cleanup

## Quick Start

### Installation

```bash
# Clone the repository
git clone https://github.com/jacques/glowing-fiesta.git
cd glowing-fiesta

# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Create an API key
php artisan make:api-key "My Application"

# Start the development server
php artisan serve
```

### Basic Usage

**Record a counter metric:**
```bash
curl -X POST http://localhost:8000/api/metrics/counter \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"metric": "page.views", "value": 1}'
```

**Record a value metric:**
```bash
curl -X POST http://localhost:8000/api/metrics/value \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"metric": "response.time.ms", "value": 145.67}'
```

**Query aggregated data:**
```bash
curl "http://localhost:8000/api/metrics/page.views?from=2024-02-01T00:00:00Z&to=2024-02-15T23:59:59Z&interval=hour&aggregation=sum" \
  -H "X-API-Key: your-api-key"
```

## Documentation

- **[Architecture](ARCHITECTURE.md)** - System design and components
- **[API Documentation](API.md)** - Complete API reference with examples
- **[Deployment Guide](DEPLOYMENT.md)** - Production deployment instructions
- **[Testing Guide](TESTING.md)** - Testing strategy and requirements

## Project Status

**Current Phase:** Planning and Documentation

This project is currently in the planning phase. The engineering specification has been completed, and we are now creating implementation issues and detailed documentation.

### MVP Scope

The Minimum Viable Product includes:
- Counter and value metric types
- Basic aggregation (sum, avg, min, max, count)
- API key authentication
- Batch ingestion endpoint
- Automated retention cleanup
- Comprehensive documentation

### Excluded from MVP

- Real-time streaming
- Alerting and notifications
- Multi-tenant RBAC
- Complex tagging/labeling
- Downsampling pipelines
- Pre-aggregated rollup tables

## Development

### Requirements

- PHP 8.2+
- MySQL 8.0+
- Redis 6.0+ (optional for MVP)
- Composer 2.x

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test suite
php artisan test --testsuite=Feature
```

### Code Quality

```bash
# Run PHPStan
./vendor/bin/phpstan analyse

# Run PHP CS Fixer
./vendor/bin/php-cs-fixer fix
```

## Contributing

This is an internal project. Please follow these guidelines:

1. Create a feature branch from `main`
2. Write tests for new functionality
3. Ensure all tests pass
4. Update documentation as needed
5. Submit a pull request for review

## Performance Targets

- **Write Throughput:** 10,000 writes/minute sustained
- **p95 Latency:** < 200ms for ingestion API
- **Error Rate:** < 1%
- **Uptime:** 99.9%

## Security

- HTTPS required for all endpoints
- API keys stored as SHA-256 hashes
- Per-key rate limiting
- Request size limits enforced
- SQL injection protection via parameterized queries

## License

Internal use only. Not licensed for external distribution.

## Support

For questions or issues:
- Create an issue in this repository
- Contact the DevOps team
- Refer to the documentation in the `/docs` directory

## Roadmap

See open issues for planned features and enhancements. Major upcoming features include:
- Pre-aggregated rollup tables (Phase 2)
- Dashboard UI (Phase 3)
- Alerting system (Phase 3)
- Multi-tenancy (Future)

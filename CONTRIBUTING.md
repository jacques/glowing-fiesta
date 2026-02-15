# Contributing to Glowing Fiesta

Thank you for your interest in contributing to Glowing Fiesta! This document provides guidelines for contributing to the project.

## Code of Conduct

- Be respectful and inclusive
- Focus on constructive feedback
- Collaborate openly and transparently
- Prioritize the project's goals and maintainability

## Getting Started

### Development Environment Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/jacques/glowing-fiesta.git
   cd glowing-fiesta
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Set up database**
   ```bash
   # For local development, use SQLite
   touch database/database.sqlite
   php artisan migrate
   php artisan db:seed
   ```

5. **Start development server**
   ```bash
   php artisan serve
   ```

## Development Workflow

### Branching Strategy

- `main` - Production-ready code
- `develop` - Integration branch for features
- `feature/*` - New features
- `bugfix/*` - Bug fixes
- `hotfix/*` - Critical production fixes

### Creating a Branch

```bash
# For a new feature
git checkout -b feature/metric-tagging

# For a bug fix
git checkout -b bugfix/timestamp-validation

# For a hotfix
git checkout -b hotfix/rate-limit-bypass
```

### Commit Message Guidelines

Use clear, descriptive commit messages:

**Format:**
```
<type>: <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```
feat: add batch ingestion endpoint

Implements POST /api/metrics/batch endpoint to accept up to 1000
metric points in a single request. Uses bulk insert for efficiency.

Closes #8
```

```
fix: prevent type mismatch on existing metrics

Adds validation to ensure metric type cannot change once created.
Returns 409 status code when type conflict detected.

Fixes #42
```

## Pull Request Process

### Before Submitting

1. **Update from main**
   ```bash
   git fetch origin
   git rebase origin/main
   ```

2. **Run tests**
   ```bash
   php artisan test
   ```

3. **Run code quality checks**
   ```bash
   ./vendor/bin/phpstan analyse
   ./vendor/bin/php-cs-fixer fix
   ```

4. **Update documentation**
   - Update relevant `.md` files
   - Add/update code comments
   - Update API documentation if endpoints changed

### Submitting a Pull Request

1. **Push your branch**
   ```bash
   git push origin feature/your-feature-name
   ```

2. **Create Pull Request**
   - Go to GitHub and create a new Pull Request
   - Fill in the PR template completely
   - Link related issues
   - Request reviews from appropriate team members

3. **PR Title Format**
   ```
   [TYPE] Brief description
   ```
   
   Examples:
   - `[FEATURE] Add batch ingestion endpoint`
   - `[FIX] Correct timestamp validation logic`
   - `[DOCS] Update API documentation`

4. **PR Description Template**
   ```markdown
   ## Description
   Brief description of changes
   
   ## Type of Change
   - [ ] Bug fix
   - [ ] New feature
   - [ ] Breaking change
   - [ ] Documentation update
   
   ## Related Issues
   Closes #123
   
   ## Testing
   - [ ] Unit tests added/updated
   - [ ] Feature tests added/updated
   - [ ] Manual testing completed
   
   ## Checklist
   - [ ] Code follows project style guidelines
   - [ ] Self-review completed
   - [ ] Comments added for complex logic
   - [ ] Documentation updated
   - [ ] No new warnings generated
   - [ ] Tests pass locally
   ```

### Code Review Process

- At least one approval required
- Address all review comments
- Keep discussions professional and constructive
- Be responsive to feedback

## Coding Standards

### PHP Standards

Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard:

```php
<?php

namespace App\Services;

use App\Models\Metric;
use Illuminate\Support\Facades\DB;

class MetricIngestionService
{
    /**
     * Record a metric point.
     *
     * @param string $name
     * @param float $value
     * @param string $type
     * @return MetricPoint
     */
    public function record(string $name, float $value, string $type): MetricPoint
    {
        $metric = $this->findOrCreateMetric($name, $type);
        
        return $metric->points()->create([
            'value' => $value,
            'recorded_at' => now(),
        ]);
    }
}
```

### Laravel Best Practices

1. **Use Eloquent appropriately**
   - Use Eloquent for single records and relationships
   - Use Query Builder for bulk operations
   - Use raw queries only when necessary

2. **Service Layer**
   - Extract business logic into service classes
   - Keep controllers thin
   - Services should be testable

3. **Validation**
   - Use Form Requests for complex validation
   - Validate early and fail fast
   - Provide clear error messages

4. **Database**
   - Always use migrations
   - Never modify existing migrations in production
   - Use factories for test data

5. **Security**
   - Never trust user input
   - Use parameterized queries
   - Validate and sanitize all inputs
   - Follow OWASP guidelines

### Code Comments

Add comments for:
- Complex algorithms
- Non-obvious business logic
- Workarounds or hacks (with explanation)
- Public API methods (use PHPDoc)

Don't comment:
- Obvious code
- What the code does (should be self-explanatory)

**Example:**
```php
// Good: Explains WHY
// Use bulk insert to reduce database round trips for better performance
DB::insert($points);

// Bad: Explains WHAT (obvious from code)
// Insert points into database
DB::insert($points);
```

## Testing Guidelines

### Test Coverage

- Aim for 80%+ code coverage
- 100% coverage for critical paths
- All public methods should have tests

### Test Structure

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MetricIngestionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test description in plain English.
     */
    public function test_counter_metric_can_be_created(): void
    {
        // Arrange
        $apiKey = ApiKey::factory()->create();
        
        // Act
        $response = $this->withHeader('X-API-Key', $apiKey->key)
                         ->postJson('/api/metrics/counter', [
                             'metric' => 'test.metric',
                             'value' => 1,
                         ]);
        
        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('metrics', [
            'name' => 'test.metric',
            'type' => 'counter',
        ]);
    }
}
```

### Running Tests

```bash
# All tests
php artisan test

# Specific test suite
php artisan test --testsuite=Feature

# With coverage
php artisan test --coverage

# Specific test
php artisan test --filter testCounterMetricCanBeCreated
```

## Documentation Standards

### API Documentation

When adding or modifying endpoints:

1. Update `API.md` with:
   - Endpoint path and method
   - Request parameters
   - Response format
   - Error codes
   - Example requests/responses

2. Include cURL examples

3. Add client library examples (PHP, JavaScript, Python)

### Code Documentation

Use PHPDoc for all public methods:

```php
/**
 * Aggregate metric data over a time range.
 *
 * @param Metric $metric The metric to aggregate
 * @param Carbon $from Start of time range
 * @param Carbon $to End of time range
 * @param string $interval Time bucket interval (minute|hour|day)
 * @param string $aggregation Aggregation type (sum|avg|min|max|count)
 * @return array Array of time-bucketed aggregated values
 * @throws InvalidArgumentException If interval or aggregation is invalid
 */
public function aggregate(
    Metric $metric,
    Carbon $from,
    Carbon $to,
    string $interval,
    string $aggregation
): array {
    // Implementation
}
```

## Performance Considerations

### Database Queries

- Use `select()` to limit columns
- Eager load relationships to avoid N+1
- Use indexes appropriately
- Paginate large result sets

### Caching

- Cache expensive computations
- Use appropriate cache keys
- Set reasonable TTLs
- Invalidate cache when data changes

### Queue Jobs

- Keep jobs small and focused
- Make jobs idempotent when possible
- Handle failures gracefully
- Log important events

## Security Guidelines

### Input Validation

```php
// Always validate input
$validated = $request->validate([
    'metric' => 'required|string|max:191',
    'value' => 'required|numeric',
    'timestamp' => 'nullable|integer',
]);
```

### SQL Injection Prevention

```php
// Good: Parameterized query
DB::table('metrics')->where('name', $name)->first();

// Bad: String concatenation
DB::select("SELECT * FROM metrics WHERE name = '$name'");
```

### Authentication

- Never log API keys
- Hash sensitive data
- Use HTTPS in production
- Implement rate limiting

## Issue Reporting

### Bug Reports

Include:
- Clear description of the bug
- Steps to reproduce
- Expected vs actual behavior
- Environment details
- Error messages/logs

### Feature Requests

Include:
- Problem statement
- Proposed solution
- Use cases
- Impact on existing functionality

## Questions?

- Check existing documentation
- Search closed issues
- Ask in team chat
- Create a discussion issue

## License

By contributing to Glowing Fiesta, you agree that your contributions will be subject to the project's license.

## Attribution

This contributing guide is adapted from open-source best practices and tailored for Glowing Fiesta.

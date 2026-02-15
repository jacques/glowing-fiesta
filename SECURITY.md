# Security Policy

## Overview

Security is a top priority for Glowing Fiesta. This document outlines our security policies, vulnerability reporting process, and security best practices.

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

### How to Report

**DO NOT** create a public GitHub issue for security vulnerabilities.

Instead, please report security vulnerabilities to:

- **Email:** security@yourdomain.com
- **Subject:** [SECURITY] Glowing Fiesta - Brief Description

### What to Include

1. **Description of the vulnerability**
   - Type of issue (e.g., SQL injection, XSS, authentication bypass)
   - Impact assessment (data exposure, service disruption, etc.)

2. **Steps to reproduce**
   - Detailed steps to reproduce the vulnerability
   - Sample requests/payloads if applicable
   - Environment details

3. **Proof of concept**
   - Code snippets
   - Screenshots (if applicable)
   - Video demonstration (if helpful)

4. **Suggested fix** (if you have one)
   - Proposed solution
   - Code patch (optional)

### Response Timeline

- **Initial Response:** Within 48 hours
- **Triage:** Within 5 business days
- **Fix Timeline:** Depends on severity
  - Critical: 1-7 days
  - High: 7-30 days
  - Medium: 30-90 days
  - Low: Next planned release

### Disclosure Policy

- We follow coordinated disclosure
- We'll work with you to understand and fix the issue
- Public disclosure after fix is deployed (typically 30 days)
- Credit will be given to reporters (unless they prefer anonymity)

## Security Best Practices

### For Developers

#### API Key Security

```php
// Good: Hash keys before storage
$hashedKey = hash('sha256', $rawKey);
ApiKey::create(['key' => $hashedKey]);

// Bad: Store raw keys
ApiKey::create(['key' => $rawKey]);
```

**Best Practices:**
- Never log API keys
- Hash keys using SHA-256 or better
- Show raw keys only once during creation
- Implement key rotation mechanism

#### Input Validation

```php
// Good: Validate all inputs
$validated = $request->validate([
    'metric' => 'required|string|max:191',
    'value' => 'required|numeric|min:0',
    'timestamp' => 'nullable|integer|between:' . $minTime . ',' . $maxTime,
]);

// Bad: Trust user input
$metric = $request->input('metric');
DB::table('metrics')->where('name', $metric)->first();
```

**Best Practices:**
- Validate all user inputs
- Use Laravel's validation rules
- Implement strict type checking
- Sanitize output when necessary

#### SQL Injection Prevention

```php
// Good: Parameterized queries
DB::table('metric_points')
    ->where('metric_id', $metricId)
    ->where('recorded_at', '>=', $from)
    ->get();

// Bad: String interpolation
DB::select("SELECT * FROM metric_points WHERE metric_id = $metricId");
```

**Best Practices:**
- Always use parameterized queries
- Use Eloquent or Query Builder
- Never concatenate user input into SQL
- Use prepared statements for raw queries

#### Authentication & Authorization

```php
// Good: Verify authentication
if (!$request->apiKey) {
    return response()->json(['error' => 'Unauthorized'], 401);
}

// Check rate limit
if (RateLimiter::tooManyAttempts($request->apiKey->id, 1000)) {
    return response()->json(['error' => 'Rate limit exceeded'], 429);
}
```

**Best Practices:**
- Authenticate all requests
- Implement rate limiting
- Use middleware for authentication
- Log authentication failures

#### Timestamp Validation

```php
// Prevent timestamp manipulation
$maxSkew = 7 * 24 * 60 * 60; // 7 days in seconds
$now = time();

if ($timestamp < ($now - $maxSkew) || $timestamp > ($now + $maxSkew)) {
    return response()->json([
        'error' => 'Timestamp outside acceptable range',
        'code' => 'INVALID_TIMESTAMP'
    ], 400);
}
```

**Best Practices:**
- Validate timestamp ranges
- Prevent backdating attacks
- Use server time when not provided
- Document timestamp format

### For Deployment

#### HTTPS Configuration

```nginx
# Enforce HTTPS
server {
    listen 80;
    server_name api.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.yourdomain.com;
    
    # Strong SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
```

#### Environment Security

```bash
# .env file permissions
chmod 600 .env

# Owned by web server user
chown www-data:www-data .env

# Never commit .env to git
echo ".env" >> .gitignore
```

**Best Practices:**
- Use strong SSL/TLS configuration
- Implement security headers
- Restrict file permissions
- Keep secrets in environment variables
- Never commit secrets to version control

#### Database Security

```ini
# MySQL configuration
[mysqld]
# Disable remote root login
bind-address = 127.0.0.1

# Use strong authentication
default_authentication_plugin = mysql_native_password

# Enable SSL for connections (production)
require_secure_transport = ON
```

**Best Practices:**
- Use strong database passwords
- Limit database user permissions
- Enable SSL for database connections
- Keep database software updated
- Regular security patches

#### Rate Limiting

```php
// Configure rate limits per API key
Route::middleware(['throttle:' . $apiKey->rate_limit . ',1'])->group(function () {
    // Protected routes
});
```

**Best Practices:**
- Implement per-key rate limits
- Use Redis for distributed rate limiting
- Return appropriate error codes (429)
- Include rate limit headers in responses

## Security Features

### Built-in Security

1. **API Key Authentication**
   - SHA-256 hashed keys
   - Per-key rate limiting
   - Key rotation support

2. **Input Validation**
   - Laravel validation rules
   - Type checking
   - Range validation
   - Size limits

3. **SQL Injection Protection**
   - Parameterized queries
   - Eloquent ORM
   - Query builder

4. **Rate Limiting**
   - Per-API-key limits
   - Redis-backed throttling
   - Configurable limits

5. **Request Size Limits**
   - Maximum request body size
   - Maximum batch size (1000 points)
   - Protection against large payloads

### Security Headers

All responses should include:

```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

## Known Security Considerations

### Timestamp Manipulation

**Risk:** Users could backdate or future-date metrics.

**Mitigation:** Validate timestamps within ±7 days of current time.

### Rate Limit Bypass

**Risk:** Distributed attacks from multiple keys.

**Mitigation:** Global rate limits in addition to per-key limits.

### Database Injection

**Risk:** SQL injection through user-controlled inputs.

**Mitigation:** Exclusive use of parameterized queries.

### Denial of Service

**Risk:** Large payloads or many requests could overwhelm service.

**Mitigation:** 
- Request size limits (2MB)
- Batch size limits (1000 points)
- Rate limiting
- Queue-based processing

### API Key Exposure

**Risk:** Keys could be logged or transmitted insecurely.

**Mitigation:**
- Never log API keys
- HTTPS required
- Keys shown only once during creation
- Hash keys in database

## Security Checklist

### Development

- [ ] All inputs validated
- [ ] Parameterized queries used
- [ ] API keys hashed
- [ ] No secrets in code
- [ ] Security tests written
- [ ] Authentication required
- [ ] Rate limiting implemented

### Deployment

- [ ] HTTPS enforced
- [ ] Security headers configured
- [ ] .env file secured (600 permissions)
- [ ] Database credentials secured
- [ ] Firewall configured
- [ ] Only necessary ports open
- [ ] Logs monitored
- [ ] Backups encrypted

### Maintenance

- [ ] Regular security updates
- [ ] Dependency vulnerability scanning
- [ ] Log review
- [ ] Access audit
- [ ] Backup testing
- [ ] Incident response plan
- [ ] Security training

## Security Tools

### Recommended Tools

1. **Static Analysis**
   - PHPStan (type checking)
   - Psalm (security analysis)
   - SonarQube (code quality & security)

2. **Dependency Scanning**
   - Composer audit
   - Snyk
   - GitHub Dependabot

3. **Penetration Testing**
   - OWASP ZAP
   - Burp Suite
   - Manual security audits

### Running Security Scans

```bash
# Check for vulnerable dependencies
composer audit

# Run static analysis
./vendor/bin/phpstan analyse --level=max

# Security-focused tests
php artisan test --testsuite=Security
```

## Incident Response

### If a Security Incident Occurs

1. **Contain**
   - Isolate affected systems
   - Revoke compromised API keys
   - Block malicious IPs

2. **Assess**
   - Determine scope of breach
   - Identify affected data
   - Document timeline

3. **Remediate**
   - Patch vulnerability
   - Deploy fix
   - Verify fix effectiveness

4. **Communicate**
   - Notify affected users
   - Publish security advisory
   - Credit reporter

5. **Learn**
   - Post-mortem analysis
   - Update security practices
   - Improve detection

## Compliance

### Data Protection

- No personal data stored (API-only service)
- Metrics are internal and non-sensitive
- Access controls via API keys
- Retention policy enforced (90 days default)

### Audit Trail

- Authentication attempts logged
- Failed requests logged
- Database changes tracked
- Security events monitored

## Contact

For security concerns, contact:

- **Security Email:** security@yourdomain.com
- **Security Team Lead:** [Name]
- **Response Time:** 48 hours maximum

## Updates

This security policy is reviewed and updated quarterly or after any security incident.

**Last Updated:** 2024-02-15
**Next Review:** 2024-05-15

## Acknowledgments

We thank all security researchers who responsibly disclose vulnerabilities to us.

### Hall of Fame

(Security researchers who have helped improve Glowing Fiesta will be listed here)

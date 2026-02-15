# Deployment Guide

## Overview

This guide covers deploying Glowing Fiesta to production environments. The application is designed to be stateless and horizontally scalable.

## Prerequisites

### System Requirements

**Application Server:**
- PHP 8.2 or higher
- PHP Extensions: PDO, MySQL, Redis, BCMath, Ctype, JSON, Mbstring, OpenSSL, Tokenizer, XML
- Composer 2.x
- 2+ CPU cores
- 4GB+ RAM (recommended)

**Database Server:**
- MySQL 8.0 or higher
- InnoDB storage engine
- 4GB+ RAM (minimum)
- SSD storage (recommended for write performance)

**Queue System:**
- Redis 6.0+ (recommended)
- OR: MySQL (acceptable for MVP)

**Web Server:**
- Nginx 1.20+ or Apache 2.4+
- HTTPS/TLS certificate

## Initial Setup

### 1. Clone and Install Dependencies

```bash
# Clone repository
git clone https://github.com/your-org/glowing-fiesta.git
cd glowing-fiesta

# Install dependencies
composer install --no-dev --optimize-autoloader

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 2. Configure Environment

Edit `.env` file with production values:

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=glowing_fiesta
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password

# Queue (Redis recommended)
QUEUE_CONNECTION=redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379

# Cache
CACHE_DRIVER=redis

# Session (not used in API-only mode)
SESSION_DRIVER=array

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=info

# Rate Limiting
RATE_LIMIT_DEFAULT=1000

# Retention
RETENTION_DAYS=90
```

### 3. Database Setup

```bash
# Run migrations
php artisan migrate --force

# Seed with initial API key (optional)
php artisan db:seed --class=ApiKeySeeder
```

**Database Schema Creation:**

The migrations will create:
- `metrics` table
- `metric_points` table
- `api_keys` table
- Required indexes

**Performance Tuning (MySQL):**

Add to MySQL configuration (`my.cnf`):

```ini
[mysqld]
# InnoDB settings
innodb_buffer_pool_size = 2G        # 50-70% of available RAM
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2  # Acceptable durability for metrics
innodb_flush_method = O_DIRECT

# Connection settings
max_connections = 200
wait_timeout = 300
interactive_timeout = 300

# Query cache (optional)
query_cache_type = 1
query_cache_size = 64M

# Slow query log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2
```

### 4. Create API Keys

```bash
# Generate a new API key
php artisan make:api-key "Production Key" --rate-limit=5000

# Output will include the API key (save this securely!)
# API Key Created: abc123...
# Name: Production Key
# Rate Limit: 5000 requests/minute
```

**Important:** API keys are shown only once during creation. Store them securely.

## Web Server Configuration

### Nginx Configuration

Create `/etc/nginx/sites-available/glowing-fiesta`:

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.yourdomain.com;
    root /var/www/glowing-fiesta/public;
    
    index index.php;
    
    # SSL Configuration
    ssl_certificate /etc/ssl/certs/yourdomain.crt;
    ssl_certificate_key /etc/ssl/private/yourdomain.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Request size limit
    client_max_body_size 2M;
    
    # Logging
    access_log /var/log/nginx/glowing-fiesta-access.log;
    error_log /var/log/nginx/glowing-fiesta-error.log;
    
    # PHP-FPM configuration
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Timeout settings
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/glowing-fiesta /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Apache Configuration

Create `/etc/apache2/sites-available/glowing-fiesta.conf`:

```apache
<VirtualHost *:80>
    ServerName api.yourdomain.com
    Redirect permanent / https://api.yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName api.yourdomain.com
    DocumentRoot /var/www/glowing-fiesta/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/yourdomain.crt
    SSLCertificateKeyFile /etc/ssl/private/yourdomain.key
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5
    
    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    
    # Request size limit
    LimitRequestBody 2097152
    
    <Directory /var/www/glowing-fiesta/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/glowing-fiesta-error.log
    CustomLog ${APACHE_LOG_DIR}/glowing-fiesta-access.log combined
</VirtualHost>
```

Enable required modules and site:

```bash
sudo a2enmod rewrite ssl headers
sudo a2ensite glowing-fiesta
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## Queue Worker Setup

### Supervisor Configuration

Create `/etc/supervisor/conf.d/glowing-fiesta-worker.conf`:

```ini
[program:glowing-fiesta-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/glowing-fiesta/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopascompletable=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/glowing-fiesta/storage/logs/worker.log
stopwaitsecs=3600
```

Start the workers:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start glowing-fiesta-worker:*
```

**Scaling Workers:**

Adjust `numprocs` based on:
- CPU cores available
- Queue depth during peak times
- Database connection pool size

**Monitoring Workers:**

```bash
# Check worker status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart glowing-fiesta-worker:*

# View logs
tail -f /var/www/glowing-fiesta/storage/logs/worker.log
```

## Cron Jobs

Add to crontab for Laravel scheduler:

```bash
# Edit crontab
sudo crontab -e -u www-data

# Add this line
* * * * * cd /var/www/glowing-fiesta && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler will handle:
- Retention cleanup jobs
- Health check tasks
- Metric aggregation (if using rollup tables)

## Optimization

### PHP-FPM Configuration

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock

# Connection pool
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

# Timeouts
request_terminate_timeout = 300s

# Process management
slowlog = /var/log/php8.2-fpm-slow.log
request_slowlog_timeout = 10s
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm
```

### Laravel Optimization

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### OPcache Configuration

Edit `/etc/php/8.2/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.fast_shutdown=1
```

## Monitoring

### Health Check Endpoint

Create a health check endpoint:

```bash
# Add to routes/api.php
Route::get('/health', function() {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String()
    ]);
});
```

Configure load balancer to use: `GET /api/health`

### Application Monitoring

Monitor these metrics:
- Request rate and latency
- Queue depth and processing time
- Database connection pool usage
- Worker process health
- Disk space (especially for MySQL data directory)
- Memory usage

**Recommended Tools:**
- Laravel Horizon (for Redis queues)
- New Relic or Datadog for APM
- MySQL slow query log analysis
- Nginx/Apache access logs

### Database Monitoring

```bash
# Check MySQL connection count
mysql -e "SHOW STATUS LIKE 'Threads_connected';"

# Check InnoDB buffer pool usage
mysql -e "SHOW ENGINE INNODB STATUS\G" | grep -A 20 "BUFFER POOL"

# Monitor slow queries
tail -f /var/log/mysql/slow-query.log
```

## Backup Strategy

### Database Backups

**Daily backup script:**

```bash
#!/bin/bash
# /usr/local/bin/backup-glowing-fiesta.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/backups/glowing-fiesta
DB_NAME=glowing_fiesta
DB_USER=backup_user
DB_PASS=backup_password

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u$DB_USER -p$DB_PASS \
    --single-transaction \
    --quick \
    --lock-tables=false \
    $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Retain only last 7 days
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
```

Add to crontab:

```bash
# Daily backup at 2 AM
0 2 * * * /usr/local/bin/backup-glowing-fiesta.sh >> /var/log/glowing-fiesta-backup.log 2>&1
```

### Application Backup

```bash
# Backup .env file (encrypt this!)
tar -czf /backups/glowing-fiesta/env_backup.tar.gz /var/www/glowing-fiesta/.env

# Backup storage directory
tar -czf /backups/glowing-fiesta/storage_backup.tar.gz /var/www/glowing-fiesta/storage
```

## Scaling

### Horizontal Scaling

**Application Servers:**

1. Deploy multiple instances behind a load balancer
2. Ensure session driver is set to `array` (stateless)
3. Share `.env` configuration across instances
4. Use shared Redis for cache and queue

**Load Balancer Configuration (Example - HAProxy):**

```
frontend http_front
    bind *:80
    redirect scheme https code 301 if !{ ssl_fc }

frontend https_front
    bind *:443 ssl crt /etc/ssl/certs/yourdomain.pem
    default_backend app_servers

backend app_servers
    balance roundrobin
    option httpchk GET /api/health
    http-check expect status 200
    server app1 10.0.1.10:80 check
    server app2 10.0.1.11:80 check
    server app3 10.0.1.12:80 check
```

### Database Scaling

**Read Replicas:**

For read-heavy workloads, configure read replicas:

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => ['replica1.example.com', 'replica2.example.com'],
    ],
    'write' => [
        'host' => ['primary.example.com'],
    ],
    // ... other config
]
```

## Security Checklist

- [ ] HTTPS enforced (no HTTP access)
- [ ] Strong SSL/TLS configuration
- [ ] API keys stored as hashes
- [ ] `.env` file permissions restricted (600)
- [ ] Database credentials secured
- [ ] Firewall configured (only necessary ports open)
- [ ] Rate limiting enabled
- [ ] Request size limits enforced
- [ ] Security headers configured
- [ ] Regular security updates applied
- [ ] Logs monitored for suspicious activity
- [ ] Backups encrypted and tested

## Troubleshooting

### High Database Load

**Symptoms:** Slow API responses, high CPU on database server

**Solutions:**
1. Check slow query log
2. Add missing indexes
3. Enable query result caching
4. Consider read replicas
5. Implement rollup tables

### Queue Backup

**Symptoms:** Growing queue depth, delayed processing

**Solutions:**
1. Increase number of workers
2. Check worker logs for errors
3. Optimize job processing logic
4. Increase worker memory limit

### Out of Memory

**Symptoms:** 502 errors, worker crashes

**Solutions:**
1. Increase PHP memory limit
2. Reduce batch sizes
3. Optimize database queries (avoid loading too much data)
4. Add more application servers

### Connection Pool Exhaustion

**Symptoms:** "Too many connections" errors

**Solutions:**
1. Increase MySQL `max_connections`
2. Optimize application database connection usage
3. Configure proper connection timeouts
4. Use persistent connections wisely

## Production Checklist

Before going live:

- [ ] All environment variables configured
- [ ] Database migrations run
- [ ] API keys created and distributed
- [ ] SSL certificates installed and valid
- [ ] Web server configuration tested
- [ ] Queue workers running
- [ ] Cron jobs configured
- [ ] Monitoring in place
- [ ] Backup strategy implemented
- [ ] Load testing completed
- [ ] Security audit performed
- [ ] Documentation reviewed
- [ ] Rollback plan prepared
- [ ] Support team trained

## Support

For deployment issues, contact the DevOps team or refer to:
- [Architecture Documentation](ARCHITECTURE.md)
- [API Documentation](API.md)
- [Testing Guide](TESTING.md)

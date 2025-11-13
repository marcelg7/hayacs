# TR-069 ACS Deployment Guide

Complete guide to deploying your TR-069 ACS to production.

## 🚀 Quick Production Deployment

### Prerequisites
- PHP 8.2+
- Composer
- Web server (Apache/Nginx)
- Database (MySQL/PostgreSQL recommended for production)
- SSL certificate (for HTTPS)

### Step 1: Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Configure your database
# Edit .env and set:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tr069_acs
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Step 2: Install Dependencies

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 3: Web Server Configuration

#### Nginx Configuration

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-acs-domain.com;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-acs-domain.com;

    root /var/www/hay-acs/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # CWMP endpoint - allow larger payloads
    location /cwmp {
        client_max_body_size 10M;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Increase timeouts for CWMP
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName your-acs-domain.com
    Redirect permanent / https://your-acs-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-acs-domain.com
    DocumentRoot /var/www/hay-acs/public

    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key

    <Directory /var/www/hay-acs/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Increase timeouts for CWMP
    Timeout 300
    ProxyTimeout 300
</VirtualHost>
```

### Step 4: Configure TR-069 Devices

Point your CPE devices to:
```
ACS URL: https://your-acs-domain.com/cwmp
```

Optional (if using HTTP Digest authentication):
```
ACS Username: your_acs_username
ACS Password: your_acs_password
```

## 🧪 Testing Before Production

### 1. Test with Simulator

```bash
# Test TR-098 device
php simulate-device.php --url https://your-acs-domain.com/cwmp

# Test TR-181 device
php simulate-device.php --url https://your-acs-domain.com/cwmp --tr181

# Test with custom manufacturer
php simulate-device.php --manufacturer Acme --model Router5G
```

### 2. Verify Dashboard

Visit `https://your-acs-domain.com` and verify:
- ✅ Dashboard loads correctly
- ✅ Statistics are displayed
- ✅ Simulated devices appear in device list
- ✅ Device details page works
- ✅ Parameters are stored

### 3. Test REST API

```bash
# Get statistics
curl https://your-acs-domain.com/api/stats

# List devices
curl https://your-acs-domain.com/api/devices

# Create a task
curl -X POST https://your-acs-domain.com/api/devices/DEVICE-ID/reboot
```

## 🔐 Security Hardening

### 1. Enable API Authentication (Optional)

Add to your `.env`:
```env
API_AUTH_ENABLED=true
API_KEY=your-secure-random-key
```

### 2. Enable CWMP Authentication (Optional)

For HTTP Digest authentication, you would add middleware to verify credentials from devices.

### 3. Firewall Rules

```bash
# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Restrict CWMP endpoint to known IPs (optional)
# In nginx/apache configuration, add IP whitelist for /cwmp
```

### 4. Database Security

```sql
-- Create dedicated database user with limited privileges
CREATE USER 'tr069_acs'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON tr069_acs.* TO 'tr069_acs'@'localhost';
FLUSH PRIVILEGES;
```

## 📊 Monitoring & Maintenance

### Application Logs

```bash
# View Laravel logs
tail -f storage/logs/laravel.log

# Filter for CWMP activity
grep "CWMP" storage/logs/laravel.log

# Filter for errors
grep "ERROR" storage/logs/laravel.log
```

### Database Maintenance

```bash
# Backup database
mysqldump -u root -p tr069_acs > backup_$(date +%Y%m%d).sql

# Clean old sessions (older than 30 days)
php artisan tinker
>>> DB::table('cwmp_sessions')->where('ended_at', '<', now()->subDays(30))->delete();

# Clean completed tasks (older than 7 days)
>>> DB::table('tasks')->where('status', 'completed')->where('completed_at', '<', now()->subDays(7))->delete();
```

### Performance Monitoring

Monitor these metrics:
- Number of online/offline devices
- Pending tasks count
- Average Inform frequency
- Database size growth
- Web server response times

## 🔄 Auto-Provisioning Configuration

Edit `app/Services/ProvisioningService.php` to customize:

### By Manufacturer
```php
private function getManufacturerRules(?string $manufacturer): array
{
    return match (strtolower($manufacturer)) {
        'acme' => [
            'type' => 'set_params',
            'parameters' => [
                'values' => [
                    'Device.ManagementServer.PeriodicInformInterval' => '180',
                    // Add your ACME-specific settings
                ],
            ],
        ],
        'yourvendor' => [
            // Your vendor rules
        ],
        default => [],
    };
}
```

### By Location/Tag
```php
private function getTagRules(string $tag): array
{
    return match (strtolower($tag)) {
        'office' => [
            'type' => 'set_params',
            'parameters' => [
                'values' => [
                    'Device.WiFi.SSID.1.SSID' => 'Office-WiFi',
                ],
            ],
        ],
        default => [],
    };
}
```

## 📈 Scaling Considerations

### For 1,000+ Devices

1. **Use PostgreSQL or MySQL** instead of SQLite
2. **Add Redis** for session caching
3. **Enable Queue Workers** for background tasks
   ```bash
   php artisan queue:work --daemon
   ```
4. **Use Laravel Horizon** for queue monitoring

### For 10,000+ Devices

1. **Load Balancer** with multiple ACS instances
2. **Dedicated Database Server**
3. **Redis Cluster** for distributed caching
4. **Separate Queue Workers** on different servers

## 🐛 Troubleshooting

### Devices Not Connecting

**Check:**
1. Is /cwmp endpoint accessible?
   ```bash
   curl -X POST https://your-acs-domain.com/cwmp
   ```
2. Are devices configured with correct ACS URL?
3. Check firewall/security groups
4. Review web server error logs

### Tasks Not Executing

**Check:**
1. Are tasks marked as "pending"?
   ```bash
   php artisan tinker
   >>> Task::where('status', 'pending')->count()
   ```
2. Is device online and connecting?
3. Check device's PeriodicInformInterval

### High Database Size

**Solutions:**
1. Clean old sessions and tasks (see Database Maintenance)
2. Archive old parameter history
3. Implement parameter value change detection (only store on change)

## 📞 Support & Resources

- **TR-069 Specification:** Broadband Forum TR-069
- **Data Models:** TR-098 (IGD), TR-181 (Device:2)
- **Laravel Documentation:** https://laravel.com/docs

## ✅ Production Checklist

Before going live:

- [ ] Database configured (MySQL/PostgreSQL)
- [ ] SSL certificate installed
- [ ] Web server configured (Nginx/Apache)
- [ ] Environment variables set (.env)
- [ ] Migrations run
- [ ] Cache optimized (config, routes, views)
- [ ] Firewall configured
- [ ] Backups configured
- [ ] Monitoring set up
- [ ] Tested with simulator
- [ ] Tested with real device
- [ ] Documentation reviewed
- [ ] Auto-provisioning rules configured

## 🎉 Ready to Deploy!

Your TR-069 ACS is now ready for production deployment!

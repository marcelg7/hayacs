# Slack Interactive Buttons - Fix TODO

## Current Status
- **One-way notifications**: ✅ Working (feedback posts to Slack)
- **Interactive buttons**: ❌ 403 Forbidden error

## Problem
Slack interactive buttons send POST requests to `https://hayacs.hay.net/webhooks/slack/interaction` from dynamic AWS IP addresses that are not in our IP whitelist.

## What's Been Tried

### 1. .htaccess SetEnvIf with THE_REQUEST
- **Result**: Failed - `THE_REQUEST` is not available to `SetEnvIf` in .htaccess

### 2. RewriteRule with [E=] flag to set environment variable
- **Result**: Failed - `mod_authz_core` runs before `mod_rewrite`, so env vars aren't available

### 3. `<If>` directive in .htaccess
- **Result**: Failed - `<If>` doesn't override `<RequireAll>` block

### 4. `<Location>` directive in VirtualHost
- **Result**: Failed - Directory-level .htaccess restrictions still applied

### 5. Moved IP restrictions from .htaccess to VirtualHost only
- **Current state**: IP restrictions now only in VirtualHost `<Directory>` block
- `<Location /webhooks/slack>` with `Require all granted` added before Directory
- **Result**: Still getting 403 errors

## Current Configuration

### /etc/httpd/conf.d/hayacs-le-ssl.conf
```apache
# Location blocks before Directory
<Location /webhooks/slack>
    Require all granted
</Location>

<Directory /var/www/hayacs/public>
    AllowOverride All
    Options -Indexes +FollowSymLinks
    <RequireAny>
        Require ip 163.182.0.0/16
        Require ip 104.247.0.0/16
        Require ip 45.59.0.0/16
        Require ip 136.175.0.0/16
        Require ip 206.130.0.0/16
        Require ip 23.155.0.0/16
    </RequireAny>
</Directory>
```

### /var/www/hayacs/public/.htaccess
- IP restrictions removed
- Only rewrite rules remain
- Has comment noting IP restrictions moved to VirtualHost

## Next Steps to Investigate

1. **Check Apache logs** to see exact request details:
   ```bash
   sudo grep -i "webhooks/slack" /var/log/httpd/hayacs-access.log | tail -20
   ```

2. **Test with curl** from an external IP to verify behavior:
   ```bash
   curl -X POST https://hayacs.hay.net/webhooks/slack/interaction \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -d "payload=%7B%22test%22%3A%22data%22%7D"
   ```

3. **Possible causes**:
   - Apache merge order may not work as expected with `AllowOverride All`
   - The .htaccess may be re-enabling restrictions somehow
   - There may be another config file applying restrictions

4. **Alternative approaches**:
   - Use `<Location>` with higher precedence (before all Directory blocks)
   - Try `<LocationMatch>` instead of `<Location>`
   - Consider using `mod_proxy` to reverse proxy the webhook to a different port
   - Set up a separate VirtualHost just for webhooks

## Files Modified
- `/var/www/hayacs/public/.htaccess` - Removed IP restrictions
- `/etc/httpd/conf.d/hayacs-le-ssl.conf` - Added Location blocks, IP restrictions in Directory

## Related Code
- `app/Http/Controllers/SlackWebhookController.php` - Handles webhooks
- `app/Services/SlackService.php` - Sends notifications
- `bootstrap/app.php` - CSRF exception for webhook routes

#!/bin/bash
# Remove HTTP->HTTPS redirect from Apache VirtualHost
# Let .htaccess handle it instead

echo "Backing up current Apache config..."
cp /etc/httpd/conf.d/hayacs.conf /etc/httpd/conf.d/hayacs.conf.backup2

echo "Updating Apache config (removing HTTPS redirect)..."
cat > /etc/httpd/conf.d/hayacs.conf << 'EOF'
<VirtualHost *:80>
    ServerName hayacs.hay.net
    ServerAdmin admin@haymail.ca
    DocumentRoot /var/www/hayacs/public

    <Directory /var/www/hayacs/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # Laravel-specific: Deny access to sensitive files
    <Directory /var/www/hayacs/storage>
        Require all denied
    </Directory>

    <Directory /var/www/hayacs/bootstrap/cache>
        Require all denied
    </Directory>

    ErrorLog /var/log/httpd/hayacs-error.log
    CustomLog /var/log/httpd/hayacs-access.log combined

    # NO HTTP->HTTPS redirect here - handled in .htaccess instead
    # This allows .htaccess to properly exclude /cwmp before any redirects

</VirtualHost>
EOF

echo "Testing Apache configuration..."
apachectl configtest

if [ $? -eq 0 ]; then
    echo "Apache config is valid. Reloading Apache..."
    systemctl reload httpd
    echo "Done! Apache reloaded successfully."
    echo ""
    echo "Testing /cwmp endpoint (should get 401 Unauthorized, not 301 redirect)..."
    sleep 1
    curl -v -X POST http://hayacs.hay.net/cwmp 2>&1 | grep -E "HTTP/|Location:" | head -5
else
    echo "ERROR: Apache config test failed. Restoring backup..."
    cp /etc/httpd/conf.d/hayacs.conf.backup2 /etc/httpd/conf.d/hayacs.conf
    echo "Backup restored. Please check the error above."
fi

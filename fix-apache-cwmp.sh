#!/bin/bash
# Fix Apache to allow /cwmp over HTTP by checking environment variable

echo "Backing up current Apache config..."
cp /etc/httpd/conf.d/hayacs.conf /etc/httpd/conf.d/hayacs.conf.backup

echo "Updating Apache config..."
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
    CustomLog /var/www/hayacs/public/hayacs-access.log combined

    # Redirect HTTP to HTTPS, but NOT for /cwmp endpoint
    # Check for CWMP_REQUEST environment variable set by .htaccess
    RewriteEngine on
    RewriteCond %{SERVER_NAME} =hayacs.hay.net
    RewriteCond %{ENV:REDIRECT_CWMP_REQUEST} !^1$
    RewriteCond %{REQUEST_URI} !^/cwmp
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]

</VirtualHost>
EOF

echo "Testing Apache configuration..."
apachectl configtest

if [ $? -eq 0 ]; then
    echo "Apache config is valid. Reloading Apache..."
    systemctl reload httpd
    echo "Done! Apache reloaded successfully."
    echo "Testing /cwmp endpoint..."
    sleep 1
    curl -v -X POST http://hayacs.hay.net/cwmp 2>&1 | grep -E "HTTP/|Location:"
else
    echo "ERROR: Apache config test failed. Restoring backup..."
    cp /etc/httpd/conf.d/hayacs.conf.backup /etc/httpd/conf.d/hayacs.conf
    echo "Backup restored. Please check the error above."
fi

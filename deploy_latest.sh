#!/bin/bash

echo "=================================================="
echo "  Hay ACS - Deployment Script"
echo "=================================================="
echo ""

# Exit on any error
set -e

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo "📥 Step 1: Pulling latest code from Git..."
git pull origin master
echo "✅ Code updated"
echo ""

echo "📦 Step 2: Installing/Updating Composer dependencies..."
composer install --no-dev --optimize-autoloader
echo "✅ Dependencies updated"
echo ""

echo "🗄️  Step 3: Running database migrations..."
php artisan migrate --force
echo "✅ Migrations completed"
echo ""

echo "🧹 Step 4: Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo "✅ Caches cleared"
echo ""

echo "⚡ Step 5: Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "✅ Application optimized"
echo ""

echo "🔗 Step 6: Ensuring storage link exists..."
php artisan storage:link 2>/dev/null || echo "Storage link already exists"
echo "✅ Storage configured"
echo ""

echo "🔄 Step 7: Restarting queue workers (if any)..."
php artisan queue:restart 2>/dev/null || echo "No queue workers to restart"
echo "✅ Queue workers restarted"
echo ""

echo "=================================================="
echo "  ✅ Deployment completed successfully!"
echo "=================================================="
echo ""
echo "Application is now running the latest version."

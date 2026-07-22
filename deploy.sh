#!/bin/bash

# Deployment script for Wawi Kadio
echo "Starting deployment for Wawi Kadio..."

# Pull latest changes from git
# git pull origin main

# Install composer dependencies
echo "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Run database migrations
echo "Running Database Migrations..."
php artisan migrate --force

# Install NPM dependencies
echo "Installing NPM dependencies..."
npm ci

# Build frontend assets
echo "Building Frontend Assets..."
npm run build

# Link Storage
echo "Linking Storage..."
php artisan storage:link

# Clear and cache configurations
echo "Optimizing Laravel Config & Routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart PM2 services
echo "Restarting PM2 Services..."
npx pm2 reload ecosystem.config.js || npx pm2 start ecosystem.config.js

echo "Deployment complete!"

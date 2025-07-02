#!/bin/bash

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "Error: PHP is not installed. Please install PHP 8.1 or higher."
    exit 1
fi

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "Error: Composer is not installed. Please install Composer."
    exit 1
fi

# Install dependencies if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    composer install
fi

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "Creating .env file..."
    cp .env.example .env
    php artisan key:generate
fi

# Create database directories if they don't exist
mkdir -p storage/app/uploads

# Set proper permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Create storage link if it doesn't exist
if [ ! -d "public/storage" ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

# Run migrations
echo "Running database migrations..."
php artisan migrate

# Start the development server
echo "Starting the development server..."
php artisan serve --host=0.0.0.0 --port=8000 
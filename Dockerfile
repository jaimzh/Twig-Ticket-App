# Minimal PHP image with the built-in web server
FROM php:8.2-cli-alpine

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps (no dev), using the lockfile for reproducible builds
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader

# Copy the rest of your app
COPY . .

# Render will inject PORT; default to 10000 so it runs locally too
ENV PORT=10000

# Start PHP built-in server, serving the public/ directory
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t public"]

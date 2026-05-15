FROM dunglas/frankenphp:php8.4-bookworm

# Install PHP extensions required by the app.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip mysqli pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies before copying the full app for better layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy the application code.
COPY . .

# Ensure runtime directories exist and are writable by the web server user.
RUN mkdir -p /app/uploads/advisers /app/uploads/id_photos /app/uploads/templates /app/uploads/theme \
    && chown -R www-data:www-data /app/uploads \
    && chmod -R 775 /app/uploads

ENV PORT=8080
EXPOSE 8080

# Railway provides PORT at runtime, so use a shell to expand it.
CMD ["sh", "-c", "exec frankenphp php-server --root /app --listen :${PORT}"]

FROM php:8.1-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Remove default Apache files and copy application
RUN rm -rf /var/www/html/*
COPY . /var/www/html/

# Configure Apache VirtualHost template to serve DRANHS portal
RUN cat > /etc/apache2/sites-available/000-default.conf.template <<'EOF'
<VirtualHost *:${PORT}>
    ServerAdmin admin@dranhs.local
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
        
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteBase /
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^ index.php [QSA,L]
        </IfModule>
    </Directory>

    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Create startup script that initializes DB then starts Apache
RUN cat > /usr/local/bin/start.sh <<'SCRIPT'
#!/bin/bash
set -e

PORT="${PORT:-80}"

# Railway injects PORT at runtime, so Apache must be configured here instead of at build time.
sed "s/\${PORT}/${PORT}/g" /etc/apache2/sites-available/000-default.conf.template > /etc/apache2/sites-available/000-default.conf
cat > /etc/apache2/ports.conf <<EOF
Listen ${PORT}

<IfModule ssl_module>
    Listen 443
</IfModule>

<IfModule mod_gnutls.c>
    Listen 443
</IfModule>
EOF
# Auto-initialize database on first boot (safe — uses IF NOT EXISTS)
if [ -n "$MYSQLHOST" ]; then
    echo "Railway MySQL detected. Running database setup..."
    php /var/www/html/setup_db.php || echo "DB setup encountered an issue (may already be initialized)."
fi
# Start Apache
exec apache2-foreground
SCRIPT
RUN chmod +x /usr/local/bin/start.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/EMS2/uploads 2>/dev/null || true

# Default PORT for local Docker (Railway overrides this)
ENV PORT=80
EXPOSE 80

# Start via startup script
CMD ["/usr/local/bin/start.sh"]

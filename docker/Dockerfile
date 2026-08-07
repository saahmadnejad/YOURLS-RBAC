FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install \
    mysqli \
    pdo_mysql \
    gd \
    mbstring \
    intl \
    ctype \
    dom

# Enable Apache mod_rewrite and allow .htaccess overrides
RUN a2enmod rewrite \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/Options Indexes FollowSymLinks/Options Indexes FollowSymLinks/' /etc/apache2/sites-available/000-default.conf

# Clone YOURLS
WORKDIR /var/www/html
RUN git clone --depth 1 https://github.com/YOURLS/YOURLS.git /tmp/yourls \
    && cp -a /tmp/yourls/. /var/www/html/ \
    && rm -rf /tmp/yourls \
    && chown -R www-data:www-data /var/www/html

# Install Composer and YOURLS dependencies
# --ignore-platform-req=php bypasses YOURLS's platform: {php: "8.1.0"} override
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer update --no-dev --optimize-autoloader --ignore-platform-req=php

# Create RBAC plugin directory (plugin files mounted as volume in compose)
RUN mkdir -p /var/www/html/user/plugins/rbac

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]

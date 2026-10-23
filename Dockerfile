# Use the existing LimeSurvey image as the source of application files
FROM acspri/limesurvey:6.5.12 AS limesurvey-source

# Use PHP 8.1 backports with CVE-2026-109618 / CVE-2026-109619 fixes
FROM ghcr.io/mpyw/php-eol-cve-2026-109618-109619:8.1-apache-bookworm

# Copy LimeSurvey from the source image
COPY --from=limesurvey-source /var/www/html/ /var/www/html/

# Install the PHP extensions and system libraries required by LimeSurvey
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        unzip \
        libc-client-dev \
        libfreetype6-dev \
        libmcrypt-dev \
        libpng-dev \
        libjpeg-dev \
        libldap-common \
        libldap2-dev \
        zlib1g-dev \
        libkrb5-dev \
        libtidy-dev \
        libzip-dev \
        libsodium-dev \
        libpq-dev \
        libonig-dev \
        netcat-openbsd; \
    rm -rf /var/lib/apt/lists/*; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        gd \
        mysqli \
        mbstring \
        pgsql \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        opcache \
        zip \
        iconv \
        tidy; \
    docker-php-ext-configure ldap --with-libdir="lib/$(gcc -dumpmachine)"; \
    docker-php-ext-install -j"$(nproc)" ldap; \
    docker-php-ext-configure imap --with-imap-ssl --with-kerberos; \
    docker-php-ext-install -j"$(nproc)" imap; \
    docker-php-ext-install -j"$(nproc)" sodium; \
    pecl install mcrypt-1.0.6; \
    docker-php-ext-enable mcrypt; \
    docker-php-ext-install -j"$(nproc)" exif

# Enable Apache URL rewriting
RUN a2enmod rewrite

# Recommended OPcache settings
RUN { \
       echo 'opcache.memory_consumption=128'; \
       echo 'opcache.interned_strings_buffer=8'; \
       echo 'opcache.max_accelerated_files=4000'; \
       echo 'opcache.revalidate_freq=2'; \
       echo 'opcache.fast_shutdown=1'; \
       echo 'opcache.enable_cli=1'; \
   } > "$PHP_INI_DIR/conf.d/opcache-recommended.ini"

# Set up LimeSurvey's writable directories and persistent configuration
RUN set -eux; \
    chown -R www-data:www-data /var/www/html; \
    chmod -R ug=rx /var/www/html; \
    mkdir -p \
        /var/lime/application/config \
        /var/lime/upload \
        /var/lime/plugins \
        /var/lime/sessions; \
    cp -dpR /var/www/html/application/config/. /var/lime/application/config/; \
    cp -dpR /var/www/html/upload/. /var/lime/upload/; \
    cp -dpR /var/www/html/plugins/. /var/lime/plugins/; \
    chown -R www-data:www-data \
        /var/lime/application \
        /var/lime/plugins \
        /var/lime/upload \
        /var/lime/sessions

# PHP defaults for LimeSurvey
RUN { \
       echo 'memory_limit=256M'; \
       echo 'upload_max_filesize=128M'; \
       echo 'post_max_size=128M'; \
       echo 'max_execution_time=120'; \
       echo 'max_input_vars=10000'; \
       echo 'date.timezone=UTC'; \
       echo 'session.gc_maxlifetime=86400'; \
       echo 'session.save_path="/var/lime/sessions"'; \
   } > "$PHP_INI_DIR/conf.d/limesurvey.ini"

# Persistent data
VOLUME ["/var/www/html/plugins"]
VOLUME ["/var/www/html/upload"]
VOLUME ["/var/lime/sessions"]
VOLUME ["/var/www/html/application/config"]

# Entrypoint
COPY --chmod=755 docker-entrypoint.sh /bin/docker-entrypoint.sh
RUN ln -sf /usr/local/bin/docker-entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]

# System timezone
ENV TZ=Europe/Zurich

RUN ln -snf "/usr/share/zoneinfo/$TZ" /etc/localtime \
    && echo "$TZ" > /etc/timezone

# PHP timezone
RUN printf 'date.timezone=%s\n' "$TZ" \
    > "$PHP_INI_DIR/conf.d/zz-timezone.ini"

# Deny access to selected files in the web root
RUN printf '%s\n' \
    '<FilesMatch "^(gulpfile\.js|open-api-gen\.php|setdebug\.php|psalm.*)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    >> /var/www/html/.htaccess

# Copy the necessary files into the project volumes
COPY config/. /var/www/html/application/config/
COPY plugins/. /var/www/html/plugins/
COPY upload/. /var/www/html/upload/

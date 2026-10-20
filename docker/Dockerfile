FROM php:8.4-apache-trixie

ENV TZ=Europe/Zurich
ENV LANG=en_US.UTF-8
ENV LANGUAGE=en_US:en
ENV LC_ALL=en_US.UTF-8

# Add Sury repository for libc-client-dev / IMAP support on Debian Trixie
RUN curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ trixie main" > /etc/apt/sources.list.d/php.list

# Install PHP extension installer helper
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

# Install OS packages, set locales, timezone, and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    locales \
    unzip \
    netcat-openbsd \
    curl \
    vim \
    && rm -rf /var/lib/apt/lists/* \
    && echo "en_US.UTF-8 UTF-8" > /etc/locale.gen \
    && locale-gen en_US.UTF-8 \
    && update-locale LANG=en_US.UTF-8 \
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && install-php-extensions \
    gd \
    mysqli \
    mbstring \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    opcache \
    zip \
    iconv \
    tidy \
    sodium \
    ldap \
    imap \
    mcrypt \
    redis

# PHP Security Hardening
RUN { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
    } > /etc/apache2/conf-available/security-hardening.conf \
    && a2enconf security-hardening

RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'opcache.fast_shutdown=1'; \
        echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN { \
        echo 'expose_php = Off'; \
        echo "date.timezone=$TZ"; \
        echo 'memory_limit=256M'; \
        echo 'upload_max_filesize=128M'; \
        echo 'post_max_size=128M'; \
        echo 'max_execution_time=120'; \
        echo 'max_input_vars=10000'; \
        echo 'session.gc_maxlifetime=86400'; \
        echo 'session.save_path="/var/lime/sessions"'; \
    } > /usr/local/etc/php/conf.d/limesurvey.ini

# Download, extract LimeSurvey dynamically using the VERSION file
COPY VERSION /tmp/LIMESURVEY_VERSION
RUN set -eux; \
    FULL_VERSION="$(tr -d ' \t\r\n' </tmp/LIMESURVEY_VERSION)"; \
    LIMESURVEY_TAG="${FULL_VERSION%%-rev*}"; \
    curl -SL "https://download.limesurvey.org/latest-master/limesurvey${LIMESURVEY_TAG}.zip" -o /tmp/lime.zip; \
    unzip /tmp/lime.zip -d /tmp; \
    mv /tmp/lime*/* /var/www/html/; \
    mv /tmp/lime*/.[a-zA-Z]* /var/www/html/; \
    rm /tmp/lime.zip; \
    rmdir /tmp/lime*; \
    rm -f /tmp/LIMESURVEY_VERSION; \
    chown -R www-data:www-data /var/www/html; \
    chmod -R ug=rx /var/www/html

# Setup internal fallback directories for volume initialization
RUN mkdir -p /var/lime/application/config /var/lime/upload /var/lime/plugins /var/lime/tmp /var/lime/sessions \
    && chown -R www-data:www-data /var/lime/sessions \
    && cp -dpR /var/www/html/application/config/* /var/lime/application/config \
    && cp -dpR /var/www/html/upload/* /var/lime/upload \
    && cp -dpR /var/www/html/plugins/* /var/lime/plugins \
    && cp -dpR /var/www/html/tmp/* /var/lime/tmp \
    && chown -R www-data:www-data /var/lime/application /var/lime/plugins /var/lime/tmp /var/lime/upload \
    && chmod -R ug+rwx /var/lime/tmp /var/www/html/tmp

# Apache RemoteIP and Rewrite configuration
RUN a2enmod rewrite remoteip && \
    { \
      echo "RemoteIPHeader X-Real-IP" ; \
      echo "RemoteIPTrustedProxy 10.0.0.0/8" ; \
      echo "RemoteIPTrustedProxy 172.16.0.0/12" ; \
      echo "RemoteIPTrustedProxy 192.168.0.0/16" ; \
    } > /etc/apache2/conf-available/remoteip.conf && \
    a2enconf remoteip

# Deny access to sensitive files in .htaccess
RUN printf '%s\n' \
    '<FilesMatch "^(gulpfile\.js|open-api-gen\.php|setdebug\.php|psalm.*)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    >> /var/www/html/.htaccess

# Copy your local project configurations, plugins, and uploads
COPY ./config/. /var/www/html/application/config
COPY ./plugins/. /var/www/html/plugins
COPY ./upload/. /var/www/html/upload

# Fix permissions on newly copied custom assets
RUN chown -R www-data:www-data /var/www/html/application/config /var/www/html/plugins /var/www/html/upload

# Copy entrypoint script
COPY docker/docker-entrypoint.sh /bin/docker-entrypoint.sh

ENTRYPOINT ["/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]

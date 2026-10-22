FROM php:8.4-apache-trixie

ENV TZ=Europe/Zurich
ENV LANG=en_US.UTF-8
ENV LANGUAGE=en_US:en
ENV LC_ALL=en_US.UTF-8

# Add Sury repository for libc-client-dev / IMAP support
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

# Configure PHP settings for LimeSurvey
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

# Limit Apache version disclosure
RUN { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
    } > /etc/apache2/conf-available/security-hardening.conf \
    && a2enconf security-hardening

# Apache RemoteIP and Rewrite configuration
RUN a2enmod rewrite remoteip && \
    { \
      echo "RemoteIPHeader X-Real-IP" ; \
      echo "RemoteIPTrustedProxy 10.0.0.0/8" ; \
      echo "RemoteIPTrustedProxy 172.16.0.0/12" ; \
      echo "RemoteIPTrustedProxy 192.168.0.0/16" ; \
    } > /etc/apache2/conf-available/remoteip.conf && \
    a2enconf remoteip

# Apache must listen on an unprivileged port since the container runs as an unprivileged user
RUN sed -i -E 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

# Create unprivileged user and set permissions for Apache runtime directories
RUN groupadd -r dockeruser --gid=1000 && \
    useradd -r -g dockeruser --uid=1000 --create-home --home-dir=/home/dockeruser --shell=/sbin/nologin dockeruser && \
    sed -i 's/: ${APACHE_RUN_USER:=www-data}/: ${APACHE_RUN_USER:=dockeruser}/' /etc/apache2/envvars && \
    sed -i 's/: ${APACHE_RUN_GROUP:=www-data}/: ${APACHE_RUN_GROUP:=dockeruser}/' /etc/apache2/envvars && \
    mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2 && \
    chown -R dockeruser:dockeruser /var/run/apache2 /var/lock/apache2 /var/log/apache2

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
    chown -R dockeruser:dockeruser /var/www/html; \
    chmod -R u+rwX,go=rX /var/www/html

# Setup internal fallback directories for volume initialization
RUN mkdir -p /var/lime/application/config /var/lime/upload /var/lime/plugins /var/lime/tmp /var/lime/sessions \
    && chown -R dockeruser:dockeruser /var/lime/sessions \
    && cp -dpR /var/www/html/application/config/* /var/lime/application/config \
    && cp -dpR /var/www/html/upload/* /var/lime/upload \
    && cp -dpR /var/www/html/plugins/* /var/lime/plugins \
    && cp -dpR /var/www/html/tmp/* /var/lime/tmp \
    && chown -R dockeruser:dockeruser /var/lime/application /var/lime/plugins /var/lime/tmp /var/lime/upload \
    && chmod -R u+rwX,go=rX /var/lime/tmp /var/www/html/tmp

# Deny access to sensitive files in .htaccess
RUN printf '%s\n' \
    '<FilesMatch "^(open-api-gen\.php|setdebug\.php)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    >> /var/www/html/.htaccess

# Copy your local project plugins
COPY ./plugins/. /var/www/html/plugins
RUN chown -R dockeruser:dockeruser /var/www/html/plugins \
    && chmod -R u+rwX,go=rX /var/www/html/plugins

# The base image symlinks Apache logs to /dev/stdout and /dev/stderr; those
# /proc/self/fd links are not resolvable by an unprivileged user, so use
# regular files instead (tail them with: docker exec lime-app tail -f /var/log/apache2/*.log)
RUN rm -f /var/log/apache2/access.log /var/log/apache2/error.log /var/log/apache2/other_vhosts_access.log \
    && touch /var/log/apache2/access.log /var/log/apache2/error.log /var/log/apache2/other_vhosts_access.log \
    && chown dockeruser:dockeruser /var/log/apache2/*.log

# The entrypoint (running unprivileged) rewrites this file on every start
RUN touch /usr/local/etc/php/conf.d/sessions.ini \
    && chown dockeruser:dockeruser /usr/local/etc/php/conf.d/sessions.ini

# Copy entrypoint script
COPY config/docker-entrypoint.sh /bin/docker-entrypoint.sh
RUN chmod +x /bin/docker-entrypoint.sh

# Switch to unprivileged user
USER dockeruser

EXPOSE 8080

ENTRYPOINT ["/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]

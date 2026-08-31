FROM acspri/limesurvey:6.5.12

ENV TZ=Europe/Zurich

# Set system timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Set PHP timezone
RUN printf 'date.timezone=%s\n' "$TZ" \
    > "$PHP_INI_DIR/conf.d/zz-timezone.ini"

# Deny access to specific files in the web root
RUN printf '%s\n' \
    '<FilesMatch "^(gulpfile\.js|open-api-gen\.php|setdebug\.php|psalm.*)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    >> /var/www/html/.htaccess

# Copy the necessary files into the project volumes
COPY ./config/. /var/www/html/application/config
COPY ./plugins/. /var/www/html/plugins
COPY ./upload/. /var/www/html/upload

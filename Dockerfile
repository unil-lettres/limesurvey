FROM acspri/limesurvey:6.15.5

# Copy the necessary files into the project volumes
COPY ./config/. /var/www/html/application/config
COPY ./plugins/. /var/www/html/plugins
COPY ./upload/. /var/www/html/upload

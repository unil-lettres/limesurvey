#!/bin/bash
set -eu

cd /var/www/html

# usage: file_env VAR [DEFAULT]
#    ie: file_env 'XYZ_DB_PASSWORD' 'example'
# (will allow for "$XYZ_DB_PASSWORD_FILE" to fill in the value of
#  "$XYZ_DB_PASSWORD" from a file, especially for Docker's secrets feature)
file_env() {
    local var="$1"
    local fileVar="${var}_FILE"
    local def="${2:-}"
    if [ "${!var:-}" ] && [ "${!fileVar:-}" ]; then
        echo >&2 "error: both $var and $fileVar are set (but are exclusive)"
        exit 1
    fi
    local val="$def"
    if [ "${!var:-}" ]; then
        val="${!var}"
    elif [ "${!fileVar:-}" ]; then
        val="$(< "${!fileVar}")"
    fi
    export "$var"="$val"
    unset "$fileVar"
}

# Quote a value as a PHP single-quoted string literal
php_str() {
    local s
    s="$(printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/\\\\'/g")"
    printf "'%s'" "$s"
}

# Replaces the key's value if present, otherwise adds it to the 'config' array.
set_config() {
    local key="$1" value="$2"
    if grep -q "'$key'[[:space:]]*=>" application/config/config.php; then
        KEY="$key" VAL="$value" awk '
            BEGIN { pat = "^[[:space:]]*\047" ENVIRON["KEY"] "\047[[:space:]]*=>" }
            $0 ~ pat && !done {
                idx = index($0, "=>")
                $0 = substr($0, 1, idx + 1) " " ENVIRON["VAL"] ","
                done = 1
            }
            { print }' application/config/config.php > application/config/config.tmp
        mv application/config/config.tmp application/config/config.php
    else
        LINE="        '$key' => $value," awk -v pat="'config'[[:space:]]*=>[[:space:]]*array" \
            '{ print } $0 ~ pat && !done { print ENVIRON["LINE"]; done = 1 }' application/config/config.php > application/config/config.tmp
        mv application/config/config.tmp application/config/config.php
    fi
}

# Download a CA certificate if it is the one requested through MYSQL_SSL_CA
download_ca() {
    local file="$1" url="$2"
    if [ "$MYSQL_SSL_CA" = "$file" ] && ! [ -e "$file" ]; then
        echo "Downloading $file"
        if curl -o "$file" -fsL "$url"; then
            echo "Downloaded successfully"
        else
            echo "Failed to download certificate - continuing anyway"
        fi
    fi
}

if [[ "${1:-}" == apache2* ]] || [ "${1:-}" == php-fpm ]; then
    file_env 'LIMESURVEY_DB_TYPE' 'mysql'
    file_env 'LIMESURVEY_DB_HOST' 'mysql'
    file_env 'LIMESURVEY_DB_PORT' '3306'
    file_env 'LIMESURVEY_TABLE_PREFIX' ''
    file_env 'LIMESURVEY_ADMIN_NAME' 'Lime Administrator'
    file_env 'LIMESURVEY_ADMIN_EMAIL' 'lime@lime.lime'
    file_env 'LIMESURVEY_ADMIN_USER' ''
    file_env 'LIMESURVEY_ADMIN_PASSWORD' ''
    file_env 'LIMESURVEY_FROM_EMAIL' "$LIMESURVEY_ADMIN_EMAIL"
    file_env 'LIMESURVEY_SMTP_HOST' ''
    file_env 'LIMESURVEY_SMTP_USER' ''
    file_env 'LIMESURVEY_SMTP_PASSWORD' ''
    file_env 'LIMESURVEY_SMTP_SSL' ''
    file_env 'LIMESURVEY_DEBUG' '0'
    file_env 'LIMESURVEY_SMTP_DEBUG' ''
    file_env 'LIMESURVEY_SQL_DEBUG' '0'
    file_env 'MYSQL_SSL_CA' ''
    file_env 'LIMESURVEY_USE_INNODB' ''
    file_env 'LIMESURVEY_USE_DB_SESSIONS' ''
    file_env 'LIMESURVEY_DONT_SHOW_SCRIPT_NAME' ''
    file_env 'LIMESURVEY_PHP_SESSION_SAVE_HANDLER' ''
    file_env 'LIMESURVEY_PHP_SESSION_SAVE_PATH' ''
    file_env 'LIMESURVEY_DONT_UPDATE' ''
    file_env 'LIMESURVEY_API_MODE' 'off'

    if [ -z "$LIMESURVEY_DONT_UPDATE" ]; then
        # if we're linked to MySQL and thus have credentials already, let's use them
        file_env 'LIMESURVEY_DB_USER' "${MYSQL_ENV_MYSQL_USER:-root}"
        if [ "$LIMESURVEY_DB_USER" = 'root' ]; then
            file_env 'LIMESURVEY_DB_PASSWORD' "${MYSQL_ENV_MYSQL_ROOT_PASSWORD:-}"
        else
            file_env 'LIMESURVEY_DB_PASSWORD' "${MYSQL_ENV_MYSQL_PASSWORD:-}"
        fi
        file_env 'LIMESURVEY_DB_NAME' "${MYSQL_ENV_MYSQL_DATABASE:-limesurvey}"
        if [ -z "$LIMESURVEY_DB_PASSWORD" ]; then
            echo >&2 'error: missing required LIMESURVEY_DB_PASSWORD environment variable'
            echo >&2 '  Did you forget to -e LIMESURVEY_DB_PASSWORD=... ?'
            echo >&2
            echo >&2 '  (Also of interest might be LIMESURVEY_DB_USER and LIMESURVEY_DB_NAME.)'
            exit 1
        fi

        # Must run before copying into the config dir
        chmod ug+w -R application/config

        echo >&2 "Copying default container default config files into config volume..."
        cp -dpRf /var/lime/application/config/* application/config

        if ! [ -e plugins/index.html ]; then
            echo >&2 "No index.html file in plugins dir in $(pwd) Copying defaults..."
            cp -dpRf /var/lime/plugins/* plugins
        fi

        if ! [ -e tmp/index.html ]; then
            echo >&2 "No index.html file in tmp dir in $(pwd) Copying defaults..."
            cp -dpRf /var/lime/tmp/* tmp
        fi

        if ! [ -e upload/index.html ]; then
            echo >&2 "No index.html file upload dir in $(pwd) Copying defaults..."
            cp -dpRf /var/lime/upload/* upload
        fi

        if ! [ -e application/config/config.php ]; then
            echo >&2 "No config file in $(pwd) Copying default config file..."
            # Copy the sample config, adding a placeholder for db attributes (filled in below)
            sed -e "/^[[:space:]]*'tablePrefix'[[:space:]]*=>/a\\            'attributes' => array()," \
                application/config/config-sample-mysql.php > application/config/config.php
        fi

        LIMESURVEY_DB_CHARSET="utf8mb4"
        if [ "$LIMESURVEY_DB_TYPE" = "pgsql" ]; then
            LIMESURVEY_DB_CHARSET="utf8"
        fi

        set_config 'connectionString' "$(php_str "$LIMESURVEY_DB_TYPE:host=$LIMESURVEY_DB_HOST;port=$LIMESURVEY_DB_PORT;dbname=$LIMESURVEY_DB_NAME;")"
        set_config 'charset' "$(php_str "$LIMESURVEY_DB_CHARSET")"
        set_config 'tablePrefix' "$(php_str "$LIMESURVEY_TABLE_PREFIX")"
        set_config 'username' "$(php_str "$LIMESURVEY_DB_USER")"
        set_config 'password' "$(php_str "$LIMESURVEY_DB_PASSWORD")"
        set_config 'urlFormat' "'path'"
        set_config 'debug' "$LIMESURVEY_DEBUG"
        set_config 'debugsql' "$LIMESURVEY_SQL_DEBUG"
        set_config 'RPCInterface' "$(php_str "$LIMESURVEY_API_MODE")"
        if [ -n "$LIMESURVEY_DONT_SHOW_SCRIPT_NAME" ]; then
            set_config 'showScriptName' "false"
        else
            set_config 'showScriptName' "true"
        fi

        if [ -n "$MYSQL_SSL_CA" ]; then
            set_config 'attributes' "array(PDO::MYSQL_ATTR_SSL_CA => $(php_str "/var/www/html/$MYSQL_SSL_CA"), PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false)"
        fi

        # Remove session line if exists
        sed -i "/^[[:space:]]*'session'/d" application/config/config.php
        if [ -n "$LIMESURVEY_USE_DB_SESSIONS" ]; then
            # Add session line before the commented DbHttpSession example
            awk '/^[[:space:]]*.urlManager.[[:space:]]*=>/ && c == 0 { c = 1; system("cat") } { print }' application/config/config.php > application/config/config.tmp <<'EOPHP'
    'session' => array ('class' => 'application.core.web.DbHttpSession', 'connectionID' => 'db', 'sessionTableName' => '{{sessions}}', 'autoCreateSessionTable' => false, ),
EOPHP
            mv application/config/config.tmp application/config/config.php
        fi
    fi

    download_ca 'DigiCertGlobalRootG2.crt.pem' 'https://cacerts.digicert.com/DigiCertGlobalRootG2.crt.pem'
    download_ca 'global-bundle.pem' 'https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem'

    if [ -n "$LIMESURVEY_PHP_SESSION_SAVE_HANDLER" ] && [ -n "$LIMESURVEY_PHP_SESSION_SAVE_PATH" ]; then
        echo "Configuring custom session handler for PHP"
        printf 'session.save_handler = "%s"\nsession.save_path = "%s"\n' \
            "$LIMESURVEY_PHP_SESSION_SAVE_HANDLER" "$LIMESURVEY_PHP_SESSION_SAVE_PATH" > /usr/local/etc/php/conf.d/sessions.ini
    else
        : > /usr/local/etc/php/conf.d/sessions.ini
    fi

    if [ -z "$LIMESURVEY_DONT_UPDATE" ]; then
        DBENGINE='MyISAM'
        if [ -n "$LIMESURVEY_USE_INNODB" ]; then
            # Read by MysqlSchema and by the install command (via DBENGINE)
            set_config 'mysqlEngine' "'InnoDB'"
            DBENGINE='InnoDB'
        fi

        # Default SMTP settings, overridden below when LIMESURVEY_SMTP_HOST is set
        set_config 'emailmethod' "'mail'"
        set_config 'emailsmtphost' "'localhost'"
        set_config 'siteadminemail' "'your-email@example.net'"
        set_config 'siteadminbounce' "'your-email@example.net'"
        set_config 'siteadminname' "'Your Name'"
        set_config 'emailsmtpuser' "''"
        set_config 'emailsmtppassword' "''"
        set_config 'emailsmtpssl' "''"
        set_config 'emailsmtpdebug' "''"
        if [ -n "$LIMESURVEY_SMTP_HOST" ]; then
            set_config 'emailmethod' "'smtp'"
            set_config 'emailsmtphost' "$(php_str "$LIMESURVEY_SMTP_HOST")"
            set_config 'siteadminemail' "$(php_str "$LIMESURVEY_ADMIN_EMAIL")"
            set_config 'siteadminbounce' "$(php_str "$LIMESURVEY_FROM_EMAIL")"
            set_config 'siteadminname' "$(php_str "$LIMESURVEY_ADMIN_NAME")"
            if [ -n "$LIMESURVEY_SMTP_USER" ] && [ -n "$LIMESURVEY_SMTP_PASSWORD" ]; then
                set_config 'emailsmtpuser' "$(php_str "$LIMESURVEY_SMTP_USER")"
                set_config 'emailsmtppassword' "$(php_str "$LIMESURVEY_SMTP_PASSWORD")"
            fi
            if [ -n "$LIMESURVEY_SMTP_SSL" ]; then
                set_config 'emailsmtpssl' "$(php_str "$LIMESURVEY_SMTP_SSL")"
            fi
            if [ -n "$LIMESURVEY_SMTP_DEBUG" ]; then
                set_config 'emailsmtpdebug' "1"
            fi
        fi

        # Owner (app user) must keep write access (e.g. LSSodium writes security.php);
        # only group/other get read-only access
        chmod u+rwX,go=rX -R application/config
        chmod ug=rwx -R tmp
        chmod ug=rwx -R upload
        mkdir -p upload/surveys
        mkdir -p /var/lime/sessions
        chmod ug=rwx -R /var/lime/sessions
    fi

    # The following lines are borrowed from martialblog's entrypoint.sh
    # see: https://github.com/martialblog/docker-limesurvey/blob/master/6.0/apache/entrypoint.sh
    # Check if LimeSurvey database is provisioned

    # Check if database is available
    until nc -z -v -w30 "$LIMESURVEY_DB_HOST" "$LIMESURVEY_DB_PORT"
    do
        echo "Info: Waiting for database connection..."
        sleep 5
    done

    if [ -z "$LIMESURVEY_DONT_UPDATE" ]; then
        echo 'Info: Check if database already provisioned. Nevermind the stack trace.'
        php application/commands/console.php updatedb || MUST_CREATE_DB=true

        if [ -v MUST_CREATE_DB ] && [ -n "$LIMESURVEY_ADMIN_USER" ] && [ -n "$LIMESURVEY_ADMIN_PASSWORD" ]; then
            echo >&2 'Database not yet populated - installing Limesurvey database'
            DBENGINE=$DBENGINE php application/commands/console.php install "$LIMESURVEY_ADMIN_USER" "$LIMESURVEY_ADMIN_PASSWORD" "$LIMESURVEY_ADMIN_NAME" "$LIMESURVEY_ADMIN_EMAIL" verbose
        fi

        if [ -n "$LIMESURVEY_ADMIN_USER" ] && [ -n "$LIMESURVEY_ADMIN_PASSWORD" ]; then
            echo >&2 'Updating password for admin user'
            php application/commands/console.php resetpassword "$LIMESURVEY_ADMIN_USER" "$LIMESURVEY_ADMIN_PASSWORD"
        fi

        # Flush asssets (clear cache on restart)
        php application/commands/console.php flushassets
    fi
fi

# Relay Apache's file-based logs to the container's stdout/stderr
tail -n 0 -f /var/log/apache2/access.log >&1 &
tail -n 0 -f /var/log/apache2/error.log >&2 &

exec "$@"

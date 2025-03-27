
# Introduction

Docker-compose file and application settings for the LimeSurvey server
installation and configuration.

[LimeSurvey](https://www.limesurvey.org) is a free online survey tool.

The docker compose file uses a [third party docker
image](https://hub.docker.com/r/acspri/limesurvey) from docker hub.

## Run the server for development

Copy the `example.env` to `.env` (`cp example.env .env`) and update the values
as wished.

Execute `docker compose up -d` to run the server.
It will be served at `http://localhost:8087`.
You can access the admin interface at `http://localhost:8087/admin`.

**Warning:** you must use `localhost` to access the server and not `127.0.0.1`.
Accessing the server through `127.0.0.1` causes the explorer of survey ressources not working correctly.

## Deployment for production

Copy the `example.env` to `.env` (`cp example.env .env`) and update the values
as wished.

Make sure to copy & rename the **docker-compose.override.yml.prod** file to
**docker-compose.override.yml**.

`cp docker-compose.override.yml.prod docker-compose.override.yml`

Execute `docker compose up -d` to run the server. It will be served at
port `8087` by default.

**Warning:** If you run Docker behind a proxy that redirects to `localhost`, be
sure to specify `localhost` and not `127.0.0.1`. Using `127.0.0.1` will not
working with the explorer of survey ressources.

## Add the organizeSurvey plugin

Install the plugin through Configuration -> Plugins -> Scan files.
Click install in regards of `organizeSurvey`.

Next, find the plugin in the plugins list and activate it.

## Add custom themes

To add a custom theme, go to Admin -> Configuration -> Themes and click `Upload & install`.
You must chose a zip file containing the new theme.
Themes available in the themes folder are not automatically installed in LimeSurvey.

# Upgrade

[Official documentation](https://manual.limesurvey.org/Upgrading_from_a_previous_version)

If you want to upgrade LimeSurvey to a new version, change the base image version
in the `docker-compose.yml` file to the desired one, then push the changes.

## Deploy changes

Run the following commands (backup your database) from the repo directory:

```bash
docker compose down
git pull origin main
docker compose pull
docker compose up -d
```

Connect to the admin interface to check if a database upgrade is required (the
prompt will show as soon as you open the admin interface).

Check that the custom theme `faculte_lettres` is still installed. Install it
otherwise.

Check that the organizeSurvey plugin is still installed and activated. Install
and / or activate it otherwise.

# Configuration
## Main configuration

The config file is automatically updated with the values set in the `.env` file
the first time you launch the server. You can modify this file as wished for
the config values not available in environment variables.

The main config file is under `config/config.php`.

### Encrypted data

If you use encrypted data, you must update the `config/security.php` file and
set the required values. You can find an example of the values to set in the
`config/config-defaults.php` file, search for the `encryptionkeypair` keywords.

# Information about Apple Silicon

## Rosetta Emulation
If you activated emulation in Docker, you must not use the "rosetta" emulation
in the "in development" settings section. PHP will raise an error that will
crash the `entrypoint.sh` script.

To check if PHP works as intended, execute `php --version` in the app[^1]
container. You should not see the rosetta section as shown below:

```bash
PHP 8.0.15 (cli) (built: Jan 26 2022 17:33:13) ( NTS )
Copyright (c) The PHP Group
Zend Engine v4.0.15, Copyright (c) Zend Technologies
    with Zend OPcache v8.0.15, Copyright (c), by Zend Technologies
rosetta error: futex(FUTEX_LOCK_PI_PRIVATE) failure: 35
Trace/breakpoint trap
```
[^1]: You must override the entrypoint of the container to be able to access
    it. To do so, add `entrypoint: tail -f /dev/null` in the docker compose
    file under the lime-app service.


## Problem with file permission on MacOs

The version `>=6.3` don't works on MacOs with Docker (ok on Ubuntu). There is a
problem with files permissions. The `/usr/local/bin/docker-entrypoint.sh` try to
copy a default folders structure into the working folders structure and keeps
the original files permissions that do not have write permission. These folders
are bound to the host machine. If the host machine is on MacOS, the script will
fail with the following error:

`cp -dpRf /var/lime/application/config/* application/config`
`cp: setting permissions for 'application/config/rest': Permission denied`

It seems like somewhat MacOS will prevent `cp` to change the file permissions.

For local environment, change the binded folders in favor of a docker volume.

You will have to manually copy plugins inside the `data-plugins` volume.

```diff
diff --git a/docker-compose.yml b/docker-compose.yml
  index 244ed1e..c924f7a 100644
  --- a/docker-compose.yml
  +++ b/docker-compose.yml
  @@ -2,9 +2,9 @@ services:
     lime-app:
       image: acspri/limesurvey:6.5.12
       volumes:
  -      - ./upload:/var/www/html/upload
  -      - ./plugins:/var/www/html/plugins
  -      - ./config:/var/www/html/application/config
  +      - data-upload:/var/www/html/upload
  +      - data-plugins:/var/www/html/plugins
  +      - data-config:/var/www/html/application/config
       ports:
         - "127.0.0.1:8087:80"
       depends_on:
  @@ -26,3 +26,6 @@ services:
   
   volumes:
     db-data:
  +  data-upload:
  +  data-plugins:
  +  data-config:

```

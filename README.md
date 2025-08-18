# Introduction

Docker-compose file and application settings for the LimeSurvey server installation and configuration.

[LimeSurvey](https://www.limesurvey.org) is a free online survey tool.

The docker compose file uses a [third party docker image](https://hub.docker.com/r/acspri/limesurvey) from docker hub as base image.

## Run the server for development

Copy the `example.env` to `.env` (`cp example.env .env`) and update the values as wished.

Make sure to copy & rename the **docker-compose.override.yml.dev** file to **docker-compose.override.yml**.

`cp docker-compose.override.yml.dev docker-compose.override.yml`

Execute `docker compose up` (add -d if you want to run in the background and silence the logs) to run the server.

To access the application admin please use the following link.

[http://localhost:8087/admin](http://localhost:8087/admin)

+ admin / secret

**Warning:** you must use `localhost` to access the server and not `127.0.0.1`. Accessing the server through `127.0.0.1` causes the explorer of survey ressources not working correctly.

## Deployment for production

Copy the `example.env` to `.env` (`cp example.env .env`) and update the values as wished.

Make sure to copy & rename the **docker-compose.override.yml.prod** file to **docker-compose.override.yml**.

`cp docker-compose.override.yml.prod docker-compose.override.yml`

Execute `docker compose up -d` to run the server. It will be served at port `8087` by default.

**Warning:** If you run Docker behind a proxy that redirects to `localhost`, be sure to specify `localhost` and not `127.0.0.1`. Using `127.0.0.1` will not working with the explorer of survey ressources.

## Add the organizeSurvey plugin

Install the plugin through Configuration -> Plugins -> Scan files. Click install in regards of `organizeSurvey`.

Next, find the plugin in the plugins list and activate it.

## Add custom themes

To add a custom theme, go to Admin -> Configuration -> Themes and click `Upload & install`.
You must chose a zip file containing the new theme.
Themes available in the themes folder are not automatically installed in LimeSurvey.

# Upgrade

[Official documentation](https://manual.limesurvey.org/Upgrading_from_a_previous_version)

If you want to upgrade LimeSurvey to a new version, change the base image version in the `Dockerfile` to the desired one, then push the changes.

## Deploy changes

Run the following commands (backup your database) from the repo directory:

```bash
docker compose down
git pull origin main
docker compose pull
docker compose up -d
```

Connect to the admin interface to check if a database upgrade is required (the prompt will show as soon as you open the admin interface).

Check that the custom theme `faculte_lettres` is still installed. Install it otherwise.

Check that the organizeSurvey plugin is still installed and activated. Install and / or activate it otherwise.

# Docker images

Changes in the `development` branch will create new images tagged `latest-dev` & `latest-stage`, while changes in the `main` branch will create images tagged `latest` and the defined limesurvey version.

# Configuration
## Main configuration

The config file is automatically updated with the values set in the `.env` file the first time you launch the server. You can modify this file as wished for the config values not available in environment variables.

The main config file is under `config/config.php`.

### Encrypted data

If you use encrypted data, you must update the `config/security.php` file and set the required values. You can find an example of the values to set in the `config/config-defaults.php` file, search for the `encryptionkeypair` keywords.

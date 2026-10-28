# Introduction

Docker configuration files and application settings for the LimeSurvey server installation and configuration.

[LimeSurvey](https://www.limesurvey.org) is a free online survey tool.

# Development with Docker

## Docker installation

A working [Docker](https://docs.docker.com/engine/install/) installation is mandatory.

## Environment files

Copy & rename the following environment files.

```bash
cp env.example .env
cp db.env.example db.env
```

You can replace the values if needed, but the default ones should work for local development.

Make sure to copy & rename the **docker-compose.override.yml.dev** file to **docker-compose.override.yml**.

``cp docker-compose.override.yml.dev docker-compose.override.yml``

You can replace the values if needed, but the default ones should work for local development.

## Installation & configuration

Build & run all the containers for this project.

``docker compose up`` (add -d if you want to run in the background and silence the logs)

## Frontends

To access the application admin please use the following link.

[http://localhost:8087/admin](http://localhost:8087/admin)

+ admin / password

**Warning:** you must use `localhost` to access the server and not `127.0.0.1`. Using `127.0.0.1` will not work with the explorer of survey resources.

# Deployment with Docker

## Environment files

```bash
cp env.example .env
cp db.env.example db.env
```

You should replace the values since the default ones are not ready for production.

Make sure to copy & rename the **docker-compose.override.yml.prod** file to **docker-compose.override.yml**.

`cp docker-compose.override.yml.prod docker-compose.override.yml`

You can replace the values if needed, but the default ones should work for production.

## Installation & configuration

Build & run all the containers for this project.

`docker compose up -d`

## Reverse proxy

Use a reverse proxy configuration to map the url to port `8087`.

**Warning:** If you run Docker behind a proxy that redirects to `localhost`, be sure to specify `localhost` and not `127.0.0.1`. Using `127.0.0.1` will not work with the explorer of survey resources.

## Add the organizeSurvey plugin

Install the plugin through Configuration → Plugins → Scan files. Click install in regard to `organizeSurvey`.

Next, find the plugin in the plugins list and activate it.

## Add custom themes

To add a custom theme, go to Admin → Configuration → Themes and click `Upload & install`. You must choose a zip file containing the new theme.

Themes available in the themes folder are not automatically installed in LimeSurvey.

# Application updates

We trigger a GitHub Actions workflow to build the custom image and push it to our [Docker Hub repository](https://hub.docker.com/r/unillett/limesurvey).

When a new [Limesurvey version](https://github.com/LimeSurvey/LimeSurvey/tags) is available, you should update the value in the [VERSION](https://github.com/unil-lettres/limesurvey/blob/development/VERSION) file to reference the new tag. When the change is pushed to the repository, the workflow will automatically build the new image and push it to [Docker Hub](https://hub.docker.com/r/unillett/limesurvey).

If you need to trigger a rebuild or mark a custom image version, you can add a revision suffix (such as `-rev1`) to the value in the `VERSION` file. This suffix will be ignored when fetching the BookStack source code, but it allows you to distinguish builds in your workflow and Docker image tags.

# Docker images

GitHub Actions workflows generate Docker image tags based on these events:
- Push to `development`: `dev-latest`, `stage-latest`
- Push to `main`: `latest`
- Push a git tag: `vX.Y.Z` (immutable, from VERSION)

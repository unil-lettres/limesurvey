# Changes

The only change these themes bring is displaying the Unil 'Faculté des lettres'
logo instead of the default one.

# Update

## Add compatibility

For updating these themes for a new version of LimeSurvey, just add a `version`
tag in the `config.xml` file of each theme.

[More on compatibility version](https://manual.limesurvey.org/Extension_compatibility)

## Update config.xml

You can make a diff between the original `config.xml` from the original theme and
the one of each theme to adapt them with new configuration (download the original
theme from the admin theme page).

## Install the new themes

Go to Admin -> Config -> Theme page.

First zip all files contained in the theme folder.

**Important: The theme name and identifier will be the name of the zip file
uploaded. Be sure to name your zip file with the name used in the `name` tag
from `config.xml` file (in the `metadata` tag).**

Next uninstall and delete (scroll down the theme admin page) the current theme.

Finally upload the zip from the theme admin page ('Upload & install').

#!/bin/sh

CDPATH= cd -- "$(dirname -- "$0")" || exit 1
exec php ../../../../vendor/bin/phpdoxy phpdoxy_config.xml

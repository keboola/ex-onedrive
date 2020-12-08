#!/usr/bin/env bash

set -o errexit          # Exit on most errors (see the manual)
set -o errtrace         # Make sure any error trap is inherited
set -o nounset          # Disallow expansion of unset variables
set -o pipefail         # Use last non-zero exit code in a pipeline
#set -o xtrace          # Trace the execution of the script (debug)

# Load env variables from .env file, but not overwrite the existing one
if [ -f ".env" ]; then
  source <(grep -v '^#' .env | sed -E 's|^([^=]+)=(.*)$|: ${\1=\2}; export \1|g')
fi

# Required environment variables
: "${OAUTH_APP_NAME:?Need to set OAUTH_APP_NAME env variable}"

# Constants
SCRIPT=$(realpath "$0")
SCRIPT_DIR=$(dirname "$SCRIPT")
SCRIPT_FILENAME=$(basename "$SCRIPT")
AZ_CLI_IMG="mcr.microsoft.com/azure-cli"

# If NOT run in the Docker container AND "az" executable not exists locally ...
if [ ! -f /.dockerenv ] && ! command -v az >/dev/null 2>&1; then
  # ... run script in Docker container
  echo "Running in Docker container ..."
  exec docker run \
    --rm -it \
    --volume "$SCRIPT_DIR:/utils" \
    -e OAUTH_APP_NAME \
    "$AZ_CLI_IMG" \
    "/utils/$SCRIPT_FILENAME"
fi

# Check if logged in, if not then login
if az account show >/dev/null 2>&1; then
  echo "You are already logged in!"  >&2
else
  az login --use-device-code >&2
  echo "You have been successfully logged in!"  >&2
fi

# List app info
echo "Getting info about app \"$OAUTH_APP_NAME\""
az ad app list \
    --output json \
    --filter "displayName eq '$OAUTH_APP_NAME'" "$@"
echo -e "\nDone\n"

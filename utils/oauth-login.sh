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
: "${OAUTH_APP_ID:?Need to set OAUTH_APP_ID env variable}"
: "${OAUTH_APP_SECRET:?Need to set OAUTH_APP_SECRET env variable}"

# Constants
SCRIPT=$(realpath "$0")
SCRIPT_DIR=$(dirname "$SCRIPT")
SCRIPT_FILENAME=$(basename "$SCRIPT")
BASH_UTILS_IMG="bretfisher/netshoot"
HTTP_SERVER_PORT="10000"
HTTP_SERVER_URI="http://localhost:$HTTP_SERVER_PORT"

# OAuth constants
OAUTH_AUTHORITY_URL='https://login.microsoftonline.com/common'
OAUTH_AUTHORIZE_ENDPOINT="$OAUTH_AUTHORITY_URL/oauth2/v2.0/authorize"
OAUTH_TOKEN_ENDPOINT="$OAUTH_AUTHORITY_URL/oauth2/v2.0/token"
OAUTH_SCOPE="offline_access User.Read Files.Read.All Sites.Read.All";
# Scopes required for testing
OAUTH_SCOPE="$OAUTH_SCOPE Files.ReadWrite.All Sites.ReadWrite.All";

printInfo() {
  echo
  echo -e "Please open \"$HTTP_SERVER_URI\" in your browser and click \"signing in\".\n";
  echo -e "You will be redirected to the login page.\n"
  echo -e "After you receive the OAuth tokens, you can close this script.\n"
}

main() {
  case $1 in
    "handle") handle;;
    "runServer") runServer;;
    *) echo "Unexpected command '$1'." 2>&1; exit 1;;
  esac
}

runServer() {
  # If NOT run in the Docker container AND "az" executable not exists locally ...
  # This part can be removed if all tools are locally installed
  if [ ! -f /.dockerenv ]; then
    echo "Running in Docker container ..."
    exec docker run \
      --rm -it \
      --volume "$SCRIPT_DIR:/utils" \
      -p "$HTTP_SERVER_PORT:$HTTP_SERVER_PORT" \
      -e OAUTH_APP_ID \
      -e OAUTH_APP_SECRET \
      "$BASH_UTILS_IMG" \
      "/utils/$SCRIPT_FILENAME"
  fi

  # Run http server
  printInfo
  exec socat "tcp-l:$HTTP_SERVER_PORT",reuseaddr,fork,crlf exec:"$0 handle"
}

debug() {
  echo -e "DEBUG: $1\n" 1>&2
}

handle() {
  # Read HTTP request from stdin: method path version
  read _ path _
  debug "Received http request '$path'"
  route "$path"
}

route() {
  path="$1"
  if [ "$path" == "/" ]; then render_start_page
  elif [ "$path" == "/sign-in" ]; then render_sign_in
  elif [[ "$path" =~ ^/sign-in/callback\?code=(.+)$ ]]; then render_sign_in_callback "${BASH_REMATCH[1]}"
  elif [[ "$path" =~ ^/sign-in/callback\?error=(.+)\&error_description=(.+)$ ]]; then render_error_page "$(urldecode "${BASH_REMATCH[1]}: ${BASH_REMATCH[2]}")"
  else render_404_page
  fi
}

urlencode() {
  local string="${1}"
  local strlen=${#string}
  local encoded=""
  local pos c o

  for (( pos=0 ; pos<strlen ; pos++ )); do
     c=${string:$pos:1}
     case "$c" in
        [-_.~a-zA-Z0-9] ) o="${c}" ;;
        * )               printf -v o '%%%02x' "'$c"
     esac
     encoded+="${o}"
  done
  echo "${encoded}"
}

urldecode() {
  : "${*//+/ }"; echo -e "${_//%/\\x}";
}

function get_authorize_url() {
  echo -n "$OAUTH_AUTHORIZE_ENDPOINT"
  echo -n "?client_id=$(urlencode "$OAUTH_APP_ID")"
  echo -n "&prompt=login"
  echo -n "&redirect_uri=$(urlencode "$HTTP_SERVER_URI/sign-in/callback")"
  echo -n "&scope=$(urlencode "$OAUTH_SCOPE")"
  echo -n "&response_type=code"
  echo -n "&response_mode=query"
}

function get_token_post_args() {
  authorization_code="$1"
  echo -n "client_id=$(urlencode "$OAUTH_APP_ID")"
  echo -n "&client_secret=$(urlencode "$OAUTH_APP_SECRET")"
  echo -n "&redirect_uri=$(urlencode "$HTTP_SERVER_URI/sign-in/callback")"
  echo -n "&code=$authorization_code"
  echo -n "&grant_type=authorization_code"
}

function render_404_page {
  echo 'HTTP/1.1 404 Not Found'
  echo 'Content-Type: text/html'
  echo ''
  echo '<html>'
  echo '<body>'
  echo '<h1>404 Not Found</h1>'
  echo "<p>Resource \"$path\" could not be found.</p>"
  echo '</body>'
}

function render_error_page {
  errorMsg="$1"
  echo 'HTTP/1.1 400 Bad Request'
  echo 'Content-Type: text/html'
  echo
  echo '<html>'
  echo '<body>'
  echo '<h1>An error occurred</h1>'
  echo '<p style="max-width: 600px;">'
  echo "$errorMsg"
  echo '</p>'
  echo '<p>'
  echo '<a href="/sign-in">Try signing in again</a>.'
  echo '</p>'
  echo '</body></html>'
}

function render_token_page {
  access_token="$1"
  refresh_token="$2"
  echo 'HTTP/1.1 400 Bad Request'
  echo 'Content-Type: text/html'
  echo
  echo '<html>'
  echo '<body>'
  echo '<h1>Hurray!</h1>'
  echo '<p><b>Please, add this envrioment variables to ".env" file:</b></p>'
  echo "<p style=\"max-width: 800px; word-wrap: break-word; word-break: break-all;\">"
  echo "<b>OAUTH_ACCESS_TOKEN</b>=<small>$access_token</small>"
  echo "</p>"
  echo "<p style=\"max-width: 800px; word-wrap: break-word; word-break: break-all;\">"
  echo "<b>OAUTH_REFRESH_TOKEN</b>=<small>$refresh_token</small>"
  echo "</p>"
  echo '<p>'
  echo '<a href="/sign-in">Sign in again.</a>.'
  echo '</p>'
  echo '</body></html>'
}

function render_start_page {
  echo 'HTTP/1.1 200 OK'
  echo 'Content-Type: text/html'
  echo
  echo '<html>'
  echo '<body>'
  echo '<h1>Hi from bash!</h1>'
  echo '<p>'
  echo 'This is a server running on socat and bash.'
  echo 'It can perform an OAuth 2 authorization code grant flow.'
  echo '</p>'
  echo '<p>'
  echo 'Try it now by'
  echo '<a href="/sign-in">signing in</a>.'
  echo '</p>'
  echo '</body></html>'
}

function render_sign_in {
  # Redirect to sign in page
  authorize_url=$(get_authorize_url)
  debug "Redirecting to: $authorize_url"
  echo 'HTTP/1.1 302 Found'
  echo "Location: $authorize_url"
}

function render_sign_in_callback {
  # Get token
  authorization_code="$1"
  post="$(get_token_post_args "$authorization_code")"
  response=$(curl -s -X POST -d "$post" "$OAUTH_TOKEN_ENDPOINT")

  # Handle error
  error=$(echo "$response" | jq -r ".error_description")
  if [ "$error" != "null" ]; then
    render_error_page "$error"
    debug "\nAn error occurred: \n$error\n\n"
    return
  fi

  # Print tokens
  access_token=$(echo "$response" | jq -r ".access_token")
  refresh_token=$(echo "$response" | jq -r ".refresh_token")
  render_token_page "$access_token" "$refresh_token"
  debug "The obtained tokens are displayed in a web browser."
}

# Command is first argument or default "runServer"
main "${1-runServer}"

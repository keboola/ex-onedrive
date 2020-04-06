# Keboola OneDrive Extractor

[![Build Status](https://travis-ci.com/keboola/ex-onedrive-v2.svg?branch=master)](https://travis-ci.com/keboola/ex-onedrive-v2)

Extracts spreadsheets from OneDrive

## Configuration

The configuration `config.json` contains following properties in `parameters` key: 

- `workbook` - object (required): Workbook `XLSX` file
   - One of [`driveId` and `fileId`] or `search` must be configured.
    - `driveId` - string: id of [drive resource](https://docs.microsoft.com/en-us/graph/api/resources/drive?view=graph-rest-1.0)    
    - `fileId` - string: id of [driveItem resource](https://docs.microsoft.com/en-us/graph/api/resources/driveitem?view=graph-rest-1.0)
    - `search` - string: in same format as in [Search Action](#search-action) 
- `worksheet` - object (required): Worksheet, one sheet from workbook's sheets
    - `name` - string (required): Name of the output CSV file
    - One of `id` or `position` must be configured.
    - `id` - string: id of [worksheet resource](https://docs.microsoft.com/en-us/graph/api/resources/worksheet?view=graph-rest-1.0)
    - `position` - int: worksheet position, first is 0, hidden sheets are included

**Examples of `config.json`**

Input sheet configured by IDs.
```json
{
  "authorization": {"oauth_api":  "..."},
  "parameters": {
    "workbook": {
      "driveId": "...",
      "fileId": "..."
    },
    "worksheet": {
      "name": "sheet-export",
      "id": "..."
    }
  }
}
```

Input sheet configured by `search`. Format is same as in [Search Action](#search-action).    
The number of search results must then be exactly one. 
Otherwise, an error is returned.
```json
{
  "authorization": {"oauth_api":  "..."},
  "parameters": {
    "workbook": {
      "search": "https://.../sharing/link/..."
    },
    "worksheet": {
      "name": "sheet-export",
      "position": 0
    }
  }
}
```


## Actions

Read more about actions [in KBC documentation](https://developers.keboola.com/extend/common-interface/actions/).

### Search Action

- Action `search` serves to find spreadsheet `XLSX` files and get their `driveId` and `fileId`.
- Obtained `driveId` and `fileId` can be later used to export file content.
- The search can result in none, one or more files.
- The input parameter `parameters.workbook.search` can take several forms:
  - **`/path/to/file.xlsx`**
    - The file is searched on a personal OneDrive that belongs to the logged-in account.
  - **`file.xlsx`**
    - The file is searched by name on:
        - personal OneDrive 
        - all shared files
        - all SharePoint sites
  - **`https://...`**
    - The file is searched by sharing link obtained from OneDrive.
    - The copied URL of an open OneDrive Excel file in should also work.
  - **`drive://{driveId}/path/to/file.xlsx`**
    - The file is searched on drive specified with `{driveId}`
    - The `{driveId}` value must be correctly url-encoded
  - **`site://{siteName}/path/to/file.xlsx`**
    - The file is searched on SharePoint site drive specified with `{siteName}`, eg. `Excel Sheets`
    - The `{siteName}` value must be correctly url-encoded

**Example `config.json`**:
```json
{
  "authorization": {"oauth_api":  "..."},
  "action": "search",
  "parameters": {
    "workbook": {
      "search": "https://.../sharing/link/from/OneDrive/...."
    }
  }
}

```

**Example result**:

*Note*: `path` is `null` if searching only by file name (`file.xlsx`) in all destinations.  
It prevents to sync-action timeout - paths loading would be slow.    
It is limitation of API - paths are not part of API search results. 

```json
{
   "files":[
      {
         "driveId":"...",
         "fileId":"...",
         "name":"one_sheet.xlsx",
         "path":"/path/to/folder"
      }
   ]
}
```

### Get Worksheets Action

Action `getWorksheets` serves to list all worksheets (tabs) from workbook `XSLX` file.

**Example `config.json`**:

Workbook is specified by `driveId` and `fileId`

```json
{
  "authorization": {"oauth_api":  "..."},
  "action": "getWorksheets",
  "parameters": {
    "workbook": {
      "driveId": "...",
      "fileId": "..."
    }
  }
}
```

Or it is specified by `search`, same as in [Search Action](#search-action).  
The number of search results must then be exactly one. 
Otherwise, an error is returned.

```json
{
  "authorization": {"oauth_api":  "..."},
  "action": "getWorksheets",
  "parameters": {
    "workbook": {
      "search": "(same as in search action)"
    }
  }
}
```


**Example result**:
```json
{
   "worksheets":[
      {
         "position":0,
         "name":"Hidden Sheet",
         "title":"Hidden Sheet (hidden)",
         "driveId":"...",
         "fileId":"...",
         "worksheetId":"...",
         "visible":false,
         "header":[
            "Col_1",
            "Col_2",
            "Col_3"
         ]
      }
   ]
}
```
## Development

For development it is necessary to:
  - Have an [Application in Microsoft identity platform](#application-in-microsoft-identity-platform)
    - Env variables: `OAUTH_APP_NAME`, `OAUTH_APP_ID`, `OAUTH_APP_SECRET`
    - You can use script to create app: `utils/oauth-app-setup.sh` 
    - Permissions (scopes): 
        - Component itself needs: `offline_access User.Read Files.Read.All Sites.Read.All`
        - For development and run tests: `Files.ReadWrite.All Sites.ReadWrite.All`
  - Be logged in some OneDrive Business (Office 365) Account and have [OAuth tokens](#oauth-tokens)
    - Env variables: `OAUTH_ACCESS_TOKEN`, `OAUTH_REFRESH_TOKEN`, `TEST_SHAREPOINT_SITE`
    - To log in you can use script: `utils/oauth-login.sh` 

### Application in Microsoft identity platform 

- Component uses [Microsoft Graph API](https://developer.microsoft.com/en-us/graph) to connect to user's OneDrive.
- So for development you need access to some Microsoft application:
    - If you are Keboola employee, you can use existing app `ex-onedrive-dev-test`. Credentials are stored in [1Password](https://1password.com).
    - Or if you have work account on [portal.azure.com](https://portal.azure.com), you can create new app by `utils/oauth-app-setup.sh`
    - Or you can have personal account on [portal.azure.com](https://portal.azure.com). App can be created manually in `App registrations` section.
- To access all types of accounts (personal / work / school):
    - Property `signInAudience` must be set to `AzureADandPersonalMicrosoftAccount`. 
    - You can check it in Azure Portal, in app detail, in `Manifest` section.
- At least one `Redirect URIs` must be set:
    - Open `portal.azure.com` -> `App registrations` -> app-name -> `Authentication`
    - In `Web` -> `Redirect URIs` click `Add URI`
    - For development you should add `http://localhost:10000/sign-in/callback`.
    - Click `Save`
- If you have an application set, please store credentials in `.env` file.
```.env
OAUTH_APP_NAME=my-app-name
OAUTH_APP_ID=...
OAUTH_APP_SECRET=...
```

### OAuth tokens

- OAuth tokens are result of login to specific OneDrive account.
- OAuth login is not part of this repository. It is done in other parts of KBC, see [OAuth 2.0 Authentication](https://developers.keboola.com/extend/generic-extractor/configuration/api/authentication/oauth20/).
- Component uses the OAuth tokens to authorize to Graph API.
- The `access_token` and `refresh_token` are part of `config.json` in `authorization.oauth_api.credentials.#data`.
- Component uses `refresh_token` (expires in 90 days) to generate new `access_token` (expires in 1 hour).
- For development / tests you must obtain this token manually:
    1. Setup environment variables `OAUTH_APP_NAME`, `OAUTH_APP_ID`, `OAUTH_APP_SECRET`
        - If are present in `.env` file, the script loads them.
    2. Run script `utils/oauth-login.sh`
    3. Follow the instructions (open the URL and login)
    4. Save tokens to `.env` file
 
### Workspace setup

Clone this repository and init the workspace with following command:

```sh
git clone https://github.com/keboola/ex-onedrive-v2
cd ex-onedrive-v2
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

Create `.env` file with following variables (from the previous steps)
```env
OAUTH_APP_NAME=
OAUTH_APP_ID=
OAUTH_APP_SECRET=
OAUTH_ACCESS_TOKEN=
OAUTH_REFRESH_TOKEN=
TEST_SHAREPOINT_SITE=(optional)
```

Run the test suite using this command:

```sh
docker-compose run --rm dev composer tests
```

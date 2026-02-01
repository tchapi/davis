# Davis API

## API Version 1

### Open Endpoints

Open endpoints require no Authentication.

* [Health](v1/health.md) : `GET /api/v1/health`

### Endpoints that require Authentication

Closed endpoints require a valid `X-Davis-API-Token` to be included in the header of the request. Token needs to be configured in .env file (as a environment variable `API_KEY`) and can be generated using `php bin/console api:generate` command.

#### User related

Each endpoint displays information related to the User:

* [Get Users](v1/users/all.md) : `GET /api/v1/users`
* [Get User Details](v1/users/details.md) : `GET /api/v1/users/:username`

#### Calendars related

Endpoints for viewing and modifying user calendars.

* [Show All User Calendars](v1/calendars/all.md) : `GET /api/v1/calendars/:username`
* [Show User Calendar Details](v1/calendars/details.md) : `GET /api/v1/calendars/:username/:calendar_id`
* [Create User Calendar](v1/calendars/create.md) : `POST /api/v1/calendars/:username/create`
* [Edit User Calendar](v1/calendars/edit.md) : `POST /api/v1/calendars/:username/:calendar_id/edit`
* [Show User Calendar Shares](v1/calendars/shares.md) : `GET /api/v1/calendars/:username/shares/:calendar_id`
* [Share User Calendar](v1/calendars/share_add.md) : `POST /api/v1/calendars/:username/share/:calendar_id/add`
* [Remove Share User Calendar](v1/calendars/share_remove.md) : `POST /api/v1/calendars/:username/share/:calendar_id/remove`

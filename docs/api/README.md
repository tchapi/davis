# Davis API

## Open Endpoints

Open endpoints require no Authentication.

* [Health](health.md) : `GET /api/health`

## Endpoints that require Authentication

Closed endpoints require a valid `X-API-Key` to be included in the header of the request. Token needs to be configured in .env file (as a environment variable `API_KEY`) and can be generated using `php bin/console api:generate` command.

### User related

Each endpoint displays information related to the User:

* [Get Users](users/all.md) : `GET /api/users`
* [Get User Details](users/details.md) : `GET /api/users/:username`

### Calendars related

Endpoints for viewing and modifying user calendars.

* [Show All User Calendars](calendars/all.md) : `GET /api/calendars/:username`
* [Show User Calendar Details](calendars/details.md) : `GET /api/calendars/:username/:calendar_id`
* [Show User Calendar Shares](calendars/shares.md) : `GET /api/calendars/:username/shares/:calendar_id`
* [Share User Calendar](calendars/share_add.md) : `POST /api/calendars/:username/share/:calendar_id/add`
* [Remove Share User Calendar](calendars/share_remove.md) : `POST /api/calendars/:username/share/:calendar_id/remove`
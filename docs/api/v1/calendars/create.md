# Create User Calendar

Creates a new calendar for a specific user.

**URL** : `/api/v1/calendars/:username/create`

**Method** : `POST`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
```

**Request Body constraints**

```json
{
	"name": "[string: calendar name, alphanumeric, spaces, underscores and hyphens, max 64 chars]",
	"uri": "[string: calendar URI, lowercase alphanumeric, underscores and hyphens, max 128 chars]",
	"description": "[string: calendar description, alphanumeric, spaces, underscores and hyphens, max 256 chars, optional]",
	"events_support": "[string: 'true' or 'false', default 'true', optional]",
	"notes_support": "[string: 'true' or 'false', default 'false', optional]",
	"tasks_support": "[string: 'true' or 'false', default 'false', optional]"
}
```

**URL example**

```
/api/v1/calendars/jdoe/create
```

**Body example**

```json
{
	"name": "Work Calendar",
	"uri": "work-calendar",
	"description": "Calendar for work events",
	"events_support": "true",
	"notes_support": "false",
	"tasks_support": "true"
}
```

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
	"data": {
		"calendar_id": 5,
		"calendar_uri": "work-calendar"
	},
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

## Error Response

**Condition** : If 'X-Davis-API-Token' is not present or mismatched in headers.

**Code** : `401 UNAUTHORIZED`

**Content** :

```json
{
	"message": "No API token provided",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

or

```json
{
	"message": "Invalid API token",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If user is not found.

**Code** : `404 NOT FOUND`

**Content** :

```json
{
	"status": "error",
	"message": "User Not Found",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If request body contains invalid JSON.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid JSON",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If 'name' parameter is invalid (not matching the regex or exceeds length).

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Calendar Name",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If 'uri' parameter is invalid (not matching the regex or exceeds length).

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Calendar URI",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If 'description' parameter is invalid (not matching the regex or exceeds length).

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Calendar Description",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

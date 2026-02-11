# User Calendar Details

Gets a list of all available calendars for a specific user.

**URL** : `/api/v1/calendars/:username/:calendar_id`

**Method** : `GET`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
:calendar_id -> "[numeric id of a calendar owned by the user]",
```

**URL example**

```json
/api/v1/calendars/jdoe/1
```

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
	"data": {
		"id": 1,
		"uri": "default",
		"displayname": "Default Calendar",
		"description": "Default Calendar for Joe Doe",
		"events": {
			"enabled": true,
			"count": 0
		},
		"notes": {
			"enabled": false,
			"count": 0
		},
		"tasks": {
			"enabled": false,
			"count": 0
		}
	},
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

Shown when user has no calendars with the given id:
```json
{
	"status": "success",
	"data": {},
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
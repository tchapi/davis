# User Calendars

Gets a list of all available calendars for a specific user.

**URL** : `/api/v1/calendars/:username`

**Method** : `GET`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
```

**URL example**

```json
/api/v1/calendars/jdoe
```

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
	"data": {
		"user_calendars": [
			{
				"id": 1,
				"uri": "default",
				"displayname": "Default Calendar",
				"description": "Default Calendar for John Doe",
				"events": 0,
				"notes": 0,
				"tasks": 0
			}
		],
		"shared_calendars": [
			{
				"id": 10,
				"uri": "c2152eb0-ada1-451f-bf33-b4a9571ec92e",
				"displayname": "Default Calendar",
				"description": "Default Calendar for Mark Doe",
				"events": 0,
				"notes": 0,
				"tasks": 0
			}
		],
		"subscriptions": []
	},
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

Shown when user does not have calendars:
```json
{
	"status": "success",
	"data": {
		"user_calendars": [],
		"shared_calendars": [],
		"subscriptions": []
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
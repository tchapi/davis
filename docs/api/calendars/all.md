# User Calendars

Gets a list of all available calendars for a specific user.

**URL** : `/api/calendars/:username`

**Method** : `GET`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
```

**URL example**

```json
/api/calendars/jdoe
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
	}
}
```

Shown when no there are no users in Davis:
```json
{
	"status": "success",
	"data": []
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
	}
}
```

## Error Response

**Condition** : If 'X-API-Key' is not present or mismatched in headers.

**Code** : `401 UNAUTHORIZED`

**Content** :

```json
{
	"status": "error",
	"message": "Unauthorized"
}
```

**Condition** : If ':username' is not a valid string containing chars: `a-zA-Z0-9_-`.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error", 
    "message": "Invalid Username"
}
```
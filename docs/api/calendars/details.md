# User Calendar Details

Gets a list of all available calendars for a specific user.

**URL** : `/api/calendars/:username/:calendar_id`

**Method** : `GET`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
:calendar_id -> "[numeric id of a calendar owned by the user]",
```

**URL example**

```json
/api/calendars/jdoe/1
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
		"events": 0,
		"notes": 0,
		"tasks": 0
	}
}
```

Shown when user has no calendars with the given id:
```json
{
	"status": "success",
	"data": []
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

**Condition** : If ':calendar_id' is not a valid numeric value.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error", 
    "message": "Invalid Calendar ID"
}
```
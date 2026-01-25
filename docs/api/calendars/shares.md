# User Calendar Shares

Gets a list of all users with whom a specific user calendar is shared.

**URL** : `/api/calendars/:username/shares/:calendar_id`

**Method** : `GET`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
:calendar_id -> "[numeric id of a calendar owned by the user]",
```

**URL example**

```json
/api/calendars/mdoe/shares/1
```

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
	"data": [
		{
			"username": "adoe",
			"user_id": 9,
			"displayname": "Aiden Doe",
			"email": "adoe@example.org",
			"write_access": false
		},
		{
			"username": "jdoe",
			"user_id": 3,
			"displayname": "John Doe",
			"email": "jdoe@example.org",
			"write_access": true
		}
	]
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

**Condition** : If ':calendar_id' is not for the specified ':username'.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error", 
    "message": "Invalid Calendar ID/Username"
}
```
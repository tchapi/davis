# User Calendar Shares

Gets a list of all users with whom a specific user calendar is shared.

**URL** : `/api/v1/calendars/:user_id/shares/:calendar_id`

**Method** : `GET`

**Auth required** : YES

**Params constraints**

```
:user_id -> "[user id as an int]",
:calendar_id -> "[numeric id of a calendar owned by the user]",
```

**URL example**

```json
/api/v1/calendars/mdoe/shares/1
```

**Important Note** : The `:calendar_id` must be a calendar instance owned by the user. The endpoint retrieves shares of the underlying Calendar entity, ensuring shares are found correctly regardless of the instance reference.

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
	"data": [
		{
			"username": "adoe",
			"principal_id": 9,
			"displayname": "Aiden Doe",
			"email": "adoe@example.org",
			"write_access": false
		},
		{
			"username": "jdoe",
			"principal_id": 3,
			"displayname": "John Doe",
			"email": "jdoe@example.org",
			"write_access": true
		}
	]
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

**Condition** : If ':calendar_id' and ':username' combination is invalid.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Calendar ID/Username",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```
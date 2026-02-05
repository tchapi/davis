# Remove Share User Calendar

Removes access to a specific shared calendar for a specific user.

**URL** : `/api/v1/calendars/:username/share/:calendar_id/remove`

**Method** : `POST`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
:calendar_id -> "[numeric id of a calendar owned by the user]",
```

** Request Body Constraints**
```json
{
	"username": "[username of the user to remove access]"
}
```

**URL example**

```json
/api/v1/calendars/mdoe/share/1/remove
```

**Body example**

```json
{
	"username": "jdoe"
}
```

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
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

**Condition** : If ':calendar_id' is not owned by the specified ':username'.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Calendar ID",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If 'username' is not valid.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Username",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If calendar instance or user to remove is not found.

**Code** : `404 NOT FOUND`

**Content** :

```json
{
	"status": "error", 
    "message": "Calendar Instance/User Not Found",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```
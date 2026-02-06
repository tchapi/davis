# Share User Calendar

Shares (or updates write access) a calendar owned by the specified user to another user.

**URL** : `/api/v1/calendars/:username/share/:calendar_id/add`

**Method** : `POST`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
:calendar_id -> "[numeric id of a calendar owned by the user]",
```

** Request Body constraints**
```json
{
	"username": "[username of the user to add/update access]",
	"write_access": "[boolean: true to grant write access, false for read-only]"
}
```

**URL example**

```json
/api/v1/calendars/mdoe/share/1/add
```

**Body example**

```json
{
	"username": "jdoe",
	"write_access": true
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
	"message": "Invalid Calendar ID and User ID",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If 'username' is not valid or 'write_access' is not 'true' or 'false'.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Sharee ID/Write Access Value",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If calendar instance or user to share with is not found.

**Code** : `404 NOT FOUND`

**Content** :

```json
{
	"status": "error", 
    "message": "Calendar Instance/User Not Found",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```
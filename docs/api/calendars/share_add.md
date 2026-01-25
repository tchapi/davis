# Share User Calendar

Shares (or updates write access) a calendar owned by the specified user to another user.

**URL** : `/api/calendars/:username/share/:calendar_id/add`

**Method** : `POST`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
:calendar_id -> "[numeric id of a calendar owned by the user]",
```

**URL example**

```json
/api/calendars/mdoe/share/1/add
```

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success"
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
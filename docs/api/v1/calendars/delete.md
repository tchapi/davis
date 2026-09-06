# Delete User Calendar

Deletes a specific calendar for a specific user.

**URL** : `/api/v1/calendars/:user_id/:calendar_id`

**Method** : `DELETE`

**Auth required** : YES

**Params constraints**

```
:user_id -> "[user id as an int]",
:calendar_id -> "[numeric id of a calendar instance belonging to the user]",
```

If the calendar is owned by the user, the calendar, its events, its change log, its scheduling objects and every
instance shared with other users are deleted. The user's calendar subscriptions and unrelated inbox items are left
untouched.

If the calendar was merely *shared with* the user, only the user's access to it is removed (the owner's calendar
and events are kept).

**URL example**

```
/api/v1/calendars/jdoe/1
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

**Condition** : If ':calendar_id' is not owned by the specified ':username' or calendar instance is not found.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Instance Not Found",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

**Condition** : If an error occurs while deleting the calendar.

**Code** : `500 INTERNAL SERVER ERROR`

**Content** :

```json
{
	"status": "error",
	"message": "Failed to Delete Calendar",
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

# Get Users

Gets a list of all available users.

**URL** : `/api/v1/users`

**Method** : `GET`

**Auth required** : YES

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
	"data": [
		{
			"id": 3,
			"uri": "principals/jdoe",
			"username": "jdoe"
		}
    ],
	"timestamp": "2026-01-23T15:01:33+01:00"
}
```

Shown when there are no users in Davis:
```json
{
	"status": "success",
	"data": [],
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

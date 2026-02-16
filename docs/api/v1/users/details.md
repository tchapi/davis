# User Details

Gets details about a specific user account.

**URL** : `/api/v1/users/:user_id`

**Method** : `GET`

**Auth required** : YES

**Params constraints**

```
:user_id -> "[user id as an int]",
```

**URL example**

```json
/api/v1/users/jdoe
```

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
	"data": {
		"principal_id": 3,
		"uri": "principals/jdoe",
		"username": "jdoe",
		"displayname": "John Doe",
		"email": "jdoe@example.org"
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
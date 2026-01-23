# Get Users

Gets a list of all available Davis users.

**URL** : `/api/users`

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
			"username": "jdoe",
			"displayname": "John Doe",
			"email": "jdoe@example.org"
		}
    ]
}
```

Shown when no there are no users in Davis:
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

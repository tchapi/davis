# User Details

Gets details about a specific user account.

**URL** : `/api/users/:username`

**Method** : `GET`

**Auth required** : YES

**Params constraints**

```
:username -> "[username in plain text]",
```

**URL example**

```json
/api/users/jdoe
```

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
	"data": {
		"id": 3,
		"uri": "principals/jdoe",
		"username": "jdoe",
		"displayname": "John Doe",
		"email": "jdoe@example.org"
	}
}
```

Shown when there are no users in Davis:
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
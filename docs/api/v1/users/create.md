# Create User

Create a new user.

**URL** : `/api/v1/users/create`

**Method** : `POST`

**Auth required** : YES

**Request Body constraints**

```json
{
	"name": "[string: username, alphanumeric, spaces, underscores and hyphens, max 64 chars]",
	"display_name": "[string: max 255 chars]",
	"email": "[string: valid email, max 255 chars]",
	"password": "[string: max 255 chars]",
	"is_admin": "[string or boolean: 'true', 'false', true, false, default false]"
}
```

**Body example**

```json
{
	"name": "user",
	"display_name": "New User",
	"email": "user@user.user",
	"password": "password",
	"is_admin": "false"
}
```

## Success Response

**Code** : `200 OK`

**Content examples**

```json
{
	"status": "success",
	"data": {
		"user_id": 5,
		"user_name": "user"
	},
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

## Error Response

**Condition** : If 'X-Davis-API-Token' is not present or mismatched in headers.

**Code** : `401 UNAUTHORIZED`

**Content** :

```json
{
	"message": "No API token provided",
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

or

```json
{
	"message": "Invalid API token",
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

**Condition** : If request body contains invalid JSON.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid JSON",
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

**Condition** : If 'name' parameter is invalid (not matching the regex or exceeds length).

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Userame",
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

**Condition** : If 'display_name' parameter is invalid (null or empty string).

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Display Name",
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

**Condition** : If 'email' parameter is invalid (null, empty string or invalid email).

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Email",
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

**Condition** : If 'password' parameter is invalid (null or empty string).

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Password",
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

**Condition** : If 'is_admin' parameter is invalid (not in [true, false, 'true', 'false']).

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Invalid Is Admin",
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

**Condition** : If user with specified username already exists.

**Code** : `400 BAD REQUEST`

**Content** :

```json
{
	"status": "error",
	"message": "Usrname Already Exists",
	"timestamp": "2026-09-23T15:01:33+01:00"
}
```

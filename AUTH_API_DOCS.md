# Authentication API Documentation

## Base URL
```
http://localhost/lectra
```

## Endpoints

### 1. POST /api/auth/login

**Description:** Authenticate user with email and password, returns JWT token.

**Request:**
```
POST /api/auth/login
Content-Type: application/json

{
    "email": "admin@lectra.edu",
    "password": "password123"
}
```

**Success Response (200):**
```json
{
    "status": "success",
    "message": "Login successful",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@lectra.edu",
        "role": "admin"
    }
}
```

**Error Responses:**
- `400` - Missing email or password
- `401` - Invalid email or password

---

### 2. POST /api/auth/register

**Description:** Register a new user (admin-only endpoint).

**Request:**
```
POST /api/auth/register
Content-Type: application/json
Authorization: Bearer <admin_token>

{
    "name": "New Student",
    "email": "student2@lectra.edu",
    "password": "securepass123",
    "role": "student",
    "department_id": 1
}
```

**Success Response (200):**
```json
{
    "status": "success",
    "message": "User registered successfully",
    "user": {
        "id": 4,
        "name": "New Student",
        "email": "student2@lectra.edu",
        "role": "student",
        "department_id": 1
    }
}
```

**Error Responses:**
- `400` - Missing required fields or invalid role
- `401` - Missing or invalid token
- `403` - Non-admin attempting to register
- `409` - Email already exists

---

### 3. PUT /api/auth/change-password

**Description:** Authenticated user changes their own password.

**Request:**
```
PUT /api/auth/change-password
Content-Type: application/json
Authorization: Bearer <token>

{
    "current_password": "password123",
    "new_password": "newpassword456"
}
```

**Success Response (200):**
```json
{
    "status": "success",
    "message": "Password changed successfully"
}
```

**Error Responses:**
- `400` - Missing current_password or new_password
- `401` - Missing/invalid token or incorrect current password

---

## Postman Testing Guide

### Setup
1. Create a new collection "Lecktra Auth API"
2. Set variable: `baseUrl = http://localhost/api/auth`

### Test Flow

#### Test 1: Login as Admin
```
POST {{baseUrl}}/login
Body (raw JSON):
{
    "email": "admin@lectra.edu",
    "password": "password123"
}
```
In Tests tab, add:
```javascript
if (pm.response.json().token) {
    pm.environment.set("adminToken", pm.response.json().token);
}
```

#### Test 2: Register New Student (as Admin)
```
POST {{baseUrl}}/register
Headers:
    Authorization: Bearer {{adminToken}}
Body (raw JSON):
{
    "name": "Test Student",
    "email": "teststudent@lectra.edu",
    "password": "password123",
    "role": "student",
    "department_id": 1
}
```

#### Test 3: Change Password
```
PUT {{baseUrl}}/change-password
Headers:
    Authorization: Bearer {{adminToken}}
Body (raw JSON):
{
    "current_password": "password123",
    "new_password": "newpassword456"
}
```

#### Test 4: Verify New Password
```
POST {{baseUrl}}/login
Body (raw JSON):
{
    "email": "admin@lectra.edu",
    "password": "newpassword456"
}
```

#### Test 5: Unauthorized Registration Attempt
```
POST {{baseUrl}}/register
Body (raw JSON):
{
    "name": "Unauthorized User",
    "email": "unauth@lectra.edu",
    "password": "password123",
    "role": "admin"
}
```
Expected: 401 Unauthorized

#### Test 6: Non-Admin Registration Attempt
- Login as student/lecturer first
- Use their token to attempt registration
Expected: 403 Forbidden

---
## JWT Token Structure

The JWT token payload contains:
- `id` - User ID
- `email` - User email
- `role` - User role (admin/lecturer/student)
- `iat` - Issued at timestamp
- `exp` - Expires in 1 hour

## Default Test Users

| Email | Password | Role |
|-------|----------|------|
| admin@lectra.edu | password123 | admin |
| lecturer@lectra.edu | password123 | lecturer |
| student@lectra.edu | password123 | student |
# CyberGuard API Reference

This document describes the HTTP JSON API under the `/api` prefix for web and mobile clients.

**Base URL:** `/api`

---

#> Conventions

**Endpoint workflow:** This block is reference only. There is no HTTP call; use it to understand headers, auth, and error shapes before implementing the endpoints below.

| **Topic** | **Detail** |
| --- | --- |
| Content type | `application/json` for JSON bodies unless an endpoint accepts file upload (e.g. avatar). |
| Authentication | Protected routes expect: `Authorization: Bearer {token}` (Laravel Sanctum). |
| Validation errors | Either app style (`status`, `message`, `errors`) or Laravel default (`message`, `errors`). |
| IDs | Resource identifiers are usually UUIDs. |

---

#> 1. Authentication

### Client workflow

1. The user opens the **Sign up** screen. They type their full name, email, job title, password, and password confirmation. They may add an **avatar** image if the product supports it.
2. The client checks basic field rules in the UI (length, matching passwords) so the user gets fast feedback before the request.
3. The client sends **`POST /api/auth/register`**. On success, the API confirms registration. The user should be told that a **verification email** may arrive next, and what to do if it does not.
4. The user opens **Sign in**, enters email and password, and the client calls **`POST /api/auth/login`**.
5. If the response says the email is not verified yet, the client shows a clear message and can offer a link to the **Resend verification** flow (see Email verification).
6. If the response says **2FA is required**, the client shows a second step for the **authenticator code** and then calls **`POST /api/auth/2fa/verify`** with the same email and the code. Only after that does the user receive a normal **token**.
7. If login succeeds without 2FA, the client reads **`token`** from the response and stores it safely (secure storage on mobile, memory or http-only patterns on web, per your security model).
8. For every later request to protected routes, the client adds **`Authorization: Bearer {token}`**.
9. The client can call **`GET /api/auth/me`** or **`GET /api/auth/status`** after navigation or refresh to confirm the session is still valid and to fill the user profile in the UI.
10. On **Sign out**, the client calls **`POST /api/auth/logout`** with the Bearer token, then deletes the token locally so the user cannot keep using the old session.

###> POST /api/auth/register

**Endpoint workflow**

1. The user fills the registration form (full name, email, job title, password, confirmation; optional avatar).
2. The client performs quick client-side checks, then builds the request body (JSON, or `multipart/form-data` if uploading `avatar`).
3. The client calls this endpoint without a Bearer token.
4. On **201**, the client shows success and directs the user to verify email or sign in, per product rules. On **422**, the client maps `errors` to form fields.

Registers a new user and queues verification email (via background job).

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{
  "full_name": "Eid Yasser Eid",
  "email": "eid2000yasser@gmail.com",
  "job_tittle": "Bug Hunter",
  "password": "ExamplePassword123!",
  "password_confirmation": "ExamplePassword123!"
}
```

**Validation**

| **Field** | **Rules** |
| --- | --- |
| `full_name` | Required, string, 3–255 characters |
| `email` | Required, valid email, unique |
| `job_tittle` | Required, string, 3–255 characters |
| `password` | Required, confirmed, strength regex (min 8 chars, upper, lower, number, symbol) |
| `password_confirmation` | Required, must match `password` |
| `avatar` | Optional, image (`jpeg`, `png`, `jpg`, `gif`), max 2048 KB |

**Success (201)**

```json
{
  "status": "success",
  "message": "Registered successfully",
  "data": {
    "email": "eid2000yasser@gmail.com",
    "full_name": "Eid Yasser Eid",
    "job_tittle": "Bug Hunter"
  }
}
```

**Validation / duplicate email (422)** — `status: "error"`, `message: "Validation failed"`, `errors` object (duplicate email may show as `"Invalid Email."` on `email`).

---

###> POST /api/auth/login

**Endpoint workflow**

1. The user enters email and password on the sign-in screen.
2. The client calls this endpoint with no Bearer token.
3. If **`requires_2fa`** is false and a **`token`** is present, the client stores the token and continues into the app.
4. If **`requires_2fa`** is true, the client keeps the email, shows the 2FA code step, and calls **`POST /api/auth/2fa/verify`** next (not this endpoint again).
5. On **401**, show a generic or specific message (unverified email vs wrong password) per API `message`. On **423**, show lockout time if returned.

Authenticates the user. Returns an API token, or a signal that 2FA is required.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{
  "email": "eid2000yasser@gmail.com",
  "password": "ExamplePassword123!"
}
```

**Success (200)** — normal login

```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "requires_2fa": false,
    "user": {
      "id": "019d30ea-e001-7353-8fb8-a835f8a8dd7f",
      "email": "eid2000yasser@gmail.com",
      "full_name": "Eid Yasser Eid",
      "job_title": "Bug Hunter"
    },
    "token": "30|token_value"
  }
}
```

**2FA required (200)**

```json
{
  "status": "success",
  "message": "2FA verification required",
  "data": {
    "requires_2fa": true,
    "email": "user@example.com"
  }
}
```

**Other errors**

- **401** — Email not verified: `{"status":"error","message":"Please verify your email address"}`
- **401** — Bad credentials: `{"status":"error","message":"Invalid email or password"}`
- **423** — Account locked after failed attempts (message includes retry time)
- **422** — Validation failed

---

###> POST /api/auth/logout

**Endpoint workflow**

1. The user taps **Sign out** (or session expires and the client cleans up).
2. The client sends this request with the current **`Authorization: Bearer {token}`** header.
3. On **200**, the client deletes the stored token and navigates to the public login screen.

Revokes the current access token.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Headers:** `Authorization: Bearer {token}`

**Success (200)**

```json
{
  "status": "success",
  "message": "Logged out successfully"
}
```

---

###> POST /api/auth/forgot-password

**Endpoint workflow**

1. The user opens **Forgot password** and enters their account email.
2. The client calls this endpoint without a token.
3. On **202**, show a neutral success message (“If an account exists, you will receive an email”) to avoid email enumeration if you prefer; otherwise follow your product copy.
4. The user leaves the app and uses the email link to reach your reset screen, which then uses **`GET /api/auth/password/reset/{token}`** and **`POST /api/auth/reset-password`**.

Starts password reset: the server queues an email with a reset link or token.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{
  "email": "eid2000yasser@gmail.com"
}
```

| **Field** | **Rules** |
| --- | --- |
| `email` | Required, valid email, must exist in `users` |

**Success (202)**

```json
{
  "status": "success",
  "message": "Password reset request queued. Please check your email shortly."
}
```

---

###> POST /api/auth/reset-password

**Endpoint workflow**

1. The user lands on the reset screen with **token** (from URL or prior GET) and confirms their **email**.
2. The user types a new password twice; the client validates match and strength locally.
3. The client calls this endpoint with **token**, **email**, **password**, and **password_confirmation** (no Bearer token).
4. On **200**, redirect to login. On **500** or validation errors, show the message and allow retry or request a new link.

Sets a new password using the token from the reset email.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{
  "token": "reset-token",
  "email": "eid2000yasser@gmail.com",
  "password": "ExamplePassword123!",
  "password_confirmation": "ExamplePassword123!"
}
```

**Success (200)** — `status: "success"`, message confirms reset.

**Error (500)** — e.g. invalid token: `status: "error"` with explanatory `message`.

---

###> GET /api/auth/password/reset/{token}

**Endpoint workflow**

1. The user clicks the password-reset link from email; the browser loads your frontend route or this API URL depending on setup.
2. If the SPA handles routing, the client may call this GET with **`{token}`** from the path to receive a JSON payload that includes the token for the next step.
3. The client then shows the “new password” form and submits **`POST /api/auth/reset-password`** using that token and the user’s email.

Used when the user opens a reset link in the browser and your SPA needs the token from the URL (Laravel `password.reset` named route).

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required |

**Success (200)** — JSON includes `token` and guidance to submit it with **`POST /api/auth/reset-password`**.

---

###> GET /api/auth/me

**Endpoint workflow**

1. After login or on app load, the client needs the current user record for the profile header or settings.
2. The client calls this endpoint with **`Authorization: Bearer {token}`**.
3. On **200**, bind `user` to state. On **401**, clear the token and send the user to login.

Returns the authenticated user.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `{ "user": { ... } }`

---

###> GET /api/auth/status

**Endpoint workflow**

1. The client wants a quick “is the session still OK?” check (heartbeat, tab focus, or before a sensitive action).
2. The client sends the Bearer token.
3. On **200**, treat the session as valid (`status` + `user`). On **401**, treat as logged out and refresh UI.

Lightweight check that the token is valid; includes user payload.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `{ "status": "success", "user": { ... } }`

---

#> 2. Two-factor authentication (2FA)

### Client workflow

1. The signed-in user opens **Security** or **Account settings** and chooses **Turn on two-factor authentication**.
2. The client calls **`POST /api/auth/2fa/setup`**. The response includes a **QR code** and often a **secret** string. The UI shows the QR for scanning and can offer “Enter secret manually” for advanced users.
3. The user installs an authenticator app (Google Authenticator, Authy, etc.), scans the QR, and enters the **6-digit code** shown in the app.
4. The client sends **`POST /api/auth/2fa/enable`** with that code. On success, 2FA is on; the UI should warn the user to keep backup codes or recovery options if your product adds them later.
5. Next time the user logs in with email and password, **`POST /api/auth/login`** may return **`requires_2fa: true`** instead of a token. The client should show a **second screen** asking only for the authenticator code (and keep the email from the login step).
6. The client calls **`POST /api/auth/2fa/verify`** with **email** and **code**. The response then contains **`token`** and **user** like a normal login.
7. To turn 2FA off, the user enters a fresh code from the app and the client calls **`POST /api/auth/2fa/disable`**.
8. **`GET /api/auth/2fa/status`** can run when the settings screen opens so the UI shows “On” or “Off” without guessing.

###> POST /api/auth/2fa/setup

**Endpoint workflow**

1. The signed-in user opens **Enable 2FA** in settings.
2. The client calls this endpoint with the Bearer token.
3. On **200**, render **`data.qr_code`** (image) and optionally show **`data.secret`** for manual entry. Then prompt for a code to confirm via **`POST /api/auth/2fa/enable`**.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `data.qr_code`, `data.secret`.

---

###> POST /api/auth/2fa/enable

**Endpoint workflow**

1. After scanning the QR from **`/2fa/setup`**, the user reads the 6-digit code from the authenticator app.
2. The client sends **`{ "code": "..." }`** with the Bearer token.
3. On **200**, mark 2FA as enabled in the UI. On **400**, prompt the user to run **setup** again. On **401**, ask for a new code.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body**

```json
{
  "code": "123456"
}
```

**Success (200)** — 2FA enabled.

**Errors**

- **400** — 2FA setup was not started first
- **401** — invalid verification code

---

###> POST /api/auth/2fa/disable

**Endpoint workflow**

1. The signed-in user chooses **Turn off 2FA** and confirms the risk in the UI.
2. The user enters a current TOTP code from the authenticator app.
3. The client posts **`{ "code": "..." }`** with the Bearer token.
4. On **200**, update settings to show 2FA off. On **400**/**401**, show the API message.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body**

```json
{
  "code": "123456"
}
```

**Success (200)** — 2FA disabled.

**Errors**

- **400** — 2FA is not enabled
- **401** — invalid verification code

---

###> POST /api/auth/2fa/verify

**Endpoint workflow**

1. **`POST /api/auth/login`** returned **`requires_2fa: true`** and an **`email`**; the client shows a second screen for the authenticator code only.
2. The user enters the 6-digit code; the client sends **`email`** (same as login) and **`code`**.
3. No Bearer token is required on this call.
4. On **200**, store **`data.token`** and **`data.user`** like a normal login. On **401**, clear the code field and allow retry.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{
  "email": "user@example.com",
  "code": "123456"
}
```

**Success (200)** — `data.user`, `data.token` (same shape as a normal login after **`requires_2fa`** on **`POST /api/auth/login`**).

**Errors**

- **400** — invalid request
- **401** — invalid verification code

---

###> GET /api/auth/2fa/status

**Endpoint workflow**

1. The client opens the security or account settings screen (or any place that shows a 2FA on/off badge).
2. The client calls this endpoint with the Bearer token.
3. On **200**, set the toggle or label from **`data.two_factor_enabled`**.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `data.two_factor_enabled` boolean.

---

#> 3. Email verification

### Client workflow

1. After registration, the user receives an email with a **signed** verification link. The link is only valid for a limited time and must not be modified.
2. The user taps or clicks the link. The browser or in-app web view opens the URL, which triggers **`GET /api/email/verify/{id}/{hash}`**. Here **`id`** is the user id and **`hash`** proves the link matches that email. The user does not type these values by hand in a normal flow.
3. If the response says the email is already verified, the client can show a short “Already verified” message and send the user to login.
4. If the user did not receive the email, they open a **Resend** screen, type the same address they used to register, and the client calls **`POST /api/email/verification-notification/resend`**. The UI should mention that only a few resends per minute are allowed so users do not spam the button.

###> GET /api/email/verify/{id}/{hash}

**Endpoint workflow**

1. The user taps the verification link in the registration email (full signed URL including **`id`** and **`hash`**).
2. The mail client or browser performs a **GET** to this path; your app usually does not build the URL manually.
3. On **200**, show “Email verified” (or “Already verified”) and a button to **Sign in**. On **403**, show that the link is invalid or expired and offer **Resend**.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required (must be a valid signed URL) |

**Success (200)** — `message`: verified or already verified.

**Forbidden (403)** — invalid link.

---

###> POST /api/email/verification-notification/resend

**Endpoint workflow**

1. The user opens **Resend verification** (from login error or registration follow-up).
2. The user enters the **email** they registered with.
3. The client posts **`{ "email": "..." }`** without a Bearer token.
4. On **200**, confirm that a new email was sent. On **400**, explain already verified. On **403**, explain unknown email if that matches your UX. Respect **throttle** (6/min): disable the button briefly after send.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |
| **Throttle** | 6 requests per minute |

**Request body**

```json
{
  "email": "user@example.com"
}
```

**Success (200)** — `status: "success"`, `message`: verification link sent.

**Errors**

- **403** — email not registered (`status: "error"`, `message`: e.g. “Please register”)
- **400** — already verified

---

#> 4. Projects

### Client workflow

1. After login, the user lands on a **Projects** home screen. The client loads **`GET /api/projects`** so you can show two lists or tabs: **Owned** and **Collaborating**.
2. **Owned** projects are the ones the user created or owns. **Collaborating** are projects where they were invited. Different badges or subtitles help users understand their role later when they open a project.
3. To create a project, the user taps **New project**, enters **name**, optional **description**, and optional **start** and **end** dates. The client posts **`POST /api/projects`**. On success, you can navigate to the new project detail or refresh the list.
4. Opening a project calls **`GET /api/projects/{project}`**. Use this to show overview, targets, collaborators, and counts so the user sees one coherent dashboard.
5. Only the **owner** should see **Edit project**. Saving changes uses **`PUT`** or **`PATCH /api/projects/{project}`** with only the fields that changed.
6. **Delete project** is also owner-only: **`DELETE /api/projects/{project}`**. The UI should use a confirmation dialog because the server performs a **soft delete** but the project disappears from normal lists.

###> GET /api/projects

**Endpoint workflow**

1. The user opens the **Projects** dashboard after login.
2. The client calls this endpoint with the Bearer token.
3. On **200**, split **`owned`** and **`collaborating`** into tabs or sections and render cards/lists. Refresh after creating or joining a project.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)**

```json
{
  "owned": [],
  "collaborating": []
}
```

---

###> POST /api/projects

**Endpoint workflow**

1. The user opens **New project** and fills name, optional description, optional start/end dates.
2. The client validates dates (end after start) locally, then POSTs JSON with the Bearer token.
3. On **201**, read **`project`** (id, etc.), navigate to project detail or refresh the list. On **422**, show validation errors.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body**

```json
{
  "name": "Project Alpha",
  "description": "Security assessment",
  "start_date": "2026-04-01",
  "end_date": "2026-05-01"
}
```

| **Field** | **Rules** |
| --- | --- |
| `name` | Required, string, max 255 |
| `description` | Optional string |
| `start_date` | Optional date |
| `end_date` | Optional date; must be after `start_date` when both present |

**Success (201)** — `message`, `project` object.

---

###> GET /api/projects/{project}

**Endpoint workflow**

1. The user selects a project from the list; the client has the project **`id`** (UUID).
2. The client calls **`GET /api/projects/{project}`** with the Bearer token.
3. On **200**, render overview, targets, collaborators, and counts from **`project`**. On **403**, show that the user has no access.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

Returns the project with relations (e.g. targets, collaborators) and computed counts where implemented.

**Forbidden (403)** — `{ "message": "Unauthorized" }` if the user has no access.

---

###> PUT/PATCH /api/projects/{project}

**Endpoint workflow**

1. Only the **owner** should see **Edit project**. The user changes name, description, status, or dates.
2. The client sends **PUT** or **PATCH** with only changed fields and the Bearer token.
3. On **200**, update local state from **`project`**. On **403**, hide edit UI or show “owner only”.

| **Method** | `PUT` or `PATCH` |
| --- | --- |
| **Auth** | Required (project **owner** only) |

**Request body (partial update allowed)**

```json
{
  "name": "Updated Name",
  "description": "…",
  "status": "active",
  "start_date": "2026-04-01",
  "end_date": "2026-06-01"
}
```

| **Field** | **Rules** |
| --- | --- |
| `name` | Optional, string, max 255 |
| `description` | Optional string |
| `status` | Optional: `active`, `archived`, `completed` |
| `start_date` | Optional date |
| `end_date` | Optional date |

**Forbidden (403)** — only owner may edit.

---

###> DELETE /api/projects/{project}

**Endpoint workflow**

1. The **owner** chooses **Delete project**; the client shows a strong confirmation (name typing, etc.).
2. On confirm, the client sends **DELETE** with the Bearer token.
3. On **200**, remove the project from lists and navigate away. On **403**, show not allowed.

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required (creator/owner per server rules) |

**Success (200)** — project deleted (soft delete).

**Forbidden (403)** — only owner may delete.

---

#> 5. Collaborators and invitations

### Client workflow

1. The **project owner** opens **Team** or **Invite people**. They pick a role: **editor** (can change most work data) or **viewer** (read-only). They may type an **email** if you want to track who was invited, but the API allows email to be optional depending on your form.
2. The client calls **`POST /api/projects/{project}/invite`**. The response contains **`invite_link`** and **`expires_at`**. The owner copies the link, sends it in chat, or your app sends email later. The UI should show **expiry** so the owner knows to resend if needed.
3. The invitee must be **logged in** before accepting. They open the link; your app parses the **token** from the URL and calls **`GET /api/invitations/{token}`** to show **project name**, **role**, **who invited them**, and profile hints (`job_tittle`, `avatar_url`).
4. If the invite is expired or invalid, show a clear **404** message and a way to contact the project owner.
5. **Accept:** **`POST /api/invitations/{token}/accept`** adds the user to the project. Then navigate them to that project’s dashboard.
6. **Decline:** **`DELETE /api/invitations/{token}/reject`** declines without joining.
7. For day-to-day management, **`GET /api/projects/{project}/collaborators`** lists members. The owner can **`PATCH /api/projects/{project}/collaborators/{user}`** to change someone’s role, or **`DELETE /api/projects/{project}/collaborators/{user}`** to remove them. The owner cannot remove **themselves** with remove; the API returns **400** in that case.

###> POST /api/projects/{project}/invite

**Endpoint workflow**

1. The **owner** opens **Invite** on a project and selects **role** (`editor` or `viewer`); **email** is optional for your UI but can be filled for records.
2. The client POSTs JSON with the Bearer token and project id in the path.
3. On **201**, show **`invite_link`** and **`expires_at`**; offer copy-to-clipboard or share. On **403**, explain only the owner may invite.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required (**owner** only) |

**Request body**

```json
{
  "email": "member@example.com",
  "role": "editor"
}
```

| **Field** | **Rules** |
| --- | --- |
| `email` | Optional, valid email if present |
| `role` | Required: `editor` or `viewer` |

**Success (201)** — `status`, `message`, `invite_link`, `expires_at`.

**Forbidden (403)** — only owner can invite.

---

###> GET /api/invitations/{token}

**Endpoint workflow**

1. The invitee opens the shared link; the client parses **`token`** from the URL path or query (however your frontend routes it).
2. The invitee must be **logged in**; the client calls this GET with the Bearer token.
3. On **200**, show **`invitation`** (project name, role, expiry, inviter, avatar). On **404**, show expired or invalid and suggest contacting the owner.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `invitation` object: `project_name`, `role`, `expires_at`, `invited_by`, `job_tittle`, `avatar_url`.

**Not found (404)** — invitation missing or expired.

---

###> POST /api/invitations/{token}/accept

**Endpoint workflow**

1. On the invitation preview screen, the user taps **Join project** (or equivalent).
2. The client POSTs with the Bearer token and the same **`token`** as in **`GET /api/invitations/{token}`**.
3. On **200**, read **`project`**, navigate to that project’s home. On **404**, show invalid/expired. On **409**, show already a member and go to the project if you have its id.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — joined project; includes `project`.

**404** — invalid or expired.

**409** — already a member.

---

###> DELETE /api/invitations/{token}/reject

**Endpoint workflow**

1. On the invitation preview screen, the user taps **Decline** or **Reject**.
2. The client sends **DELETE** with the Bearer token and invitation **`token`**.
3. On **200**, show confirmation and return to projects or home. On **404**, handle like expired invite.

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required |

Declines the invitation (server updates invitation status).

**Success (200)** — e.g. `status: "Success"` and confirmation message.

---

###> GET /api/projects/{project}/collaborators

**Endpoint workflow**

1. The user opens **Team** or **Members** inside a project they can access.
2. The client GETs with Bearer token and project id.
3. On **200**, render **`collaborators`** (with nested **`user`**). On **403**, block the screen.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required (user must have project access) |

**Success (200)** — `status: "Success"`, `collaborators` array with related `user` fields.

**Forbidden (403)** — no access.

---

###> PATCH /api/projects/{project}/collaborators/{user}

**Endpoint workflow**

1. The **owner** opens member management, picks a user row, and changes **role** (e.g. editor → viewer).
2. The client PATCHes **`{ "role": "..." }`** with project id and **user** id in the path.
3. On **200**, refresh the member list. On **403**, show owner-only message.

| **Method** | `PATCH` |
| --- | --- |
| **Auth** | Required (**owner** only) |

**Request body**

```json
{
  "role": "viewer"
}
```

Use roles aligned with your product (e.g. `editor` / `viewer`).

**Forbidden (403)** — only owner can change roles.

---

###> DELETE /api/projects/{project}/collaborators/{user}

**Endpoint workflow**

1. The **owner** chooses **Remove** on a member (not themselves).
2. The client confirms, then DELETEs with project id and **user** id.
3. On **200**, remove the row from the UI. On **400**, show “cannot remove yourself”. On **403**, owner-only.

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required (**owner** only) |

**Forbidden (403)** — only owner can remove members.

**Bad request (400)** — cannot remove yourself.

---

#> 6. Targets

### Client workflow

1. Inside a project, the user taps **Add new target** (or similar). This opens a form dedicated to one asset in scope.
2. The user chooses **target type**: **domain** (hostname), **ip** (single IPv4 address), or **network** (IPv4 **CIDR** block). The form should change hints or validation as the type changes so users do not paste a URL when a hostname is required.
3. The user enters a **label**: a short human description (for example “Production API gateway” or “Office egress IP”) so the team can recognize the row in a list.
4. The user enters **value**: for **domain**, a hostname like `example.com` (not `https://example.com`); for **ip**, an address like `203.0.113.10`; for **network**, CIDR like `192.0.2.0/24`.
5. The client sends **`POST /api/projects/{project}/targets`** with JSON **`type`**, **`label`**, and **`value`**. Only users with access can call this; **viewers** receive **403** if they try to add.
6. To show everything in scope, call **`GET /api/projects/{project}/targets`** and render the list with type and label.
7. To inspect one row, use **`GET /api/targets/{target}`**.
8. To fix a typo or update scope, **owners and editors** use **`PATCH /api/projects/{project}/targets/{target}`** (viewers cannot).
9. To remove a target, **owners and editors** use **`DELETE /api/projects/{project}/targets/{target}`**.

###> POST /api/projects/{project}/targets

**Endpoint workflow**

1. The user taps **Add new target** inside a project.
2. The user selects **type** (`domain`, `ip`, `network`), enters **label** and **value** (hostname, IP, or CIDR per type).
3. The client POSTs JSON **`type`**, **`label`**, **`value`** with Bearer token and project id.
4. On **201**, append **`target`** to the list or go to detail. On **403**, explain no access or viewer restriction. On **422**, fix field errors.

Adds a target to the project. Callers must have project access; **viewers** cannot create targets.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body**

The API expects lowercase **`type`** values. Conceptually: **Domain**, **IP**, or **Network (CIDR)**.

```json
{
  "type": "domain",
  "label": "Small description of target",
  "value": "the value of target"
}
```

**Examples by type**

| **`type`** | **Meaning** | **Example `value`** |
| --- | --- | --- |
| `domain` | Hostname / FQDN (not a full URL with `https://`) | `"example.com"` |
| `ip` | IPv4 address | `"203.0.113.10"` |
| `network` | IPv4 CIDR | `"192.0.2.0/24"` |

**Validation**

| **Field** | **Rules** |
| --- | --- |
| `type` | Required: `domain`, `ip`, or `network` |
| `label` | Required string |
| `value` | Required; must match rules for the selected `type` |

**Success (201)**

```json
{
  "status": "Success",
  "message": "Target added successfuly",
  "target": {
    "id": "uuid",
    "type": "domain",
    "value": "example.com",
    "label": "Small description of target"
  }
}
```

**Errors**

- **403** — no project access, or viewer trying to add (`message` explains which).

---

###> GET /api/projects/{project}/targets

**Endpoint workflow**

1. The user opens the **Targets** tab or list for a project.
2. The client GETs with Bearer token and project id.
3. On **200**, render **`targets`** from **`status`** + array. Pull to refresh after add/edit/delete.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `status: "Success"`, `targets` array.

---

###> GET /api/targets/{target}

**Endpoint workflow**

1. The user opens a single target (from list row or deep link); the client has **`target`** id.
2. The client GETs **`/api/targets/{target}`** with Bearer token.
3. On **200**, show **`target`** fields. On **404**, show not found.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `{ "target": { ... } }`

**404** — target not found.

---

###> PATCH /api/projects/{project}/targets/{target}

**Endpoint workflow**

1. An **owner** or **editor** opens **Edit target** (viewers should not see this).
2. The user updates **label** and/or **value**; **`value`** must still match the target’s existing **type** rules.
3. The client PATCHes with project id, target id, and JSON body.
4. On **200**, update the row. On **403**/**404**, show API message.

| **Method** | `PATCH` |
| --- | --- |
| **Auth** | Required (owner or editor; not viewer) |

**Request body** — `label` optional; `value` validated against the target’s existing `type` (implementation requires `value` on update).

```json
{
  "label": "Updated label",
  "value": "example.com"
}
```

**Success (200)** — `status: "Success"`, updated `target`.

**Errors:** **403** unauthorized role; **404** target not found.

---

###> DELETE /api/projects/{project}/targets/{target}

**Endpoint workflow**

1. An **owner** or **editor** chooses **Delete target** and confirms.
2. The client DELETEs with project id and target id and Bearer token.
3. On **200**, remove from list or navigate back. On **403**/**404**, show error.

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required (owner or editor; not viewer) |

**Success (200)** — deletion confirmed.

**Errors:** **403**, **404** as applicable.

---

#> 7. Scanning

### Client workflow

1. The user picks **what to scan** (for example a hostname string or a value tied to a target in your UI) and a **scan kind** your workers understand (`scanSlug`, e.g. subdomain discovery).
2. The client sends **`POST /api/scan/start`** with **`target`** and **`scanSlug`**. Show a “Scan queued” or “Started” state so the user knows the job is asynchronous.
3. When your product has a **`scanJobId`** (from this response in a future API version, or from another screen), the client can poll **`GET /api/scan/{scanJobId}/status`** on an interval. Update the UI with **job status** and any **findings** returned.
4. If the job id is not found, show a friendly message and stop polling; the id may be wrong or the job may have been purged.

###> POST /api/scan/start

**Endpoint workflow**

1. The user picks a **target** string (e.g. hostname) and a **scanSlug** your backend supports (e.g. subdomain scan).
2. The client POSTs **`target`** and **`scanSlug`** with Bearer token.
3. On **200**, show “started” / queued state. If you later expose **`scan_job_id`** in the response, store it for polling; otherwise obtain the id from your UI flow.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body**

```json
{
  "target": "example.com",
  "scanSlug": "subdomain"
}
```

**Success (200)**

```json
{
  "status": "success",
  "message": "Scan started..."
}
```

---

###> GET /api/scan/{scanJobId}/status

**Endpoint workflow**

1. The client has a **`scanJobId`** (from a previous start response, list, or notification).
2. On a timer or user **Refresh**, GET this URL with Bearer token.
3. On **200**, update UI from **`scan_job`** and **`findings`**. On **404**, stop polling and show job not found.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `status`, `scan_job`, `findings`.

**404** — scan job id not found (Laravel model not found style message).

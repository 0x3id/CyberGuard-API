# CyberGuard API Documentation

Base URL: `/api`

All authenticated endpoints require a **Bearer token** (Laravel Sanctum):

```
Authorization: Bearer {token}
```

---

## Workspace context

Many endpoints live under organization-aware middleware. Behavior depends on the optional header:

| **Header** | **Value** |
| --- | --- |
| `X-Organization-Id` | UUID of the organization workspace |

| **Scenario** | **Behavior** |
| --- | --- |
| Header **omitted** | **Personal workspace** — owned/collaborating projects, personal subscription limits |
| Header **present** + valid member + **active** org subscription | **Organization workspace** — org projects, org IAM roles (`owner`, `admin`, `member`, `viewer`) |
| Header present but user is not a member | **403** — `Forbidden. You are not a member of this organization.` |
| Header present but org subscription is not `active` | **403** — `Forbidden. This organization does not have an active subscription.` |

Send `X-Organization-Id` on every request after the user switches workspace in the UI.

---

# *1. Authentication*

### Client workflow

1. **Register** with `POST /api/auth/register`, then verify email via the link sent to the inbox.
2. **Login** with `POST /api/auth/login`. Store the returned `token` and send it on all protected requests.
3. If login returns `requires_2fa: true`, complete **`POST /api/auth/2fa/verify`** before using the app.
4. Use **`GET /api/auth/me`** or **`GET /api/auth/status`** to hydrate the session user.
5. **Logout** with `POST /api/auth/logout` to revoke the current token.

Alternatively, use **Google OAuth** (`/api/auth/google/redirect` → `/api/auth/google/callback`).

### POST /api/auth/register

**Endpoint workflow**

1. User fills registration form (name, job title, email, password).
2. Client POSTs JSON (multipart if avatar is included).
3. On **201**, show “check your email”. On **422**, show validation errors.

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{
  "email": "user@example.com",
  "password": "SecurePass1!",
  "password_confirmation": "SecurePass1!",
  "full_name": "Jane Doe",
  "job_tittle": "Security Analyst"
}
```

| **Field** | **Rules** |
| --- | --- |
| `email` | Required, valid email, unique |
| `password` | Required, min 6, confirmed; must be ≥8 chars with uppercase, lowercase, number, symbol |
| `password_confirmation` | Required, must match `password` |
| `full_name` | Required string, 3–255 chars |
| `job_tittle` | Required string, 3–255 chars |
| `avatar` | Optional image (jpeg, png, jpg, gif), max 2048 KB |

**Success (201)** — `status`, `message`, `data` (user fields, password stripped).

A **free** subscription is provisioned automatically. A verification email is queued.

---

### POST /api/auth/login

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{
  "email": "user@example.com",
  "password": "SecurePass1!"
}
```

| **Field** | **Rules** |
| --- | --- |
| `email` | Required, email |
| `password` | Required |

**Success (200)** — no 2FA:

```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "requires_2fa": false,
    "user": { "id": "...", "email": "...", "full_name": "...", "job_title": "..." },
    "token": "..."
  }
}
```

**Success (200)** — 2FA required:

```json
{
  "status": "success",
  "message": "2FA verification required",
  "data": { "requires_2fa": true, "email": "user@example.com" }
}
```

| **Error** | **Code** |
| --- | --- |
| Invalid credentials | 401 |
| Email not verified | 401 |
| Account locked (5 failed attempts, 15 min) | 423 |
| Validation failed | 422 |

---

### POST /api/auth/logout

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `status`, `message`.

---

### GET /api/auth/me

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `{ "user": { ... } }` (full User model).

---

### GET /api/auth/status

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `{ "status": "success", "user": { ... } }`.

---

# *2. Password reset*

### Client workflow

1. User enters email on **Forgot password** → `POST /api/auth/forgot-password`.
2. User opens email link, extracts token → `POST /api/auth/reset-password`.
3. On success, redirect to login (all existing tokens are revoked).

### POST /api/auth/forgot-password

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{ "email": "user@example.com" }
```

| **Field** | **Rules** |
| --- | --- |
| `email` | Required, must exist in `users` |

**Success (202)** — reset email queued.

---

### POST /api/auth/reset-password

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{
  "token": "...",
  "email": "user@example.com",
  "password": "NewSecurePass1!",
  "password_confirmation": "NewSecurePass1!"
}
```

| **Field** | **Rules** |
| --- | --- |
| `token` | Required string |
| `email` | Required, exists in `users` |
| `password` | Required, confirmed; strength regex (same as register) |

**Success (200)** — password reset; all Sanctum tokens deleted.

---

### GET /api/auth/password/reset/{token}

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required |

Laravel named route for password-reset emails. Returns the token as JSON for SPA clients.

**Success (200)** — `status`, `message`, `token`.

---

# *3. Email verification*

### Client workflow

1. User clicks link from registration email → `GET /api/email/verify/{id}/{hash}` (signed URL).
2. If needed, resend with `POST /api/email/verification-notification/resend` (throttled: 6/min).

### GET /api/email/verify/{id}/{hash}

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required (signed URL) |

**Success (200)** — `message`: `Email verified successfully`.

| **Error** | **Code** |
| --- | --- |
| Invalid link | 403 |
| Already verified | 200 with message |

---

### POST /api/email/verification-notification/resend

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{ "email": "user@example.com" }
```

**Success (200)** — verification link sent.

---

# *4. Two-factor authentication (2FA)*

### Client workflow

1. Authenticated user starts setup → `POST /api/auth/2fa/setup` (QR + secret).
2. User scans QR, enters TOTP code → `POST /api/auth/2fa/enable`.
3. On login when `requires_2fa` is true → `POST /api/auth/2fa/verify` with `email` + `code`.
4. Disable with `POST /api/auth/2fa/disable` (requires current TOTP code).

### POST /api/auth/2fa/setup

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `data.qr_code` (data URI), `data.secret`.

---

### POST /api/auth/2fa/enable

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body**

```json
{ "code": "123456" }
```

| **Field** | **Rules** |
| --- | --- |
| `code` | Required, 6-digit numeric |

**Success (200)** — 2FA enabled.

---

### POST /api/auth/2fa/disable

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body** — same as enable.

---

### POST /api/auth/2fa/verify

Used during login when 2FA is enabled.

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

**Success (200)** — `data.user`, `data.token`.

---

### GET /api/auth/2fa/status

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `data.two_factor_enabled` (boolean).

---

# *5. Google OAuth*

### Client workflow

1. Call `GET /api/auth/google/redirect` → open `redirect_url` in browser.
2. Google redirects to `GET /api/auth/google/callback` → server redirects to `{FRONTEND_URL}google-callback?token=...`.
3. SPA reads `token` from query string and stores it like a normal login token.

### GET /api/auth/google/redirect

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required |

**Success (200)** — `redirect_url`.

---

### GET /api/auth/google/callback

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required |

Browser redirect (not JSON). New users get a free subscription; existing email accounts are linked.

---

# *6. Dashboard*

### Client workflow

1. After login, load **`GET /api/dashboard/metrics`** for the risk overview.
2. Optional `?project_id={uuid}` scopes all metrics to one project.
3. Optional `?recent_limit=N` (default 10, max 50) for recent findings.

### GET /api/dashboard/metrics

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Query params**

| **Param** | **Description** |
| --- | --- |
| `project_id` | Optional UUID — scope to one project |
| `recent_limit` | Optional int — recent findings count (max 50) |

**Success (200)**

```json
{
  "status": "success",
  "generated_at": "2026-06-23T12:00:00+00:00",
  "scope": { "project_id": null, "project_ids": ["..."] },
  "data": {
    "findings_summary": { "total": 0, "critical": 0, "open": 0, "in_progress": 0, "resolved": 0 },
    "findings_by_severity": { "critical": { "count": 0, "percentage": 0 }, "...": "..." },
    "risk_score": { "global_score": 0, "average_target_score": 0, "formula": "...", "risk_level": "Minimal" },
    "infrastructure": { "total_targets": 0, "total_scans": 0 },
    "active_scans": { "count": 0, "scans": [] },
    "recent_findings": { "limit": 10, "count": 0, "findings": [] }
  }
}
```

---

# *7. Projects*

### Client workflow

1. After login, the user lands on a **Projects** home screen. The client loads **`GET /api/projects`** so you can show two lists or tabs: **Owned** and **Collaborating** (personal workspace only).
2. **Owned** projects are the ones the user created or owns. **Collaborating** are projects where they were invited. Different badges or subtitles help users understand their role later when they open a project.
3. In **organization workspace** (`X-Organization-Id` set), the same endpoint returns a single `projects` list for that org.
4. To create a project, the user taps **New project**, enters **name**, optional **description**, and optional **start** and **end** dates. The client posts **`POST /api/projects`**. On success, navigate to the new project detail or refresh the list.
5. Opening a project calls **`GET /api/projects/{project}`**. Use this to show overview, targets, collaborators, and counts so the user sees one coherent dashboard.
6. Only the **owner** (personal) or **owner/admin** (org) should see **Edit project**. Saving changes uses **`PUT`** or **`PATCH /api/projects/{project}`** with only the fields that changed.
7. **Delete project** is owner-only: **`DELETE /api/projects/{project}`**. The UI should use a confirmation dialog because the server performs a **soft delete** but the project disappears from normal lists.

### GET /api/projects

**Endpoint workflow**

1. The user opens the **Projects** dashboard after login.
2. The client calls this endpoint with the Bearer token (and `X-Organization-Id` when in org workspace).
3. On **200**, split **`owned`** and **`collaborating`** into tabs or sections (personal), or render **`projects`** (organization). Refresh after creating or joining a project.

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |
| **Org header** | Optional |

**Success (200)** — personal workspace:

```json
{
  "owned": [],
  "collaborating": []
}
```

**Success (200)** — organization workspace:

```json
{
  "status": "success",
  "projects": []
}
```

Each project includes `targets_count` and a computed `risk_score`.

---

### POST /api/projects

**Endpoint workflow**

1. The user opens **New project** and fills name, optional description, optional start/end dates.
2. The client validates dates (end after start) locally, then POSTs JSON with the Bearer token.
3. On **201**, read **`project`** (id, etc.), navigate to project detail or refresh the list. On **422**, show validation errors or subscription limit message.

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

**Errors** — **422** if personal/org project limit reached.

---

### GET /api/projects/{project}

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `project` with `targets`, `activeCollaborators`, `creator`, `targets_count`, `findings_count`.

---

### PUT / PATCH /api/projects/{project}

| **Method** | `PUT` or `PATCH` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Owner (personal) or owner/admin (org) |

**Request body** (all optional with `sometimes`):

```json
{
  "name": "Updated name",
  "description": "Updated description",
  "status": "active",
  "start_date": "2026-04-01",
  "end_date": "2026-06-01"
}
```

| **Field** | **Rules** |
| --- | --- |
| `name` | Optional string, max 255 |
| `description` | Optional string |
| `status` | Optional: `active`, `archived`, `completed` |
| `start_date` | Optional date |
| `end_date` | Optional date |

**Success (200)** — `message`, `project`.

---

### DELETE /api/projects/{project}

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Owner (personal) or owner/admin (org) |

**Success (200)** — `message`: `Project deleted successfully` (soft delete).

---

# *8. Project invitations & collaborators*

### Client workflow

1. **Owner** invites via **`POST /api/projects/{project}/invite`** → share `invite_link` (expires in 7 days).
2. Invitee opens link → **`GET /api/invitations/{token}`** (public details).
3. Logged-in invitee accepts → **`POST /api/invitations/{token}/accept`** or rejects → **`DELETE /api/invitations/{token}/reject`**.
4. Project members list → **`GET /api/projects/{project}/collaborators`**.
5. Owner changes role → **`PATCH /api/projects/{project}/collaborators/{user}`**; removes → **`DELETE ...`**.
6. Owner views pending invites → **`GET /api/projects/{project}/invitations`**.

> Project collaboration applies to **personal workspace** projects. Organization projects use org IAM instead.

### POST /api/projects/{project}/invite

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Project owner only |

**Request body**

```json
{
  "email": "collaborator@example.com",
  "role": "editor"
}
```

| **Field** | **Rules** |
| --- | --- |
| `email` | Optional email |
| `role` | Required: `editor` or `viewer` |

**Success (201)** — `invite_link`, `expires_at`.

---

### GET /api/invitations/{token}

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required |

**Success (200)** — `invitation` with `project_name`, `role`, `expires_at`, `invited_by`, etc.

---

### POST /api/invitations/{token}/accept

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `message`, `project`.

| **Error** | **Code** |
| --- | --- |
| Already a member | 409 |
| Invalid/expired | 404 |

---

### DELETE /api/invitations/{token}/reject

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — invitation marked expired.

---

### GET /api/projects/{project}/invitations

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Project access required |

**Success (200)** — `pending_invitations` array with links and inviter info.

---

### GET /api/projects/{project}/collaborators

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `collaborators` with nested `user` (`id`, `full_name`, `email`) and pivot `role`.

---

### PATCH /api/projects/{project}/collaborators/{user}

| **Method** | `PATCH` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Project owner only |

**Request body**

```json
{ "role": "viewer" }
```

---

### DELETE /api/projects/{project}/collaborators/{user}

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Project owner only (cannot remove self) |

**Success (200)** — `message`: `Member removed successfully`.

---

# *9. Targets*

### Client workflow

1. Inside a project, **Add target** → `POST /api/projects/{project}/targets`.
2. For **organization** domains, the response includes **`dns_verification`** TXT record instructions. User adds DNS record, then calls **`POST /api/targets/{target}/verify-dns`**.
3. List targets per project → `GET /api/projects/{project}/targets`; global list → `GET /api/targets`.
4. Detail → `GET /api/targets/{target}`; update → `PATCH ...`; delete → `DELETE ...`.

### POST /api/projects/{project}/targets

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Owner/editor (personal) or owner/admin (org) |

**Request body**

```json
{
  "type": "domain",
  "label": "Main website",
  "value": "example.com"
}
```

| **Field** | **Rules** |
| --- | --- |
| `type` | Required: `domain`, `ip`, `network` |
| `label` | Required string |
| `value` | Required; validated by type (domain regex, IP, or CIDR for network) |

**Success (201)** — `target`, optional `dns_verification` (`record_type`, `record_name`, `record_value`) for org context.

Org targets start **`is_verified: false`** until DNS verification succeeds.

---

### GET /api/projects/{project}/targets

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `targets` array.

---

### GET /api/targets

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

All targets in current workspace (personal owned + editor collaborations, or all org targets).

**Success (200)** — `targets` array with computed `risk_score`.

---

### GET /api/targets/{target}

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `target` with updated `risk_score`.

---

### PATCH /api/projects/{project}/targets/{target}

| **Method** | `PATCH` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Owner/editor (not viewer) |

**Request body**

```json
{
  "label": "Updated label",
  "value": "new.example.com"
}
```

Changing `value` in org context resets DNS verification.

---

### DELETE /api/projects/{project}/targets/{target}

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — target deleted.

---

### POST /api/targets/{target}/verify-dns

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Manage permission on target |

Checks live DNS TXT records for the ownership token.

**Success (200)** — verified; **`target.is_verified: true`**.

**Error (422)** — TXT not found or domain-only constraint.

---

# *10. Scans*

### Client workflow

1. Load available tools → `GET /api/scanners`.
2. Select target + drivers → `POST /api/scan/start`. Poll **`GET /api/scan/{scanJobId}/status`** and **`GET /api/scan/{scanJobId}/findings`**.
3. List history → `GET /api/projects/{project}/scans` or `GET /api/targets/{target}/scans`.
4. Owner/editor can **pause**, **continue**, or **cancel** running scans.

> Scans require the target to be **DNS-verified** in organization workspace. Monthly scan limits apply per subscription.

### GET /api/scanners

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)**

```json
{
  "status": "success",
  "scanners": [
    { "id": "nmap-tcp-scan", "name": "...", "category": "..." }
  ]
}
```

---

### POST /api/scan/start

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body**

```json
{
  "target_id": "uuid",
  "driver_ids": ["nmap-tcp-scan", "subdomain-scan"],
  "flags": {}
}
```

| **Field** | **Rules** |
| --- | --- |
| `target_id` | Required, exists in `targets` |
| `driver_ids` | Required array of valid scanner IDs |
| `flags` | Optional per-driver flags object |

**Success (200)** — `scan_job` with `id`, `status`, `started_at`.

**Errors** — **400** invalid drivers; **403** unverified target; **422** monthly limit.

---

### GET /api/scan/{scanJobId}/status

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `scan_session` (full ScanJob with target).

---

### GET /api/scan/{scanJobId}/findings

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `findings` array for that scan job.

---

### GET /api/projects/{project}/scans

### GET /api/targets/{target}/scans

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `scans` array; each item has `scan` + `metadata` (`target_name`, `project_name`, `triggered_by`).

---

### POST /api/scan/{scanJobId}/pause

Sets status to `pending` (worker stops container). Owner/editor only; scan must be `running`.

---

### POST /api/scan/{scanJobId}/continue

Resumes `pending` scan. Owner/editor only.

---

### POST /api/scan/{scanJobId}/cancel

Cancels active scan (`running` or `pending`). Owner/editor only.

---

# *11. Findings*

### Client workflow

1. Browse findings per target → `GET /api/targets/{target}/findings` (paginated, filterable).
2. Project-wide view → `GET /api/projects/{project}/findings`.
3. Triage: **`PATCH /api/findings/{finding}/status`** and **`PATCH .../severity`**.
4. Manual upload → `POST /api/targets/{target}/findings`.
5. Domain endpoints discovery → `GET /api/targets/{target}/endpoints`.

### GET /api/targets/{target}/findings

### GET /api/projects/{project}/findings

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Query params**

| **Param** | **Description** |
| --- | --- |
| `severity` | Filter: `critical`, `high`, `medium`, `low`, `info` |
| `status` | Filter: `open`, `in_progress`, `resolved`, `false_positive` |
| `tool` | Filter by `driver_id` |
| `target` | (project endpoint only) filter by `target_id` |

**Success (200)** — Laravel paginator under `findings` (20 per page), sorted by severity.

---

### PATCH /api/findings/{finding}/status

| **Method** | `PATCH` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Owner/editor (not viewer) |

**Request body**

```json
{ "status": "resolved" }
```

| **Field** | **Rules** |
| --- | --- |
| `status` | Required: `open`, `in_progress`, `resolved`, `false_positive` |

---

### PATCH /api/findings/{finding}/severity

**Request body**

```json
{ "severity": "high" }
```

| **Field** | **Rules** |
| --- | --- |
| `severity` | Required: `critical`, `high`, `medium`, `low`, `info` |

---

### POST /api/targets/{target}/findings

Manual finding upload.

**Request body**

```json
{
  "title": "SQL Injection",
  "description": "Detailed description",
  "severity": "high",
  "cvss_score": 8.5,
  "cvss_vector": "CVSS:3.1/...",
  "cve_id": "CVE-2024-0000",
  "remediation": "Patch the application",
  "status": "open",
  "affected_url": "https://example.com/login",
  "proof": "Payload: ' OR 1=1--",
  "metadata": {},
  "tags": ["web", "injection"]
}
```

| **Field** | **Rules** |
| --- | --- |
| `title` | Required, max 255 |
| `description` | Required string |
| `severity` | Required enum |
| `cvss_score` | Optional 0–10 |
| `cvss_vector` | Optional string |
| `cve_id` | Optional string |
| `remediation` | Optional string |
| `status` | Optional enum (default `open`) |
| `affected_url` | Optional URL |
| `proof` | Optional string |
| `metadata` | Optional array |
| `tags` | Optional string array |

**Success (201)** — `finding`.

---

### GET /api/targets/{target}/endpoints

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

Returns discovered URLs from `web-endpoint-fuzzer` findings. **Domain targets only**.

**Success (200)** — `{ "endpoints": ["https://..."] }`.

---

# *12. API keys*

### Client workflow

1. Settings → Integrations loads **`GET /api/apiKeys`** for all supported services.
2. Save keys in one request → **`POST /api/apiKeys`**.
3. Delete one key → **`DELETE /api/apiKeys/{apiKey}`**.

Supported services: `virustotal`, `abuseipdb`, `whoisxml`, `shodan`, `urlscan`, `ai_assistant`.

### GET /api/apiKeys

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — object keyed by service with `id`, `has_key`, `key`, `masked`.

---

### POST /api/apiKeys

**Request body**

```json
{
  "keys": {
    "virustotal": "your-api-key",
    "shodan": ""
  }
}
```

Empty string removes a key. Unknown service names are ignored.

**Success (201)** — `message`.

---

### DELETE /api/apiKeys/{apiKey}

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required |

Deletes by key UUID. Must belong to authenticated user.

---

# *13. Subscription & billing (personal)*

### Client workflow

1. Show plans → **`GET /api/billing/plans`** (public).
2. Current subscription → **`GET /api/subscription`**.
3. Upgrade/checkout → **`POST /api/billing/checkout`** → open `iframe_url` (Paymob).
4. After redirect, user lands on payment status page. Order history → **`GET /api/billing/orders`**.

### GET /api/billing/plans

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required |

**Success (200)** — `user_plans`, `organization_plans` with limits and `amount_egp`.

---

### GET /api/subscription

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — current `UserSubscription` record.

---

### PATCH /api/subscription

| **Method** | `PATCH` |
| --- | --- |
| **Auth** | Required |

**Request body**

```json
{ "plan": "free" }
```

Currently only `free` plan is accepted via this endpoint (downgrade/reset).

---

### POST /api/billing/checkout

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body**

```json
{
  "plan": "starter",
  "billing_data": {
    "first_name": "Jane",
    "last_name": "Doe",
    "email": "jane@example.com",
    "phone_number": "+201000000000",
    "city": "Cairo",
    "country": "EG",
    "street": "NA",
    "building": "NA",
    "floor": "NA",
    "apartment": "NA",
    "postal_code": "NA"
  }
}
```

| **Field** | **Rules** |
| --- | --- |
| `plan` | Required: `starter` or `pro` |
| `billing_data.*` | See `BillingCheckoutRequest` |

**Success (200)** — `data.iframe_url`, `billing_order_id`, `amount_cents`, etc.

---

### GET /api/billing/orders

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — last 50 personal billing orders.

---

# *14. Organizations*

### Client workflow

1. **Create org** → `POST /api/organizations/initiate` (pending org + verification email).
2. User verifies corporate email via signed link → `GET /api/organizations/corporate-email/verify/{billing_order}`.
3. **Pay** → `POST /api/organizations/{organization_id}/payment/checkout`.
4. Poll **`GET /api/organizations/{organization_id}/payment/status`** until subscription is `active`.
5. Load workspace switcher → **`GET /api/organizations/my-workspaces`**.
6. With `X-Organization-Id` set, manage org details, members, and tenant-scoped resources.

### GET /api/organizations/my-workspaces

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `organizations` with `subscription`.

Use this to populate the workspace dropdown; store selected org ID for `X-Organization-Id`.

---

### POST /api/organizations/initiate

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

**Request body**

```json
{
  "org_name": "Acme Security",
  "company_domain": "acme.com",
  "plan": "starter",
  "corporate_email": "admin@acme.com"
}
```

| **Field** | **Rules** |
| --- | --- |
| `org_name` | Required, max 255 |
| `company_domain` | Required, max 255 |
| `plan` | Required: `starter`, `pro`, `enterprise` |
| `corporate_email` | Required email, unique; domain must match `company_domain` |

**Success (200)** — `organization_id`, `billing_order_id`, `next_step`.

---

### POST /api/organizations/{organization_id}/corporate-email

Resend or update corporate email verification (owner only).

**Request body**

```json
{ "corporate_email": "admin@acme.com" }
```

---

### GET /api/organizations/corporate-email/verify/{billing_order}

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required (signed URL + `?email=`) |

**Success (200)** — corporate email verified; proceed to payment.

---

### POST /api/organizations/{organization_id}/payment/checkout

Same shape as personal checkout; plans: `starter`, `pro`, `enterprise`. Requires verified corporate email.

**Success (200)** — Paymob `iframe_url` and order metadata.

---

### GET /api/organizations/{organization_id}/payment/status

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |

**Success (200)** — `payment_status`, `plan`, `expires_at`, `latest_billing_order`.

---

### GET /api/organizations/details

| **Method** | `GET` |
| --- | --- |
| **Auth** | Required |
| **Org header** | **Required** (`X-Organization-Id`) |

**Success (200)** — `organization`, `limits`, `usage` (`projects_count`, `scans_used`, `members_count`).

---

### PUT / PATCH /api/organizations

| **Method** | `PUT` or `PATCH` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Org `owner` or `admin` |

**Request body**

```json
{
  "name": "Acme Security Ltd",
  "logo_url": "https://cdn.example.com/logo.png"
}
```

`company_domain` cannot be changed.

---

### DELETE /api/organizations

| **Method** | `DELETE` |
| --- | --- |
| **Auth** | Required |
| **Authorization** | Org `owner` only |

Cascade-deletes projects, subscription, members. Irreversible — confirm in UI.

---

# *15. Organization members (IAM)*

Requires **`X-Organization-Id`** and active org subscription.

### Client workflow

1. List members → `GET /api/organizations/members`.
2. Invite → `POST /api/organizations/members/invite` (email must match `company_domain`).
3. Pending invites → `GET /api/organizations/invitations` (owner/admin).
4. Change role → `PUT /api/organizations/members/{userId}/role`.
5. Remove member → `DELETE /api/organizations/members/{userId}` (revokes all their tokens).

### GET /api/organizations/members

**Success (200)** — `members` with pivot roles.

---

### POST /api/organizations/members/invite

**Request body**

```json
{
  "email": "colleague@acme.com",
  "role": "member"
}
```

| **Field** | **Rules** |
| --- | --- |
| `email` | Required; domain must equal org `company_domain` |
| `role` | Required: `admin`, `member`, `viewer` |

Invitation email sent; expires in 24 hours.

---

### GET /api/organizations/invitations

Owner/admin only. Returns non-expired pending invitations.

---

### PUT /api/organizations/members/{userId}/role

**Request body**

```json
{ "role": "admin" }
```

Cannot modify the org owner's role.

---

### DELETE /api/organizations/members/{userId}

Removes member and deletes all their Sanctum tokens immediately.

---

# *16. Organization invitations (public & authenticated)*

### Client workflow

1. Invitee opens email link → **`GET /api/organizations/invitations/{token}`** (shows org name, role, whether account exists).
2. **New user** registers and joins → **`POST /api/organizations/invitations/{token}/register`**.
3. **Existing user** logs in, then → **`POST /api/organizations/{token}/accept`**.

### GET /api/organizations/invitations/{token}

| **Method** | `GET` |
| --- | --- |
| **Auth** | Not required |

**Success (200)** — `is_exist`, `invitation` (email, role, organization).

---

### POST /api/organizations/invitations/{token}/register

| **Method** | `POST` |
| --- | --- |
| **Auth** | Not required |

**Request body**

```json
{
  "full_name": "Jane Doe",
  "job_tittle": "Analyst",
  "password": "SecurePass1!",
  "password_confirmation": "SecurePass1!"
}
```

**Success (201)** — `token`, `user` (auto-login).

---

### POST /api/organizations/{token}/accept

| **Method** | `POST` |
| --- | --- |
| **Auth** | Required |

Authenticated user's email must match invitation email.

**Success (200)** — joined organization.

---

# *17. Webhooks & server callbacks*

These endpoints are called by payment providers or email links — not by the main client app.

| **Endpoint** | **Method** | **Purpose** |
| --- | --- | --- |
| `/api/billing/paymob/webhook` | POST | Paymob payment notifications |
| `/api/billing/stripe/organization-webhook` | POST | Stripe B2B payment success |
| `/api/billing/paymob/redirect` | GET | Post-payment browser landing (HTML view) |

---

# *18. Common error responses*

| **Code** | **Meaning** |
| --- | --- |
| **401** | Missing/invalid token, or auth failure |
| **403** | Forbidden — no access to resource or workspace |
| **404** | Resource not found |
| **409** | Conflict (e.g. already a member) |
| **422** | Validation error or business rule (limits, DNS, domain mismatch) |
| **423** | Account locked |
| **500** | Server error |

**Validation (422)** — Laravel style:

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "field": ["Error message"]
  }
}
```

---

# *19. Project roles reference*

### Personal workspace (project collaborators)

| **Role** | **View** | **Edit targets** | **Run scans** | **Manage findings** | **Invite / manage members** |
| --- | --- | --- | --- | --- | --- |
| `owner` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `editor` | ✓ | ✓ | ✓ | ✓ | ✗ |
| `viewer` | ✓ | ✗ | ✗ | ✗ | ✗ |

### Organization workspace (org member role on org-owned projects)

| **Role** | **Manage projects/targets/scans** |
| --- | --- |
| `owner`, `admin` | ✓ |
| `member`, `viewer` | View access via `hasAccess`; manage actions require owner/admin |

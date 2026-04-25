# ⚡ CyberGuard — Complete Project Documentation

> **Graduation Project** | Laravel 12 | SaaS Platform | Penetration Testing & Security Assessment

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Core Concept & Vision](#2-core-concept--vision)
3. [User Journey — From Registration to Report](#3-user-journey--from-registration-to-report)
4. [User Types & Roles](#4-user-types--roles)
5. [Subscription Plans](#5-subscription-plans)
6. [Database Schema — All 20 Tables](#6-database-schema--all-20-tables)
7. [API Endpoints — Complete Reference](#7-api-endpoints--complete-reference)
8. [Scan System — How It Works](#8-scan-system--how-it-works)
9. [Scan Modules — All 12](#9-scan-modules--all-12)
10. [Collaboration & Invite System](#10-collaboration--invite-system)
11. [Findings & Evidence Management](#11-findings--evidence-management)
12. [Phishing Simulation Module](#12-phishing-simulation-module)
13. [PDF Report Generation](#13-pdf-report-generation)
14. [WebSocket Events — Real-time Updates](#14-websocket-events--real-time-updates)
15. [Security Architecture](#15-security-architecture)
16. [Tech Stack](#16-tech-stack)
17. [Backend Implementation Checklist](#17-backend-implementation-checklist)
18. [Frontend Integration Guide](#18-frontend-integration-guide)

---

## 1. Project Overview

**CyberGuard** is a multi-tenant SaaS platform built for professional penetration testing and security assessment. It consolidates every stage of a security engagement into one collaborative workspace — from project creation through automated vulnerability scanning, evidence collection, phishing simulation, and professional PDF report delivery.

### The Problem It Solves

| Without CyberGuard | With CyberGuard |
|---|---|
| Scattered tools (Burp Suite, Nmap, Notepad) | One unified platform |
| No collaboration — files shared over email | Real-time team collaboration with roles |
| Reports built manually — takes hours | One-click professional PDF |
| No tracking of vulnerability status | Full finding lifecycle management |
| Every project isolated | Central dashboard for everything |

---

## 2. Core Concept & Vision

### The Main Flow

```
User Registers
    ↓
Creates a Project (personal or organization-owned)
    ↓
Adds Targets (domains, IPs, network ranges)
    ↓
Invites teammates via link → they join with roles
    ↓
Launches Scans → Docker containers run security tools
    ↓
Findings auto-created → team reviews & manages them
    ↓
Optional: runs Phishing campaign on client's employees
    ↓
Generates PDF Report → delivers to client
```

### Architecture Pattern

```
HTTP Request
    → Laravel Router
    → Sanctum Auth Middleware
    → Laravel Policy (RBAC check)
    → Controller (validates → creates DB record → dispatches job)
    → JSON Response (immediate)

Background (async):
    Redis Queue → Worker → Docker Container
    → Script runs tool → outputs JSON
    → Laravel parses → creates Findings
    → broadcast(Event) → WebSocket → Frontend updates live
```

---

## 3. User Journey — From Registration to Report

### Step 1: Registration

```
POST /api/auth/register
Body: { name, email, password, password_confirmation }
```

- System creates `User` record with bcrypt hashed password
- `email_verified_at` = null (not active yet)
- System auto-creates `UserSubscription` with `plan: free` and default limits
- Sends verification email with signed URL
- User clicks link → `email_verified_at` = now() → account active

**Branches:**
- ✅ `201` → show "Check your email" message
- ❌ `422` → show validation errors under each field

---

### Step 2: Login

```
POST /api/auth/login
Body: { email, password }
```

**Branch A — No 2FA:**
- Returns `{ token, user }` → store token → redirect to Dashboard

**Branch B — 2FA enabled:**
- Returns `{ requires_2fa: true, temp_token }` → show 6-digit code screen
- User submits code → `POST /api/auth/2fa/verify { code, temp_token }`
- Returns full token

**Errors:**
- `401` → Invalid credentials
- `403` → Email not verified
- `429` → Too many attempts (rate limit: 5 tries per 15 min)

---

### Step 3: Create Project

```
POST /api/projects
Body: { name, description, owner_type, owner_id, start_date, end_date }
```

- `owner_type`: `"user"` (personal) or `"organization"`
- `owner_id`: the user's UUID or org's UUID
- System auto-adds creator to `project_collaborators` as `owner`
- Quota checked against `user_subscriptions.max_projects`

---

### Step 4: Add Targets

```
POST /api/projects/{id}/targets
Body: { type, value, label }
```

- `type`: `domain` | `ip` | `network`
- `value`: `techcorp.com` / `192.168.1.1` / `192.168.1.0/24`
- System validates format for each type
- `risk_score` starts at 0, recalculated after each scan
- `is_verified` starts false — optional DNS verification available

---

### Step 5: Invite Team

```
POST /api/projects/{id}/invitations
Body: { email, role }   // role: "editor" or "viewer"
```

- System generates unique 64-char token
- Stores in `project_invitations` with `expires_at = +7 days`
- If email provided → sends email with invite link
- Returns `{ invite_link }` for sharing via any channel

**Invitee accepts:**
```
POST /api/invitations/{token}/accept
```
- Validates token (not expired, not used)
- Creates `project_collaborators` row → immediate access

---

### Step 6: Launch Scan

```
POST /api/projects/{id}/scans
Body: { target_id, type, modules? }
// type: "auto" or "targeted"
// modules (if targeted): ["port-scan", "ssl-check", ...]
```

- Controller checks scan quota
- Creates `scan_job` with `status: pending`
- Dispatches `RunScanJob` to Redis queue
- Returns `202` immediately — doesn't wait for scan

**Background (async):**
1. Queue worker picks up job → `status: running`
2. Broadcasts `ScanStatusUpdated` → frontend shows spinner
3. For each module: `docker run --rm cyberguard/{module} {target}`
4. Script runs tool → outputs JSON to stdout
5. Laravel parses JSON → `Finding::create()` for each vulnerability
6. `target->recalculateRiskScore()`
7. `status: completed` → broadcasts `ScanCompleted`
8. Frontend receives findings live via WebSocket

---

### Step 7: Manage Findings

Each finding has a lifecycle:

```
open → in_progress → resolved
                   → accepted_risk
```

```
PATCH /api/findings/{id}
Body: { status: "in_progress" }
```

- After status change → risk score recalculates
- Evidence can be attached: screenshots, HTTP logs, files → stored in S3

---

### Step 8: Phishing Simulation (Optional)

```
POST /api/phishing/campaigns
→ POST /api/phishing/{id}/targets/import  (CSV upload)
→ POST /api/phishing/{id}/launch
```

- Each employee gets a unique tracking token
- System tracks: `sent` → `opened` → `clicked` → `submitted`
- Awareness score (0-100) calculated per employee
- Department-level risk aggregation

---

### Step 9: Generate PDF Report

```
POST /api/projects/{id}/reports
```

- Dispatches `GenerateReportJob` to queue → returns `202`
- Job renders Blade template → DomPDF → stores PDF in S3
- Creates `reports` record
- Broadcasts `ReportGenerated` → frontend shows download button

---

## 4. User Types & Roles

### Three Project-Level Roles

| Role | Project & Targets | Scans & Findings | Members & Reports |
|---|---|---|---|
| **👑 Owner** | Full control — create, edit, archive, delete | Launch scans, manage findings, upload evidence | ✅ Invite, remove, change roles. Generate & delete reports |
| **✏️ Editor** | Edit details, add targets. Cannot archive/delete | Launch scans, update findings, upload evidence | ❌ Cannot manage members. Can generate reports |
| **👁 Viewer** | Read-only | View findings & evidence. Cannot launch scans | ❌ No management. Can download existing reports |

**Critical Rule:** The Owner cannot remove themselves. To transfer ownership, they must first promote another member to Owner.

### Three User Personas

**1. Freelance Pentester**
- Works independently on personal projects
- Invites collaborators per-project without an organization
- Free or Pro plan

**2. Security Team**
- Works under a shared Organization
- Projects owned by the org entity
- Multiple admins can manage projects
- Pro or Enterprise plan

**3. Corporate Client**
- Runs internal security assessments
- Uses phishing simulation on their own employees
- Invites external pentesters as Editors
- Enterprise plan

---

## 5. Subscription Plans

### Personal Plans (user_subscriptions)

| Feature | Free | Starter | Pro |
|---|---|---|---|
| Max Projects | 3 | 10 | Unlimited |
| Max Targets | 10 | 50 | Unlimited |
| Scans / Month | 10 | 100 | Unlimited |
| Collaborators per project | 3 | 10 | Unlimited |
| Phishing Module | ❌ | ✅ | ✅ |
| PDF Reports | Basic | Advanced | Custom |
| Organizations | ❌ | ❌ | ✅ |
| Audit Logs | ❌ | 30 days | Full history |

### Organization Plans (organization_subscriptions)

| Feature | Starter | Pro | Enterprise |
|---|---|---|---|
| Max Projects | 10 | 50 | Unlimited |
| Max Targets | 50 | 200 | Unlimited |
| Max Members | 5 | 20 | Unlimited |
| Scans / Month | 100 | 500 | Unlimited |

**Quota Enforcement:**
- Checked before creating: projects, targets, scans
- Returns `403` with remaining count if exceeded
- Monthly scan count resets on 1st of each month

---

## 6. Database Schema — All 20 Tables

### Group 1: Core / Auth

#### `users`
```sql
id               UUID PK
email            VARCHAR UNIQUE
password         VARCHAR (bcrypt)
email_verified_at TIMESTAMP nullable
two_factor_secret VARCHAR nullable (encrypted)
two_factor_enabled BOOLEAN default false
remember_token   VARCHAR nullable
full_name        VARCHAR
avatar_url       TEXT nullable
last_login_at    TIMESTAMP nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
deleted_at       TIMESTAMP nullable (SoftDelete)
```

#### `user_subscriptions`
```sql
id               UUID PK
user_id          UUID FK → users (cascade)
plan             ENUM(free, starter, pro) default free
status           ENUM(active, expired, cancelled) default active
max_projects     INT default 3
max_targets      INT default 10
max_scans_per_month INT default 10
started_at       TIMESTAMP
expires_at       TIMESTAMP nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

#### `organizations`
```sql
id               UUID PK
owner_id         UUID FK → users (restrict)
name             VARCHAR
slug             VARCHAR UNIQUE
domain           VARCHAR nullable
logo_url         TEXT nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
deleted_at       TIMESTAMP nullable (SoftDelete)
```

#### `organization_subscriptions`
```sql
id               UUID PK
organization_id  UUID FK → organizations (cascade)
plan             ENUM(starter, pro, enterprise) default starter
status           ENUM(active, expired, cancelled)
max_projects     INT default 10
max_targets      INT default 50
max_members      INT default 5
max_scans_per_month INT default 100
started_at       TIMESTAMP
expires_at       TIMESTAMP nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

#### `organization_members`
```sql
id               UUID PK
organization_id  UUID FK → organizations (cascade)
user_id          UUID FK → users (cascade)
role             ENUM(owner, admin, member, viewer)
joined_at        TIMESTAMP
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

---

### Group 2: Projects & Collaboration

#### `projects`
```sql
id               UUID PK
owner_type       VARCHAR  -- "App\Models\User" or "App\Models\Organization"
owner_id         UUID     -- polymorphic FK
created_by       UUID FK → users (restrict)
name             VARCHAR
description      TEXT nullable
status           ENUM(active, archived) default active
max_collaborators INT default 5
start_date       DATE nullable
end_date         DATE nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
deleted_at       TIMESTAMP nullable (SoftDelete)
```

#### `project_collaborators`
```sql
id               UUID PK
project_id       UUID FK → projects (cascade)
user_id          UUID FK → users (cascade)
invited_by       UUID FK → users nullable
role             ENUM(owner, editor, viewer)
status           ENUM(pending, accepted, rejected) default accepted
invited_at       TIMESTAMP nullable
accepted_at      TIMESTAMP nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
deleted_at       TIMESTAMP nullable (SoftDelete)
```

#### `project_invitations`
```sql
id               UUID PK
project_id       UUID FK → projects (cascade)
invited_by       UUID FK → users (cascade)
email            VARCHAR nullable
token            VARCHAR(100) UNIQUE
role             ENUM(editor, viewer) default editor
status           ENUM(pending, accepted, expired) default pending
expires_at       TIMESTAMP  -- +7 days from creation
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

---

### Group 3: Targets & Scan System

#### `targets`
```sql
id               UUID PK
project_id       UUID FK → projects (cascade)
type             ENUM(domain, ip, network)
value            VARCHAR(500)  -- "techcorp.com" / "1.2.3.4" / "192.168.1.0/24"
label            VARCHAR nullable
is_verified      BOOLEAN default false
risk_score       DECIMAL(5,2) nullable
last_scanned_at  TIMESTAMP nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
INDEX(project_id)
```

#### `scan_modules`
```sql
id               UUID PK
name             VARCHAR        -- "SQL Injection Scanner"
slug             VARCHAR UNIQUE -- "sqli"
category         ENUM(web, network, recon)
description      TEXT nullable
is_active        BOOLEAN default true
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

**Seeded modules:**

| Slug | Name | Category |
|---|---|---|
| `sqli` | SQL Injection Scanner | web |
| `xss` | XSS Scanner | web |
| `csrf` | CSRF Checker | web |
| `http-headers` | HTTP Security Headers | web |
| `cors-check` | CORS Misconfiguration | web |
| `port-scan` | Port Scanner (nmap) | network |
| `banner-grab` | Banner Grabber | network |
| `ssl-check` | SSL/TLS Checker | network |
| `subdomain-enum` | Subdomain Enumeration | recon |
| `whois` | WHOIS Lookup | recon |
| `dir-bruteforce` | Directory Bruteforce (ffuf) | recon |
| `cve-lookup` | CVE Lookup | recon |

#### `scan_jobs`
```sql
id               UUID PK
target_id        UUID FK → targets (cascade)
project_id       UUID FK → projects (cascade)
triggered_by     UUID FK → users (restrict)
scan_type        ENUM(auto, targeted)
status           ENUM(pending, running, completed, failed) default pending
container_id     VARCHAR nullable  -- Docker container ID
started_at       TIMESTAMP nullable
finished_at      TIMESTAMP nullable
error_log        TEXT nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
INDEX(target_id, status)
```

#### `scan_job_modules` (pivot)
```sql
id               UUID PK
scan_job_id      UUID FK → scan_jobs (cascade)
module_id        UUID FK → scan_modules (restrict)
status           ENUM(pending, running, done, failed) default pending
duration_ms      INT nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

#### `findings`
```sql
id               UUID PK
scan_job_id      UUID FK → scan_jobs (cascade)
target_id        UUID FK → targets (cascade)
title            VARCHAR(500)
description      TEXT nullable
severity         ENUM(critical, high, medium, low, info)
cvss_score       DECIMAL(4,1) nullable  -- 0.0 to 10.0
cvss_vector      VARCHAR(200) nullable  -- "CVSS:3.1/AV:N/AC:L/..."
cve_id           VARCHAR(50) nullable   -- "CVE-2024-1234"
remediation      TEXT nullable
status           ENUM(open, in_progress, resolved, accepted_risk) default open
accepted_risk_note TEXT nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
deleted_at       TIMESTAMP nullable (SoftDelete)
INDEX(scan_job_id, severity)
INDEX(target_id, status)
```

#### `evidences`
```sql
id               UUID PK
finding_id       UUID FK → findings (cascade)
type             ENUM(screenshot, request_response, log, file)
file_path        TEXT        -- S3 key
file_size        INT nullable -- bytes
mime_type        VARCHAR nullable
uploaded_by      UUID FK → users nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

---

### Group 4: Phishing Module

#### `phishing_campaigns`
```sql
id               UUID PK
project_id       UUID FK → projects (cascade)
created_by       UUID FK → users (restrict)
name             VARCHAR
status           ENUM(draft, active, completed, paused) default draft
email_subject    VARCHAR(500)
email_body       TEXT
phishing_url     TEXT nullable
sender_name      VARCHAR nullable
sender_email     VARCHAR nullable
authorized_domain VARCHAR  -- must match a project target
scheduled_at     TIMESTAMP nullable
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

#### `phishing_targets`
```sql
id               UUID PK
campaign_id      UUID FK → phishing_campaigns (cascade)
employee_email   VARCHAR
employee_name    VARCHAR nullable
department       VARCHAR(100) nullable
tracking_token   VARCHAR(100) UNIQUE  -- unique per employee
sent_at          TIMESTAMP nullable
awareness_score  INT nullable  -- 0 to 100
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

#### `phishing_events`
```sql
id                    UUID PK
phishing_target_id    UUID FK → phishing_targets (cascade)
campaign_id           UUID FK → phishing_campaigns (cascade)
event_type            ENUM(sent, opened, clicked, submitted)
ip_address            VARCHAR(45) nullable
user_agent            TEXT nullable
submitted_data        JSON nullable  -- stored encrypted, never plain text
occurred_at           TIMESTAMP
created_at            TIMESTAMP
updated_at            TIMESTAMP
INDEX(campaign_id, event_type)
```

---

### Group 5: Reporting & Audit

#### `reports`
```sql
id               UUID PK
project_id       UUID FK → projects nullable (nullOnDelete)
target_id        UUID FK → targets nullable (nullOnDelete)
generated_by     UUID FK → users (restrict)
title            VARCHAR
type             ENUM(project, target, phishing)
format           ENUM(pdf, json, html)
file_url         TEXT nullable  -- S3 URL
summary          JSON nullable  -- { total_findings, critical, risk_score }
generated_at     TIMESTAMP
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

#### `risk_scores`
```sql
id               UUID PK
target_id        UUID FK → targets (cascade)
scan_job_id      UUID FK → scan_jobs (cascade)
overall_score    DECIMAL(5,2)   -- 0 to 100
critical_count   INT default 0
high_count       INT default 0
medium_count     INT default 0
low_count        INT default 0
calculated_at    TIMESTAMP
created_at       TIMESTAMP
updated_at       TIMESTAMP
INDEX(target_id, calculated_at)  -- for trend charts
```

#### `audit_logs`
```sql
id               UUID PK
user_id          UUID FK → users nullable (nullOnDelete)
owner_type       VARCHAR nullable  -- "User" or "Organization"
owner_id         UUID nullable
action           VARCHAR(100)  -- "project.created", "scan.started", "finding.resolved"
entity_type      VARCHAR(100) nullable  -- "Project", "Finding", etc.
entity_id        UUID nullable
metadata         JSON nullable  -- { old_values, new_values, ... }
ip_address       VARCHAR(45) nullable
created_at       TIMESTAMP  -- no updated_at, no soft delete, append-only
INDEX(owner_type, owner_id, created_at)
```

---

### Database Design Principles

- **All PKs are UUIDs** → prevents ID enumeration attacks
- **SoftDeletes on all main entities** → data never lost, can be restored
- **Polymorphic ownership** on `projects` → one model, two owner types
- **Audit logs are append-only** → no update, no delete
- **Sensitive columns encrypted** → `two_factor_secret`, `submitted_data`
- **Risk scores tracked per scan** → enables trend chart over time
- **Quota enforced in DB + code** → two layers of protection

---

## 7. API Endpoints — Complete Reference

### Auth

```
POST   /api/auth/register            Register new user
GET    /api/auth/verify-email/{id}/{hash}  Verify email
POST   /api/auth/login               Login
POST   /api/auth/logout              Logout (delete current token)
POST   /api/auth/forgot-password     Send password reset email
POST   /api/auth/reset-password      Reset password with token
POST   /api/auth/2fa/enable          Get QR code for 2FA setup
POST   /api/auth/2fa/confirm         Confirm 2FA with first code
POST   /api/auth/2fa/verify          Verify 2FA code on login
POST   /api/auth/2fa/disable         Disable 2FA
GET    /api/auth/me                  Get current user + subscription
```

### Organizations

```
GET    /api/organizations            List user's organizations
POST   /api/organizations            Create organization
GET    /api/organizations/{id}       Get organization details
PATCH  /api/organizations/{id}       Update organization
DELETE /api/organizations/{id}       Delete organization (soft)
POST   /api/organizations/{id}/members        Add member
PATCH  /api/organizations/{id}/members/{uid}  Change member role
DELETE /api/organizations/{id}/members/{uid}  Remove member
```

### Projects

```
GET    /api/projects                 List all accessible projects
POST   /api/projects                 Create project
GET    /api/projects/{id}            Get project + targets + collaborators
PATCH  /api/projects/{id}            Update project
PATCH  /api/projects/{id}/archive    Archive project
DELETE /api/projects/{id}            Delete project (soft)
GET    /api/projects/{id}/scans      List scan jobs
GET    /api/projects/{id}/findings   List findings (filterable)
```

### Invitations

```
POST   /api/projects/{id}/invitations         Create invitation
DELETE /api/projects/{id}/invitations/{inv}   Cancel invitation
GET    /api/invitations/{token}               Preview invitation
POST   /api/invitations/{token}/accept        Accept invitation
```

### Collaborators

```
GET    /api/projects/{id}/collaborators            List members
PATCH  /api/projects/{id}/collaborators/{uid}      Change role
DELETE /api/projects/{id}/collaborators/{uid}      Remove member
```

### Targets

```
GET    /api/projects/{id}/targets          List targets
POST   /api/projects/{id}/targets          Add target
GET    /api/targets/{id}                   Get target details + risk history
PATCH  /api/targets/{id}                   Update target
DELETE /api/targets/{id}                   Delete target (soft)
POST   /api/targets/{id}/verify            Trigger domain verification
```

### Scans

```
POST   /api/projects/{id}/scans       Launch scan
GET    /api/scans/{id}                Get scan job status + modules
DELETE /api/scans/{id}                Stop running scan
GET    /api/scan-modules              List available modules
```

### Findings

```
GET    /api/projects/{id}/findings    List findings (filter: severity, status, target_id)
POST   /api/projects/{id}/findings    Create manual finding
GET    /api/findings/{id}             Get finding details
PATCH  /api/findings/{id}             Update status / details
DELETE /api/findings/{id}             Delete finding (soft)
POST   /api/findings/{id}/evidences   Upload evidence file
DELETE /api/evidences/{id}            Delete evidence
```

### Phishing

```
GET    /api/phishing/campaigns                    List campaigns
POST   /api/phishing/campaigns                    Create campaign
GET    /api/phishing/campaigns/{id}               Get campaign stats
PATCH  /api/phishing/campaigns/{id}               Update campaign
POST   /api/phishing/campaigns/{id}/targets/import  Import employees CSV
POST   /api/phishing/campaigns/{id}/launch        Launch campaign
POST   /api/phishing/campaigns/{id}/pause         Pause campaign

-- Public routes (no auth — for tracking) --
GET    /track/{token}/open           Track email open (returns 1x1 pixel)
GET    /track/{token}/click          Track link click (redirects)
POST   /track/{token}/submit         Track form submission
```

### Reports

```
GET    /api/projects/{id}/reports    List generated reports
POST   /api/projects/{id}/reports    Generate new report (async)
GET    /api/reports/{id}/download    Get pre-signed S3 download URL
DELETE /api/reports/{id}             Delete report
```

### Dashboard

```
GET    /api/dashboard/stats           Overview counts
GET    /api/dashboard/recent-activity Last 20 events
```

---

## 8. Scan System — How It Works

### The Full Flow

```
User → POST /api/projects/{id}/scans
         ↓
    ScanController
    1. Validate: target belongs to project
    2. Check scan quota (monthly limit)
    3. ScanJob::create(status: pending)
    4. RunScanJob::dispatch() → Redis queue
    5. Return 202 immediately
         ↓
    Queue Worker picks up job
    6. ScanJob status → "running"
    7. broadcast(ScanStatusUpdated) → WebSocket
         ↓
    For each module:
    8. docker run --rm \
           --network=cyberguard-scan-net \
           --memory=256m \
           --cpus=0.5 \
           --read-only \
           --security-opt=no-new-privileges \
           cyberguard/{module} {target}
         ↓
    Inside container:
    9.  Tool runs (nmap / subfinder / ffuf / etc.)
    10. Python/Bash script parses output
    11. Prints structured JSON to stdout
         ↓
    Back in Laravel:
    12. ScanExecutor reads JSON
    13. Finding::create() × N
    14. target->recalculateRiskScore()
         ↓
    15. ScanJob status → "completed"
    16. broadcast(ScanCompleted) → WebSocket
         ↓
    Frontend:
    17. Receives findings live — no page refresh
    18. Risk score gauge updates
    19. Toast notification shown
```

### Security — Command Injection Prevention

```php
// ❌ NEVER do this
shell_exec("nmap " . $target);

// ✅ ALWAYS use array form — target is a separate argument
Process::run(['docker', 'run', '--rm', 'cyberguard/port-scan', $target]);

// ✅ Validate target before anything
if (!filter_var($target, FILTER_VALIDATE_DOMAIN) &&
    !filter_var($target, FILTER_VALIDATE_IP)) {
    throw new \InvalidArgumentException('Invalid target');
}
```

### Docker Image Structure

Each module has its own directory:

```
docker/
├── port-scan/
│   ├── Dockerfile       # installs nmap on Alpine
│   └── scan.py          # runs nmap, parses XML → JSON
├── subdomain-enum/
│   ├── Dockerfile       # installs subfinder + dnsx
│   └── scan.py
├── ssl-check/
│   ├── Dockerfile       # uses openssl
│   └── scan.sh
├── http-headers/
│   ├── Dockerfile       # uses curl
│   └── scan.py
└── ...
```

**Every script must:**
1. Validate input with whitelist regex before doing anything
2. Print ONLY valid JSON to stdout
3. Print errors to stderr only
4. Exit with code 0 on success, non-zero on failure

**JSON output format (same for all modules):**
```json
{
  "target": "techcorp.com",
  "findings": [
    {
      "title": "Open Port 23/tcp (Telnet)",
      "description": "Telnet is enabled and transmits data in plaintext...",
      "severity": "critical",
      "cvss_score": 9.1,
      "cvss_vector": "AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:N",
      "cve_id": null,
      "remediation": "Disable Telnet. Use SSH instead.",
      "raw_output": "{...}"
    }
  ],
  "stats": { "total": 3, "critical": 1, "high": 1, "medium": 1 },
  "scanned_at": "2025-04-10T14:32:11Z"
}
```

### Risk Score Formula

```
Score = (critical × 10 + high × 7 + medium × 4 + low × 1)
        ─────────────────────────────────────────────────── × 100
                    maximum_possible_score

Result: 0 to 100 (stored in targets.risk_score)
```

Saved per scan in `risk_scores` table → enables trend chart over time.

---

## 9. Scan Modules — All 12

### Web Modules

| Module | Tool | What it detects |
|---|---|---|
| `sqli` | Custom / sqlmap | SQL Injection in form inputs and URL parameters |
| `xss` | Custom / dalfox | Reflected and stored XSS in all input vectors |
| `csrf` | Custom | Missing CSRF tokens on state-changing endpoints |
| `http-headers` | curl | Missing security headers: HSTS, CSP, X-Frame-Options, etc. |
| `cors-check` | Custom | Misconfigured CORS allowing arbitrary origins |

### Network Modules

| Module | Tool | What it detects |
|---|---|---|
| `port-scan` | nmap | Open ports + running services + versions |
| `banner-grab` | nmap --script banner | Service banners → matches against vulnerable versions |
| `ssl-check` | openssl s_client | Weak protocols (SSLv2/3, TLS1.0), weak ciphers, cert expiry |

### Recon Modules

| Module | Tool | What it detects |
|---|---|---|
| `subdomain-enum` | subfinder + dnsx | Subdomains, takeover risks, internal IP leaks |
| `whois` | whois | Domain registration details, expiry dates |
| `dir-bruteforce` | ffuf | Hidden paths and files (max 1000 requests enforced) |
| `cve-lookup` | Custom + NVD API | Matches detected software versions against CVE database |

---

## 10. Collaboration & Invite System

### How Invitations Work

```
1. Owner → POST /api/projects/{id}/invitations { role: "editor" }
2. System → generates token (Str::random(64))
3. System → project_invitations::create(token, expires_at: +7days, status: pending)
4. If email provided → sends email with link
5. Returns { invite_link: "https://app/invite/{token}" }
6. Owner shares link via any channel
7. Invitee opens link → sees project name + role
8. Invitee → POST /api/invitations/{token}/accept
9. System validates: not expired, not already member
10. System → project_collaborators::create(role, status: accepted)
11. Invitee has immediate access
```

### Edge Cases

| Scenario | What happens |
|---|---|
| Token expired (7 days) | `422` — "Invitation has expired". Owner must create new invitation. |
| Already a member | `409` — "Already a collaborator on this project." |
| Owner removes member | Soft-delete collaborator row. Member loses access immediately on next API call. |
| Role change | `PATCH` by Owner only. Takes effect immediately. |
| Owner tries to remove themselves | `403` — Business rule enforced at Policy level. |
| Invitation for non-registered email | Link still works — user registers first, then accepts. |

### Permission Checks

Every API endpoint that touches a project resource calls a Laravel Policy:

```php
// Example — ScanPolicy
public function create(User $user, Project $project): bool
{
    return $project->collaborators()
        ->where('user_id', $user->id)
        ->whereIn('role', ['owner', 'editor'])
        ->where('status', 'accepted')
        ->exists();
}
```

---

## 11. Findings & Evidence Management

### Finding Lifecycle

```
open  →  in_progress  →  resolved
                      →  accepted_risk (requires acceptance_note)
```

After every status change → `target->recalculateRiskScore()` is called.

### Severity Levels

| Severity | CVSS Range | Weight in risk score |
|---|---|---|
| Critical | 9.0 – 10.0 | × 10 |
| High | 7.0 – 8.9 | × 7 |
| Medium | 4.0 – 6.9 | × 4 |
| Low | 0.1 – 3.9 | × 1 |
| Info | 0.0 | × 0 |

### Evidence Types

| Type | Description | Source |
|---|---|---|
| `screenshot` | Page screenshot | Auto via Puppeteer, or manual upload |
| `request_response` | HTTP request/response pair | Auto from scan, or manual |
| `log` | Tool output log | Auto from Docker container |
| `file` | Any file (pcap, etc.) | Manual upload only |

All files stored in S3. Never on app server disk. Returned as pre-signed URLs (1 hour validity).

---

## 12. Phishing Simulation Module

### Campaign Lifecycle

```
draft → active → completed
              → paused
```

### Tracking Events Per Employee

| Event | Trigger | Score deduction |
|---|---|---|
| `sent` | Email delivered | — |
| `opened` | Pixel image loaded | -40 |
| `clicked` | Phishing link clicked | -30 |
| `submitted` | Form filled and submitted | -20 |

Employee starts at score 100. Score floored at 0.

### Tracking Routes (Public — No Auth Required)

```
GET  /track/{token}/open     → return 1×1 transparent PNG
GET  /track/{token}/click    → record event, redirect to phishing page
POST /track/{token}/submit   → record event, show "awareness" page
                               NOTE: submitted credentials are NOT stored
```

### Risk Levels

| Score | Level | Action |
|---|---|---|
| 80-100 | Safe | Positive reinforcement |
| 40-79 | At Risk | Targeted awareness training |
| 0-39 | High Risk | Mandatory training + enforce MFA immediately |

---

## 13. PDF Report Generation

### Flow

```
POST /api/projects/{id}/reports
    ↓
ReportPolicy check (Owner or Editor only)
    ↓
GenerateReportJob dispatched to queue
    ↓
Returns 202 Accepted immediately
    ↓ (background)
Load project with all relations:
  - targets + risk_score history
  - findings (with evidences URLs)
  - scan_jobs
  - phishing_campaigns (if any)
    ↓
Render resources/views/reports/project.blade.php
    ↓
DomPDF (barryvdh/laravel-dompdf) → PDF binary
    ↓
Storage::disk('s3')->put("reports/{project_id}/{timestamp}.pdf")
    ↓
reports::create(file_path, risk_score_snapshot, summary JSON)
    ↓
broadcast(ReportGenerated) → frontend shows download button
```

### Report Contents

1. Executive Summary
2. Project scope and methodology
3. All findings grouped by severity
4. CVSS v3 score + vector per finding
5. Remediation recommendations
6. Evidence screenshots inline
7. Historical risk score trend chart (from `risk_scores` table)
8. Phishing awareness results (if applicable)
9. Generated timestamp + pentester name

### Report Types

| Type | Scope | Use case |
|---|---|---|
| `project` | All targets + all findings | Final client deliverable |
| `target` | Single target only | Partial deliverable or milestone |
| `phishing` | Phishing campaign results only | Awareness campaign report |

---

## 14. WebSocket Events — Real-time Updates

### Setup

```javascript
// Channel: private per project
window.Echo.private(`project.${projectId}`)
  .listen('ScanStatusUpdated', handler)
  .listen('ScanCompleted', handler)
  .listen('ScanFailed', handler)
  .listen('ReportGenerated', handler)
```

### Channel Authorization

```php
// routes/channels.php
Broadcast::channel('project.{id}', function (User $user, string $id) {
    return $user->projectCollaborators()
        ->where('project_id', $id)
        ->where('status', 'accepted')
        ->exists();
});
```

### Events

#### `ScanStatusUpdated`
Fired when: scan starts running
```json
{
  "scan_job": { "id": "uuid", "status": "running" }
}
```

#### `ScanCompleted`
Fired when: scan finishes successfully
```json
{
  "scan_job": { "id": "uuid", "status": "completed", "finished_at": "..." },
  "findings": [
    { "id": "uuid", "title": "...", "severity": "critical", "cvss_score": 9.8 }
  ],
  "stats": {
    "total_findings": 5,
    "critical": 1,
    "high": 2,
    "new_risk_score": 78.5
  }
}
```

#### `ScanFailed`
Fired when: scan fails or times out
```json
{
  "scan_job": { "id": "uuid", "status": "failed", "error_log": "..." }
}
```

#### `ReportGenerated`
Fired when: PDF report is ready
```json
{
  "report": { "id": "uuid", "download_url": "https://s3.../signed-url" }
}
```

---

## 15. Security Architecture

### Input Validation (Two Layers)

1. **Laravel FormRequest** → validates before controller runs
2. **Docker script regex** → validates again inside the container

```bash
# Inside every scan script (bash)
if [[ ! "$TARGET" =~ ^[a-zA-Z0-9._/-]+$ ]]; then
    echo '{"error": "Invalid target"}' >&2
    exit 1
fi
```

### Command Injection Prevention

- Always use `Process::run([array])` not `shell_exec(string)`
- Target is always a separate array element — never interpolated
- Docker network `cyberguard-scan-net` is `--internal` (no external internet from containers)

### Data Protection

- Passwords: bcrypt
- 2FA secrets: Laravel encrypted cast
- Submitted phishing data: encrypted at rest
- Files: private S3 bucket, access via pre-signed URLs only
- API tokens: Sanctum (hashed in DB)

### Access Control

- Every endpoint protected by Sanctum middleware
- Every resource action goes through a Laravel Policy
- Viewers cannot call write endpoints (enforced server-side — not just hidden on frontend)
- Audit logs on every important action

### Rate Limiting

- Auth routes: 5 attempts per 15 minutes
- API routes: 60 requests per minute per user
- Scan launch: enforced by monthly quota in DB

---

## 16. Tech Stack

| Layer | Technology |
|---|---|
| **Framework** | Laravel 12 (PHP 8.3) |
| **Database** | MySQL 8+ |
| **Auth** | Laravel Sanctum + TOTP 2FA (pragmarx/google2fa-laravel) |
| **Queue** | Redis + Laravel Queues |
| **WebSocket** | Laravel Echo + Pusher (or laravel-websockets for self-hosted) |
| **Scan Execution** | Docker containers (one per module) |
| **File Storage** | Amazon S3 / MinIO |
| **Screenshots** | Puppeteer / Browsershot |
| **PDF Generation** | DomPDF (barryvdh/laravel-dompdf) |
| **Frontend** | HTML + Tailwind CSS + Vanilla JS + Axios |
| **Process Runner** | `Illuminate\Process\Process` |

---

## 17. Backend Implementation Checklist

### Phase 1: Auth & Users
- [ ] `RegisterRequest` + User creation + auto-create UserSubscription (plan: free)
- [ ] Email verification flow with signed URL
- [ ] Login with `Auth::attempt()` + email verified check
- [ ] 2FA setup: enable → QR code → confirm → verify on login → disable
- [ ] Sanctum token issue + `last_login_at` update
- [ ] Rate limiting on auth routes (5 attempts / 15 min)
- [ ] Logout: delete current token
- [ ] Password reset: forgot → email → reset → revoke all tokens
- [ ] `GET /api/auth/me` endpoint

### Phase 2: Organizations & Subscriptions
- [ ] Organization CRUD + auto-add creator as owner in `organization_members`
- [ ] `CheckProjectQuota` middleware
- [ ] `CheckScanQuota` middleware (count this month's scans)
- [ ] When org owns project → check `organization_subscriptions` quota

### Phase 3: Projects & Collaboration
- [ ] Project CRUD with polymorphic ownership
- [ ] Auto-add creator to `project_collaborators` as owner
- [ ] `ProjectPolicy` with all permission methods
- [ ] Index endpoint scoped to user's projects only
- [ ] Archive endpoint (Owner only)
- [ ] Soft delete with cascade to targets/findings
- [ ] Invitation creation with token + expiry
- [ ] Accept invitation flow (validate token → create collaborator)
- [ ] Remove collaborator (enforce: cannot remove self if sole Owner)
- [ ] Change collaborator role (Owner only, cannot downgrade sole Owner)
- [ ] Daily command to expire pending invitations

### Phase 4: Targets
- [ ] Target CRUD with type validation per format
- [ ] Max /24 CIDR enforcement for network type
- [ ] `CheckTargetQuota` against subscription
- [ ] Domain verification via DNS TXT record
- [ ] `recalculateRiskScore()` method on Target model

### Phase 5: Scan System
- [ ] Set `QUEUE_CONNECTION=redis` + install predis
- [ ] Set `BROADCAST_DRIVER=pusher` + configure
- [ ] Verify Docker accessible by Laravel process user
- [ ] Create Docker network: `docker network create cyberguard-scan-net --internal`
- [ ] Seed `scan_modules` table (12 modules)
- [ ] Configure Supervisor for queue worker
- [ ] `ScanController@store`: validate → quota check → create job → dispatch → 202
- [ ] `RunScanJob`: timeout=300, tries=1, handle(), failed()
- [ ] `ScanExecutor` service: `run(module, target)` → Docker → parse JSON → return findings
- [ ] Docker hardened flags: `--rm --memory=256m --cpus=0.5 --read-only --security-opt=no-new-privileges`
- [ ] Build all 12 Docker images
- [ ] `ScanStatusUpdated` event
- [ ] `ScanCompleted` event with findings payload
- [ ] `ScanFailed` event
- [ ] Channel authorization in `routes/channels.php`

### Phase 6: Findings & Evidence
- [ ] Findings CRUD (filter by severity, status, target_id)
- [ ] Status update → trigger `recalculateRiskScore()`
- [ ] `accepted_risk` status requires `acceptance_note`
- [ ] Evidence upload → stream to S3 → return pre-signed URL
- [ ] Pre-signed URL generation for download (1 hour validity)

### Phase 7: Phishing
- [ ] Campaign CRUD
- [ ] CSV import → create `phishing_targets` with unique tokens
- [ ] Launch campaign → dispatch email job with rate throttling
- [ ] Public tracking routes (outside auth middleware)
- [ ] `open` tracking: create event → -40 score → return 1x1 PNG
- [ ] `click` tracking: create event → -30 score → redirect
- [ ] `submit` tracking: create event → -20 score → DO NOT store credentials
- [ ] Prevent duplicate events per employee per type
- [ ] Department-level aggregation in campaign stats

### Phase 8: Reports
- [ ] `ReportPolicy` (Owner/Editor generate, Viewer download only)
- [ ] `GenerateReportJob` dispatched to queue → 202
- [ ] Blade template for PDF (`resources/views/reports/project.blade.php`)
- [ ] DomPDF rendering → store to S3
- [ ] `reports` record creation with summary JSON
- [ ] `ReportGenerated` broadcast
- [ ] Download endpoint → fresh pre-signed URL → redirect

### Phase 9: Security & Audit
- [ ] `AuditLog` model observer on: Project, Target, Finding, ScanJob, PhishingCampaign
- [ ] All routes use `FormRequest` classes
- [ ] All responses use `JsonResource` classes (never raw models)
- [ ] `throttle:60,1` on API routes
- [ ] Sensitive columns use `encrypted` cast
- [ ] `APP_DEBUG=false` in production
- [ ] Register all Policies in `AuthServiceProvider`
- [ ] Feature tests: one per role per sensitive endpoint
- [ ] Cron: `* * * * * php artisan schedule:run` on server

---

## 18. Frontend Integration Guide

### Setup — api.js (do this once)

```javascript
const BASE_URL = 'http://localhost:8000/api'

async function apiCall(method, endpoint, data = null) {
  const token = localStorage.getItem('cg_token')
  const config = {
    method,
    url: BASE_URL + endpoint,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token && { 'Authorization': `Bearer ${token}` })
    },
    ...(data && { data: JSON.stringify(data) })
  }
  try {
    const res = await axios(config)
    return { ok: true, data: res.data, status: res.status }
  } catch (err) {
    const status = err.response?.status
    if (status === 401) {
      localStorage.removeItem('cg_token')
      window.location.href = '/login.html'
    }
    return { ok: false, status, errors: err.response?.data }
  }
}

const API = {
  get:    (url)       => apiCall('GET',    url),
  post:   (url, data) => apiCall('POST',   url, data),
  patch:  (url, data) => apiCall('PATCH',  url, data),
  delete: (url)       => apiCall('DELETE', url),
}
```

### HTTP Status Codes Reference

| Status | Meaning | Frontend action |
|---|---|---|
| `200` | Success | Use `res.data` |
| `201` | Created | Show success message |
| `202` | Accepted (async) | Wait for WebSocket event |
| `401` | Unauthenticated | Remove token → redirect to login |
| `403` | Unauthorized (wrong role) | Show "No permission" message |
| `422` | Validation error | Show errors under each field |
| `429` | Rate limited | Show "Try again later" |
| `500` | Server error | Show "Something went wrong" |

### 422 Error Format

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email":    ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

Display:
```javascript
Object.keys(fields).forEach(field => {
  const el = document.getElementById(`error_${field}`)
  if (el) el.textContent = fields[field][0]
})
```

### WebSocket Setup

```javascript
// Install: npm install laravel-echo pusher-js
// Or CDN: pusher.min.js + echo.iife.js

const echo = new Echo({
  broadcaster:  'pusher',
  key:          'YOUR_PUSHER_KEY',
  cluster:      'mt1',
  forceTLS:     true,
  authEndpoint: '/api/broadcasting/auth',
  auth: { headers: { Authorization: `Bearer ${localStorage.getItem('cg_token')}` } }
})

function listenToProject(projectId) {
  echo.private(`project.${projectId}`)
    .listen('ScanStatusUpdated', (data) => {
      updateScanCard(data.scan_job.id, data.scan_job.status)
    })
    .listen('ScanCompleted', (data) => {
      data.findings.forEach(f => addFindingRow(f))
      updateRiskScore(data.stats.new_risk_score)
    })
    .listen('ScanFailed', (data) => {
      showError(data.scan_job.error_log)
    })
    .listen('ReportGenerated', (data) => {
      showDownloadButton(data.report.download_url)
    })
}
```

### Frontend Build Order

**Week 1:** Auth (Login, Register, 2FA, Token handling, api.js)  
**Week 2:** Dashboard layout + Projects list + Create project  
**Week 3:** Project details + Targets + Scan launcher + WebSocket  
**Week 4:** Findings table + Team management + Phishing + Reports

---

*CyberGuard · Graduation Project · Laravel 12 · 2025*
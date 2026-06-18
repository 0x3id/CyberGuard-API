# CyberGuard - B2B Multi-Tenancy & Subscription Planning Specification

## 1. Architectural Overview
CyberGuard implements a strict B2B Multi-Tenant architecture using **Polymorphic Ownership** on core entities (such as Projects). The system guarantees absolute data isolation between individual user workspaces and Corporate Organizations (`Organizations`). Access control operates via a central **Role-Based Access Control (RBAC)** matrix inside the tenant context, enforced securely at the network/API layer via custom middleware.

---

## 2. Database & Entity Lifecycle Relationships

### 2.1 The Polymorphic Pivot (`projects` table)
Projects partition data securely using Laravel's Polymorphic relations:
* `owner_type`: `App\Models\User` (Individual/Personal) OR `App\Models\Organization` (B2B Tenant).
* `owner_id`: UUID corresponding to the owner record.

### 2.2 System Schemas Utilized
This architecture builds strictly upon the existing migrations:
1. `organizations`: Core tenant metadata (UUID, owner_id, name, slug, domain).
2. `organization_subscriptions`: Keeps active tier limits (`starter`, `pro`, `enterprise`), monitoring constraints (`max_projects`, `max_targets`, `max_members`, `max_scans_per_month`).
3. `organization_members`: Pivot defining corporate authorization levels via Enum roles: `['owner', 'admin', 'member', 'viewer']`.
4. `organization_invitations` *(Required Addition)*: Manages temporary, secure invitation states containing unique tracking tokens and expiration timestamps to prevent database pollution.

---

## 3. Core Workflows & Functional Requirements

### 3.1 Organization Onboarding & Identity Swap (Monetization Safe Flow)
To prevent organizational hijacking and preserve server resources, identity migration occurs only post-payment verification:

1. **Initiation:** Individual user provides `org_name`, `company_domain` (e.g., `intel.com`), and `company_email` (e.g., `admin@intel.com`).
2. **Domain Matching Validation:** The Backend validates that the `company_email` domain string strictly matches the provided `company_domain`.
3. **Verification Email:** A secure verification link containing an expiration token is sent to the `company_email`.
4. **Checkout Redirection:** Clicking the verification link validates the email state and redirects the user to the Stripe Checkout portal for their selected tier.
5. **Atomic Webhook Execution:** Upon receiving a successful payment payload from the payment gateway (`invoice.paid` / `checkout.session.completed`), the backend processes an atomic database transaction:
   * Sets the `organization_subscriptions.status` to `active`.
   * Swaps the user's primary authentication email inside the `users` table from their personal email (e.g., Gmail) to the verified `company_email`.
   * Attaches the user to `organization_members` with the `owner` role.

### 3.2 Secure Corporate Invitation System
Corporate accounts must never contain unverified members or allow unauthorized domains.

1. **Invitation Generation:** An Owner/Admin inputs the candidate's target email and targets an RBAC role (`admin`, `member`, `viewer`).
2. **Pre-Flight Validation:**
   * **Domain Constraint:** Checks if the target email matches the host organization's domain.
   * **Subscription Limit Enforcement:** Combines the count of active members and pending invitations to ensure it remains below the allocated `max_members` ceiling.
3. **Persistence:** The record is stored in `organization_invitations`, generating a cryptographic token valid for 24 hours.
4. **Onboarding Execution:**
   * **Existing Platform User:** Accepting the link seamlessly links their existing `user_id` into the `organization_members` table under the targeted role.
   * **New User:** Redirects to a custom registration view with the email input disabled and prefilled. Form submission creates the `users` record and populates the `organization_members` pivot simultaneously.

---

## 4. Security, Request Context & Middleware Architecture

### 4.1 Stateless Context Resolution
The system resolves the tenant layer dynamically on every incoming payload using a specialized header constraint:
* **Header:** `X-Organization-Id` (Expects a valid Organization UUID).

### 4.2 Middleware Engine: `CheckOrganizationContext`
Applied to all authenticated endpoints (`auth:sanctum`), this middleware intercepts incoming traffic to build the security context:
[Incoming Request] ➔ [Check 'X-Organization-Id' Header]
│
├──► (Absent) ➔ Set Context: Personal Workspace ➔ Pass to Controller
│
└──► (Present) ➔ Query 'organization_members'
│
├──► (Not a Member) ➔ Abort(403)
│
└──► (Is Member) ➔ Append Role to Request ➔ Pass
* **Authorization Failures:** If a user passes an organization UUID that they are not attached to within `organization_members`, the pipeline abruptly throws a `403 Forbidden` JSON response to prevent unauthorized data enumeration.

### 4.3 Granular RBAC Permissions Matrix
Security endpoints map policies to the resolved tenant role inside the current request scope:

| Role | Project Control (`Create`/`Delete`) | Targets & Scans (`Create`/`Run`) | Billing & Org Settings |
| :--- | :--- | :--- | :--- |
| **Owner** | Allowed | Allowed | Allowed |
| **Admin** | Allowed | Allowed | Denied |
| **Member**| Denied | Allowed | Denied |
| **Viewer**| Denied | Denied (403 Policy Block) | Denied |

---

## 5. Subscription Limit Enforcement Rules
Before modifying or running actions inside the engine, the system forces defensive threshold evaluations via Model Observers or structural validations:

* **Max Projects:** `Count(projects Where owner_id == current_org_id)` must be `< organization_subscriptions.max_projects`.
* **Max Targets:** `Count(targets Where project.owner_id == current_org_id)` must be `< organization_subscriptions.max_targets`.
* **Max Monthly Scans:** Running a Docker wrapper increments the monthly execution variable. If execution count hits `max_scans_per_month`, the job dispatcher rejects execution and alerts the interface.
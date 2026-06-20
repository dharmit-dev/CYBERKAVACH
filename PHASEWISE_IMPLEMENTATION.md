# CyberKavach: Smart Club Management System
## Phase-wise Implementation & Security Hardening Report

This report documents the detailed features, database additions, user interfaces, and cryptographic/concurrency security layers built for Phase 3 and Phase 4.

---

## 🛡️ Phase 3: Smart Certificate Generation & Verification System (Module 2)

### 1. Functional Features
*   **Database Schema (`008_certificates_schema.sql`)**: 
    *   Created `certificate_templates` to store coordinate configurations (Name X/Y, Event X/Y, Date X/Y, Code X/Y) for layout alignment.
    *   Created `certificates` table to store recipient metadata, unique codes, and cryptographic signatures.
    *   Registered `certificates.manage` permissions and mapped them to Coordinator roles.
*   **Core Processing Service (`CertificateService.php`)**:
    *   Dynamic image generation using native PHP GD to load templates (PNG/JPG) and alignment draw.
    *   Fail-safe system: Queries standard TrueType fonts (`Arial.ttf` on Windows, `DejaVuSans.ttf` on Linux), falling back safely to built-in GD raster fonts (`imagestring`) if no TTF files exist.
    *   Bulk ZIP compiling: Bundles all generated images into a downloadable archive using `ZipArchive`.
    *   Automated email dispatch: Triggers email delivery via `MailService` on successful generation.
*   **Typography Coordinator View (`templates.php`)**:
    *   Allowed coordinators to upload a base template and configure pixel coordinate offsets and size settings for custom layout rendering.
*   **Bulk Project Generator (`generate.php`)**:
    *   Supports loading participant lists directly from registered event tables or parsing external CSV spreadsheet uploads.
*   **Public Verification Portal (`verify.php`)**:
    *   A public search page that allows external bodies to input a certificate code to check details and download an authentic image.

### 2. Bonus Security Features Added
*   **Cryptographic Tamper-Prevention**: Generates a cryptographic digital signature for each certificate:
    $$\text{HMAC-SHA256}(\text{code} \parallel \text{name} \parallel \text{email}, \text{secret\_key})$$
    The verification page recalculates and validates this signature. Any manually modified data (like a forged name or event) triggers a security tamper alert.
*   **Timing Attack Defenses**: Verifies signatures using constant-time string comparison (`hash_equals()`) to prevent attacker profiling of timing side-channels.
*   **File Upload Validation**:
    *   Strict MIME type checking using `finfo` (vets true file byte-headers, not file extensions).
    *   File size constraints (<5MB).
    *   Sanitized unique hash naming to block remote code executions (RCE) or local file inclusion directory path traversals.
*   **Search Rate Limiter**: Implemented session-based rate limiting on the verification portal (max 30 searches per 10 minutes) to prevent bulk harvesting of certificate identifiers or database scraping.

---

## 🛠️ Root Redirect & General Bug Fixes

### 1. Root Level Redirection (`index.php`)
*   **Problem**: Directory listing is disabled (`Options -Indexes`) for security. Without a default root index file, users hitting `http://localhost/CYBERKAVACH/` directly received a `403 Forbidden` page.
*   **Solution**: Created a root redirect [index.php](file:///c:/xampp/htdocs/CYBERKAVACH/index.php) that performs a `302 Found` header routing to `/public/`, immediately pointing browsers to the login/dashboard portal.

### 2. Syntax Compilation Fix (`create.php`)
*   **Problem**: In `public/approvals/create.php`, a syntax error (unexpected end of file) blocked coordinators from creating approval requests because conditional blocks for navigation tab panels were open.
*   **Solution**: Identified and supplied the missing `<?php endif; ?>` statements, restoring normal compilation and operation.

---

## 🏆 Phase 4: Appreciation, Reward Points & Recognition System (Module 5)

### 1. Functional Features
*   **Database Schema (`009_rewards_recognition_schema.sql`)**:
    *   Created `member_points` log ledger tracking additions, deductions, redemptions, and authorizing coordinators.
    *   Created `badges` achievement reference and `user_badges` earned tables.
    *   Created `reward_items` stock/cost catalog.
    *   Registered `rewards.manage` permissions.
*   **Core Points Service (`PointsService.php`)**:
    *   Handles points additions and automatic checks for achievement badge progression.
    *   Fetches dynamic leaderboard statistics for competitive gamification.
*   **Automated Attendance Triggers (`AttendanceService.php`)**:
    *   Integrates points allocation directly into event checkout tracking:
        *   **+15 points** base for attending.
        *   **-5 points** warning penalty for late check-ins (`is_late`).
        *   **-5 points** warning penalty for early check-outs (`is_early_exit`).
*   **Points Management View (`manage.php`)**:
    *   A coordinator-only dashboard to search members, adjust points manually with reason statements, and view global transaction streams.
*   **Member Rewards Dashboard (`dashboard.php`)**:
    *   Renders points balances, earned achievements, milestone progress meters, points statements, leaderboards, and a catalog to redeem prizes (stickers, vouchers, hoodies).

### 2. Bonus Security Features Added
*   **Double-Spending Concurrency Prevention**: To block race conditions where a member rapidly submits multiple redemption requests to spend more points than they have, or claim items out-of-stock, the service wraps redemptions in a database transaction with a pessimistic lock:
    ```sql
    SELECT * FROM reward_items WHERE id = :id FOR UPDATE;
    SELECT SUM(points) FROM member_points WHERE user_id = :user_id FOR UPDATE;
    ```
    If points are insufficient or stock is depleted, the transaction immediately rolls back.
*   **Role Clearance Gating**: Access to coordinator points management is strictly verified via role checking middleware (`require_role(...)`).
*   **Sanitized Points Bounds**: Validates inputs to ensure only valid non-zero values are requested, preventing integer underflow attacks.
*   **Failsafe Audit Logging**: Points adjustments and prize claims are permanently logged inside `audit_logs` for tracking.

---

## 📊 Phase 5: Operational Analytics Dashboard & Email Alerts Integration (Module 6)

### 1. Functional Features
*   **Analytics View (`analytics.php`)**:
    *   Created a high-fidelity analytics console for coordinators showing live operational totals.
    *   Aggregates registration profiles (individual vs team), check-ins/check-outs, late arrival rates, early exits, reward points distribution across categories, badge unlock milestones, workflow status counts, and coordinator workloads.
    *   Implemented charts using raw CSS-driven responsive bar representations (HTML/SVG elements) for lightweight, high-contrast, dependency-free styling.
*   **Requester Email Notifications (`ApprovalService.php`)**:
    *   Integrates automatic notification emails via `MailService` on approval submission, transitions to intermediate statuses (`under_review`), approvals, returns, and rejections.
*   **Escalation Warning System (`escalate.php`)**:
    *   Upgraded the command-line escalation engine. For requests idle beyond their configured threshold (>48h/72h), the engine sends warning alerts to all active coordinators highlighting pending reviews.
*   **Layout & Sidebar Wiring (`components.php`)**:
    *   Wired the previously dead "Analytics Review" and "Reports" sidebar navigation items to route directly to `admin/analytics.php` for authorized roles.

### 2. Bonus Security Features Added
*   **Access Control Gating**: The analytics view is strictly restricted to Faculty and Student Coordinators using `require_role(...)`.
*   **PDO SQL Parameter Binding**: Used strict database parameter binding for all dynamic aggregations to prevent SQL injection attempts.
*   **HTML Output Sanitization**: Enforced `h()` HTML escaping on all dynamic outputs inside the dashboard grids, charts, and tables to eliminate Cross-Site Scripting (XSS) risks.
*   **Double-Alert Prevention**: In CLI escalation runs, updates `escalation_due_at = NULL` before dispatching notifications to ensure coordinators only receive a single email alert per overdue request.

---

## 🔑 Phase 6: Google/SSO Integration & Detailed Multi-Workflow Reviews

### 1. Functional Features
*   **Google OAuth & SSO Flow (`google.php` & `google-callback.php`)**:
    *   Implemented initiating Google/SSO authentication. If production credentials are not configured, falls back to a custom developer Sandbox showing pre-seeded active accounts and a guest provisioner to simulate SSO sign-in/registration easily.
    *   Verifies Google identity signals and email addresses. If the user already exists in the database, immediately logs them in.
    *   If the user is new, auto-registers/provisions them as a verified `guest_participant` and logs them in.
    *   Integrated a sleek "Sign in with Google" button with micro-animations on the login page (`login.php`).
*   **Polymorphic Workflow Details Panels (`show.php`)**:
    *   Updated the approval timeline review screen. Detected request `entity_type` and dynamically fetched metadata details for `budget_request`, `venue_resource_request`, `social_media_post`, `content_post`, and `external_collaboration`.
    *   Renders comprehensive detail panels containing specific fields (like amount, venue reservation times, social post captions, attachment file previews, and blog post bodies).

### 2. Bonus Security Features Added
*   **OAuth Login CSRF Mitigation**: Used cryptographically secure random session tokens (`state`) checked on redirect response to prevent Session Fixation/OAuth CSRF login attacks.
*   **Random secure password lock**: Provisioned SSO users are assigned a secure randomized password hash (`PASSWORD_DEFAULT`), preventing traditional form login bypasses.
*   **Deep HTML Output Escaping**: Extends strict `h()` HTML formatting to all queried fields from minor workflow tables (captions, body text, partner details, comments) to guarantee XSS prevention.

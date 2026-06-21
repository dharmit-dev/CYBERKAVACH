# 🏆 CyberKavach Feature Showcase

This document outlines the detailed features, dashboards, and systems implemented across all 6 core modules of the **CyberKavach Smart Club Management System**.

---

## 🔐 Module 1: Auth & Approvals (Multi-Level Governance)

### A. Authentication & SSO
* **Production Google SSO**: Complete authentication flow via Google accounts.
* **Google Auth Sandbox**: A developer sandbox console simulating role login profiles (`faculty_coordinator`, `student_coordinator`, `tech_coordinator`, `club_member`) without requiring live API keys.
* **Secure Registration**: Enforces profile fields, year of study, department details, and uploads validation.

### B. Polymorphic Approval Workflows
* **Step-by-Step Chain**: Multi-step approvals (e.g., Student Coordinator Review &rarr; Faculty Coordinator Final Approval).
* **Workflows Supported**:
  * User Account Activations
  * Event Proposal Proposals
  * Budget Request Allocations
  * Venue & Resource Reservations
  * Social Media Announcements
  * Content Publishing drafts
  * Cryptographic Bulk Certificates batches

---

## 📜 Module 2: Cryptographic Certificate System

### A. Dynamic Template rendering
* **GD Vector Engine**: Uses the PHP GD graphic library to compose and draw participant details, event titles, issue dates, and validation codes dynamically onto templates.
* **Batch ZIP Generation**: Renders hundreds of certificates in one request and packages them into a single zip file for batch downloads.

### B. Verification Portal
* **Public Portal**: Public-facing validation route (`certificates/verify.php`) allowing external checkers to type a certificate ID and instantly fetch the cryptographically signed certificate.
* **Rate-Limit Shield**: Built-in request rate-limiting blocks bulk script scanning (maximum 30 validations per 10 minutes per IP address).

---

## 📅 Module 3: Event & Team Management

### A. Event Scheduling
* **Registration Parameters**: Set registration deadlines, participant capacity limits, and custom vector posters.
* **URL Slugs**: Titles are parsed into unique, SEO-friendly browser URLs (`events/manage-hackathon-2026`).

### B. Team Registrations
* **Composition Rules**: Define minimum and maximum team sizes per event.
* **Saved Teams**: Members can save custom teams (e.g., "CyberSentinels") for fast single-click event registration.
* **Secure Team QRs**: The team leader receives a unique cryptographically signed check-in QR code representing the team.

---

## ⏱️ Module 4: Attendance Check-In/Out

### A. Real-Time Scanning
* **HTML5 Scanner**: Client-side video streaming camera scanning page that reads check-in codes instantly and pushes updates.
* **Dual States**: Tracks both **Check-In** and **Check-Out** timestamps.

### B. Threshold Warnings
* **Late Arrivals**: Highlights entries that occur after the coordinator-configured time threshold (e.g., 15 minutes after start).
* **Early Exits**: Flags check-outs that happen before the event's early-exit threshold.

---

## 🏆 Module 5: Points & Recognition (Appreciation Ledger)

### A. Automatic Points Ledger
* **Activity points**: Members earn +15 points for event attendance.
* **Threshold Deductions**: Auto-subtracts 5 points for late arrival or early exit to encourage punctuality.
* **Manual Awards**: Coordinators can award points for outstanding contributions.

### B. Achievements & Redemptions
* **Milestone Badges**: progression badges (*Novice*, *Dedicated*, *Cyber Sentinel*, *Elite*) unlock dynamically.
* **Item Store**: Exchange points for custom sticker packs, vouchers, or merchandise. Built with **row-level database locking** to prevent points double-spending.

---

## 📊 Module 6: Analytics & Settings

### A. Metrics Dashboard
* **Metrics Widgets**: Custom CSS visual tracks demonstrating registration trends, average check-ins, and coordinator workloads.
* **Dynamic Active Highlighting**: Navigation headers highlight page tabs dynamically based on case-insensitive URL tracking and query parameters.

### B. Audit Logging
* **State Comparisons**: The audit log console captures before-and-after states of database modifications as JSON structures:
  ```json
  {
    "old": { "status": "pending_approval" },
    "new": { "status": "active" }
  }
  ```
* **Network Logging**: Captures IP addresses and client user agent strings for every recorded action.

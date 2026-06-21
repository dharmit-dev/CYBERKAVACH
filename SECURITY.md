# 🛡️ Security Architecture & Hardening

This document outlines the security controls, cryptographic verification, and threat mitigation measures implemented in the CyberKavach Smart Club Management System.

---

## 🔒 1. Core Security Safeguards

To secure credentials, personal information, and digital rewards, the platform enforces the following protocols:

### A. SQL Injection Mitigation
Direct query string concatenations are strictly forbidden. All operations utilize **PDO prepared queries** with strict parameter bindings:
```php
$stmt = db()->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
$stmt->execute(['email' => $email]);
```

### B. Session Hijacking Protection
Every session is locked dynamically to the user's IP Address and User Agent on login:
```php
if ($_SESSION['_client_ip'] !== client_ip() || $_SESSION['_client_ua'] !== user_agent()) {
    session_destroy(); // Instantly flag and terminate hijacked session cookies
}
```

### C. Server-Level Directory Isolation
Access to backend folders (`app/`, `config/`, `database/`, `storage/`, `vendor/`) and the `.env` file is blocked at the Apache webserver level using local `.htaccess` directives containing `Deny from all`.

---

## 📜 2. Cryptographic Verification Engine

Digital certificates generated on the platform are secured against forgery using HMAC-SHA256 signatures:

$$\text{Signature} = \text{HMAC-SHA256}(\text{Certificate Code} \parallel \text{Name} \parallel \text{Email}, \text{Secret Key})$$

### Timing-Attack Shield
When validating certificates, the verification portal checks signatures using constant-time string comparison (`hash_equals`) to mitigate timing probing attacks:
```php
if (!hash_equals($signature, $expectedSignature)) {
    // Flag validation failure
}
```

### Rate-Limiting Protection
The verification portal enforces a request rate limit (maximum 30 requests per 10 minutes per IP address) to block automated brute-force scanning scripts.

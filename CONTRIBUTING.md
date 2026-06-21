# 🤝 Contributing to CyberKavach

Thank you for your interest in contributing to CyberKavach! We welcome help in resolving bugs, improving documentation, and adding new features.

---

## 🛠️ Code of Conduct
Please read and adhere to our Code of Conduct when submitting issues or pull requests to keep our community safe and welcoming.

---

## 💻 How to Contribute

### 1. Reporting Bugs & Issues
* Search existing issues to ensure the bug has not been reported.
* Open a new issue with a clear description of the problem, steps to reproduce, and screenshots if applicable.

### 2. Suggesting Features
* Open a feature request issue describing the proposed functionality and its benefits to the project.

### 3. Submitting Pull Requests (PRs)
1. Fork the repository and create your feature branch:
   ```bash
   git checkout -b feature/amazing-new-feature
   ```
2. Write clean, readable, object-oriented PHP code following the standard PSR-12 style guidelines.
3. Make sure to comment your functions and document complex logic.
4. Test your changes locally before committing.
5. Commit your changes with descriptive commit messages:
   ```bash
   git commit -m "feat(auth): lock session validation to client IP and User Agent"
   ```
6. Push to your branch and open a Pull Request against the `main` branch.

---

## 📐 Coding Standards
* Enforce PHP strict types in all new files: `declare(strict_types=1);`
* Bind all SQL parameters using PDO prepared statements to avoid SQL Injection.
* Sanitize all user-facing inputs and render outputs via the `h()` HTML escape helper function to prevent Cross-Site Scripting (XSS).

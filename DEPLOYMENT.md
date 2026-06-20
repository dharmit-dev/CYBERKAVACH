# 🚀 Deploying CyberKavach to Free Hosting (InfinityFree)

This guide provides a step-by-step walkthrough for deploying the CyberKavach Smart Club Management System to free hosting services (specifically tested on **InfinityFree**). 

---

## 📂 1. Directory Structure Optimization

Because most free hosting platforms restrict creating folders outside the public `htdocs` directory, the entire project must reside inside `htdocs/`. 

To maintain security, we use individual `.htaccess` files inside backend directories to prevent any direct web access.

### Recommended Server Directory Layout:
```text
/htdocs (your public web root)
├── app/               <-- Protected by app/.htaccess (Deny from all)
├── config/            <-- Protected by config/.htaccess (Deny from all)
├── database/          <-- Protected by database/.htaccess (Deny from all)
├── public/            <-- Contains public access entry points
│   ├── assets/        <-- Stylesheets, scripts, images
│   ├── uploads/       <-- Certificates, QR codes, posters
│   ├── login.php
│   ├── register.php
│   └── index.php
├── storage/           <-- Protected by storage/.htaccess (Deny from all)
├── vendor/            <-- Protected by vendor/.htaccess (Deny from all)
├── .env               <-- Protected by root .htaccess (Require all denied)
├── .htaccess          <-- Handles directory listing and blocks .env access
└── index.php          <-- Root redirect script pointing to public/
```

---

## 📤 2. Uploading Files Manually

Free web servers often timeout when unzipping large zip files (such as the `vendor` folder). To deploy, upload the directories manually:

1. Connect to your site via the **Web File Manager** (such as Monsta FTP on `filemanager.ai`).
2. Navigate into the **`htdocs`** folder.
3. Open your local project directory on your computer (`c:\xampp\htdocs\CYBERKAVACH`).
4. Select the folders: `app`, `config`, `database`, `public`, `storage`, and drag-and-drop them into the Web File Manager browser window.
5. Upload `.env`, `.htaccess`, and `index.php` to the same folder.
6. *(Optional)* If you choose to upload composer dependencies, you can drag-and-drop the `vendor` folder as well. If not, the application will automatically fall back to its internal, zero-dependency pure PHP libraries (e.g., for QR code generation).

---

## 🗄️ 3. Importing the Database

Instead of importing individual SQL migration files one by one, a pre-compiled, unified database schema has been prepared:

1. Open your InfinityFree Control Panel and go to **MySQL Databases**.
2. Create a database (e.g., `if0_XXXXXXXX_cyberkavach`).
3. Click the **Admin** button to open **phpMyAdmin**.
4. Select the database, navigate to the **Import** tab at the top.
5. Click **Choose File** and select **`database/schema.sql`** from your computer.
6. Click **Go** / **Import** to initialize the database tables, permissions, constraints, and default seeding configurations.

---

## ⚙️ 4. Production Environment Configuration (`.env`)

Edit the online `.env` file in the Web File Manager with your credentials. Example configuration:

```ini
APP_NAME="CyberKavach Club"
APP_ENV=production
APP_URL=https://your-domain.infinityfreeapp.com/public    # <-- Make sure to append /public!
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkata

DB_HOST=sqlXXX.infinityfree.com                           # <-- Copy from MySQL Databases cPanel
DB_PORT=3306
DB_DATABASE=if0_XXXXXXXX_cyberkavach
DB_USERNAME=if0_XXXXXXXX
DB_PASSWORD=your_mysql_password
DB_CHARSET=utf8mb4

# Live SMTP Configuration (Gmail Example)
MAIL_FROM_ADDRESS=your-gmail@gmail.com
MAIL_FROM_NAME="CyberKavach Club"
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-gmail@gmail.com
MAIL_PASSWORD=your-google-app-password                   # <-- 16-character Google App Password
MAIL_ENCRYPTION=tls

# Google Single Sign-On (SSO)
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
```

---

## 🔒 5. Google SSO Redirect Configuration

To make **Login with Google** work on your live domain, follow these steps in the **[Google Cloud Console](https://console.cloud.google.com/)**:

1. Open your OAuth Consent Screen settings and ensure your domain is authorized.
2. In the **APIs & Services ➡️ Credentials** section, edit your Client ID settings.
3. Under **Authorized JavaScript origins**, add:
   `https://your-domain.infinityfreeapp.com`
4. Under **Authorized redirect URIs**, add the exact callback path (must include `/public/`):
   `https://your-domain.infinityfreeapp.com/public/auth/google-callback.php`
5. Save the changes. (Allow 1–2 minutes for Google's servers to propagate).

---

## 🔍 6. Troubleshooting

### HTTP 500 Error (Internal Server Error)
If the site fails to load with an HTTP 500 code:
1. Open `app/Core/bootstrap.php` in the Web File Manager.
2. Enable error reporting temporarily at the top:
   ```php
   ini_set('display_errors', '1');
   ini_set('display_startup_errors', '1');
   error_reporting(E_ALL);
   ```
3. Refresh the page to see the exact PHP compiler or runtime warning. Remember to disable these lines once the issue is resolved!

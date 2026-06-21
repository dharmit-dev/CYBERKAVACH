# 🚀 Live Deployment & Hosting Guide

This guide provides steps for deploying the CyberKavach system to live hosting environments (tested specifically on **InfinityFree**).

---

## 📂 1. Directory Layout Selection

Because free hosting platforms restrict creating folders outside the public `htdocs` directory, the entire project must reside inside `htdocs/`. To maintain security, individual `.htaccess` files inside core directories prevent any direct web access.

### Recommended Layout:
```text
/htdocs (your public web root)
├── app/               <-- Protected by app/.htaccess (Deny from all)
├── config/            <-- Protected by config/.htaccess (Deny from all)
├── database/          <-- Protected by database/.htaccess (Deny from all)
├── public/            <-- Web accessible controllers & assets
├── storage/           <-- Protected by storage/.htaccess (Deny from all)
├── vendor/            <-- Protected by vendor/.htaccess (Deny from all)
├── .env               <-- Protected by root .htaccess (Require all denied)
└── index.php          <-- Root redirect script pointing to public/
```

---

## 📤 2. Manual Upload Strategy

Free web servers often timeout when unzipping large zip files. To deploy:
1. Connect to your site via your **Web File Manager** (such as Monsta FTP).
2. Navigate into the **`htdocs`** folder.
3. Drag-and-drop the directories: `app`, `config`, `database`, `public`, `storage` from your computer into the Web File Manager window.
4. Upload `.env`, `.htaccess`, and `index.php` to the same folder.
5. The system automatically falls back to its built-in zero-dependency code library if the `vendor` folder is omitted.

---

## 🗄️ 3. Database Initializing

1. Open your InfinityFree Control Panel and go to **MySQL Databases**.
2. Create a database and click the **Admin** button next to it to open **phpMyAdmin**.
3. Select your database, navigate to the **Import** tab at the top.
4. Click **Choose File** and select **`database/schema.sql`** from your computer.
5. Click **Go** / **Import** to initialize the database tables, permissions, constraints, and default seeding configurations.

---

## ⚙️ 4. Environment Variables (`.env`)

Configure your online `.env` file with these production details:
```ini
APP_NAME="CyberKavach Club"
APP_ENV=production
APP_URL=https://your-domain.infinityfreeapp.com/public    # <-- Must include /public
APP_DEBUG=false

DB_HOST=sqlXXX.infinityfree.com                           # <-- Copy from MySQL Databases cPanel
DB_PORT=3306
DB_DATABASE=if0_XXXXXXXX_cyberkavach
DB_USERNAME=if0_XXXXXXXX
DB_PASSWORD=your_mysql_password
DB_CHARSET=utf8mb4

# Live SMTP Gateway (Gmail App Passwords)
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="CyberKavach Club"
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-16-char-app-password
MAIL_ENCRYPTION=tls

# Google OAuth Integration
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
```

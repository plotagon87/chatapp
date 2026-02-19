# 💬 ChatApp — LAN Intranet Messaging Platform

A modern, responsive intranet messaging platform designed for secure team communication within private corporate networks. Built with PHP, MySQL, and vanilla JavaScript with real-time polling and WebSocket-ready architecture.

**Status:** ✅ Production-Ready | Active Development

---

## 📋 Table of Contents

- [About](#about)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Schema](#database-schema)
- [Running Locally](#running-locally)
- [Admin Panel](#admin-panel)
- [API Endpoints](#api-endpoints)
- [Project Structure](#project-structure)
- [Development](#development)
- [Browser Support](#browser-support)
- [Security Features](#security-features)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)
- [Contact](#contact)

---

## 📌 About

**ChatApp** is a secure, self-hosted intranet messaging solution for organizations that need to keep team communications private and within their corporate network. It provides:

- **One-to-One Messaging**: Direct private messages between team members
- **Group Chats**: Create and manage group conversations
- **Real-time Updates**: Near-real-time message delivery via polling
- **User Management**: Admin tools for managing users and roles
- **File Sharing**: Support for document and media uploads
- **Typing Indicators**: See when users are typing
- **Read Receipts**: Track message delivery status
- **System Announcements**: Admin-controlled announcements with smart visibility rules
- **Mobile Responsive**: Works seamlessly on desktop, tablet, and mobile devices

---

## ✨ Features

### Core Messaging
- ✅ One-to-one private messaging
- ✅ Group messaging and channels
- ✅ Message history and search functionality
- ✅ Typing indicators (user is typing...)
- ✅ Read receipts (single ✓ / double ✓✓ check)
- ✅ Emoji picker for expressive communication
- ✅ Message timestamps with smart formatting (Just now, 5m ago, etc.)
- ✅ **End-to-end encryption for one-to-one chats** (keys stored client‑side; requires browser support for the WebCrypto API, typically available in modern browsers and contexts served over HTTPS or localhost)

### User Management
- ✅ User registration and authentication
- ✅ Profile customization (avatar, status, theme preference)
- ✅ User status indicators (Online, Offline, Busy, Away)
- ✅ User directory and search
- ✅ Role-based access control (Admin, Staff, Student, User)

### File & Media
- ✅ File uploads with type validation
- ✅ Image sharing and preview
- ✅ Voice message support
- ✅ Document storage and retrieval

### Admin Features
- ✅ User management dashboard
- ✅ System announcements with scheduling
- ✅ Online users monitoring
- ✅ System logs and activity tracking
- ✅ User statistics and analytics
- ✅ Group management

### Mobile & UX
- ✅ Fully responsive design (mobile-first)
- ✅ Pull-to-refresh functionality
- ✅ PWA (Progressive Web App) ready
- ✅ Smooth animations and transitions
- ✅ Dark/Light theme support (ready for implementation)
- ✅ Tailwind CSS for modern styling

---

## 🛠 Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.0+ (Procedural) |
| **Frontend** | Vanilla JavaScript (ES6+), HTML5, CSS3 |
| **Database** | MySQL 8.0+ / MariaDB 10.5+ |
| **Web Server** | Apache 2.4+ / Nginx |
| **Styling** | Tailwind CSS 2.2+ |
| **Real-time** | Long-polling (WebSocket-ready architecture) |
| **Security** | CSRF tokens, Password hashing (bcrypt) |
| **API** | RESTful endpoints |

**Language Composition:**
- PHP: ~65%
- JavaScript: ~20%
- SQL: ~10%
- CSS/Markup: ~5%

---

## 📦 Requirements

### Docker (optional)
You can package the entire application into containers instead of running it on XAMPP/LAMP. Below are the basic files included in the repo:

- `Dockerfile` – builds a PHP 8‑Apache image with required extensions and the app code.
- `docker-compose.yml` – defines services for the web server and a MariaDB database.

To build and run:

```bash
# from the project root
docker-compose build
docker-compose up -d
```

The app will be available at http://localhost:8080.  The database is preconfigured as:

- host: db
- name: lan_chat_db
- user: chatuser
- pass: chatpass
- root password: secret

After the containers start you should run the migration script to create the necessary tables:

```bash
docker-compose exec web php migrations/run.php
```

You can also connect to the `db` service with a MySQL client if needed.

You can modify the compose file to suit your environment, mount volumes, or expose different ports.  The `.dockerignore` file excludes local node_modules, git metadata, etc.



### Server Requirements
- **PHP**: 8.0 or higher
- **MySQL/MariaDB**: 5.7+ (5.7 for full JSON support, 8.0 recommended)
- **Web Server**: Apache 2.4+ (mod_rewrite enabled) or Nginx
- **Extensions**: PDO, PDO MySQL, JSON

### Local Development
- **XAMPP** 7.4+, **LAMP**, or **LEMP** stack
- **Git** for version control
- **Code Editor**: VS Code, Sublime, PhpStorm, etc.
- **Browser**: Modern browser with ES6 support (Chrome 60+, Firefox 55+, Safari 11+)

### System Recommendations
- **RAM**: Minimum 1GB (2GB recommended)
- **Storage**: 500MB+ for application and uploads
- **Bandwidth**: 1Mbps+ for smooth operation

---

## 🚀 Installation

### Step 1: Clone Repository
```bash
git clone https://github.com/plotagon87/chatapp.git
cd chatapp
```

### Step 2: Database Setup
Import the database schema:

**Using MySQL client:**
```bash
mysql -u root -p < db.sql
```

**Using phpMyAdmin:**
1. Log in to phpMyAdmin
2. Create new database: `lan_chat_db`
3. Select the database and import `db.sql`

**Using command line (if you have MySQL running):**
```bash
mysql -u root -p -e "CREATE DATABASE lan_chat_db;"
mysql -u root -p lan_chat_db < db.sql
```

### Step 3: Configuration
Edit `includes/config.php` with your settings:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your MySQL password
define('DB_NAME', 'lan_chat_db');
```

### Step 4: Create Directories
Ensure these folders exist and are writable:
```bash
mkdir -p uploads/profiles
mkdir -p uploads/files
mkdir -p uploads/images
mkdir -p uploads/voice
chmod 755 uploads/
```

### Step 5: Run Migrations
If applying any updates:
```bash
php run_migration.php
```

---

## ⚙️ Configuration

### Environment Setup (config.php)
```php
// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'lan_chat_db');

// Application
define('BASE_URL', 'http://localhost/chatapp/');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt', 'zip']);
```

### Web Server Configuration

**Apache (.htaccess):**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /chatapp/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
</IfModule>
```

**Nginx:**
```nginx
location /chatapp/ {
    try_files $uri /chatapp/index.php$is_args$args;
}
```

---

## 🗄️ Database Schema

### Core Tables

**users** - User accounts and profiles
```sql
- user_id (PK)
- username
- email
- password (hashed)
- full_name
- profile_picture
- role (admin, staff, student, user)
- status (online, offline, busy, away)
- created_at
- updated_at
```

**messages** - One-to-one messages
```sql
- message_id (PK)
- sender_id (FK: users)
- receiver_id (FK: users)
- message_text
- message_type (text, file, image, voice)
- file_path (nullable)
- is_read
- created_at
```

**groups** - Group chats
```sql
- group_id (PK)
- name
- description
- created_by (FK: users)
- is_active
- created_at
```

**group_members** - Group membership
```sql
- member_id (PK)
- group_id (FK: groups)
- user_id (FK: users)
- joined_at
```

**announcements** - System announcements
```sql
- announcement_id (PK)
- title
- content
- created_by (FK: users)
- priority (low, medium, high, urgent)
- is_welcome (auto-hide for new users)
- is_active
- created_at
- expires_at
```

Full schema available in `db.sql`.

---

## 🏃 Running Locally

### Option 1: Using XAMPP
1. Place project in `C:\xampp\htdocs\chatapp\`
2. Start Apache and MySQL from XAMPP Control Panel
3. Open browser: `http://localhost/chatapp/`
4. Login with default credentials (see below)

### Option 2: Using PHP Built-in Server
```bash
cd /path/to/chatapp
php -S localhost:8000
# Open http://localhost:8000
```

### Option 3: Using Docker
```bash
docker-compose up -d
# Access at http://localhost:8080
```

### Default Credentials
After database setup, create your first admin user:
```sql
INSERT INTO users (username, email, password, full_name, role) 
VALUES ('admin', 'admin@localhost', PASSWORD('admin123'), 'Administrator', 'admin');
```

Or use the registration page and manually set role to 'admin' via database.

---

## 👨‍💼 Admin Panel

Access admin features at `/admin/dashboard.php` (admin users only)

### Admin Capabilities
- 📊 View system statistics
- 👥 Manage users (view, edit, deactivate)
- 📢 Create and manage announcements
- 📊 View online users in real-time
- 📋 Monitor system activity logs
- 👫 Manage groups and permissions

### Admin URLs
- `/admin/dashboard.php` - Main admin dashboard
- `/admin/manage_users.php` - User management
- `/admin/announcements.php` - Announcements
- `/admin/system_logs.php` - Activity logs

---

## 🔌 API Endpoints

### Chat API
- `POST /chat/send_message.php` - Send message
- `GET /chat/get_messages.php?user_id=X` - Fetch messages
- `GET /chat/get_users.php` - Get user list
- `POST /chat/mark_read.php` - Mark message as read
- `POST /chat/typing_status.php` - Update typing indicator
- `GET /chat/check_typing.php` - Check if user typing

### User API
- `GET /api/update_status.php?status=online` - Update user status
- `GET /api/search_users.php?q=name` - Search users
- `POST /api/notifications.php` - Get notifications

All requests include CSRF token validation.

---

## 📁 Project Structure

```
chatapp/
├── admin/                    # Admin panel
│   ├── dashboard.php
│   ├── manage_users.php
│   ├── announcements.php
│   └── system_logs.php
├── api/                      # API endpoints
│   ├── search_users.php
│   ├── update_status.php
│   └── notifications.php
├── assets/                   # Static files
│   ├── css/
│   ├── js/
│   │   └── chat.js          # Main chat application
│   └── images/
├── chat/                     # Chat operations
│   ├── get_messages.php
│   ├── send_message.php
│   ├── typing_status.php
│   └── mark_read.php
├── includes/                 # Core files
│   ├── config.php           # Configuration
│   └── profile.php          # User functions
├── migrations/              # Database migrations
├── uploads/                 # User files
│   ├── profiles/
│   ├── files/
│   ├── images/
│   └── voice/
├── db.sql                   # Database schema
├── index.php                # Home/Login
├── dashboard.php            # Main chat interface
├── profile.php              # User profile
├── settings.php             # User settings
├── register.php             # Registration
├── logout.php               # Logout
└── manifest.json            # PWA manifest
```

---

## 👨‍💻 Development

### Coding Standards
- **PHP**: PSR-12 (Extended Coding Style)
- **JavaScript**: ES6+ conventions, camelCase for variables
- **Database**: Use prepared statements to prevent SQL injection
- **Security**: Always sanitize input, validate output

### Running Locally
```bash
# Start development server
php -S localhost:8000

# Or XAMPP: Place in htdocs and start services
```

### Common Development Tasks

**Add new migration:**
```bash
# Create file in migrations/00X_description.sql
# Run: php migrations/run.php
```

**Clear cache:**
```bash
rm -rf uploads/cache/*
```

**View logs:**
```bash
tail -f logs/system.log  # if logging implemented
```

---

## 🌐 Browser Support

| Browser | Version | Status |
|---------|---------|--------|
| Chrome | 60+ | ✅ Full |
| Firefox | 55+ | ✅ Full |
| Safari | 11+ | ✅ Full |
| Edge | 15+ | ✅ Full |
| IE | 11 | ❌ Not supported |

---

## 🔒 Security Features

- ✅ **CSRF Protection** - All forms use CSRF tokens
- ✅ **Password Hashing** - bcrypt hashing algorithm
- ✅ **SQL Injection Prevention** - PDO prepared statements
- ✅ **XSS Protection** - HTML entity encoding, Content Security Policy
- ✅ **Session Management** - Secure session handling
- ✅ **HTTPS Ready** - Works with SSL/TLS
- ✅ **Input Validation** - Server-side input validation
- ✅ **Role-Based Access** - Admin, Staff, Student, User roles

### Security Checklist
- [ ] Change default database credentials
- [ ] Enable HTTPS in production
- [ ] Set strong admin password
- [ ] Configure file upload restrictions
- [ ] Regular backups of database
- [ ] Monitor system logs for suspicious activity

---

## 🐛 Troubleshooting

### Common Issues

**"Database connection failed"**
- Check DB credentials in `config.php`
- Ensure MySQL is running
- Verify database `lan_chat_db` exists

**"Permission denied for uploads"**
```bash
chmod -R 755 uploads/
chown -R www-data:www-data uploads/  # Linux/Mac
```

**"Blank page or 500 error"**
- Enable error reporting: `ini_set('display_errors', 1);`
- Check server error logs
- Verify PHP version is 8.0+

**"Messages not loading"**
- Clear browser cache (Ctrl+Shift+Del)
- Check browser console for JavaScript errors (F12)
- Verify `chat.js` is loaded correctly

**"Dropdown menu not working"**
- Clear browser cache with hard refresh (Ctrl+Shift+R)
- Check that JavaScript is enabled
- Verify browser supports ES6

---

## 🤝 Contributing

1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature/new-feature`
3. **Commit** changes: `git commit -m "Add new feature"`
4. **Push** to branch: `git push origin feature/new-feature`
5. **Open** a Pull Request with description

### Contribution Guidelines
- Follow existing code style
- Add comments for complex logic
- Test changes locally before submitting
- Update README if adding features
- Include meaningful commit messages

---

## 📄 License

MIT License - See [LICENSE](LICENSE) file for details.

This project is free to use, modify, and distribute for commercial and non-commercial purposes.

---

## 📞 Contact

- **Repository**: [github.com/plotagon87/chatapp](https://github.com/plotagon87/chatapp)
- **Issues**: [GitHub Issues](https://github.com/plotagon87/chatapp/issues)
- **Email**: your-email@example.com

For bug reports, feature requests, or questions, please open an issue on GitHub.

---

## 📈 Roadmap

### Upcoming Features
- [ ] WebSocket support for real-time updates
- [ ] Voice/Video calling
- [ ] Message reactions/emojis (expanded)
- [ ] Dark mode theme
- [ ] Multi-language support
- [ ] Desktop app (Electron)
- [ ] Mobile app (React Native)
- [x] End-to-end encryption
- [ ] Message scheduling
- [ ] Advanced search filters

### Known Limitations
- Long-polling used instead of WebSockets (can be upgraded)
- Single-file uploads (can be enhanced)
- No video/audio streaming (planned)
- Basic notification system (can be enhanced)

---

**Last Updated**: January 16, 2026  
**Version**: 1.0.0  
**Maintainer**: Your Name / Organization
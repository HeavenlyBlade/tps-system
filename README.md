# TPS System - Think-Pair-Share

A PHP web application for classroom Think-Pair-Share activities with real-time student pairing and chat.

## 📋 Requirements

- XAMPP (Apache + MySQL + PHP 7.4+)
- Cloudflared (for external access)
- Web browser

## 🚀 Quick Start

### 1. Setup XAMPP
1. Copy `tps-system` folder to `C:\xampp\htdocs\`
2. Open XAMPP Control Panel
3. Start **Apache** and **MySQL**

### 2. Setup Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Go to **Import** tab
3. Select `database.sql` and click **Go**

### 3. Start Cloudflared Tunnel
```bash
cloudflared tunnel --url http://localhost
```

Copy the generated URL (e.g., `https://random-words.trycloudflare.com`)

### 4. Access the System
Open: `https://your-tunnel-url/tps-system/`

**Default Accounts:**
| Role | Username | Password |
|------|----------|----------|
| Teacher | teacher | password |
| Student | student1-4 | password |

## 📱 Install as App (PWA)

The TPS System can be installed as an app on any device:

### Mobile (Android/iOS):
1. Open the website in your browser
2. Look for "Install" button or browser menu
3. Tap "Add to Home Screen" or "Install"
4. The app appears on your home screen

### Desktop:
1. Open in Chrome/Edge
2. Click install icon in address bar
3. Or use browser menu → "Install TPS System"

### Benefits:
- Works like a native app
- Full teacher/admin functionality
- Offline support for cached pages
- Fast loading

## 🎮 How It Works

### Think-Pair-Share Flow:

1. **Teacher** creates a game and waits for students
2. **Students** join the game from their dashboard
3. **Teacher** clicks START → Students enter **Think Phase**
4. Students read the question and think (timer countdown)
5. **Teacher** clicks END TIMER → Students enter **Pair Phase**
6. **Teacher** clicks RUN PAIRING → Students get matched
7. Paired students enter **Share Phase** - chat and submit answer together
8. **Teacher** views pair answers in Game Control

## 📁 Project Structure

```
tps-system/
├── config.php              # Database & settings
├── database.sql            # Database schema
├── index.php               # Entry point
├── setup.php               # Alternative DB setup
│
├── auth/                   # Login, Register, Logout
├── student/
│   ├── dashboard.php       # Join games
│   ├── game.php            # Unified game page (all phases)
│   └── ajax/               # Real-time endpoints
├── teacher/
│   ├── dashboard.php       # Main menu
│   ├── game_control.php    # Control game flow
│   ├── games.php           # Manage games
│   ├── questions.php       # Manage questions
│   └── ajax/               # Real-time endpoints
├── inc/                    # Header & Footer
└── assets/style.css        # Custom styles
```

## 🌐 Cloudflared Setup

Cloudflared creates a secure tunnel to access your local server from any device.

### Install Cloudflared
Download from: https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/

### Run Tunnel
```bash
cloudflared tunnel --url http://localhost
```

The tunnel URL changes each time. Share the URL with students to access the system.

### Tips
- Keep the CMD window open while using the tunnel
- The URL is temporary - get a new one each session
- Works on any device with internet access

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| users | Students & teachers |
| questions | Question bank |
| games | Game sessions |
| game_entries | Students in games |
| assigned_questions | Question assignments |
| pairs | Student pairings |
| messages | Chat messages |

## ⚙️ Configuration

Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tps_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('QUESTION_TIME_MINUTES', 15);
```

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| Database error | Check MySQL is running in XAMPP |
| Page not found | Verify folder is in `htdocs` |
| Tunnel not working | Restart cloudflared command |
| Mobile buttons not clicking | Clear browser cache |

## 📝 License

Thesis project by Benjamine Panganiban - BSIT-III

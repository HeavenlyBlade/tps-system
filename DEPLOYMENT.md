# TPS System — Production Deployment Guide

> **Target platform:** [Render](https://render.com) (free tier)  
> **Stack:** PHP 8.2 + Apache · MySQL 8

---

## Prerequisites

- A GitHub account with this repo pushed to it
- A free [Render](https://render.com) account

---

## Step 1 — Push to GitHub

If you haven't already:

```bash
git init
git add .
git commit -m "Initial production-ready commit"
git remote add origin https://github.com/<your-username>/tps-system.git
git push -u origin main
```

---

## Step 2 — Create a MySQL Database on Render

1. Go to [render.com/dashboard](https://dashboard.render.com) → **New +** → **PostgreSQL** … wait, this is MySQL.  
   Render's native database is **PostgreSQL**. For **MySQL**, use one of these options:

   ### Option A — Render + PlanetScale (recommended free MySQL)
   1. Create a free database at [planetscale.com](https://planetscale.com)
   2. Copy the connection details (host, port, database, username, password)
   3. Use those values in Step 4 below

   ### Option B — Render + Railway MySQL
   1. Create a free project at [railway.app](https://railway.app) → **New** → **Database** → **MySQL**
   2. Copy the connection details from Railway's "Connect" tab

   ### Option C — Aiven Free MySQL
   1. Sign up at [aiven.io](https://aiven.io) → create a free MySQL service
   2. Copy the connection string details

---

## Step 3 — Create a Web Service on Render

1. Render Dashboard → **New +** → **Web Service**
2. Connect your GitHub repo
3. Configure:

   | Field | Value |
   |-------|-------|
   | **Name** | `tps-system` |
   | **Runtime** | `Docker` |
   | **Branch** | `main` |
   | **Root Directory** | *(leave blank)* |

4. Create a `Dockerfile` in the project root (see below) — Render will use it automatically.

---

## Step 4 — Dockerfile (create this in the project root)

Create a file called `Dockerfile` in the same folder as `index.php`:

```dockerfile
FROM php:8.2-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libwebp-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers deflate expires

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy app files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
```

---

## Step 5 — Set Environment Variables on Render

In Render Dashboard → your Web Service → **Environment** tab, add:

| Key | Value |
|-----|-------|
| `APP_ENV` | `production` |
| `DB_HOST` | *(from your MySQL provider)* |
| `DB_PORT` | `3306` |
| `DB_NAME` | `tps_system` |
| `DB_USER` | *(from your MySQL provider)* |
| `DB_PASS` | *(from your MySQL provider)* |
| `SETUP_TOKEN` | *(any random secret, e.g. `abc123xyz`)* |
| `QUESTION_TIME_SECONDS` | `300` *(5 minutes, adjust as needed)* |

---

## Step 6 — Deploy

1. Click **Create Web Service** — Render will build and deploy automatically
2. Wait for the build to finish (2–5 minutes)
3. Your app will be live at `https://tps-system.onrender.com`

---

## Step 7 — Run Database Setup

Once deployed, run the setup wizard to create tables and seed default data:

```
https://tps-system.onrender.com/setup.php?token=<your-SETUP_TOKEN>
```

You should see all green checkmarks. After setup completes:

1. Go to Render Dashboard → **Environment**
2. **Delete** the `SETUP_TOKEN` variable
3. Redeploy (or it takes effect on next request)

---

## Step 8 — Test the App

| URL | Expected |
|-----|----------|
| `https://tps-system.onrender.com/` | Redirects to login |
| `https://tps-system.onrender.com/auth/login.php` | Login page |
| Login as `teacher` / `password` | Teacher dashboard |
| Login as `student1` / `password` | Student dashboard (username only) |

---

## Step 9 — Change Default Passwords

Log in as teacher and update your password immediately via the teacher account settings. The default `password` is only for initial setup.

---

## Local Development (XAMPP)

The app auto-detects `APP_ENV` — if it's not set (or not `production`), it runs in local mode:

- DB connects to `127.0.0.1:3307` (XAMPP MySQL default)
- Errors are displayed on-screen
- Base URL is `/tps-system`

To run locally:
1. Copy the project folder to `C:\xampp\htdocs\tps-system`
2. Start Apache + MySQL in XAMPP
3. Visit `http://localhost/tps-system/setup.php` (no token needed locally)
4. Visit `http://localhost/tps-system/`

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `503 Service Temporarily Unavailable` | Check DB env vars are correct in Render |
| `Connection refused` on MySQL | Verify DB host/port; check if DB service is running |
| Assets (CSS/JS) not loading | Ensure `bootstrap/` folder is committed to git (not in `.gitignore`) |
| Background video not playing | The `/assets/bg/238285.mp4` file must be committed; it falls back to gradient if missing |
| `setup.php` returns 403 | Set `SETUP_TOKEN` env var, then visit `?token=<value>` |
| Render free tier spins down | Free web services sleep after 15 min of inactivity; first request takes ~30s |

---

## File Structure (Production)

```
tps-system/
├── .htaccess           ← Security headers, HTTPS redirect, blocks sensitive files
├── config.php          ← DB + env config (blocked from direct web access)
├── database.sql        ← Reference schema (blocked from direct web access)
├── Dockerfile          ← Container definition for Render
├── render.yaml         ← Render blueprint (optional, for blueprint deploys)
├── setup.php           ← One-time DB setup wizard (locked by SETUP_TOKEN)
├── index.php           ← Entry point → redirects to login or dashboard
├── manifest.json       ← PWA manifest
├── sw.js               ← Service worker (caches static assets)
├── auth/               ← login.php, register.php, logout.php
├── teacher/            ← Teacher pages + ajax/
├── student/            ← Student pages + ajax/
├── inc/                ← header.php, footer.php
├── assets/             ← style.css, bg video, icons
└── bootstrap/          ← Local Bootstrap 5 CSS/JS/fonts
```

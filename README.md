# Project Handover Documentation: RaceRaja Live Streaming Platform

This document provides comprehensive instructions for setting up, managing, and deploying the RaceRaja project.

## 1. Project Overview
RaceRaja is a live-streaming platform built with a Go backend and a React (Vite) frontend. It features user authentication, subscription management, live stream integration, and an admin dashboard.

---

## 2. Tech Stack
- **Frontend**: React.js (Vite), Tailwind CSS
- **Backend**: Go (Golang)
- **Database**: MongoDB (Atlas/Local)
- **Caching/Session**: Redis
- **Authentication**: JWT (JSON Web Tokens)

---

## 3. Environment Configuration (.env)

### Backend (.env)
Location: `live_stream-backend/.env`

| Variable | Description |
| :--- | :--- |
| `PORT` | The port on which the Go server runs (default: 8080). |
| `MONGO_URI` | Connection string for your MongoDB database (Standard MongoDB SRV format). |
| `DB_NAME` | The name of the database in MongoDB. |
| `REDIS_ADDR` | Address for the Redis server (e.g., `localhost:6379`). |
| `REDIS_PASSWORD`| Password for Redis (leave empty if none). |
| `REDIS_DB` | Redis database index (default: 0). |
| `JWT_SECRET` | A secure string used to sign authentication tokens. |

### Frontend (.env)
Location: `live_stream-frontend/frotend/.env`

| Variable | Description |
| :--- | :--- |
| `VITE_API_URL` | The base URL for the backend API (e.g., `http://your-vps-ip:8080/api`). |

---

## 4. Local Setup Instructions

### Prerequisites
- Install **Go (1.19+)**
- Install **Node.js (16+)** & **npm**
- Install **MongoDB** & **Redis**

### Step 1: Backend Setup
1. Navigate to the backend directory:
   ```bash
   cd live_stream-backend
   ```
2. Install Go dependencies:
   ```bash
   go mod tidy
   ```
3. Configure your `.env` file based on the details above.
4. Run the server:
   ```bash
   go run main.go
   ```

### Step 2: Frontend Setup
1. Navigate to the frontend directory:
   ```bash
   cd live_stream-frontend/frotend
   ```
2. Install npm dependencies:
   ```bash
   npm install
   ```
3. Configure your `.env` file (ensure `VITE_API_URL` points to your local backend, e.g., `http://localhost:8080/api`).
4. Run the development server:
   ```bash
   npm run dev
   ```

---

---

## 5. Production Deployment (Hostinger VPS)

### Step 1: Core Setup on VPS
1. SSH into your VPS: `ssh root@your_vps_ip`
2. Update the system: `sudo apt update && sudo apt upgrade -y`
3. Install Go: `sudo apt install golang-go`
4. Install Node.js:
   ```bash
   curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
   sudo apt-get install -y nodejs
   ```
5. Install Redis: `sudo apt install redis-server`
6. Install MongoDB: Follow official MongoDB Ubuntu/Debian installation guides.

### Step 2: Deploy Backend
1. Upload the `live_stream-backend` folder.
2. Build the Go binary:
   ```bash
   go build -o server main.go
   ```
3. Run using **PM2** (recommended) or a Systemd service:
   ```bash
   pm2 start "./server" --name raceraja-backend
   ```

### Step 3: Deploy Frontend
1. Navigate to `live_stream-frontend/frotend`.
2. Update `.env` with the production API URL (e.g., `https://api.yourdomain.com`).
3. Build the project:
   ```bash
   npm run build
   ```
4. This generates a `dist` folder.

### Step 4: Nginx Configuration (VPS only)
Use Nginx to serve the `dist` folder and proxy API requests.
Example configuration:
```nginx
server {
    listen 80;
    server_name yourdomain.com;

    location / {
        root /path/to/frontend/dist;
        try_files $uri /index.html;
    }

    location /api {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

---

## 5.1. Production Deployment (Shared Hosting)

### Important Note on Backend
**Shared Hosting (like Hostinger's standard plans) generally does NOT support Go (Golang) binaries.**
- The **Backend (Go)** MUST be hosted on a **VPS** (recommended) or a specialized platform that supports Go (like Railway, Render, or Heroku).
- The **Frontend (React)** CAN be hosted on **Shared Hosting**.

### Hosting the Frontend on Shared Hosting
1. **Prepare the Build**:
   - On your local machine, go to `live_stream-frontend/frotend`.
   - Ensure `.env` has the correct `VITE_API_URL` pointing to your Backend (which is running on a VPS).
   - Run `npm run build`.
2. **Upload Files**:
   - Login to your Shared Hosting Control Panel (e.g., Hostinger hPanel or cPanel).
   - Open the **File Manager**.
   - Navigate to the `public_html` folder.
   - Upload all files from your local `dist` folder into `public_html`.
3. **Handle Routing (.htaccess)**:
   - Since this is a Single Page Application (SPA), you need to ensure that refreshing the page doesn't cause a 404.
   - Create a file named `.htaccess` in the `public_html` folder with this content:
     ```apache
     <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteBase /
       RewriteRule ^index\.html$ - [L]
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteCond %{REQUEST_FILENAME} !-d
       RewriteRule . /index.html [L]
     </IfModule>
     ```

### Recommendation for Backend
If you don't want to use a VPS for the backend, you can use:
- **Railway.app** (Very easy to connect to GitHub and deploy Go).
- **Render.com** (Free tier available for Go).
- **MongoDB Atlas** (Free cloud database, so you don't need to install MongoDB on your server).

---

---

## 6. How to Add New Pages
The project uses a custom routing system in `src/App.jsx`.

### 1. Create the Page Component
- Create a new file in `src/pages/`, e.g., `NewFeature.jsx`.
- Define your React component.

### 2. Register the Route in `App.jsx`
- Import your component:
  ```javascript
  import NewFeature from './pages/NewFeature';
  ```
- Find the `renderPage` function (around line 142).
- Add a new `if` condition for your path:
  ```javascript
  if (currentPath === '/new-feature') return <NewFeature siteTitle={siteTitle} />;
  ```

---

## 7. Working with Files
- **Backend**: Manages uploads in `uploads/`. Files are served by the backend or via a static file server.
- **Frontend**: API calls are centralized in `src/utils/api.jsx`.

---

## 8. Handover Files
The following ZIP files have been generated:
- `frontend.zip` (Contents of `live_stream-frontend/frotend/`)
- `backend.zip` (Contents of `live_stream-backend/`)
- 

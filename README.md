# Project Menu (Local Project Index Manager)

A lightweight PHP + MySQL application that automatically indexes and organizes your local development projects into a clean, categorized dashboard.

Designed for local environments (Laragon, UniServer, XAMPP, etc.), this tool scans your `/projects` directory and keeps your project list in sync with your filesystem — while allowing full control over organization, sorting, and metadata.

---

## 🚀 What This Is

**Project Menu** is a central hub for all your local projects.

Instead of digging through folders, you get:
- A clean UI listing all projects
- Categories (In Progress, Development, Finished, etc.)
- Version tracking
- Descriptions
- Quick access links

It acts like a **launcher + manager** for everything you build.

---

## ⚡ Key Features

### 📂 Automatic Project Detection
- Scans your `/projects` directory
- Detects new folders automatically
- Prompts you to add them if not already tracked

### 🧠 Smart Auto-Import (project_info.json)
All your projects can include a `project_info.json` file.

When present, Project Menu will:
- Automatically read project name
- Description
- Version
- Category
- And more...

➡️ This means **projects auto-add themselves** with zero manual setup.

---

### 🏷️ Categories & Organization
- Assign projects to categories (In Progress, Development, Finished, etc.)
- Clean grouped display
- Hide/show empty categories

---

### 🔒 Drag & Drop Ordering (NEW)
- Each category has a **lock/unlock toggle**
- 🔒 Locked = static view  
- 🔓 Unlocked = drag & drop enabled

Reorder projects visually and save instantly.

Order is:
- ✅ Stored in the database
- ✅ Persistent across sessions
- ✅ Not browser-dependent

---

### 📦 Upload Projects (ZIP Support)
- Upload a `.zip` file directly
- Automatically extracts into `/projects`
- If `project_info.json` exists → auto-added instantly
- If not → prompts manual setup

---

### 🧾 Changelog System
- Built-in changelog tracking
- Version file support
- View changes directly inside the app

---

### ⚙️ Database Synced
- All project data stored in MySQL
- Includes:
  - name
  - description
  - category
  - version
  - directory
  - sort order

---

## 📁 Project Structure

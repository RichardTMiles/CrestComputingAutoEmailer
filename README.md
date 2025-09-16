# 📬 After School Email Scheduler

This was vibe coded with ChatGPT.

This utility automates rich weekly email drafts for afterschool robotics programs. It pulls from JSON-based class schedules and templated lessons, formats personalized messages with an inline logo, and drafts them in Apple Mail for manual review.

It also installs itself into cron for automated hourly checks and avoids re-sending duplicate emails.

For a browser-based option, see [CHROME.md](CHROME.md) for a minimal Chrome extension.

---

## Setup 

🏫 classes.json
This file defines your scheduled classes and their corresponding recipients and time windows.

📅 `classes.json` contains:
- schoolName: Displayed and used for caching
- className: Used in the subject fallback if no template subject is found
- startDate: First class day (used to calculate curriculum week)
- startTime / endTime: When the draft should be triggered
- skip: Dates (in YYYY-MM-DD format) to exclude (holidays, breaks)
- links: URLs shown in the email footer
- bcc: A list of email addresses to BCC


`email_templates.json`
This file contains your curriculum-aligned email templates, each indexed by the week number. Every entry includes:
- week: Curriculum week index (starts at 0)
- lesson: Name of the lesson
- subject: The email subject line
- body: The full email body (supports line breaks and emoji)
---

## 🚀 Features

- ✅ Auto-detects the current curriculum week (with skipped week support)
- 📬 Opens visible Apple Mail drafts (via AppleScript) for review and manual send
- 🖼️ Automatically resizes and attaches a logo image
- 🧠 Tracks sent emails in a local cache file
- ⏱️ Adds itself to your crontab if not already scheduled
- 🧾 Uses shared signature

---

## 🛠 Requirements

- macOS with:
    - Apple Mail installed
    - `osascript`, `pbcopy`, and `sips` available (standard on macOS)
- PHP 8+
- Local crontab access

---

## ⚙️ Setup

1. **Build and run the scheduler:**

   ```bash
   npm install
   npm run build
   node dist/email-scheduler.js
   ```

   Or, in one pass (build then run):

   ```bash
   npm run build:run
   ```

   After the first build, you can also run just the compiled script:

   ```bash
   npm start
   ```

---
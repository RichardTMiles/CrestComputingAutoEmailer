# Chrome Extension

This repository includes a minimal Chrome extension that drafts weekly class emails using the existing JSON schedule and templates.

## Load the extension
1. Open `chrome://extensions/` in Chrome.
2. Toggle **Developer mode** on.
3. Click **Load unpacked** and select this repository's folder.
4. A robot icon will appear in the toolbar. Click it to generate the next email draft in Gmail.

The extension reads `classes.json`, `email_templates.json`, and `signature.txt` to assemble the message. Update those files to change recipients or template text.

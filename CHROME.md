# Chrome Extension

This repository includes a minimal Chrome extension that drafts weekly class emails using the existing JSON schedule and templates.

## Load the extension
1. Open `chrome://extensions/` in Chrome.
2. Toggle **Developer mode** on.
3. Click **Load unpacked** and select this repository's folder.
4. A robot icon will appear in the toolbar. Click it to generate the next UNSENT weekly email draft in Gmail.

## How it works
- Reads `classes.json`, `email_templates.json`, and `signature.txt`.
- Computes the current curriculum week from each class's `startDate`, subtracting any past dates in `skip`.
- Tracks which week was last drafted per class using `chrome.storage.local` under the key `lastSentWeeks`.
- On each click, it finds the earliest week that has not yet been drafted for any class and opens a single Gmail compose window for that week. Click again to catch up the next week, and so on, until you are current.
- If everything is already up to date, it does nothing.

## Resetting the tracking state (optional)
If you need to re-send or re-draft from an earlier week, you can clear the stored state:
1. Open `chrome://extensions`, enable **Developer mode**.
2. Find "After School Email Scheduler" and click **Service worker** (or **Inspect views**) to open DevTools for the background service worker.
3. In the Console, run:
   - `chrome.storage.local.remove('lastSentWeeks')` to reset all classes' last-sent markers; or
   - `chrome.storage.local.clear()` to clear everything the extension stored.

Note: After updating the extension files, click the refresh icon on the extension card in `chrome://extensions/` to reload the service worker.

const senderEmail = 'richard.miles@crestcomputing.com';
const ccEmail = 'jeremy.bickel@crestcomputing.com';
const subjectPrefix = 'Afterschool Update –';

const STORAGE_KEY = 'lastSentWeeks';

function getLastSentMap() {
  return new Promise(resolve => {
    try {
      chrome.storage.local.get([STORAGE_KEY], res => {
        resolve(res && res[STORAGE_KEY] ? res[STORAGE_KEY] : {});
      });
    } catch (e) {
      resolve({});
    }
  });
}

function setLastSentMap(map) {
  return new Promise(resolve => {
    try {
      chrome.storage.local.set({ [STORAGE_KEY]: map }, () => resolve());
    } catch (e) {
      resolve();
    }
  });
}

async function sendDraft() {
  const [classes, templates, signature] = await Promise.all([
    fetch(chrome.runtime.getURL('classes.json')).then(r => r.json()),
    fetch(chrome.runtime.getURL('email_templates.json')).then(r => r.json()),
    fetch(chrome.runtime.getURL('signature.txt')).then(r => r.text())
  ]);

  const today = new Date();
  const lastSent = await getLastSentMap();

  for (const cls of classes.classes) {
    const startDate = new Date(cls.startDate);
    if (isNaN(startDate.getTime()) || today < startDate) continue;

    const days = Math.floor((today - startDate) / 86400000);
    const weeks = Math.floor(days / 7);
    const pastSkips = (cls.skip || []).filter(d => {
      const sd = new Date(d);
      return !isNaN(sd.getTime()) && sd <= today;
    }).length;

    let currWeek = weeks - pastSkips;
    if (currWeek < 0) continue;

    const maxWeekIndex = Array.isArray(templates) ? templates.length - 1 : 0;
    if (currWeek > maxWeekIndex) currWeek = maxWeekIndex;

    const classKey = `${cls.schoolName}::${cls.className}`;
    const lastSentWeek = Number.isInteger(lastSent[classKey]) ? lastSent[classKey] : -1;
    const nextWeek = lastSentWeek + 1;

    if (nextWeek <= currWeek && nextWeek >= 0 && nextWeek <= maxWeekIndex) {
      const template = templates[nextWeek];
      if (!template) continue;
      const subject = (template.subject && String(template.subject).trim()) ? template.subject : `${subjectPrefix} ${cls.className}`;
      const footerLinks = (cls.links || []).join('\n');
      const body = `Howdy Parents 👋,\n\n${template.body}\n\n${footerLinks}\n\n${signature}`;

      const params = new URLSearchParams({
        su: subject,
        bcc: (cls.bcc || []).join(','),
        cc: ccEmail,
        body
      });
      chrome.tabs.create({ url: `https://mail.google.com/mail/?view=cm&fs=1&${params.toString()}` });

      // Optimistically record this week as drafted to avoid duplicates on rapid clicks
      lastSent[classKey] = nextWeek;
      await setLastSentMap(lastSent);
      break;
    }
  }
}

chrome.action.onClicked.addListener(sendDraft);

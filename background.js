const senderEmail = 'richard.miles@crestcomputing.com';
const ccEmail = 'jeremy.bickel@crestcomputing.com';
const subjectPrefix = 'Afterschool Update –';

async function sendDraft() {
  const [classes, templates, signature] = await Promise.all([
    fetch(chrome.runtime.getURL('classes.json')).then(r => r.json()),
    fetch(chrome.runtime.getURL('email_templates.json')).then(r => r.json()),
    fetch(chrome.runtime.getURL('signature.txt')).then(r => r.text())
  ]);
  const today = new Date();
  for (const cls of classes.classes) {
    const startDate = new Date(cls.startDate);
    const days = Math.floor((today - startDate) / 86400000);
    const weeks = Math.floor(days / 7);
    const skippedWeeks = cls.skip.filter((d) => new Date(d) <= today).length;
    const currWeek = weeks - skippedWeeks;
    const lessonNames = Object.keys(templates);
    const lessonKey = lessonNames[currWeek];
    const template = templates[lessonKey];
    if (!template) continue;
    const subject = template.subject || `${subjectPrefix} ${cls.className}`;
    const footerLinks = cls.links.join('\n');
    const body = `Howdy Parents 👋,\n\n${template.body}\n\n${footerLinks}\n\n${signature}`;
    const params = new URLSearchParams({
      su: subject,
      bcc: cls.bcc.join(','),
      cc: ccEmail,
      body
    });
    chrome.tabs.create({ url: `https://mail.google.com/mail/?view=cm&fs=1&${params.toString()}` });
    break;
  }
}

chrome.action.onClicked.addListener(sendDraft);

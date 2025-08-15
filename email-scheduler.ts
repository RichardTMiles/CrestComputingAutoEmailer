#!/usr/bin/env node
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const cronArgv = '--cron';

if (!process.stdout.isTTY) {
  console.log('Running in cron context');
} else if (process.argv.includes(cronArgv)) {
  console.log('Bypassing cronjob');
} else {
  const scriptPath = path.resolve(process.argv[1]);
  const cronLine = `0 * * * * node ${scriptPath} ${cronArgv} > /tmp/assessorly-email.log 2>&1`;
  try {
    const currentCrontab = execSync('crontab -l', { encoding: 'utf-8' });
    if (!currentCrontab.includes(scriptPath)) {
      const newCrontab = `${currentCrontab.trim()}\n${cronLine}\n`;
      fs.writeFileSync('/tmp/new_cron.txt', newCrontab);
      execSync('crontab /tmp/new_cron.txt');
      fs.unlinkSync('/tmp/new_cron.txt');
      console.log('✅ Added to crontab to run hourly. Please rerun manually or wait for cron to pick it up.');
    } else {
      console.log('⚠️ Already in crontab, but not running under cron. Exiting.');
    }
  } catch {
    fs.writeFileSync('/tmp/new_cron.txt', `${cronLine}\n`);
    execSync('crontab /tmp/new_cron.txt');
    fs.unlinkSync('/tmp/new_cron.txt');
    console.log('✅ Added to crontab to run hourly. Please rerun manually or wait for cron to pick it up.');
  }
  process.exit(0);
}

process.env.TZ = 'America/Denver';

const templateJSON = path.join(__dirname, 'email_templates.json');
const signatureFile = path.join(__dirname, 'signature.txt');
const originalLogo = path.join(__dirname, 'robot.png');
const senderName = 'Richard Miles';
const senderEmail = 'richard.miles@crestcomputing.com';
const ccEmail = 'jeremy.bickel@crestcomputing.com';
const subjectPrefix = 'Afterschool Update –';
const resizedLogo = path.join(require('os').tmpdir(), 'footer-logo-resized.png');
const cacheFile = path.join(__dirname, 'sent-classes-cache.json');

execSync(`sips --resampleWidth 200 ${originalLogo} --out ${resizedLogo}`);

const currentDate = new Date();
const currentTime = currentDate.toTimeString().substring(0,5);

if (!fs.existsSync(templateJSON)) {
  console.error(`\n✖️ Template JSON not found: ${templateJSON}`);
  process.exit(1);
}
const emailTemplates = JSON.parse(fs.readFileSync(templateJSON, 'utf-8')) as any[];

if (!fs.existsSync(signatureFile)) {
  console.error(`\n✖️ Signature file not found: ${signatureFile}`);
  process.exit(1);
}
const signature = fs.readFileSync(signatureFile, 'utf-8');

const scheduleData = JSON.parse(fs.readFileSync(path.join(__dirname, 'classes.json'), 'utf-8')) as { classes: any[] };
const sentCache: string[] = fs.existsSync(cacheFile) ? JSON.parse(fs.readFileSync(cacheFile, 'utf-8')) : [];

for (const cls of scheduleData.classes) {
  console.log(`Checking Class: (${cls.schoolName})`);
  const startDate = new Date(cls.startDate);
  const days = Math.floor((currentDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24));
  const weeks = Math.floor(days / 7);
  console.log(`Weeks since start: ${weeks}`);

  const skippedWeeks = cls.skip.filter((d: string) => new Date(d) <= currentDate).length;
  const curriculumWeek = weeks - skippedWeeks;
  console.log(`Curriculum Week: ${curriculumWeek}`);

  const lessonTemplate = emailTemplates[curriculumWeek];
  if (!lessonTemplate) {
    console.error(`\n✖️ No email template found for curriculum week ${curriculumWeek}`);
    continue;
  }

  const cacheKey = `${cls.schoolName}-${currentDate.toISOString().substring(0,10)}`;
  if (sentCache.includes(cacheKey)) {
    console.log(`⏩ Already sent for ${cacheKey}, skipping.`);
    continue;
  }

  const footerLinks = cls.links.join('\n');
  const fullMessage = `${lessonTemplate.body}\n\n${footerLinks}\n\n${signature}`;

  if (currentTime >= cls.startTime || !sentCache.includes(cacheKey)) {
    const bccArray = `{${cls.bcc.map((e:string) => `"${e}"`).join(', ')}}`;
    const escapedBody = `Howdy Parents 👋,\n\n${fullMessage.replace(/"/g, '\\"')}`;
    const subject = lessonTemplate.subject ?? `${subjectPrefix} ${cls.className}`;

    const appleScript = `tell application "Mail"
        set newMessage to make new outgoing message with properties {visible:true, subject:"${subject}", content:"${escapedBody}"}
        tell newMessage
            repeat with b in ${bccArray}
                make new bcc recipient at end of bcc recipients with properties {address:b}
            end repeat
            make new cc recipient at end of cc recipients with properties {address:"${ccEmail}"}
            set imagePath to POSIX file "${resizedLogo}"
            make new attachment with properties {file name:imagePath}
        end tell
    end tell`;

    const tmpScript = path.join(require('os').tmpdir(), `mail-draft-${Date.now()}.scpt`);
    fs.writeFileSync(tmpScript, appleScript);
    try {
      execSync(`osascript ${tmpScript}`);
      console.log(`✅ Draft for ${cls.schoolName} opened in Mail.app for review`);
      sentCache.push(cacheKey);
      fs.writeFileSync(cacheFile, JSON.stringify(sentCache, null, 2));
    } catch {
      console.error('❌ Error sending AppleScript to Mail');
    } finally {
      fs.unlinkSync(tmpScript);
    }

    break;
  }
}

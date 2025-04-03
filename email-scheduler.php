#!/usr/bin/env php
<?php

$cronArgv = '--cron';

// === CRON CHECK ===
if (!posix_isatty(STDOUT)) {
    echo "Running in cron context\n";
} elseif (in_array($cronArgv, $argv)) {
    echo "Bypassing cronjob\n";
} else {
    // Not running from cron — attempt to add ourselves
    $scriptPath = realpath(__FILE__);
    $cronLine = "0 * * * * php " . $scriptPath . ' ' . $cronArgv . " > /tmp/assessorly-email.log 2>&1";

    $currentCrontab = shell_exec("crontab -l>/dev/null || true"); // Suppress error if no crontab
    if (!str_contains($currentCrontab, $scriptPath)) {
        $newCrontab = trim($currentCrontab) . "\n" . $cronLine . "\n";
        file_put_contents('/tmp/new_cron.txt', $newCrontab);
        exec("crontab /tmp/new_cron.txt");
        unlink('/tmp/new_cron.txt');
        echo "✅ Added to crontab to run hourly. Please rerun manually or wait for cron to pick it up.\n";
    } else {
        echo "⚠️ Already in crontab, but not running under cron. Exiting.\n";
    }
    exit;
}

date_default_timezone_set('America/Denver'); // Adjust to your time zone

// === CONFIGURATION ===
$templateJSON = __DIR__ . '/email_templates.json';
$signatureFile = __DIR__ . '/signature.txt';  // Path to the signature file
$originalLogo = __DIR__ . '/robot.png';
$senderName = 'Richard Miles';
$senderEmail = 'richard.miles@crestcomputing.com';
$ccEmail = 'jeremy.bickel@crestcomputing.com';
$subjectPrefix = 'Afterschool Update –';
$resizedLogo = sys_get_temp_dir() . '/footer-logo-resized.png';
$cacheFile = __DIR__ . '/sent-classes-cache.json';

// Resize logo to width of 150px
exec("sips --resampleWidth 200 " . escapeshellarg($originalLogo) . " --out " . escapeshellarg($resizedLogo));

$currentDate = date('Y-m-d');
$currentTime = date('H:i');
$weekday = date('l');
$today = new DateTime('now');

// === Load JSON Email Templates ===
if (!file_exists($templateJSON)) {
    fwrite(STDERR, "\n✖️ Template JSON not found: $templateJSON\n");
    exit(1);
}
$emailTemplates = json_decode(file_get_contents($templateJSON), true);

// === Load signature ===
if (!file_exists($signatureFile)) {
    fwrite(STDERR, "\n✖️ Signature file not found: $signatureFile\n");
    exit(1);
}
$signature = file_get_contents($signatureFile);

// === Load class schedule ===
$scheduleData = json_decode(file_get_contents('classes.json'), true);

// === Load or initialize sent cache ===
$sentCache = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];

foreach ($scheduleData['classes'] as $class) {
    echo "Checking Class: ({$class['schoolName']})\n";

    $startDate = new DateTime($class['startDate']);
    $interval = $startDate->diff($today);
    $days = (int)$interval->format('%a');
    $weeks = floor($days / 7);

    echo "Weeks since start: $weeks\n";

    $skippedWeeks = count($class['skip']);
    $curriculumWeek = $weeks - $skippedWeeks;

    echo "Curriculum Week: $curriculumWeek\n";

    // Determine current lesson name by curriculumWeek
    $lessonNames = array_keys($emailTemplates);
    $lessonKey = $lessonNames[$curriculumWeek] ?? null;

    if (!$lessonKey || !isset($emailTemplates[$lessonKey])) {
        fwrite(STDERR, "\n✖️ No email template found for curriculum week $curriculumWeek\n");
        continue;
    }

    // Build unique cache key for this class occurrence
    $cacheKey = $class['schoolName'] . '-' . $currentDate;
    if (in_array($cacheKey, $sentCache)) {
        echo "⏩ Already sent for {$cacheKey}, skipping.\n";
        continue;
    }

    $lessonTemplate = $emailTemplates[$lessonKey];
    $mainBody = $lessonTemplate['body'];

    $footerLinks = implode("\n", $class['links']);
    $fullMessage = $mainBody . "\n\n" . $footerLinks . "\n\n" . $signature;

    // Send email if current time is in range OR we haven’t sent yet
    if ($currentTime >= $class['startTime'] || !in_array($cacheKey, $sentCache)) {
        $bccScriptArray = '{' . implode(', ', array_map(fn($email) => "\"$email\"", $class['bcc'])) . '}';

        $escapedBody = "Howdy Parents 👋,\n\n" . addslashes($fullMessage);
        $subject = $lessonTemplate['subject'] ?? "$subjectPrefix {$class['className']}";

        $appleScript = <<<APPLESCRIPT
        tell application "Mail"
            set newMessage to make new outgoing message with properties {visible:true, subject:"{$subject}", content:"{$escapedBody}"}
            tell newMessage
                -- make new to recipient at end of to recipients with properties {address:"{$senderEmail}"}
                repeat with b in {$bccScriptArray}
                    make new bcc recipient at end of bcc recipients with properties {address:b}
                end repeat
                make new cc recipient at end of cc recipients with properties {address:"{$ccEmail}"}
                set imagePath to POSIX file "{$resizedLogo}"
                set imgAttachment to make new attachment with properties {file name:imagePath}
            end tell
        end tell
APPLESCRIPT;

        $tmpScript = tempnam(sys_get_temp_dir(), 'mail-draft') . '.scpt';
        file_put_contents($tmpScript, $appleScript);
        exec("osascript " . escapeshellarg($tmpScript), $output, $exitCode);
        unlink($tmpScript);

        if ($exitCode === 0) {
            echo "✅ Draft for {$class['schoolName']} opened in Mail.app for review\n";
            $sentCache[] = $cacheKey;
            file_put_contents($cacheFile, json_encode($sentCache, JSON_PRETTY_PRINT));
        } else {
            echo "❌ Error sending AppleScript to Mail (exit code: $exitCode)\n";
        }

        break; // Stop after the first matching class for the day
    }
}
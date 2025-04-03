#!/usr/bin/env php
<?php

date_default_timezone_set('America/Denver'); // Adjust to your time zone

// === CONFIGURATION ===
$templateJSON    = __DIR__ . '/email_templates.json';
$signatureFile   = __DIR__ . '/signature.txt';  // Path to the signature file
$originalLogo    = __DIR__ . '/robot.png';
$senderName      = 'Richard Miles';
$senderEmail     = 'richard.miles@crestcomputing.com';
$subjectPrefix   = 'Afterschool Update –';
$resizedLogo     = sys_get_temp_dir() . '/footer-logo-resized.png';
$cacheFile       = __DIR__ . '/sent-classes-cache.json';

// Resize logo to width of 150px
exec("sips --resampleWidth 150 " . escapeshellarg($originalLogo) . " --out " . escapeshellarg($resizedLogo));

$currentDate = date('Y-m-d');
$currentTime = date('H:i');
$weekday     = date('l');
$today       = new DateTime('now');

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
    $interval  = $startDate->diff($today);
    $days      = (int) $interval->format('%a');
    $weeks     = floor($days / 7);

    echo "Weeks since start: $weeks\n";

    $skippedWeeks   = count($class['skip']);
    $curriculumWeek = $weeks - $skippedWeeks;

    echo "Curriculum Week: $curriculumWeek\n";

    // Lookup lesson from indexed template structure
    $lessonTemplate = $emailTemplates[$curriculumWeek] ?? null;
    if (!$lessonTemplate) {
        fwrite(STDERR, "\n✖️ No email template found for curriculum week $curriculumWeek\n");
        continue;
    }

    // Build unique cache key for this class occurrence
    $cacheKey = $class['schoolName'] . '-' . $currentDate;
    if (in_array($cacheKey, $sentCache)) {
        echo "⏩ Already sent for {$cacheKey}, skipping.\n";
        continue;
    }

    $mainBody = $lessonTemplate['body'];
    $footerLinks = implode("\n", $class['links']);
    $fullMessage = $mainBody . "\n\n" . $footerLinks . "\n\n" . $signature;

    // Send email if current time is in range OR we haven’t sent yet
    if ($currentTime >= $class['startTime'] || !in_array($cacheKey, $sentCache)) {
        $bccScriptArray = '{' . implode(', ', array_map(fn($email) => "\"$email\"", $class['bcc'])) . '}';

        $escapedBody = addslashes($fullMessage);
        $subject = $lessonTemplate['subject'] ?? "$subjectPrefix {$class['className']}";

        $appleScript = <<<APPLESCRIPT
        tell application "Mail"
            set newMessage to make new outgoing message with properties {visible:true, subject:"{$subject}"}
            set html content of newMessage to "<body>{$escapedBody}</body>"
            tell newMessage
                make new to recipient at end of to recipients with properties {address:"{$senderEmail}"}
                repeat with b in {$bccScriptArray}
                    make new bcc recipient at end of bcc recipients with properties {address:b}
                end repeat
                set imagePath to POSIX file "{$resizedLogo}"
                set imgAttachment to make new attachment with properties {file name:imagePath}
            end tell
        end tell
APPLESCRIPT;

        // === Step 6: Output AppleScript for debugging ===
        echo "====== Generated AppleScript ======\n";
        echo $appleScript . "\n";
        echo "===================================\n\n";


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
    }
}

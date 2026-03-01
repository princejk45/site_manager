<?php
require 'config/database.php';

$userId = 1;

// Get user's threads
$stmt = $pdo->prepare("
    SELECT 
        t.id,
        t.subject,
        COUNT(p.id) as participant_count,
        MAX(CASE WHEN p.user_id = ? THEN 1 ELSE 0 END) as user_is_participant
    FROM message_threads t
    LEFT JOIN thread_participants p ON t.id = p.thread_id
    GROUP BY t.id
    ORDER BY t.id DESC
    LIMIT 20
");
$stmt->execute([$userId]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>All Threads (showing user participation):</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Thread ID</th><th>Subject</th><th>Participants</th><th>User 1 is Participant?</th></tr>";
foreach ($threads as $thread) {
    $isParticipant = $thread['user_is_participant'] ? 'YES ✓' : 'NO ✗';
    echo "<tr><td>{$thread['id']}</td><td>{$thread['subject']}</td><td>{$thread['participant_count']}</td><td style='background:" . ($thread['user_is_participant'] ? 'lightgreen' : 'lightcoral') . "'>{$isParticipant}</td></tr>";
}
echo "</table>";

// Get specific participant records for user 1
echo "<h2>Thread Participants for User 1:</h2>";
$stmt = $pdo->prepare("SELECT thread_id, user_id, last_read_at, is_starred FROM thread_participants WHERE user_id = ?");
$stmt->execute([$userId]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Thread ID</th><th>User ID</th><th>Last Read At</th><th>Is Starred</th></tr>";
foreach ($participants as $p) {
    echo "<tr><td>{$p['thread_id']}</td><td>{$p['user_id']}</td><td>" . ($p['last_read_at'] ?? 'NULL') . "</td><td>{$p['is_starred']}</td></tr>";
}
echo "</table>";

echo "<p><a href='debug_threads.php'>Refresh</a></p>";

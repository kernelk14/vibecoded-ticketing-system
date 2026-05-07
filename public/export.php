<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/tickets.php';

startSession();
$user = currentUser();

if ($user === null || !isAdmin($user)) {
    header('Location: /');
    exit;
}

$tickets = listTickets($user, []);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="tickets-' . date('Y-m-d') . '.csv"');

$fh = fopen('php://memory', 'w');
fputcsv($fh, ['ID', 'Title', 'Description', 'Author', 'Project', 'Status', 'Priority', 'Severity', 'Assignee', 'Created', 'Updated']);

foreach ($tickets as $t) {
    fputcsv($fh, [
        $t['id'],
        $t['title'],
        $t['description'],
        $t['author'],
        $t['project'],
        $t['status'],
        $t['priority'],
        $t['severity'],
        $t['assigneeName'] ?? '',
        $t['createdAt'],
        $t['updatedAt'],
    ]);
}

fseek($fh, 0);
fpassthru($fh);
fclose($fh);
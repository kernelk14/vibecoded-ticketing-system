<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/tickets.php';

startSession();
$user = currentUser();

if ($user === null) {
    header('Location: /');
    exit;
}

$unreadNotificationCount = 0;
$adminNotifications = listUserNotifications($user);
$unreadNotificationCount = unreadNotificationCount($user);

$myTickets = [];
if (isAdmin($user) || canManageTickets($user)) {
    $stmt = db()->prepare(
        'SELECT a.id, a.ticketId, a.assigneeUserId, a.assignedByUserId, a.createdAt,
                t.title, t.status, t.priority,
                assig.name as assigneeName, ab.name as assignedByName
         FROM Assignment a
         JOIN Ticket t ON t.id = a.ticketId
         JOIN User assig ON assig.id = a.assigneeUserId
         JOIN User ab ON ab.id = a.assignedByUserId
         ORDER BY a.createdAt DESC'
    );
    $stmt->execute();
    $myTickets = $stmt->fetchAll();
    
    $ticketStmt = db()->prepare(
        'SELECT t.id, t.id as ticketId, t.assigneeUserId, null as assignedByUserId, t.updatedAt as createdAt,
                t.title, t.status, t.priority,
                a.name as assigneeName, t.author as assignedByName
         FROM Ticket t
         LEFT JOIN User a ON a.id = t.assigneeUserId
         WHERE t.assigneeUserId IS NOT NULL AND t.assigneeUserId > 0
         AND NOT EXISTS (SELECT 1 FROM Assignment a2 WHERE a2.ticketId = t.id)
         ORDER BY t.updatedAt DESC'
    );
    $ticketStmt->execute();
    $legacyTickets = $ticketStmt->fetchAll();
    $myTickets = array_merge($myTickets, $legacyTickets);
} else {
    $stmt = db()->prepare(
        'SELECT t.id, t.id as ticketId, t.assigneeUserId, null as assignedByUserId, t.updatedAt as createdAt,
                t.title, t.status, t.priority,
                a.name as assigneeName, t.author as assignedByName
         FROM Ticket t
         LEFT JOIN User a ON a.id = t.assigneeUserId
         WHERE t.assigneeUserId = :userId
         ORDER BY t.updatedAt DESC'
    );
    $stmt->execute(['userId' => (int) $user['id']]);
    $myTickets = $stmt->fetchAll();
}

function statusBadge(string $status): string
{
    return match ($status) {
        'OPEN' => 'bg-blue-100 text-blue-700',
        'TRIAGED' => 'bg-cyan-100 text-cyan-700',
        'IN_PROGRESS' => 'bg-amber-100 text-amber-700',
        'IN_REVIEW' => 'bg-violet-100 text-violet-700',
        'TESTING' => 'bg-orange-100 text-orange-700',
        'DONE' => 'bg-emerald-100 text-emerald-700',
        default => 'bg-zinc-100 text-zinc-600',
    };
}

function priorityBadge(string $priority): string
{
    return match ($priority) {
        'LOW' => 'bg-slate-100 text-slate-700',
        'MEDIUM' => 'bg-violet-100 text-violet-700',
        'HIGH' => 'bg-orange-100 text-orange-700',
        'URGENT' => 'bg-rose-100 text-rose-700',
        default => 'bg-zinc-100 text-zinc-600',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assignments - Ticketing System</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900 antialiased">
  <nav class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-[1400px] items-center justify-between px-4 sm:px-6 lg:px-8">
      <div class="flex items-center gap-3">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm">
          <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 7h18"></path>
            <path d="M5 7v14h6V14h2v7h6V7"></path>
          </svg>
        </span>
        <div>
          <p class="text-sm font-semibold leading-tight">Ticketing System</p>
          <p class="text-xs text-zinc-500">Assignments</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-zinc-700"><?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="hidden text-sm text-zinc-600 md:inline"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></span>
        <form method="post">
          <input type="hidden" name="action" value="logout">
          <button class="rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-zinc-900">Logout</button>
        </form>
      </div>
    </div>
  </nav>

  <main class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8">
    <section class="flex gap-6">
      <aside class="sticky top-20 h-fit w-60 flex-none rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <p class="px-2 text-xs font-semibold uppercase tracking-wider text-zinc-500">Navigation</p>
        <nav class="mt-3 space-y-1">
          <a href="/" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3z"/></svg>
            </span>
            Dashboard
          </a>
          <a href="/tickets.php" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </span>
            All Tickets
          </a>
        </nav>

        <a href="/add-ticket.php" class="mt-4 flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-700">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14m0 0l6-6-6-6"/></svg>
          </span>
          Add Ticket
        </a>
        <?php if (isAdmin($user)): ?>
        <a href="/projects.php" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-cyan-100 text-cyan-700">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3z"/><path d="M9 3v18"/></svg>
          </span>
          Projects
        </a>
        <?php endif; ?>

        <div class="mt-6 rounded-xl bg-zinc-50 p-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Account</p>
          <p class="mt-2 text-sm font-medium text-zinc-800"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></p>
          <p class="text-xs text-zinc-500"><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </aside>

      <div class="flex-1 space-y-6">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
          <h1 class="text-xl font-bold tracking-tight">Assignments</h1>
          <p class="mt-1 text-sm text-zinc-500">Tickets assigned to users</p>

          <div class="mt-6 overflow-x-auto">
            <table class="w-full" style="border-collapse:collapse;">
              <thead>
                <tr style="border-bottom:1px solid #e5e5e5;">
                  <th style="padding:12px 16px;text-align:left;font-size:14px;font-weight:600;color:#71717a;">ID</th>
                  <th style="padding:12px 16px;text-align:left;font-size:14px;font-weight:600;color:#71717a;">Title</th>
                  <th style="padding:12px 16px;text-align:left;font-size:14px;font-weight:600;color:#71717a;">Assignee</th>
                  <th style="padding:12px 16px;text-align:left;font-size:14px;font-weight:600;color:#71717a;">Assigned By</th>
                  <th style="padding:12px 16px;text-align:left;font-size:14px;font-weight:600;color:#71717a;">Status</th>
                  <th style="padding:12px 16px;text-align:left;font-size:14px;font-weight:600;color:#71717a;">Priority</th>
                  <th style="padding:12px 16px;text-align:left;font-size:14px;font-weight:600;color:#71717a;">Assigned At</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($myTickets)): ?>
                <tr>
                  <td colspan="7" style="padding:16px;text-align:center;font-size:14px;color:#71717a;">No assignments found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($myTickets as $ticket): ?>
                <tr style="border-bottom:1px solid #f5f5f5;">
                  <td style="padding:12px 16px;font-size:14px;color:#18181b;">#<?= (int) $ticket['ticketId'] ?></td>
                  <td style="padding:12px 16px;font-size:14px;color:#18181b;">
                    <a href="/issue.php?id=<?= (int) $ticket['ticketId'] ?>" style="color:#4f46e5;text-decoration:underline;"><?= htmlspecialchars($ticket['title'], ENT_QUOTES, 'UTF-8') ?></a>
                  </td>
                  <td style="padding:12px 16px;font-size:14px;color:#71717a;"><?= htmlspecialchars($ticket['assigneeName'] ?? $ticket['assigneeUserId'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td style="padding:12px 16px;font-size:14px;color:#71717a;"><?= htmlspecialchars($ticket['assignedByName'] ?? $ticket['assignedByUserId'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td style="padding:12px 16px;font-size:14px;">
                    <span class="rounded-full px-2 py-1 text-xs font-semibold <?= statusBadge($ticket['status']) ?>"><?= htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                  <td style="padding:12px 16px;font-size:14px;">
                    <span class="rounded-full px-2 py-1 text-xs font-semibold <?= priorityBadge($ticket['priority']) ?>"><?= htmlspecialchars($ticket['priority'], ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                  <td style="padding:12px 16px;font-size:14px;color:#71717a;"><?= htmlspecialchars(date('M j, Y', strtotime($ticket['createdAt'])), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
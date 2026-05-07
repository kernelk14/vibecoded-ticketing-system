<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/tickets.php';

$error = null;
$selectedStatus = $_GET['status'] ?? null;
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$selectedProject = trim((string) ($_GET['project'] ?? ''));
$selectedPriority = $_GET['priority'] ?? null;
$selectedSeverity = $_GET['severity'] ?? null;
$selectedAssigneeId = (int) ($_GET['assigneeUserId'] ?? 0);

startSession();
$user = currentUser();
$assignableUsers = [];

if ($user === null) {
    header('Location: /');
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($title !== '' && $description !== '') {
                createTicket($user, $_POST);
            }
        }

        if ($action === 'logout') {
            logoutUser();
            header('Location: /');
            exit;
        }

        if ($user !== null && $action === 'status') {
            $ticketId = (int) ($_POST['id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'OPEN');
            if ($ticketId > 0) {
                updateTicketStatusForUser($user, $ticketId, $status);
            }
        }

        if ($user !== null && $action === 'delete') {
            $ticketId = (int) ($_POST['id'] ?? 0);
            if ($ticketId > 0) {
                deleteTicketForUser($user, $ticketId);
            }
        }

        if ($user !== null && $action === 'meta_update') {
            $ticketId = (int) ($_POST['id'] ?? 0);
            if ($ticketId > 0) {
                updateTicketMetaForUser($user, $ticketId, $_POST);
            }
        }

        if ($user !== null && $action === 'add_comment') {
            $ticketId = (int) ($_POST['id'] ?? 0);
            $comment = (string) ($_POST['comment'] ?? '');
            if ($ticketId > 0) {
                addCommentToTicket($user, $ticketId, $comment);
            }
        }

        $params = [];
        if ($selectedStatus) {
            $params['status'] = $selectedStatus;
        }
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if ($selectedProject !== '') {
            $params['project'] = $selectedProject;
        }
        if ($selectedPriority) {
            $params['priority'] = $selectedPriority;
        }
        if ($selectedSeverity) {
            $params['severity'] = $selectedSeverity;
        }
        if ($selectedAssigneeId > 0) {
            $params['assigneeUserId'] = (string) $selectedAssigneeId;
        }

        $query = $params ? ('?' . http_build_query($params)) : '';
        header('Location: /tickets.php' . $query);
        exit;
    }

    $filters = [
        'status' => $selectedStatus,
        'q' => $searchQuery,
        'project' => $selectedProject,
        'priority' => $selectedPriority,
        'severity' => $selectedSeverity,
        'assigneeUserId' => $selectedAssigneeId,
    ];

    $tickets = listTickets($user, $filters);
    $assignableUsers = listAssignableUsers();
} catch (Throwable $e) {
    $error = $e->getMessage();
    $tickets = [];
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
  <title>All Tickets - Ticketing System</title>
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
          <p class="text-xs text-zinc-500">All Tickets</p>
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
          <a href="/tickets.php" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium bg-indigo-50 text-indigo-700">
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

        <p class="mt-6 px-2 text-xs font-semibold uppercase tracking-wider text-zinc-500">Status</p>
        <nav class="mt-3 space-y-1">
          <a href="/tickets.php?status=OPEN" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium <?= $selectedStatus === 'OPEN' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-blue-50 text-blue-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.5-5.5l-1.5 1.5M7 17l-1.5 1.5M17 17l1.5 1.5M7 7l-1.5-1.5"/></svg>
            </span>
            Open Tickets
          </a>
          <a href="/tickets.php?status=TRIAGED" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium <?= $selectedStatus === 'TRIAGED' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-cyan-50 text-cyan-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            </span>
            Triaged
          </a>
          <a href="/tickets.php?status=IN_PROGRESS" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium <?= $selectedStatus === 'IN_PROGRESS' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-amber-50 text-amber-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
            </span>
            In Progress
          </a>
          <a href="/tickets.php?status=IN_REVIEW" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium <?= $selectedStatus === 'IN_REVIEW' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-violet-50 text-violet-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
            In Review
          </a>
          <a href="/tickets.php?status=TESTING" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium <?= $selectedStatus === 'TESTING' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-orange-50 text-orange-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 6H7l1-6z"/><path d="M7 8h10v11H7z"/><path d="M10 13h4"/></svg>
            </span>
            Testing
          </a>
          <?php if (isAdmin($user)): ?>
            <a href="/tickets.php?status=DONE" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium <?= $selectedStatus === 'DONE' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
              <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
              </span>
              Done
            </a>
          <?php endif; ?>
        </nav>

        <div class="mt-6 rounded-xl bg-zinc-50 p-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Account</p>
          <p class="mt-2 text-sm font-medium text-zinc-800"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></p>
          <p class="text-xs text-zinc-500"><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </aside>

      <div class="flex-1 space-y-6">
        <?php if ($error !== null): ?>
          <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm xl:col-span-12">
          <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold tracking-tight"><?= isAdmin($user) ? 'All Tickets' : 'My Tickets' ?></h2>
            <div class="flex flex-wrap items-center gap-2">
              <a href="/tickets.php" class="rounded-full px-3 py-1.5 text-sm font-medium <?= $selectedStatus === null ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200' ?>">All</a>
              <?php foreach (ALLOWED_STATUSES as $status): ?>
                <a href="/tickets.php?status=<?= urlencode($status) ?>" class="rounded-full px-3 py-1.5 text-sm font-medium <?= $selectedStatus === $status ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200' ?>">
                  <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <form method="get" class="mb-5 grid gap-2 md:grid-cols-6">
            <input type="text" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search text" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm md:col-span-2">
            <input type="text" name="project" value="<?= htmlspecialchars($selectedProject, ENT_QUOTES, 'UTF-8') ?>" placeholder="Project" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm">
            <select name="priority" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm">
              <option value="">All priorities</option>
              <?php foreach (ALLOWED_PRIORITIES as $priority): ?>
                <option value="<?= $priority ?>" <?= $selectedPriority === $priority ? 'selected' : '' ?>><?= $priority ?></option>
              <?php endforeach; ?>
            </select>
            <select name="severity" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm">
              <option value="">All severity</option>
              <?php foreach (ALLOWED_SEVERITIES as $severity): ?>
                <option value="<?= $severity ?>" <?= $selectedSeverity === $severity ? 'selected' : '' ?>><?= $severity ?></option>
              <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-zinc-800 px-3 py-2 text-sm font-medium text-white">Filter</button>
            <?php if ($selectedStatus): ?><input type="hidden" name="status" value="<?= htmlspecialchars($selectedStatus, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
          </form>

          <div class="space-y-4">
            <?php if (count($tickets) === 0): ?>
              <p class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-5 text-sm text-zinc-500">No tickets found.</p>
            <?php endif; ?>

            <?php foreach ($tickets as $ticket): ?>
              <article id="ticket-<?= (int) $ticket['id'] ?>" class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-5 shadow-sm transition hover:border-zinc-300">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div class="max-w-2xl">
                    <h3 class="text-base font-semibold tracking-tight"><?= htmlspecialchars($ticket['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-xs font-medium uppercase tracking-wide text-zinc-500">
                      By <?= htmlspecialchars($ticket['author'], ENT_QUOTES, 'UTF-8') ?> |
                      Project: <?= htmlspecialchars((string) $ticket['project'], ENT_QUOTES, 'UTF-8') ?> |
                      Env: <?= htmlspecialchars((string) $ticket['environment'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="mt-2 text-sm leading-6 text-zinc-600"><?= nl2br(htmlspecialchars($ticket['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <p class="mt-2 text-xs text-zinc-500">
                      Module: <?= htmlspecialchars((string) ($ticket['module'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> |
                      Version: <?= htmlspecialchars((string) ($ticket['appVersion'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> |
                      Branch: <?= htmlspecialchars((string) ($ticket['branch'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                  </div>
                  <div class="flex flex-wrap gap-2 pt-0.5">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-inset <?= statusBadge($ticket['status']) ?>"><?= htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-inset <?= priorityBadge($ticket['priority']) ?>"><?= htmlspecialchars($ticket['priority'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-red-700 ring-1 ring-inset ring-red-200"><?= htmlspecialchars((string) $ticket['severity'], ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-medium text-zinc-500">
                  <span>Created: <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $ticket['createdAt'])), ENT_QUOTES, 'UTF-8') ?></span>
                  <span>Updated: <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $ticket['updatedAt'])), ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <details class="mt-4 rounded-lg border border-zinc-200 bg-white p-3">
                  <summary class="cursor-pointer text-sm font-semibold text-zinc-700">Technical details</summary>
                  <div class="mt-3 space-y-2 text-xs text-zinc-600">
                    <p><strong>Expected:</strong> <?= nl2br(htmlspecialchars((string) ($ticket['expectedBehavior'] ?? '-'), ENT_QUOTES, 'UTF-8')) ?></p>
                    <p><strong>Actual:</strong> <?= nl2br(htmlspecialchars((string) ($ticket['actualBehavior'] ?? '-'), ENT_QUOTES, 'UTF-8')) ?></p>
                    <p><strong>Steps:</strong> <?= nl2br(htmlspecialchars((string) ($ticket['stepsToReproduce'] ?? '-'), ENT_QUOTES, 'UTF-8')) ?></p>
                    <p><strong>Error Log:</strong> <span class="font-mono"><?= nl2br(htmlspecialchars((string) ($ticket['errorLog'] ?? '-'), ENT_QUOTES, 'UTF-8')) ?></span></p>
                  </div>
                </details>

                <?php if (canManageTickets($user)): ?>
                  <form method="post" class="mt-3 grid gap-2 rounded-lg border border-zinc-200 bg-white p-3 md:grid-cols-3">
                    <input type="hidden" name="action" value="meta_update">
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <select name="assigneeUserId" class="rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm">
                      <option value="0">Unassigned</option>
                      <?php foreach ($assignableUsers as $assignee): ?>
                        <option value="<?= (int) $assignee['id'] ?>" <?= (int) $ticket['assigneeUserId'] === (int) $assignee['id'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars((string) $assignee['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $assignee['role'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="datetime-local" name="dueAt" value="<?= !empty($ticket['dueAt']) ? htmlspecialchars(substr((string) $ticket['dueAt'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?>" class="rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm">
                    <button class="rounded-lg bg-zinc-700 px-3 py-1.5 text-sm font-medium text-white">Save Assignment</button>
                  </form>
                <?php endif; ?>

                <?php if (canManageTickets($user)): ?>
                  <div class="mt-4 flex flex-wrap gap-2 border-t border-zinc-200 pt-4">
                    <?php if (isAdmin($user)): ?>
                      <a href="/issue.php?id=<?= (int) $ticket['id'] ?>" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white">Open Admin Page</a>
                    <?php endif; ?>
                    <form method="post" class="flex items-center gap-2">
                      <input type="hidden" name="action" value="status">
                      <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                      <select name="status" class="rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-sm">
                        <?php foreach (ALLOWED_STATUSES as $status): ?>
                          <option value="<?= $status ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white">Update</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this ticket?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                      <button class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white">Delete</button>
                    </form>
                  </div>
                <?php endif; ?>

                <?php $comments = listCommentsForTicket((int) $ticket['id']); ?>
                <div class="mt-4 rounded-lg border border-zinc-200 bg-white p-3">
                  <details class="overflow-hidden rounded-lg">
                    <summary class="cursor-pointer flex items-center justify-between text-sm font-semibold text-zinc-700">
                      <span>Comments</span>
                      <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600"><?= count($comments) ?></span>
                    </summary>
                    <div class="mt-3 space-y-2">
                      <?php if (count($comments) === 0): ?>
                        <p class="rounded-md bg-zinc-50 p-2 text-sm text-zinc-500">No comments yet.</p>
                      <?php endif; ?>
                      <?php foreach ($comments as $comment): ?>
                        <div class="rounded-md bg-zinc-50 p-2">
                          <p class="text-xs font-semibold text-zinc-700"><?= htmlspecialchars((string) $comment['author'], ENT_QUOTES, 'UTF-8') ?></p>
                          <p class="text-sm text-zinc-600"><?= nl2br(htmlspecialchars((string) $comment['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </details>
                  <form method="post" class="mt-3 flex gap-2">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <input type="text" name="comment" required class="flex-1 rounded-lg border border-zinc-300 px-3 py-1.5 text-sm" placeholder="Add a comment...">
                    <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white">Post</button>
                  </form>
                </div>

                <div class="mt-3 rounded-lg border border-zinc-200 bg-white p-3">
                  <h4 class="text-sm font-semibold text-zinc-700">Recent Activity</h4>
                  <div class="mt-2 space-y-1">
                    <?php foreach (listActivitiesForTicket((int) $ticket['id']) as $activity): ?>
                      <p class="text-xs text-zinc-600">
                        <span class="font-semibold"><?= htmlspecialchars((string) $activity['actorName'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?= htmlspecialchars((string) $activity['message'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-zinc-400">(<?= htmlspecialchars(date('M j g:i A', strtotime((string) $activity['createdAt'])), ENT_QUOTES, 'UTF-8') ?>)</span>
                      </p>
                    <?php endforeach; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </section>
  </main>
</body>
</html>

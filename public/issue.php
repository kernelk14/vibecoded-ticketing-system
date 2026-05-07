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

$ticketId = (int) ($_GET['id'] ?? 0);
if ($ticketId <= 0) {
    header('Location: /');
    exit;
}

$ticketCheck = getTicketById($ticketId);
if ($ticketCheck === null) {
    header('Location: /');
    exit;
}

$canView = isAdmin($user) || (int) $ticketCheck['userId'] === (int) $user['id'] || (int) ($ticketCheck['assigneeUserId'] ?? 0) === (int) $user['id'];
if (!$canView) {
    header('Location: /');
    exit;
}

$unreadNotificationCount = 0;
$showAllComments = isset($_GET['show_comments']) && $_GET['show_comments'] == '1';
$hasMoreComments = false;
$totalComments = 0;
$visibleComments = [];

$adminNotifications = listUserNotifications($user);
$unreadNotificationCount = unreadNotificationCount($user);

$error = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_details') {
            updateTicketDetailsForAdmin($user, $ticketId, $_POST);
        }
        if ($action === 'add_comment') {
            $isInternal = isAdmin($user) && !empty($_POST['is_internal']);
            addCommentToTicket($user, $ticketId, (string) ($_POST['comment'] ?? ''), $isInternal);
            header('Location: /issue.php?id=' . $ticketId . '&show_comments=1');
            exit;
        }
        if ($action === 'change_status') {
            $newStatus = (string) ($_POST['status'] ?? 'OPEN');
            updateTicketStatusForUser($user, $ticketId, $newStatus);
        }
        if ($action === 'delete_ticket') {
            deleteTicket($ticketId);
            header('Location: /');
            exit;
        }
        if ($action === 'mark_notifications_read') {
            markNotificationsRead($user);
        }
        if ($action === 'request_review') {
            requestTicketReview($user, $ticketId);
        }
        if ($action === 'upload_attachment') {
            if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                $filename = uniqid('attach_') . '_' . basename($file['name']);
                $target = __DIR__ . '/uploads/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    saveAttachment($user, $ticketId, $filename, basename($file['name']), $file['type'], (int) $file['size']);
                }
            }
            header('Location: /issue.php?id=' . $ticketId);
            exit;
        }
        if ($action === 'delete_attachment') {
            $attachId = (int) ($_POST['attachment_id'] ?? 0);
            deleteAttachment($attachId);
            header('Location: /issue.php?id=' . $ticketId);
            exit;
        }
        if ($action === 'add_watcher') {
            $watcherId = (int) ($_POST['watcher_id'] ?? 0);
            if ($watcherId > 0) {
                addWatcher($ticketId, $watcherId);
            }
            header('Location: /issue.php?id=' . $ticketId);
            exit;
        }
        if ($action === 'remove_watcher') {
            $watcherId = (int) ($_POST['watcher_id'] ?? 0);
            if ($watcherId > 0) {
                removeWatcher($ticketId, $watcherId);
            }
            header('Location: /issue.php?id=' . $ticketId);
            exit;
        }
        header('Location: /issue.php?id=' . $ticketId);
        exit;
    }

    $ticket = getTicketById($ticketId);
    if ($ticket === null) {
        header('Location: /');
        exit;
    }
    $assignableUsers = listAssignableUsers();
    $projects = listProjects();
    $branches = listBranches();
    $comments = listCommentsForTicket($ticketId, $user);
    $activities = listActivitiesForTicket($ticketId);
    $attachments = listAttachmentsForTicket($ticketId);
    $watchers = listWatchersForTicket($ticketId);
    $comments = array_reverse($comments);
    $totalComments = count($comments);

    if ($showAllComments || $totalComments <= 3) {
        $visibleComments = $comments;
        $hasMoreComments = false;
    } else {
        $visibleComments = array_slice($comments, 0, 3);
        $hasMoreComments = $totalComments > 3;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    $ticket = null;
    $assignableUsers = [];
    $comments = [];
    $activities = [];
    $hasMoreComments = false;
    $totalComments = 0;
    $visibleComments = [];
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

function getCommentAccentColor(int $index): array
{
    $colors = [
        ['border' => '#6366f1', 'bg' => '#eef2ff', 'ring' => '#818cf8'],
        ['border' => '#10b981', 'bg' => '#ecfdf5', 'ring' => '#34d399'],
        ['border' => '#f59e0b', 'bg' => '#fffbeb', 'ring' => '#fbbf24'],
        ['border' => '#f43f5e', 'bg' => '#fff1f2', 'ring' => '#fb7185'],
        ['border' => '#8b5cf6', 'bg' => '#f5f3ff', 'ring' => '#a78bfa'],
        ['border' => '#06b6d4', 'bg' => '#ecfeff', 'ring' => '#22d3ee'],
    ];
    return $colors[$index % 6];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Issue #<?= $ticketId ?> - Ticketing System</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    @keyframes highlight {
      0%, 100% { box-shadow: 0 0 0 0 rgba(253, 224, 71, 0); }
      50% { box-shadow: 0 0 0 4px rgba(253, 224, 71, 0.6); }
    }
    .highlight-comment { animation: highlight 1s ease-in-out 3; }
  </style>
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
          <p class="text-xs text-zinc-500">Issue Management</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a id="admin-alert-chip" href="/#notifications-section" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-200">
          Alerts
          <span id="admin-alert-count" class="rounded-full bg-amber-200 px-1.5 py-0.5"><?= $unreadNotificationCount ?></span>
        </a>
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
    <?php if ($error !== null): ?>
      <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="flex gap-6">
      <aside class="sticky top-20 h-fit w-60 flex-none rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <p class="px-2 text-xs font-semibold uppercase tracking-wider text-zinc-500">Navigation</p>
        <nav class="mt-3 space-y-1">
          <a href="/" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-indigo-50 text-indigo-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3z"/></svg>
            </span>
            Dashboard
          </a>
          <a href="/?status=OPEN" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-blue-50 text-blue-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.5-5.5l-1.5 1.5M7 17l-1.5 1.5M17 17l1.5 1.5M7 7l-1.5-1.5"/></svg>
            </span>
            Open Tickets
          </a>
          <a href="/?status=TRIAGED" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-cyan-50 text-cyan-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            </span>
            Triaged
          </a>
          <a href="/?status=IN_PROGRESS" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-amber-50 text-amber-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
            </span>
            In Progress
          </a>
          <a href="/?status=IN_REVIEW" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-violet-50 text-violet-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
            In Review
          </a>
          <a href="/?status=TESTING" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-orange-50 text-orange-700">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 6H7l1-6z"/><path d="M7 8h10v11H7z"/><path d="M10 13h4"/></svg>
            </span>
            Testing
          </a>
          <?php if (isAdmin($user)): ?>
            <a href="/?status=DONE" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">
              <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
              </span>
              Done
            </a>
          <?php endif; ?>
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
        <?php if ($ticket !== null): ?>
          <header class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="flex items-center gap-3">
                  <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700"><?= isAdmin($user) ? 'Admin View' : 'User View' ?></span>
                </div>
                <h1 class="mt-2 text-xl font-bold tracking-tight">Issue #<?= (int) $ticket['id'] ?></h1>
                <p class="mt-1 text-lg text-zinc-600"><?= htmlspecialchars((string) $ticket['title'], ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="flex flex-wrap gap-2">
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-inset <?= statusBadge($ticket['status']) ?>"><?= htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-inset <?= priorityBadge($ticket['priority']) ?>"><?= htmlspecialchars($ticket['priority'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-red-700 ring-1 ring-inset ring-red-200"><?= htmlspecialchars((string) $ticket['severity'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php $isAssignee = !empty($ticket['assigneeUserId']) && (int) $ticket['assigneeUserId'] === (int) $user['id']; ?>
                <?php if ($isAssignee && $ticket['status'] !== 'IN_REVIEW' && $ticket['status'] !== 'DONE'): ?>
                <form method="post">
                  <input type="hidden" name="action" value="request_review">
                  <button class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-violet-700 ring-1 ring-inset ring-violet-200 hover:bg-violet-200">For Review</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
          </header>

          <div style="display:flex;flex-wrap:wrap;gap:24px;">
            <?php if (isAdmin($user)): ?>
<div style="flex:2;min-width:300px;">
              <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-zinc-700">Description</h2>
                <p class="mt-2 text-sm text-zinc-600 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php if (!empty($ticket['expectedBehavior'])): ?>
                <h2 class="mt-4 text-sm font-semibold text-zinc-700">Expected Behavior</h2>
                <p class="mt-1 text-sm text-zinc-600 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['expectedBehavior'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <?php if (!empty($ticket['actualBehavior'])): ?>
                <h2 class="mt-4 text-sm font-semibold text-zinc-700">Actual Behavior</h2>
                <p class="mt-1 text-sm text-zinc-600 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['actualBehavior'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <?php if (!empty($ticket['stepsToReproduce'])): ?>
                <h2 class="mt-4 text-sm font-semibold text-zinc-700">Steps to Reproduce</h2>
                <p class="mt-1 text-sm text-zinc-600 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['stepsToReproduce'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <?php if (!empty($ticket['errorLog'])): ?>
                <h2 class="mt-4 text-sm font-semibold text-zinc-700">Error Log / Stack Trace</h2>
                <p class="mt-1 text-sm text-zinc-600 font-mono whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['errorLog'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <div class="mt-4 grid gap-3 md:grid-cols-2 text-sm">
                  <div><span class="text-zinc-500">Status:</span> <span class="font-medium"><?= htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8') ?></span></div>
                  <div><span class="text-zinc-500">Priority:</span> <span class="font-medium"><?= htmlspecialchars($ticket['priority'], ENT_QUOTES, 'UTF-8') ?></span></div>
                  <div><span class="text-zinc-500">Assignee:</span> <span class="font-medium"><?= htmlspecialchars((string) ($ticket['assigneeName'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8') ?></span></div>
                  <div><span class="text-zinc-500">Due Date:</span> <span class="font-medium"><?= !empty($ticket['dueAt']) ? htmlspecialchars(date('M j, Y g:i A', strtotime((string) $ticket['dueAt'])), ENT_QUOTES, 'UTF-8') : 'Not set' ?></span></div>
                </div>
              </div>

              <details class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm mt-4" open>
                <summary class="cursor-pointer text-sm font-semibold text-indigo-600">Admin Edit</summary>
                <form method="post" class="space-y-4 mt-4">
                  <input type="hidden" name="action" value="save_details">
                  <div class="grid gap-3 md:grid-cols-2">
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Project</span>
                      <select name="project" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                        <?php foreach ($projects as $proj): ?>
                          <option value="<?= htmlspecialchars($proj['name'], ENT_QUOTES, 'UTF-8') ?>" <?= $ticket['project'] === $proj['name'] ? 'selected' : '' ?>><?= htmlspecialchars($proj['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Module</span>
                      <input name="module" value="<?= htmlspecialchars((string) ($ticket['module'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="Module">
                    </label>
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">App Version</span>
                      <input name="appVersion" value="<?= htmlspecialchars((string) ($ticket['appVersion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="App Version">
                    </label>
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Git Branch</span>
<select name="branch" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                          <option value="<?= htmlspecialchars($branch['branch'], ENT_QUOTES, 'UTF-8') ?>" <?= ($ticket['branch'] ?? '') === $branch['branch'] ? 'selected' : '' ?>><?= htmlspecialchars($branch['branch'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  </div>
                  <label class="block text-sm">
                    <span class="mb-1.5 block font-medium text-zinc-700">Description</span>
                    <textarea name="description" rows="4" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" required><?= htmlspecialchars((string) $ticket['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                  </label>
                  <div class="grid gap-3 md:grid-cols-2">
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Expected Behavior</span>
                      <textarea name="expectedBehavior" rows="2" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="Expected behavior"><?= htmlspecialchars((string) ($ticket['expectedBehavior'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Actual Behavior</span>
                      <textarea name="actualBehavior" rows="2" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="Actual behavior"><?= htmlspecialchars((string) ($ticket['actualBehavior'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                  </div>
                  <label class="block text-sm">
                    <span class="mb-1.5 block font-medium text-zinc-700">Steps to Reproduce</span>
                    <textarea name="stepsToReproduce" rows="3" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="Steps to reproduce"><?= htmlspecialchars((string) ($ticket['stepsToReproduce'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                  </label>
                  <label class="block text-sm">
                    <span class="mb-1.5 block font-medium text-zinc-700">Error Log / Stack Trace</span>
                    <textarea name="errorLog" rows="3" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 font-mono text-xs outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="Error log"><?= htmlspecialchars((string) ($ticket['errorLog'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                  </label>
                  <div class="grid gap-3 md:grid-cols-4">
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Status</span>
                      <select name="status" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                        <?php foreach (ALLOWED_STATUSES as $value): ?>
                          <option value="<?= $value ?>" <?= $ticket['status'] === $value ? 'selected' : '' ?>><?= $value ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Priority</span>
                      <select name="priority" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                        <?php foreach (ALLOWED_PRIORITIES as $value): ?>
                          <option value="<?= $value ?>" <?= $ticket['priority'] === $value ? 'selected' : '' ?>><?= $value ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Severity</span>
                      <select name="severity" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                        <?php foreach (ALLOWED_SEVERITIES as $value): ?>
                          <option value="<?= $value ?>" <?= $ticket['severity'] === $value ? 'selected' : '' ?>><?= $value ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Environment</span>
                      <select name="environment" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                        <?php foreach (ALLOWED_ENVIRONMENTS as $value): ?>
                          <option value="<?= $value ?>" <?= $ticket['environment'] === $value ? 'selected' : '' ?>><?= $value ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  </div>
                  <div class="grid gap-3 md:grid-cols-2">
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Assignee</span>
                      <select name="assigneeUserId" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                        <option value="0">Unassigned</option>
                        <?php foreach ($assignableUsers as $assignee): ?>
                          <option value="<?= (int) $assignee['id'] ?>" <?= (int) $ticket['assigneeUserId'] === (int) $assignee['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $assignee['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $assignee['role'], ENT_QUOTES, 'UTF-8') ?>)</option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="block text-sm">
                      <span class="mb-1.5 block font-medium text-zinc-700">Due Date</span>
                      <input type="datetime-local" name="dueAt" value="<?= !empty($ticket['dueAt']) ? htmlspecialchars(substr((string) $ticket['dueAt'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?>" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                    </label>
                  </div>
                  <label class="flex items-center gap-2 text-sm text-zinc-700">
                    <input type="checkbox" name="reproducible" value="1" <?= (int) $ticket['reproducible'] === 1 ? 'checked' : '' ?> class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-200">
                    Reproducible
                  </label>
                  <div class="flex flex-wrap gap-2 pt-2">
                    <button class="rounded-xl bg-indigo-600 px-4 py-2.5 font-medium text-white shadow-sm transition hover:bg-indigo-700">Save All Details</button>
                    <?php if ($ticket['status'] !== 'DONE'): ?>
                    <form method="post" class="inline">
                      <input type="hidden" name="action" value="change_status">
                      <input type="hidden" name="status" value="DONE">
                      <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2.5 font-medium text-white shadow-sm transition hover:bg-emerald-700">Close Ticket</button>
                    </form>
                    <?php else: ?>
                    <form method="post" class="inline">
                      <input type="hidden" name="action" value="change_status">
                      <input type="hidden" name="status" value="OPEN">
                      <button type="submit" class="rounded-xl bg-amber-600 px-4 py-2.5 font-medium text-white shadow-sm transition hover:bg-amber-700">Reopen Ticket</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this ticket? This cannot be undone.');">
                      <input type="hidden" name="action" value="delete_ticket">
                      <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2.5 font-medium text-white shadow-sm transition hover:bg-rose-700">Delete Ticket</button>
                    </form>
                  </div>
                </form>
              </details>
              </div>
            <?php else: ?>
            <div style="flex:2;min-width:300px;">
              <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-zinc-700">Description</h2>
                <p class="mt-2 text-sm text-zinc-600 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php if (!empty($ticket['expectedBehavior'])): ?>
                <h2 class="mt-4 text-sm font-semibold text-zinc-700">Expected Behavior</h2>
                <p class="mt-1 text-sm text-zinc-600 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['expectedBehavior'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <?php if (!empty($ticket['actualBehavior'])): ?>
                <h2 class="mt-4 text-sm font-semibold text-zinc-700">Actual Behavior</h2>
                <p class="mt-1 text-sm text-zinc-600 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['actualBehavior'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <?php if (!empty($ticket['stepsToReproduce'])): ?>
                <h2 class="mt-4 text-sm font-semibold text-zinc-700">Steps to Reproduce</h2>
                <p class="mt-1 text-sm text-zinc-600 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['stepsToReproduce'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <?php if (!empty($ticket['errorLog'])): ?>
                <h2 class="mt-4 text-sm font-semibold text-zinc-700">Error Log / Stack Trace</h2>
                <p class="mt-1 text-sm text-zinc-600 font-mono whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $ticket['errorLog'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <div class="mt-4 grid gap-3 md:grid-cols-2 text-sm">
                  <div><span class="text-zinc-500">Status:</span> <span class="font-medium"><?= htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8') ?></span></div>
                  <div><span class="text-zinc-500">Priority:</span> <span class="font-medium"><?= htmlspecialchars($ticket['priority'], ENT_QUOTES, 'UTF-8') ?></span></div>
                  <div><span class="text-zinc-500">Assignee:</span> <span class="font-medium"><?= htmlspecialchars((string) ($ticket['assigneeName'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8') ?></span></div>
                  <div><span class="text-zinc-500">Due Date:</span> <span class="font-medium"><?= !empty($ticket['dueAt']) ? htmlspecialchars(date('M j, Y g:i A', strtotime((string) $ticket['dueAt'])), ENT_QUOTES, 'UTF-8') : 'Not set' ?></span></div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <div style="flex:1;min-width:250px;">
              <div class="space-y-4">
              <section id="comments" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <details open>
                  <summary class="cursor-pointer flex items-center justify-between text-sm font-semibold text-zinc-700">
                    <span>Comments (latest <?= count($visibleComments) ?>)</span>
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600"><?= $totalComments ?></span>
                  </summary>
                  <div class="mt-3 space-y-2">
                    <?php if (empty($comments)): ?>
                      <p class="rounded-md bg-zinc-50 p-2 text-sm text-zinc-500">No comments yet.</p>
                    <?php else: ?>
                      <?php $commentIndex = 0; ?>
                      <?php foreach ($visibleComments as $comment): ?>
                        <?php $colors = getCommentAccentColor($commentIndex); ?>
                        <div id="comment-<?= (int) $comment['id'] ?>" class="rounded-lg border-l-4 p-3" style="border-left-color: <?= $colors['border']; ?>; background-color: <?= $colors['bg']; ?>; outline: 2px solid <?= $colors['ring']; ?>; outline-offset: -2px">
                          <p class="text-xs font-semibold text-zinc-700"><?= htmlspecialchars((string) $comment['author'], ENT_QUOTES, 'UTF-8') ?>
                            <span class="font-normal text-zinc-400">(<?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $comment['createdAt'])), ENT_QUOTES, 'UTF-8') ?>)</span>
                          </p>
                          <p class="mt-1 text-sm text-zinc-600"><?= nl2br(htmlspecialchars((string) $comment['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>
                        <?php $commentIndex++; ?>
                      <?php endforeach; ?>
                      <?php if ($hasMoreComments): ?>
                        <details class="mt-2 rounded-lg border border-zinc-200 bg-zinc-50 p-2">
                          <summary class="cursor-pointer text-xs font-medium text-indigo-600 hover:underline">Show <?= $totalComments - 3 ?> older comments</summary>
                          <div class="mt-2 space-y-2">
                            <?php $commentIndex = 3; ?>
                            <?php foreach (array_slice($comments, 3) as $comment): ?>
                              <?php $colors = getCommentAccentColor($commentIndex); ?>
                              <div id="comment-<?= (int) $comment['id'] ?>" class="rounded-lg border-l-4 p-3" style="border-left-color: <?= $colors['border']; ?>; background-color: <?= $colors['bg']; ?>; outline: 2px solid <?= $colors['ring']; ?>; outline-offset: -2px">
                                <p class="text-xs font-semibold text-zinc-700"><?= htmlspecialchars((string) $comment['author'], ENT_QUOTES, 'UTF-8') ?>
                                  <span class="font-normal text-zinc-400">(<?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $comment['createdAt'])), ENT_QUOTES, 'UTF-8') ?>)</span>
                                </p>
                                <p class="mt-1 text-sm text-zinc-600"><?= nl2br(htmlspecialchars((string) $comment['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                              </div>
                              <?php $commentIndex++; ?>
                            <?php endforeach; ?>
                          </div>
                        </details>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </details>
                <form method="post" class="mt-3 space-y-2">
                  <input type="hidden" name="action" value="add_comment">
                  <textarea name="comment" required rows="3" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm" placeholder="Add comment..."></textarea>
                  <div class="flex items-center gap-4">
                    <?php if (isAdmin($user)): ?>
                    <label class="flex items-center gap-2 text-sm text-zinc-700">
                      <input type="checkbox" name="is_internal" value="1"> Internal (admin only)
                    </label>
                    <?php endif; ?>
                    <button class="rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white">Post</button>
                  </div>
                </form>
              </section>

              <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-zinc-700">Attachments</h2>
                <div class="mt-3 space-y-2">
                  <?php if (empty($attachments)): ?>
                    <p class="text-sm text-zinc-500">No attachments yet.</p>
                  <?php else: ?>
                    <?php foreach ($attachments as $attach): ?>
                      <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 p-2">
                        <a href="/uploads/<?= htmlspecialchars($attach['filename'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="text-sm text-indigo-600 hover:underline"><?= htmlspecialchars($attach['originalName'], ENT_QUOTES, 'UTF-8') ?></a>
                        <span class="text-xs text-zinc-500"><?= htmlspecialchars(formatBytes($attach['fileSize']), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (isAdmin($user)): ?>
                        <form method="post">
                          <input type="hidden" name="action" value="delete_attachment">
                          <input type="hidden" name="attachment_id" value="<?= (int) $attach['id'] ?>">
                          <button class="text-xs text-red-600 hover:underline">Delete</button>
                        </form>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <?php if (isAdmin($user)): ?>
                  <form method="post" enctype="multipart/form-data" class="mt-2">
                    <input type="hidden" name="action" value="upload_attachment">
                    <input type="file" name="attachment" class="text-sm">
                    <button class="rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white ml-2">Upload</button>
                  </form>
                  <?php endif; ?>
                </div>
              </section>

              <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-zinc-700">Watchers</h2>
                <div class="mt-3 space-y-2">
                  <?php if (empty($watchers)): ?>
                    <p class="text-sm text-zinc-500">No watchers yet.</p>
                  <?php else: ?>
                    <?php foreach ($watchers as $w): ?>
                      <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 p-2">
                        <span class="text-sm"><?= htmlspecialchars($w['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (isAdmin($user)): ?>
                        <form method="post">
                          <input type="hidden" name="action" value="remove_watcher">
                          <input type="hidden" name="watcher_id" value="<?= (int) $w['userId'] ?>">
                          <button class="text-xs text-red-600 hover:underline">Remove</button>
                        </form>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <?php if (isAdmin($user)): ?>
                  <form method="post" class="mt-2 flex gap-2">
                    <input type="hidden" name="action" value="add_watcher">
                    <select name="watcher_id" class="text-sm rounded-lg border border-zinc-300">
                      <option value="">Add watcher...</option>
                      <?php foreach ($assignableUsers as $u): ?>
                        <?php $isWatcher = in_array($u['id'], array_column($watchers, 'userId')); ?>
                        <?php if (!$isWatcher && (int) $u['id'] !== (int) ($ticket['assigneeUserId'] ?? 0)): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </select>
                    <button class="rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white">Add</button>
                  </form>
                  <?php endif; ?>
                </div>
              </section>

              <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-zinc-700">Activity</h2>
                <div class="mt-3 space-y-2">
                  <?php if (empty($activities)): ?>
                    <p class="text-sm text-zinc-500">No activity yet.</p>
                  <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                      <p class="text-xs text-zinc-600">
                        <span class="font-semibold"><?= htmlspecialchars((string) $activity['actorName'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?= htmlspecialchars((string) $activity['message'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-zinc-400">(<?= htmlspecialchars(date('M j g:i A', strtotime((string) $activity['createdAt'])), ENT_QUOTES, 'UTF-8') ?>)</span>
                      </p>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </section>

              <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-zinc-700">Details</h2>
                <div class="mt-3 space-y-2 text-xs">
                  <p><span class="font-medium text-zinc-500">Author:</span> <span class="text-zinc-800"><?= htmlspecialchars((string) $ticket['author'], ENT_QUOTES, 'UTF-8') ?></span></p>
                  <p><span class="font-medium text-zinc-500">Created:</span> <span class="text-zinc-800"><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $ticket['createdAt'])), ENT_QUOTES, 'UTF-8') ?></span></p>
                  <p><span class="font-medium text-zinc-500">Updated:</span> <span class="text-zinc-800"><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $ticket['updatedAt'])), ENT_QUOTES, 'UTF-8') ?></span></p>
                </div>
              </section>
            </div>
          </div>
          </div>
        <?php else: ?>
          <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">Ticket not found.</div>
        <?php endif; ?>
      </div>
    </section>
  </main>
<script>
  if (window.location.hash === '#comments' || window.location.hash.startsWith('#comment-')) {
    const hash = window.location.hash;
    let target = null;
    if (hash.startsWith('#comment-')) {
      target = document.querySelector(hash);
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('highlight-comment');
        setTimeout(() => target.classList.remove('highlight-comment'), 3000);
      }
    }
    if (!target) {
      const comments = document.getElementById('comments');
      if (comments) {
        comments.scrollIntoView({ behavior: 'smooth', block: 'center' });
        comments.classList.add('highlight-comment');
        setTimeout(() => comments.classList.remove('highlight-comment'), 3000);
      }
    }
  }
  if (window.location.search.includes('refresh=1')) {
    const url = new URL(window.location.href);
    url.searchParams.delete('refresh');
    window.history.replaceState({}, '', url);
  }
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
      setTimeout(() => location.reload(), 100);
    });
  });
</script>
<script>
  const toastHost = document.createElement("div");
  toastHost.id = "admin-toast-host";
  toastHost.className = "fixed right-4 top-20 z-50 flex w-full max-w-sm flex-col gap-2";
  document.body.appendChild(toastHost);
  const seenNotificationIds = new Set();
  let firstFetchComplete = false;

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function showToast(message, ticketId, commentId) {
    if (!toastHost) return;
    const toast = document.createElement("div");
    toast.className = "rounded-xl border border-indigo-200 bg-white px-4 py-3 shadow-lg";
    const commentHash = commentId ? `#comment-${Number(commentId)}` : (message.toLowerCase().includes('comment') ? '#comments' : '');
    const link = ticketId ? `/issue.php?id=${Number(ticketId)}${commentHash}&refresh=1` : "#";
    const content = `
      <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Notification</p>
      <p class="mt-1 text-sm font-medium text-zinc-800">${escapeHtml(message)}</p>
    `;
    toast.innerHTML = ticketId ? `<a href="${link}" class="block">${content}</a>` : content;
    toastHost.appendChild(toast);

    setTimeout(() => {
      toast.classList.add("opacity-0", "translate-x-2", "transition");
      setTimeout(() => toast.remove(), 250);
    }, 4500);
  }

  function maybeToastNewNotifications(data) {
    if (!Array.isArray(data.notifications)) return;

    for (const item of data.notifications) {
      if (!seenNotificationIds.has(item.id)) {
        if (firstFetchComplete) {
          showToast(item.message, item.ticketId, item.commentId);
        }
        seenNotificationIds.add(item.id);
      }
    }
    firstFetchComplete = true;
  }

  async function fetchNotifications() {
    try {
      const response = await fetch("/?ajax_notifications=1", { headers: { "X-Requested-With": "XMLHttpRequest" } });
      if (!response.ok) return;
      const data = await response.json();
      maybeToastNewNotifications(data);
    } catch (error) {}
  }

  fetchNotifications();
  setInterval(fetchNotifications, 5000);
</script>
</body>
</html>
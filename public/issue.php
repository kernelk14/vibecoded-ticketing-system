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

$ticketId = (int) ($_GET['id'] ?? 0);
if ($ticketId <= 0) {
    header('Location: /');
    exit;
}

$error = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_details') {
            updateTicketDetailsForAdmin($user, $ticketId, $_POST);
        }
        if ($action === 'add_comment') {
            addCommentToTicket($user, $ticketId, (string) ($_POST['comment'] ?? ''));
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
    $comments = listCommentsForTicket($ticketId);
    $activities = listActivitiesForTicket($ticketId);
} catch (Throwable $e) {
    $error = $e->getMessage();
    $ticket = null;
    $assignableUsers = [];
    $comments = [];
    $activities = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Issue Details</title>
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
          <p class="text-xs text-zinc-500">Issue Management</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span class="inline-flex items-center gap-2 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">
          <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
          Admin Issue Page
        </span>
        <a href="/" class="inline-flex items-center gap-2 rounded-lg bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200">
          <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
          Dashboard
        </a>
      </div>
    </div>
  </nav>

  <main class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8">
    <section class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
      <aside class="sticky top-20 self-start rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
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

        <div class="mt-6 rounded-xl bg-zinc-50 p-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Account</p>
          <p class="mt-2 text-sm font-medium text-zinc-800"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></p>
          <p class="text-xs text-zinc-500"><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </aside>

      <div class="space-y-6">
        <div class="mb-4 flex items-center justify-between gap-3">
          <a href="/" class="inline-flex items-center gap-2 text-sm font-medium text-indigo-700 transition hover:text-indigo-900">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            Back to dashboard
          </a>
          <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">Admin Issue Page</span>
        </div>

        <?php if ($error !== null): ?>
      <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($ticket !== null): ?>
      <section class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm lg:col-span-2">
          <h1 class="text-xl font-semibold">Issue #<?= (int) $ticket['id'] ?> - <?= htmlspecialchars((string) $ticket['title'], ENT_QUOTES, 'UTF-8') ?></h1>
          <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="save_details">
            <div class="grid gap-3 md:grid-cols-2">
              <input name="title" value="<?= htmlspecialchars((string) $ticket['title'], ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" placeholder="Title" required>
              <input name="project" value="<?= htmlspecialchars((string) $ticket['project'], ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" placeholder="Project">
              <input name="module" value="<?= htmlspecialchars((string) ($ticket['module'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" placeholder="Module">
              <input name="appVersion" value="<?= htmlspecialchars((string) ($ticket['appVersion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" placeholder="App Version">
              <input name="branch" value="<?= htmlspecialchars((string) ($ticket['branch'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" placeholder="Branch">
              <input type="datetime-local" name="dueAt" value="<?= !empty($ticket['dueAt']) ? htmlspecialchars(substr((string) $ticket['dueAt'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?>" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm">
            </div>
            <textarea name="description" rows="4" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" required><?= htmlspecialchars((string) $ticket['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="grid gap-3 md:grid-cols-2">
              <textarea name="expectedBehavior" rows="2" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" placeholder="Expected behavior"><?= htmlspecialchars((string) ($ticket['expectedBehavior'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
              <textarea name="actualBehavior" rows="2" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" placeholder="Actual behavior"><?= htmlspecialchars((string) ($ticket['actualBehavior'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
              <textarea name="stepsToReproduce" rows="3" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" placeholder="Steps"><?= htmlspecialchars((string) ($ticket['stepsToReproduce'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
              <textarea name="errorLog" rows="3" class="rounded-lg border border-zinc-300 px-3 py-2 font-mono text-xs" placeholder="Error log"><?= htmlspecialchars((string) ($ticket['errorLog'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="grid gap-3 md:grid-cols-4">
              <select name="status" class="rounded-lg border border-zinc-300 px-2 py-2 text-sm"><?php foreach (ALLOWED_STATUSES as $value): ?><option value="<?= $value ?>" <?= $ticket['status'] === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select>
              <select name="priority" class="rounded-lg border border-zinc-300 px-2 py-2 text-sm"><?php foreach (ALLOWED_PRIORITIES as $value): ?><option value="<?= $value ?>" <?= $ticket['priority'] === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select>
              <select name="severity" class="rounded-lg border border-zinc-300 px-2 py-2 text-sm"><?php foreach (ALLOWED_SEVERITIES as $value): ?><option value="<?= $value ?>" <?= $ticket['severity'] === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select>
              <select name="environment" class="rounded-lg border border-zinc-300 px-2 py-2 text-sm"><?php foreach (ALLOWED_ENVIRONMENTS as $value): ?><option value="<?= $value ?>" <?= $ticket['environment'] === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
              <select name="assigneeUserId" class="rounded-lg border border-zinc-300 px-2 py-2 text-sm">
                <option value="0">Unassigned</option>
                <?php foreach ($assignableUsers as $assignee): ?>
                  <option value="<?= (int) $assignee['id'] ?>" <?= (int) $ticket['assigneeUserId'] === (int) $assignee['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $assignee['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $assignee['role'], ENT_QUOTES, 'UTF-8') ?>)</option>
                <?php endforeach; ?>
              </select>
              <label class="flex items-center gap-2 text-sm text-zinc-700"><input type="checkbox" name="reproducible" value="1" <?= (int) $ticket['reproducible'] === 1 ? 'checked' : '' ?>> Reproducible</label>
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save All Details</button>
          </form>
        </div>

        <div class="space-y-4">
          <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold">Comments</h2>
            <div class="mt-2 space-y-2">
              <?php foreach ($comments as $comment): ?>
                <div class="rounded-md bg-zinc-50 p-2">
                  <p class="text-xs font-semibold text-zinc-700"><?= htmlspecialchars((string) $comment['author'], ENT_QUOTES, 'UTF-8') ?></p>
                  <p class="text-sm text-zinc-600"><?= nl2br(htmlspecialchars((string) $comment['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
            <form method="post" class="mt-3 space-y-2">
              <input type="hidden" name="action" value="add_comment">
              <textarea name="comment" required rows="3" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" placeholder="Add admin comment..."></textarea>
              <button class="rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white">Post Comment</button>
            </form>
          </section>

          <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold">Activity</h2>
            <div class="mt-2 space-y-1">
              <?php foreach ($activities as $activity): ?>
                <p class="text-xs text-zinc-600"><span class="font-semibold"><?= htmlspecialchars((string) $activity['actorName'], ENT_QUOTES, 'UTF-8') ?></span> <?= htmlspecialchars((string) $activity['message'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endforeach; ?>
            </div>
          </section>
        </div>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>

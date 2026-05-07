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

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($title !== '' && $description !== '') {
            createTicket($user, $_POST);
            $success = 'Ticket created successfully!';
        } else {
            $error = 'Title and description are required.';
        }
    }
}

$projects = listProjects();
$branches = listBranches();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Ticket - Ticketing System</title>
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
          <p class="text-xs text-zinc-500">Create New Ticket</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <?php if ($user !== null): ?>
          <a id="admin-alert-chip" href="/#notifications-section" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-200">
            Alerts
            <span id="admin-alert-count" class="rounded-full bg-amber-200 px-1.5 py-0.5">0</span>
          </a>
          <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-zinc-700"><?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="hidden text-sm text-zinc-600 md:inline"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></span>
          <form method="post">
            <input type="hidden" name="action" value="logout">
            <button class="rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-zinc-900">Logout</button>
          </form>
        <?php else: ?>
          <span class="text-sm text-zinc-600">Sign in required</span>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <main class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8">
    <?php if ($success !== null): ?>
      <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
      <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="mb-4">
      <button onclick="window.location.href='/'" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline">Go back to Dashboard</button>
    </div>

    <section class="space-y-6">
      <div class="border-b border-zinc-200 pb-3">
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900">Create New Ticket</h1>
        <p class="text-sm text-zinc-500">Submit a clear issue report for faster triage.</p>
      </div>

      <form method="post" class="space-y-6">
        <input type="hidden" name="action" value="create">

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm">
              <span class="mb-1.5 block font-medium text-zinc-700">Project</span>
              <select name="project" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                <option value="">Select Project</option>
                <?php foreach ($projects as $proj): ?>
                  <option value="<?= htmlspecialchars($proj['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($proj['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div>
            <label class="block text-sm">
              <span class="mb-1.5 block font-medium text-zinc-700">Title</span>
              <input type="text" name="title" required class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="e.g. Login page throws 500">
            </label>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm">
              <span class="mb-1.5 block font-medium text-zinc-700">Description</span>
              <textarea name="description" required rows="6" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="Describe the issue..."></textarea>
            </label>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-sm">
                <span class="mb-1.5 block font-medium text-zinc-700">Priority</span>
                <select name="priority" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                  <?php foreach (ALLOWED_PRIORITIES as $priority): ?>
                    <option value="<?= $priority ?>"><?= htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <div>
              <label class="block text-sm">
                <span class="mb-1.5 block font-medium text-zinc-700">Severity</span>
                <select name="severity" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                  <?php foreach (ALLOWED_SEVERITIES as $severity): ?>
                    <option value="<?= $severity ?>"><?= htmlspecialchars($severity, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-sm">
                <span class="mb-1.5 block font-medium text-zinc-700">Environment</span>
                <select name="environment" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                  <?php foreach (ALLOWED_ENVIRONMENTS as $env): ?>
                    <option value="<?= $env ?>"><?= htmlspecialchars($env, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <div>
              <label class="block text-sm">
                <span class="mb-1.5 block font-medium text-zinc-700">Module</span>
                <input type="text" name="module" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="e.g. Auth service">
              </label>
            </div>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-sm">
                <span class="mb-1.5 block font-medium text-zinc-700">App Version</span>
                <input type="text" name="appVersion" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="e.g. v1.24.0">
              </label>
            </div>
            <div>
              <label class="block text-sm">
                <span class="mb-1.5 block font-medium text-zinc-700">Git Branch</span>
                <select name="branch" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                  <option value="">Select Branch</option>
                  <?php foreach ($branches as $branch): ?>
                    <option value="<?= htmlspecialchars($branch['branch'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($branch['branch'], ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm">
              <span class="mb-1.5 block font-medium text-zinc-700">Expected Behavior</span>
              <textarea name="expectedBehavior" rows="2" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="What should happen..."></textarea>
            </label>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm">
              <span class="mb-1.5 block font-medium text-zinc-700">Actual Behavior</span>
              <textarea name="actualBehavior" rows="2" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="What actually happened..."></textarea>
            </label>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm">
              <span class="mb-1.5 block font-medium text-zinc-700">Steps to Reproduce</span>
              <textarea name="stepsToReproduce" rows="3" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="Step-by-step instructions..."></textarea>
            </label>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm">
              <span class="mb-1.5 block font-medium text-zinc-700">Error Log / Stack Trace</span>
              <textarea name="errorLog" rows="3" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 font-mono text-xs outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="Paste error logs or stack traces..."></textarea>
            </label>
          </div>
          <div class="flex items-center gap-2 text-sm text-zinc-700">
            <input type="checkbox" name="reproducible" value="1" checked class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-200">
            Reproducible
          </div>
        </div>

        <div class="pt-4 border-t border-zinc-200">
          <button class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 font-medium text-white shadow-sm transition hover:bg-indigo-700">
            Create Ticket
          </button>
        </div>
      </form>
    </section>
  </main>
<script>
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') window.location.href = '/';
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
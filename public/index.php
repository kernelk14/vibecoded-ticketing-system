<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/tickets.php';

$error = null;
$loginError = null;
$selectedStatus = $_GET['status'] ?? null;
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$selectedProject = trim((string) ($_GET['project'] ?? ''));
$selectedPriority = $_GET['priority'] ?? null;
$selectedSeverity = $_GET['severity'] ?? null;
$selectedAssigneeId = (int) ($_GET['assigneeUserId'] ?? 0);
startSession();
$user = currentUser();
$adminNotifications = [];
$unreadNotificationCount = 0;
$assignableUsers = [];

if ($user !== null && isset($_GET['ajax_notifications'])) {
    header('Content-Type: application/json');
    $notifications = listUserNotifications($user);
    $unread = unreadNotificationCount($user);
    $payload = array_map(static function (array $notification): array {
        return [
            'id' => (int) $notification['id'],
            'ticketId' => isset($notification['ticketId']) ? (int) $notification['ticketId'] : null,
            'commentId' => isset($notification['commentId']) ? (int) $notification['commentId'] : null,
            'message' => (string) $notification['message'],
            'isRead' => (int) $notification['isRead'] === 1,
            'createdAt' => (string) $notification['createdAt'],
            'createdAtLabel' => date('M j, Y g:i A', strtotime((string) $notification['createdAt'])),
        ];
    }, $notifications);

    echo json_encode([
        'unreadCount' => $unread,
        'notifications' => $payload,
    ], JSON_THROW_ON_ERROR);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'login') {
            $email = (string) ($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            if (!loginUser($email, $password)) {
                $loginError = 'Invalid email or password.';
            } else {
                header('Location: /');
                exit;
            }
        }

        if ($action === 'logout') {
            logoutUser();
            header('Location: /');
            exit;
        }

        if ($user !== null && $action === 'create') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($title !== '' && $description !== '') {
                createTicket($user, $_POST);
            }
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

        if ($user !== null && $action === 'mark_notifications_read') {
            markNotificationsRead($user);
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
        header('Location: /' . $query);
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

    $tickets = $user ? listTickets($user, $filters) : [];
    if ($user !== null) {
        $adminNotifications = listUserNotifications($user);
        $unreadNotificationCount = unreadNotificationCount($user);
        $assignableUsers = listAssignableUsers();
    }
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

$statusCounts = array_fill_keys(ALLOWED_STATUSES, 0);
foreach ($tickets as $ticket) {
    $currentStatus = (string) ($ticket['status'] ?? '');
    if (array_key_exists($currentStatus, $statusCounts)) {
        $statusCounts[$currentStatus]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PHP Ticketing System</title>
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
          <p class="text-xs text-zinc-500">Enterprise Support Desk</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <?php if ($user !== null): ?>
          <a id="admin-alert-chip" href="#notifications-section" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-200">
            Alerts
            <span id="admin-alert-count" class="rounded-full bg-amber-200 px-1.5 py-0.5"><?= $unreadNotificationCount ?></span>
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
    <?php if ($user === null): ?>
      <section class="mx-auto grid max-w-5xl overflow-hidden rounded-3xl border border-zinc-200/80 bg-white shadow-2xl shadow-indigo-900/10 lg:grid-cols-2">
        <div class="relative hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-700 p-8 text-white lg:block">
          <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-white/10"></div>
          <div class="absolute -bottom-14 -left-14 h-40 w-40 rounded-full bg-violet-300/20"></div>
          <p class="relative inline-flex rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-widest">Support Portal</p>
          <h1 class="relative mt-6 text-3xl font-bold leading-tight">Professional ticket management for your team</h1>
          <p class="relative mt-4 text-sm text-indigo-100">Stay on top of incidents with role-based access, clear priorities, and streamlined workflows.</p>
        </div>
        <div class="p-6 sm:p-8">
          <h1 class="text-2xl font-bold tracking-tight text-zinc-900">Sign in to Dashboard</h1>
          <p class="mt-2 text-sm text-zinc-600">Use seeded accounts: `admin@local.test` / `admin123` or `user@local.test` / `user123`.</p>

        <?php if ($loginError !== null): ?>
          <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" class="mt-6 space-y-4">
          <input type="hidden" name="action" value="login">
          <label class="block text-sm">
            <span class="mb-1.5 block font-medium text-zinc-700">Email</span>
            <input name="email" type="email" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 focus:border-indigo-300 focus:outline-none focus:ring focus:ring-indigo-200">
          </label>
          <label class="block text-sm">
            <span class="mb-1.5 block font-medium text-zinc-700">Password</span>
            <input name="password" type="password" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 focus:border-indigo-300 focus:outline-none focus:ring focus:ring-indigo-200">
          </label>
          <button class="inline-flex w-full justify-center rounded-xl bg-indigo-600 px-4 py-2.5 font-medium text-white shadow-sm shadow-indigo-600/30 transition hover:-translate-y-0.5 hover:bg-indigo-700">Sign In</button>
        </form>
        </div>
      </section>
    <?php else: ?>
      <section class="flex gap-6">
        <aside class="sticky top-20 h-fit w-60 flex-none rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
          <p class="px-2 text-xs font-semibold uppercase tracking-wider text-zinc-500">Navigation</p>
          <nav class="mt-3 space-y-1">
            <a href="/" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition <?= $selectedStatus === null ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
              <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-700">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3z"/></svg>
              </span>
              Dashboard
            </a>
            <a href="/?status=OPEN" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition <?= $selectedStatus === 'OPEN' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
              <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.5-5.5l-1.5 1.5M7 17l-1.5 1.5M17 17l1.5 1.5M7 7l-1.5-1.5"/></svg>
              </span>
              Open Tickets
            </a>
            <a href="/?status=TRIAGED" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition <?= $selectedStatus === 'TRIAGED' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
              <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-cyan-50 text-cyan-700">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
              </span>
              Triaged
            </a>
            <a href="/?status=IN_PROGRESS" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition <?= $selectedStatus === 'IN_PROGRESS' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
              <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-amber-50 text-amber-700">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
              </span>
              In Progress
            </a>
            <a href="/?status=IN_REVIEW" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition <?= $selectedStatus === 'IN_REVIEW' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
              <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-violet-50 text-violet-700">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
              In Review
            </a>
            <a href="/?status=TESTING" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition <?= $selectedStatus === 'TESTING' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
              <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-orange-50 text-orange-700">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 6H7l1-6z"/><path d="M7 8h10v11H7z"/><path d="M10 13h4"/></svg>
              </span>
              Testing
            </a>
            <?php if (isAdmin($user)): ?>
              <a href="/?status=DONE" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition <?= $selectedStatus === 'DONE' ? 'bg-indigo-50 text-indigo-700' : 'text-zinc-700 hover:bg-zinc-100' ?>">
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

          <div class="mt-6 rounded-xl bg-zinc-50 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Account</p>
            <p class="mt-2 text-sm font-medium text-zinc-800"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs text-zinc-500"><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </aside>

        <div class="flex-1 space-y-6">
          <aside class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
          <section id="notifications-section" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h2 class="text-lg font-semibold tracking-tight">Notifications</h2>
                  <p class="text-sm text-zinc-500">
                    <?= isAdmin($user) ? 'New issues created by users appear here.' : 'Updates from admin on your submitted issues appear here.' ?>
                  </p>
                </div>
                <?php if ($unreadNotificationCount > 0): ?>
                  <form method="post" id="mark-read-form">
                    <input type="hidden" name="action" value="mark_notifications_read">
                    <button class="rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-900">
                      Mark all as read
                    </button>
                  </form>
                <?php endif; ?>
              </div>

              <div id="admin-notification-list" class="mt-4 space-y-2">
                <?php if ($adminNotifications === []): ?>
                  <p class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm text-zinc-500">No notifications yet.</p>
                <?php endif; ?>

                <?php foreach ($adminNotifications as $notification): ?>
                  <article class="rounded-xl border px-4 py-3 <?= (int) $notification['isRead'] === 0 ? 'border-amber-200 bg-amber-50/70' : 'border-zinc-200 bg-zinc-50' ?>">
                    <?php if (!empty($notification['ticketId'])): ?>
                      <?php $commentHash = !empty($notification['commentId']) ? '#comment-' . (int) $notification['commentId'] : (str_contains((string) $notification['message'], 'comment') ? '#comments' : ''); ?>
                      <a href="/issue.php?id=<?= (int) $notification['ticketId'] ?><?= $commentHash ?>" class="text-sm font-medium text-indigo-700 hover:underline">
                        <?= htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8') ?>
                      </a>
                    <?php else: ?>
                      <p class="text-sm font-medium text-zinc-800"><?= htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $notification['createdAt'])), ENT_QUOTES, 'UTF-8') ?></p>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>

          <header class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p class="inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700"><?= isAdmin($user) ? 'Admin Workspace' : 'User Workspace' ?></p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight"><?= isAdmin($user) ? 'Operations Dashboard' : 'My Support Dashboard' ?></h1>
              </div>
              <div class="rounded-xl border border-zinc-200 px-4 py-2 text-right">
                <p class="text-xs uppercase text-zinc-500">Visible Tickets</p>
                <p class="text-xl font-semibold"><?= count($tickets) ?></p>
              </div>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <?php foreach (ALLOWED_STATUSES as $status): ?>
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2">
                  <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500"><?= htmlspecialchars(str_replace('_', ' ', $status), ENT_QUOTES, 'UTF-8') ?></p>
                  <p class="mt-1 text-lg font-semibold text-zinc-900"><?= (int) $statusCounts[$status] ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </header>

          <?php if ($error !== null): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm xl:col-span-12">
              <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold tracking-tight"><?= isAdmin($user) ? 'All Tickets' : 'My Tickets' ?></h2>
                <div class="flex flex-wrap items-center gap-2">
                  <a href="/" class="rounded-full px-3 py-1.5 text-sm font-medium transition <?= $selectedStatus === null ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200' ?>">All</a>
                  <?php foreach (ALLOWED_STATUSES as $status): ?>
                    <a href="/?status=<?= urlencode($status) ?>" class="rounded-full px-3 py-1.5 text-sm font-medium transition <?= $selectedStatus === $status ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200' ?>">
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

                    <div class="mt-4 rounded-lg border border-zinc-200 bg-white p-3">
                      <h4 class="text-sm font-semibold text-zinc-700">Comments</h4>
                      <div class="mt-2 space-y-2">
                        <?php foreach (listCommentsForTicket((int) $ticket['id']) as $comment): ?>
                          <div class="rounded-md bg-zinc-50 p-2">
                            <p class="text-xs font-semibold text-zinc-700"><?= htmlspecialchars((string) $comment['author'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-sm text-zinc-600"><?= nl2br(htmlspecialchars((string) $comment['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                          </div>
                        <?php endforeach; ?>
                      </div>
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
            </div>
          </section>
        </div>
      </section>
    <?php endif; ?>
  </main>
</body>
<?php if ($user !== null): ?>
<script>
  const notifList = document.getElementById("admin-notification-list");
  const notifCount = document.getElementById("admin-alert-count");
  const notifChip = document.getElementById("admin-alert-chip");
  const markReadForm = document.getElementById("mark-read-form");
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

  function renderNotifications(data) {
    if (!notifList || !notifCount || !notifChip) return;

    notifCount.textContent = String(data.unreadCount);
    notifChip.style.display = data.unreadCount > 0 ? "inline-flex" : "none";

    if (markReadForm) {
      markReadForm.style.display = data.unreadCount > 0 ? "block" : "none";
    }

    if (!Array.isArray(data.notifications) || data.notifications.length === 0) {
      notifList.innerHTML = '<p class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm text-zinc-500">No notifications yet.</p>';
      return;
    }

    notifList.innerHTML = data.notifications.map((item) => {
      const wrapperClasses = item.isRead
        ? "rounded-xl border px-4 py-3 border-zinc-200 bg-zinc-50"
        : "rounded-xl border px-4 py-3 border-amber-200 bg-amber-50/70";
      const commentHash = item.commentId ? `#comment-${Number(item.commentId)}` : (item.message.toLowerCase().includes('comment') ? '#comments' : '');
      const message = item.ticketId
        ? `<a href="/issue.php?id=${Number(item.ticketId)}${commentHash}" class="text-sm font-medium text-indigo-700 hover:underline">${escapeHtml(item.message)}</a>`
        : `<p class="text-sm font-medium text-zinc-800">${escapeHtml(item.message)}</p>`;
      return `<article class="${wrapperClasses}">
        ${message}
        <p class="mt-1 text-xs text-zinc-500">${escapeHtml(item.createdAtLabel)}</p>
      </article>`;
    }).join("");
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
      renderNotifications(data);
    } catch (error) {
      // Keep UI stable if polling fails.
    }
  }

  fetchNotifications();
  setInterval(fetchNotifications, 5000);
</script>
<?php endif; ?>
</html>

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

if (!isAdmin($user)) {
    header('Location: /');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_project') {
        $project = trim((string) ($_POST['project'] ?? ''));
        $repoUrl = trim((string) ($_POST['repoUrl'] ?? ''));
        $branch = trim((string) ($_POST['branch'] ?? ''));
        if ($project !== '') {
            createProject($project, $repoUrl, $branch);
            $success = 'Project added successfully!';
        } else {
            $error = 'Project name is required.';
        }
    }
    if ($action === 'delete_project') {
        $project = trim((string) ($_POST['project'] ?? ''));
        if ($project !== '') {
            deleteProject($project);
            $success = 'Project deleted successfully!';
        }
    }
}

$projectList = db()->query("SELECT name FROM Project ORDER BY name ASC")->fetchAll();
if ($projectList === false) { $projectList = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projects - Ticketing System</title>
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
          <p class="text-xs text-zinc-500">Projects</p>
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
        <a href="/projects.php" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium bg-indigo-50 text-indigo-700">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-cyan-100 text-cyan-700">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3z"/><path d="M9 3v18"/></svg>
          </span>
          Projects
        </a>

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
        <?php if ($success !== null): ?>
          <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-xl font-bold tracking-tight">Projects</h1>
              <p class="mt-1 text-sm text-zinc-500">Manage your projects/repos</p>
            </div>
            <button onclick="document.getElementById('add-project-modal').showModal()" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">Add Project</button>
          </div>

          <dialog id="add-project-modal" class="rounded-2xl border border-zinc-200 p-6 shadow-xl backdrop:bg-black/50" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;max-width:90vw;">
            <form method="post" class="space-y-4">
              <input type="hidden" name="action" value="add_project">
              <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add Project</h2>
                <button type="button" onclick="document.getElementById('add-project-modal').close()" class="text-zinc-400 hover:text-zinc-600">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
              <div>
                <label class="block text-sm font-medium text-zinc-700">Project Name</label>
                <input type="text" name="project" required class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="Enter project name">
              </div>
              <div>
                <label class="block text-sm font-medium text-zinc-700">Git Repo Link (optional)</label>
                <input type="url" name="repoUrl" id="repo-url-input" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring" placeholder="https://github.com/username/repo" onchange="fetchBranches()">
                <p class="mt-1 text-xs text-zinc-500">Enter a GitHub repo URL to auto-fetch branches</p>
              </div>
              <div id="branch-field" class="hidden">
                <label class="block text-sm font-medium text-zinc-700">Branch</label>
                <select name="branch" id="branch-select" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none ring-indigo-200 transition focus:border-indigo-300 focus:ring">
                  <option value="">Select Branch</option>
                </select>
              </div>
              <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('add-project-modal').close()" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Cancel</button>
                <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Add Project</button>
              </div>
            </form>
          </dialog>
          <script>
            async function fetchBranches() {
              const url = document.getElementById('repo-url-input').value.trim();
              if (!url) return;
              
              const match = url.match(/github\.com[/:]([\w-]+)\/([\w-]+)/);
              if (!match) return;
              
              const owner = match[1];
              const repo = match[2];
              
              try {
                const response = await fetch('https://api.github.com/repos/' + owner + '/' + repo + '/branches');
                if (!response.ok) return;
                
                const branches = await response.json();
                if (!Array.isArray(branches)) return;
                
                const select = document.getElementById('branch-select');
                select.innerHTML = '<option value="">Select Branch</option>';
                branches.forEach(b => {
                  const opt = document.createElement('option');
                  opt.value = 'origin/' + b.name;
                  opt.textContent = b.name;
                  select.appendChild(opt);
                });
                
                document.getElementById('branch-field').classList.remove('hidden');
              } catch (e) {}
            }
          </script>

          <div class="mt-6 overflow-x-auto">
            <table class="w-full" style="border-collapse:collapse;">
                <thead>
                <tr style="border-bottom:1px solid #e5e5e5;">
                  <th style="padding:12px 16px;text-align:left;font-size:14px;font-weight:600;color:#71717a;">Project</th>
                  <th style="padding:12px 16px;text-align:right;font-size:14px;font-weight:600;color:#71717a;">Tickets</th>
                  <th style="padding:12px 16px;text-align:right;font-size:14px;font-weight:600;color:#71717a;">Actions</th>
                </tr>
                </thead>
                <tbody style="border-bottom:1px solid #e5e5e5;">
                <?php if (count($projectList) === 0): ?>
                <tr>
                  <td colspan="3" style="padding:16px;text-align:center;font-size:14px;color:#71717a;">No projects yet.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($projectList as $proj): $projName = $proj['name']; ?>
                <?php $countStmt = db()->prepare("SELECT COUNT(*) as cnt FROM Ticket WHERE project = :p"); $countStmt->execute(['p' => $projName]); $count = (int) $countStmt->fetch()['cnt']; ?>
                <tr style="border-bottom:1px solid #f5f5f5;">
                  <td style="padding:12px 16px;text-align:left;font-size:14px;color:#18181b;font-weight:500;"><a href="/project.php?project=<?= urlencode($projName) ?>" style="color:#4f46e5;text-decoration:underline;"><?= htmlspecialchars($projName, ENT_QUOTES, 'UTF-8') ?></a></td>
                  <td style="padding:12px 16px;text-align:right;font-size:14px;color:#71717a;"><?= $count ?></td>
                  <td style="padding:12px 16px;text-align:right;">
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars($projName) ?>?')">
                      <input type="hidden" name="action" value="delete_project">
                      <input type="hidden" name="project" value="<?= htmlspecialchars($projName) ?>">
                      <button type="submit" style="font-size:14px;color:#e11d48;background:none;border:none;cursor:pointer;">Delete</button>
                    </form>
                  </td>
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
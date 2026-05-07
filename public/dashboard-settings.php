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

$dashboardWidgets = json_decode($user['dashboardWidgets'] ?? '{"stats":true,"chart":true,"activity":true,"tickets":true}', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $widgets = [
        'stats' => !empty($_POST['stats']),
        'chart' => !empty($_POST['chart']),
        'activity' => !empty($_POST['activity']),
        'tickets' => !empty($_POST['tickets']),
    ];
    $stmt = db()->prepare('UPDATE User SET dashboardWidgets = :widgets WHERE id = :id');
    $stmt->execute(['widgets' => json_encode($widgets), 'id' => (int) $user['id']]);
    refreshUser();
    header('Location: /');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Settings</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900">
  <nav class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-2xl items-center justify-between px-4">
      <a href="/" class="text-lg font-bold text-zinc-800">← Back to Dashboard</a>
    </div>
  </nav>

  <main class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="text-2xl font-bold">Dashboard Settings</h1>
    <p class="mt-1 text-zinc-500">Choose which widgets to display on your dashboard</p>

    <form method="post" class="mt-6 space-y-4">
      <label class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4">
        <input type="checkbox" name="stats" value="1" <?= $dashboardWidgets['stats'] ? 'checked' : '' ?> class="h-5 w-5">
        <div>
          <p class="font-medium">Statistics Widget</p>
          <p class="text-sm text-zinc-500">Status and priority counts</p>
        </div>
      </label>

      <label class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4">
        <input type="checkbox" name="chart" value="1" <?= $dashboardWidgets['chart'] ? 'checked' : '' ?> class="h-5 w-5">
        <div>
          <p class="font-medium">Chart Widget</p>
          <p class="text-sm text-zinc-500">Visual breakdown by priority</p>
        </div>
      </label>

      <label class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4">
        <input type="checkbox" name="activity" value="1" <?= $dashboardWidgets['activity'] ? 'checked' : '' ?> class="h-5 w-5">
        <div>
          <p class="font-medium">Activity Widget</p>
          <p class="text-sm text-zinc-500">Recent ticket activity</p>
        </div>
      </label>

      <label class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4">
        <input type="checkbox" name="tickets" value="1" <?= $dashboardWidgets['tickets'] ? 'checked' : '' ?> class="h-5 w-5">
        <div>
          <p class="font-medium">Tickets List</p>
          <p class="text-sm text-zinc-500">Main ticket listing</p>
        </div>
      </label>

      <button class="rounded-lg bg-zinc-800 px-4 py-2 font-medium text-white">Save Settings</button>
    </form>
  </main>
</body>
</html>
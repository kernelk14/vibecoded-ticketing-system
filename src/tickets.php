<?php

declare(strict_types=1);

const ALLOWED_STATUSES = ['OPEN', 'TRIAGED', 'IN_PROGRESS', 'IN_REVIEW', 'TESTING', 'DONE'];
const ALLOWED_PRIORITIES = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
const ALLOWED_ENVIRONMENTS = ['DEVELOPMENT', 'STAGING', 'PRODUCTION'];
const ALLOWED_SEVERITIES = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];

function normalizedRole(array $user): string
{
    return strtoupper((string) ($user['role'] ?? 'USER'));
}

function canManageTickets(array $user): bool
{
    return in_array(normalizedRole($user), ['ADMIN', 'DEVELOPER', 'QA'], true);
}

function listAssignableUsers(): array
{
    return db()->query(
        "SELECT id, name, role FROM User WHERE role IN ('ADMIN', 'DEVELOPER', 'QA') ORDER BY name ASC"
    )->fetchAll();
}

function getTicketById(int $ticketId): ?array
{
    $stmt = db()->prepare(
        'SELECT
            t.id, t.userId, t.author, t.title, t.description, t.project, t.module, t.appVersion, t.branch,
            t.environment, t.severity, t.reproducible, t.expectedBehavior, t.actualBehavior,
            t.stepsToReproduce, t.errorLog, t.status, t.priority, t.createdAt, t.updatedAt,
            t.assigneeUserId, t.dueAt, a.name AS assigneeName
         FROM Ticket t
         LEFT JOIN User a ON a.id = t.assigneeUserId
         WHERE t.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $ticketId]);
    $ticket = $stmt->fetch();

    return $ticket ?: null;
}

function listTickets(array $user, array $filters = []): array
{
    $pdo = db();
    $where = [];
    $params = [];

    if (!canManageTickets($user)) {
        $where[] = 'userId = :userId';
        $params['userId'] = (int) $user['id'];
    }

    $status = $filters['status'] ?? null;
    if ($status !== null && in_array($status, ALLOWED_STATUSES, true)) {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }

    $priority = $filters['priority'] ?? null;
    if ($priority !== null && in_array($priority, ALLOWED_PRIORITIES, true)) {
        $where[] = 'priority = :priority';
        $params['priority'] = $priority;
    }

    $severity = $filters['severity'] ?? null;
    if ($severity !== null && in_array($severity, ALLOWED_SEVERITIES, true)) {
        $where[] = 'severity = :severity';
        $params['severity'] = $severity;
    }

    $project = trim((string) ($filters['project'] ?? ''));
    if ($project !== '') {
        $where[] = 'project LIKE :project';
        $params['project'] = '%' . $project . '%';
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(title LIKE :q OR description LIKE :q OR author LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    $assigneeId = (int) ($filters['assigneeUserId'] ?? 0);
    if ($assigneeId > 0 && canManageTickets($user)) {
        $where[] = 'assigneeUserId = :assigneeUserId';
        $params['assigneeUserId'] = $assigneeId;
    }

    $sql =
        'SELECT
            t.id, t.author, t.title, t.description, t.project, t.module, t.appVersion, t.branch,
            t.environment, t.severity, t.reproducible, t.expectedBehavior, t.actualBehavior,
            t.stepsToReproduce, t.errorLog, t.status, t.priority, t.createdAt, t.updatedAt,
            t.assigneeUserId, t.dueAt, a.name AS assigneeName
         FROM Ticket t
         LEFT JOIN User a ON a.id = t.assigneeUserId';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY t.updatedAt DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function createTicket(array $user, array $input): void
{
    $pdo = db();
    $rawPriority = (string) ($input['priority'] ?? 'MEDIUM');
    $rawSeverity = (string) ($input['severity'] ?? 'MEDIUM');
    $rawEnvironment = (string) ($input['environment'] ?? 'DEVELOPMENT');
    $cleanPriority = in_array($rawPriority, ALLOWED_PRIORITIES, true) ? $rawPriority : 'MEDIUM';
    $cleanSeverity = in_array($rawSeverity, ALLOWED_SEVERITIES, true) ? $rawSeverity : 'MEDIUM';
    $cleanEnv = in_array($rawEnvironment, ALLOWED_ENVIRONMENTS, true) ? $rawEnvironment : 'DEVELOPMENT';
    $cleanAuthor = trim((string) ($user['name'] ?? '')) !== '' ? trim((string) $user['name']) : 'Anonymous';
    $reproducible = isset($input['reproducible']) && $input['reproducible'] === '1' ? 1 : 0;
    $project = trim((string) ($input['project'] ?? '')) !== '' ? trim((string) $input['project']) : 'General';

    $stmt = $pdo->prepare(
        'INSERT INTO Ticket (
            userId, author, title, description, project, module, appVersion, branch, environment,
            severity, reproducible, expectedBehavior, actualBehavior, stepsToReproduce, errorLog,
            status, priority, createdAt, updatedAt
         ) VALUES (
            :userId, :author, :title, :description, :project, :module, :appVersion, :branch, :environment,
            :severity, :reproducible, :expectedBehavior, :actualBehavior, :stepsToReproduce, :errorLog,
            :status, :priority, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
         )'
    );

    $stmt->execute([
        'userId' => (int) $user['id'],
        'author' => $cleanAuthor,
        'title' => trim((string) $input['title']),
        'description' => trim((string) $input['description']),
        'project' => $project,
        'module' => nullableText($input['module'] ?? null),
        'appVersion' => nullableText($input['appVersion'] ?? null),
        'branch' => nullableText($input['branch'] ?? null),
        'environment' => $cleanEnv,
        'severity' => $cleanSeverity,
        'reproducible' => $reproducible,
        'expectedBehavior' => nullableText($input['expectedBehavior'] ?? null),
        'actualBehavior' => nullableText($input['actualBehavior'] ?? null),
        'stepsToReproduce' => nullableText($input['stepsToReproduce'] ?? null),
        'errorLog' => nullableText($input['errorLog'] ?? null),
        'status' => 'OPEN',
        'priority' => $cleanPriority,
    ]);

    $ticketId = (int) $pdo->lastInsertId();
    addTicketActivity($ticketId, $user, 'CREATED', 'Ticket was created.');

    if (!isAdmin($user)) {
        createAdminNotifications($cleanAuthor, trim((string) $input['title']), $ticketId);
    }
}

function updateTicketStatus(int $id, string $status): void
{
    if (!in_array($status, ALLOWED_STATUSES, true)) {
        return;
    }

    $stmt = db()->prepare(
        'UPDATE Ticket SET status = :status, updatedAt = CURRENT_TIMESTAMP WHERE id = :id'
    );

    $stmt->execute(['status' => $status, 'id' => $id]);
}

function updateTicketStatusForUser(array $user, int $id, string $status): void
{
    if (!canManageTickets($user) || !in_array($status, ALLOWED_STATUSES, true)) {
        return;
    }

    $pdo = db();
    $ticketStmt = $pdo->prepare(
        'SELECT id, userId, title, status FROM Ticket WHERE id = :id LIMIT 1'
    );
    $ticketStmt->execute(['id' => $id]);
    $ticket = $ticketStmt->fetch();
    if (!$ticket) {
        return;
    }

    updateTicketStatus($id, $status);
    addTicketActivity((int) $ticket['id'], $user, 'STATUS_UPDATED', sprintf('Status changed to %s.', str_replace('_', ' ', $status)));

    $ownerId = isset($ticket['userId']) ? (int) $ticket['userId'] : 0;
    if ($ownerId > 0 && $ownerId !== (int) $user['id']) {
        $message = sprintf(
            'Your issue "%s" was updated to %s by admin.',
            (string) $ticket['title'],
            str_replace('_', ' ', $status)
        );
        createNotificationForUser($ownerId, $message, (int) $ticket['id']);
    }
}

function updateTicketMetaForUser(array $user, int $ticketId, array $input): void
{
    if (!canManageTickets($user)) {
        return;
    }

    $assigneeUserId = (int) ($input['assigneeUserId'] ?? 0);
    $dueAt = trim((string) ($input['dueAt'] ?? ''));
    $dueAtValue = $dueAt !== '' ? $dueAt . ':00' : null;

    $stmt = db()->prepare(
        'UPDATE Ticket
         SET assigneeUserId = :assigneeUserId, dueAt = :dueAt, updatedAt = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $stmt->execute([
        'assigneeUserId' => $assigneeUserId > 0 ? $assigneeUserId : null,
        'dueAt' => $dueAtValue,
        'id' => $ticketId,
    ]);

    addTicketActivity($ticketId, $user, 'ASSIGNMENT_UPDATED', 'Assignee or due date updated.');
}

function updateTicketDetailsForAdmin(array $user, int $ticketId, array $input): void
{
    if (!isAdmin($user)) {
        return;
    }

    $rawPriority = (string) ($input['priority'] ?? 'MEDIUM');
    $rawSeverity = (string) ($input['severity'] ?? 'MEDIUM');
    $rawEnvironment = (string) ($input['environment'] ?? 'DEVELOPMENT');
    $rawStatus = (string) ($input['status'] ?? 'OPEN');
    $cleanPriority = in_array($rawPriority, ALLOWED_PRIORITIES, true) ? $rawPriority : 'MEDIUM';
    $cleanSeverity = in_array($rawSeverity, ALLOWED_SEVERITIES, true) ? $rawSeverity : 'MEDIUM';
    $cleanEnv = in_array($rawEnvironment, ALLOWED_ENVIRONMENTS, true) ? $rawEnvironment : 'DEVELOPMENT';
    $cleanStatus = in_array($rawStatus, ALLOWED_STATUSES, true) ? $rawStatus : 'OPEN';

    $assigneeUserId = (int) ($input['assigneeUserId'] ?? 0);
    $dueAt = trim((string) ($input['dueAt'] ?? ''));
    $dueAtValue = $dueAt !== '' ? $dueAt . ':00' : null;
    $reproducible = isset($input['reproducible']) && $input['reproducible'] === '1' ? 1 : 0;

    $stmt = db()->prepare(
        'UPDATE Ticket SET
            title = :title,
            description = :description,
            project = :project,
            module = :module,
            appVersion = :appVersion,
            branch = :branch,
            environment = :environment,
            severity = :severity,
            reproducible = :reproducible,
            expectedBehavior = :expectedBehavior,
            actualBehavior = :actualBehavior,
            stepsToReproduce = :stepsToReproduce,
            errorLog = :errorLog,
            status = :status,
            priority = :priority,
            assigneeUserId = :assigneeUserId,
            dueAt = :dueAt,
            updatedAt = CURRENT_TIMESTAMP
         WHERE id = :id'
    );

    $stmt->execute([
        'title' => trim((string) ($input['title'] ?? '')),
        'description' => trim((string) ($input['description'] ?? '')),
        'project' => trim((string) ($input['project'] ?? '')) ?: 'General',
        'module' => nullableText($input['module'] ?? null),
        'appVersion' => nullableText($input['appVersion'] ?? null),
        'branch' => nullableText($input['branch'] ?? null),
        'environment' => $cleanEnv,
        'severity' => $cleanSeverity,
        'reproducible' => $reproducible,
        'expectedBehavior' => nullableText($input['expectedBehavior'] ?? null),
        'actualBehavior' => nullableText($input['actualBehavior'] ?? null),
        'stepsToReproduce' => nullableText($input['stepsToReproduce'] ?? null),
        'errorLog' => nullableText($input['errorLog'] ?? null),
        'status' => $cleanStatus,
        'priority' => $cleanPriority,
        'assigneeUserId' => $assigneeUserId > 0 ? $assigneeUserId : null,
        'dueAt' => $dueAtValue,
        'id' => $ticketId,
    ]);

    addTicketActivity($ticketId, $user, 'DETAILS_UPDATED', 'Admin updated ticket details.');
}

function deleteTicket(int $id): void
{
    $stmt = db()->prepare('DELETE FROM Ticket WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function deleteTicketForUser(array $user, int $id): void
{
    if (!canManageTickets($user)) {
        return;
    }

    $pdo = db();
    $ticketStmt = $pdo->prepare(
        'SELECT id, userId, title FROM Ticket WHERE id = :id LIMIT 1'
    );
    $ticketStmt->execute(['id' => $id]);
    $ticket = $ticketStmt->fetch();
    if (!$ticket) {
        return;
    }

    $ownerId = isset($ticket['userId']) ? (int) $ticket['userId'] : 0;
    if ($ownerId > 0 && $ownerId !== (int) $user['id']) {
        $message = sprintf(
            'Your issue "%s" was deleted by admin.',
            (string) $ticket['title']
        );
        createNotificationForUser($ownerId, $message, null);
    }

    addTicketActivity((int) $ticket['id'], $user, 'DELETED', 'Ticket was deleted.');
    deleteTicket($id);
}

function createAdminNotifications(string $author, string $title, int $ticketId): void
{
    $pdo = db();
    $adminUsers = $pdo->query("SELECT id FROM User WHERE role = 'ADMIN'")->fetchAll();
    if ($adminUsers === []) {
        return;
    }

    $message = sprintf('%s added a new issue: %s', $author, $title);
    $stmt = $pdo->prepare(
        'INSERT INTO Notification (userId, ticketId, message, isRead, createdAt)
         VALUES (:userId, :ticketId, :message, 0, CURRENT_TIMESTAMP)'
    );

    foreach ($adminUsers as $admin) {
        createNotificationForUser((int) $admin['id'], $message, $ticketId);
    }
}

function createNotificationForUser(int $userId, string $message, ?int $ticketId = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO Notification (userId, ticketId, message, isRead, createdAt)
         VALUES (:userId, :ticketId, :message, 0, CURRENT_TIMESTAMP)'
    );

    $stmt->execute([
        'userId' => $userId,
        'ticketId' => $ticketId,
        'message' => $message,
    ]);
}

function listUserNotifications(array $user, int $limit = 8): array
{
    if (!isset($user['id'])) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT id, ticketId, message, isRead, createdAt
         FROM Notification
         WHERE userId = :userId
         ORDER BY createdAt DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':userId', (int) $user['id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function addCommentToTicket(array $user, int $ticketId, string $content): void
{
    $cleanContent = trim($content);
    if ($cleanContent === '') {
        return;
    }

    $ticketStmt = db()->prepare('SELECT userId, title FROM Ticket WHERE id = :id LIMIT 1');
    $ticketStmt->execute(['id' => $ticketId]);
    $ticket = $ticketStmt->fetch();
    if (!$ticket) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO TicketComment (ticketId, userId, author, content, createdAt)
         VALUES (:ticketId, :userId, :author, :content, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([
        'ticketId' => $ticketId,
        'userId' => (int) $user['id'],
        'author' => (string) $user['name'],
        'content' => $cleanContent,
    ]);

    addTicketActivity($ticketId, $user, 'COMMENT_ADDED', 'New comment added.');

    $ownerId = isset($ticket['userId']) ? (int) $ticket['userId'] : 0;
    if ($ownerId > 0 && $ownerId !== (int) $user['id']) {
        createNotificationForUser(
            $ownerId,
            sprintf('New comment on your issue "%s".', (string) $ticket['title']),
            $ticketId
        );
    }
}

function listCommentsForTicket(int $ticketId): array
{
    $stmt = db()->prepare(
        'SELECT id, author, content, createdAt
         FROM TicketComment
         WHERE ticketId = :ticketId
         ORDER BY createdAt ASC'
    );
    $stmt->execute(['ticketId' => $ticketId]);
    return $stmt->fetchAll();
}

function listActivitiesForTicket(int $ticketId): array
{
    $stmt = db()->prepare(
        'SELECT actorName, action, message, createdAt
         FROM TicketActivity
         WHERE ticketId = :ticketId
         ORDER BY createdAt DESC
         LIMIT 5'
    );
    $stmt->execute(['ticketId' => $ticketId]);
    return $stmt->fetchAll();
}

function addTicketActivity(int $ticketId, array $actor, string $action, string $message): void
{
    $stmt = db()->prepare(
        'INSERT INTO TicketActivity (ticketId, actorId, actorName, action, message, createdAt)
         VALUES (:ticketId, :actorId, :actorName, :action, :message, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([
        'ticketId' => $ticketId,
        'actorId' => isset($actor['id']) ? (int) $actor['id'] : null,
        'actorName' => (string) ($actor['name'] ?? 'System'),
        'action' => $action,
        'message' => $message,
    ]);
}

function nullableText(mixed $value): ?string
{
    $text = trim((string) ($value ?? ''));
    return $text === '' ? null : $text;
}

function unreadNotificationCount(array $user): int
{
    if (!isset($user['id'])) {
        return 0;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM Notification WHERE userId = :userId AND isRead = 0'
    );
    $stmt->execute(['userId' => (int) $user['id']]);

    return (int) $stmt->fetchColumn();
}

function markNotificationsRead(array $user): void
{
    if (!isset($user['id'])) {
        return;
    }

    $stmt = db()->prepare(
        'UPDATE Notification SET isRead = 1 WHERE userId = :userId AND isRead = 0'
    );
    $stmt->execute(['userId' => (int) $user['id']]);
}

# PHP Ticketing System

Simple ticketing system built with:

- PHP (server-rendered app)
- Tailwind CSS (UI styling)
- Prisma ORM (schema + migrations + seed tooling)
- SQLite (database engine)

## Requirements

- PHP 8.1+
- Node.js 20+

## Setup

1. Install dependencies:

   ```bash
   npm install
   ```

2. Run database migration and generate Prisma client:

   ```bash
   npm run db:migrate
   npm run db:generate
   ```

3. Build CSS:

   ```bash
   npm run build:css
   ```

4. (Optional) Seed sample tickets:

   ```bash
   npm run db:seed
   ```

5. Run PHP server:

   ```bash
   npm run serve
   ```

Open [http://localhost:8000](http://localhost:8000).

## Role-based Dashboards

The app now supports role-based access:

- `ADMIN` users can view all tickets, update status, and delete tickets.
- `USER` users can view only their own tickets and create new ones.

Seeded login accounts:

- Admin: `admin@local.test` / `admin123`
- User: `user@local.test` / `user123`

## Dev Ticketing Features

The system includes software-focused ticket workflows:

- Programming metadata on tickets (`project`, `module`, `version`, `branch`, `environment`, `severity`, reproducible flag, expected/actual behavior, repro steps, error log).
- Dev lifecycle statuses: `OPEN`, `TRIAGED`, `IN_PROGRESS`, `IN_REVIEW`, `TESTING`, `DONE`.
- Assignment workflow for `ADMIN` / `DEVELOPER` / `QA` roles with assignee and due date.
- Per-ticket comments and recent activity history.
- Search and filtering by status, keyword, project, priority, and severity.
- Live in-app notifications and side popups for ticket creation, updates, comments, and deletions.

## Development

- Watch and rebuild Tailwind CSS:

  ```bash
  npm run dev:css
  ```

Database file is stored at `prisma/dev.db`.

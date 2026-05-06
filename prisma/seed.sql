INSERT INTO User (name, email, password, role, createdAt, updatedAt)
SELECT
  'System Admin',
  'admin@local.test',
  '$2y$12$x4C9YG6g/HianImQMBU9KOzIe.vBt7Clz.4dJg75No1lzuDjNfMRa',
  'ADMIN',
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
WHERE NOT EXISTS (
  SELECT 1 FROM User WHERE email = 'admin@local.test'
);

INSERT INTO User (name, email, password, role, createdAt, updatedAt)
SELECT
  'Normal User',
  'user@local.test',
  '$2y$12$iPIVqwkUCyy9WC5XIw5D8epgacGylU/bnw/smsTiJP/vxek7PTDx.',
  'USER',
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
WHERE NOT EXISTS (
  SELECT 1 FROM User WHERE email = 'user@local.test'
);

INSERT INTO Ticket (userId, author, title, description, status, priority, createdAt, updatedAt)
SELECT
  (SELECT id FROM User WHERE email = 'admin@local.test'),
  'Support Team',
  'Login page shows incorrect error message',
  'Users are seeing a generic error instead of invalid credentials details.',
  'OPEN',
  'HIGH',
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
WHERE NOT EXISTS (
  SELECT 1 FROM Ticket WHERE title = 'Login page shows incorrect error message'
);

INSERT INTO Ticket (userId, author, title, description, status, priority, createdAt, updatedAt)
SELECT
  (SELECT id FROM User WHERE email = 'user@local.test'),
  'Data Team',
  'Export report button times out',
  'CSV export takes more than 30 seconds on production-like data.',
  'IN_PROGRESS',
  'URGENT',
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
WHERE NOT EXISTS (
  SELECT 1 FROM Ticket WHERE title = 'Export report button times out'
);

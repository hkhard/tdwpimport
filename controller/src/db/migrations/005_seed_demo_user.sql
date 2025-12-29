-- Seed demo user for development/demo mode
-- This creates the default user that the API uses when creating tournaments in demo mode
INSERT OR IGNORE INTO users (user_id, email, password_hash, role)
VALUES ('demo-user', 'demo@local', 'no-password', 'admin');

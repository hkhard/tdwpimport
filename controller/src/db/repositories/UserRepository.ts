/**
 * User repository for controller database
 * Manages user account data
 */

import { BaseRepository } from './BaseRepository';

interface User {
  user_id: string;
  username: string;
  email: string;
  password_hash: string;
  role: 'admin' | 'director' | 'viewer';
  created_at: string;
  updated_at: string;
}

interface CreateUserData {
  userId: string;
  username: string;
  email: string;
  passwordHash: string;
  role: 'admin' | 'director' | 'viewer';
  createdAt: string;
  updatedAt: string;
}

interface UpdateUserData {
  passwordHash?: string;
  role?: 'admin' | 'director' | 'viewer';
  updatedAt: string;
}

export class UserRepository extends BaseRepository<User> {
  constructor() {
    super('users', 'user_id');
  }

  findByUsername(username: string): User | undefined {
    return this.queryOne('SELECT * FROM users WHERE username = ?', [username]);
  }

  findByEmail(email: string): User | undefined {
    return this.queryOne('SELECT * FROM users WHERE email = ?', [email]);
  }

  insert(data: CreateUserData): void {
    this.execute(
      `INSERT INTO users (user_id, username, email, password_hash, role, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        data.userId,
        data.username,
        data.email,
        data.passwordHash,
        data.role,
        data.createdAt,
        data.updatedAt,
      ]
    );
  }

  update(id: string, data: UpdateUserData): void {
    const parts: string[] = [];
    const values: any[] = [];

    if (data.passwordHash !== undefined) {
      parts.push('password_hash = ?');
      values.push(data.passwordHash);
    }
    if (data.role !== undefined) {
      parts.push('role = ?');
      values.push(data.role);
    }
    parts.push('updated_at = ?');
    values.push(data.updatedAt);

    values.push(id);

    this.execute(`UPDATE users SET ${parts.join(', ')} WHERE user_id = ?`, values);
  }

  toDomain(row: any): User {
    return {
      user_id: row.user_id,
      username: row.username,
      email: row.email,
      password_hash: row.password_hash,
      role: row.role,
      created_at: row.created_at,
      updated_at: row.updated_at,
    };
  }
}

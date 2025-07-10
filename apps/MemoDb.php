<?php

class MemoDb
{
    private $db;

    private $dbPath;

    private $currentVersion = 2; // Increment when you add migrations

    public function __construct(string $dbPath)
    {
        $this->dbPath = $dbPath;
        $this->connect();
        $this->migrate();
    }

    private function connect(): void
    {
        try {
            $this->db = new SQLite3($this->dbPath);

            if (! $this->db) {
                exit("Error: Failed to open SQLite database at {$this->dbPath}\n");
            }

            // Enable exceptions
            $this->db->enableExceptions(true);

            // Enable WAL mode for better concurrency
            $this->db->exec('PRAGMA journal_mode=WAL');
            $this->db->exec('PRAGMA synchronous=NORMAL');
            $this->db->exec('PRAGMA busy_timeout=30000');

        } catch (Exception $e) {
            exit('Error: Cannot connect to SQLite database: '.$e->getMessage()."\n");
        }
    }

    private function migrate(): void
    {
        // First, ensure we have a migrations table
        $this->createMigrationsTable();

        // Get current database version
        $currentDbVersion = $this->getCurrentVersion();

        // Run migrations if needed
        if ($currentDbVersion < $this->currentVersion) {
            $this->runMigrations($currentDbVersion);
        }
    }

    private function createMigrationsTable(): void
    {
        $this->db->exec('
                CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    version INTEGER NOT NULL,
                    applied_at TEXT NOT NULL
                )
            ');
    }

    private function getCurrentVersion(): int
    {
        $stmt = $this->db->prepare('SELECT MAX(version) FROM migrations');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_NUM);

        return (int) ($row[0] ?? 0);
    }

    private function runMigrations(int $fromVersion): void
    {
        $migrations = $this->getMigrations();

        foreach ($migrations as $version => $migration) {
            if ($version > $fromVersion) {
                // echo "Running migration version {$version}...\n";

                $this->db->exec('BEGIN TRANSACTION');
                try {
                    // Run the migration
                    $this->db->exec($migration);

                    // Record that we ran it
                    $stmt = $this->db->prepare('INSERT INTO migrations (version, applied_at) VALUES (?, ?)');
                    $stmt->bindValue(1, $version, SQLITE3_INTEGER);
                    $stmt->bindValue(2, date('Y-m-d H:i:s'), SQLITE3_TEXT);
                    $stmt->execute();

                    $this->db->exec('COMMIT');
                    // echo "Migration {$version} completed.\n";
                } catch (Exception $e) {
                    $this->db->exec('ROLLBACK');
                    throw new Exception("Migration {$version} failed: ".$e->getMessage());
                }
            }
        }
    }

    private function getMigrations(): array
    {
        return [
            1 => '
                    CREATE TABLE users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        public_key TEXT NOT NULL UNIQUE,
                        username TEXT UNIQUE,
                        created_at TEXT NOT NULL
                    );
                    
                    CREATE TABLE memos (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        content TEXT NOT NULL,
                        created_at TEXT NOT NULL,
                        FOREIGN KEY (user_id) REFERENCES users (id)
                    );
                    
                    CREATE INDEX idx_memos_created_at ON memos(created_at);
                    CREATE INDEX idx_memos_user_id ON memos(user_id);
                ',

            // Future migrations go here:
            // 2 => 'ALTER TABLE memos ADD COLUMN likes INTEGER DEFAULT 0;',
            // 3 => 'CREATE TABLE follows (...);',

            2 => 'ALTER TABLE users ADD COLUMN color INTEGER;',
        ];
    }

    public function retryOnBusy(callable $callback, int $maxRetries = 3)
    {
        $attempts = 0;

        while ($attempts < $maxRetries) {
            try {
                return $callback();
            } catch (Exception $e) {
                if ((strpos($e->getMessage(), 'database is locked') !== false ||
                     strpos($e->getMessage(), 'busy') !== false) &&
                    $attempts < $maxRetries - 1) {
                    $attempts++;
                    usleep(rand(100000, 500000) * $attempts);

                    continue;
                }
                throw $e;
            }
        }
    }

    public function findOrCreateUser(string $publicKey): array
    {
        return $this->retryOnBusy(function () use ($publicKey) {
            // First, try to find existing user
            $stmt = $this->db->prepare('SELECT * FROM users WHERE public_key = ?');
            $stmt->bindValue(1, $publicKey, SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);

            if ($user) {
                // If existing user lacks a color, assign one.
                if ($user['color'] === null) {
                    $colorCode = rand(16, 231);
                    $update = $this->db->prepare('UPDATE users SET color = ? WHERE id = ?');
                    $update->bindValue(1, $colorCode, SQLITE3_INTEGER);
                    $update->bindValue(2, $user['id'], SQLITE3_INTEGER);
                    $update->execute();
                    $user['color'] = $colorCode;
                }

                return $user;
            }

            // Create new user
            $colorCode = rand(16, 231);
            $stmt = $this->db->prepare('INSERT INTO users (public_key, created_at, color) VALUES (?, ?, ?)');
            $stmt->bindValue(1, $publicKey, SQLITE3_TEXT);
            $stmt->bindValue(2, date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->bindValue(3, $colorCode, SQLITE3_INTEGER);
            $stmt->execute();

            return [
                'id' => $this->db->lastInsertRowID(),
                'public_key' => $publicKey,
                'username' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'color' => $colorCode,
            ];
        });
    }

    public function setUsername(int $userId, string $username): bool
    {
        return $this->retryOnBusy(function () use ($userId, $username) {
            // Check if username is taken
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $stmt->bindValue(1, $username, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_NUM);

            if ($row[0] > 0) {
                throw new Exception('Username already taken');
            }

            // Set username
            $stmt = $this->db->prepare('UPDATE users SET username = ? WHERE id = ?');
            $stmt->bindValue(1, $username, SQLITE3_TEXT);
            $stmt->bindValue(2, $userId, SQLITE3_INTEGER);

            return $stmt->execute() !== false;
        });
    }

    public function createMemo(int $userId, string $content): bool
    {
        // Validate and sanitize content
        $content = $this->sanitizeAndValidateContent($content);

        return $this->retryOnBusy(function () use ($userId, $content) {
            $stmt = $this->db->prepare('INSERT INTO memos (user_id, content, created_at) VALUES (?, ?, ?)');
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $content, SQLITE3_TEXT);
            $stmt->bindValue(3, date('Y-m-d H:i:s'), SQLITE3_TEXT);

            return $stmt->execute() !== false;
        });
    }

    /**
     * Sanitize and validate memo content for security and length constraints.
     *
     * @param  string  $content  The raw memo content
     * @return string The sanitized content
     *
     * @throws Exception If content is invalid
     */
    private function sanitizeAndValidateContent(string $content): string
    {
        // Trim whitespace
        $content = trim($content);

        // Check if empty after trimming
        if ($content === '') {
            throw new Exception('Memo content cannot be empty');
        }

        // Remove ASCII control characters (0-31 except tab, newline, carriage return)
        // and DEL character (127), plus other potentially dangerous characters
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        // Remove ANSI escape sequences (like \e[31m for colors, \e[0m for reset, etc.)
        $content = preg_replace('/\x1B\[[0-9;]*[JKmsu]/', '', $content);

        // Remove other common escape sequences
        $content = preg_replace('/\\\\[nrtvfab0]/', '', $content);

        // Validate length after sanitization
        if (mb_strlen($content) > 300) {
            throw new Exception('Memo content cannot exceed 300 characters');
        }

        // Final check if content is empty after sanitization
        if (trim($content) === '') {
            throw new Exception('Memo content cannot be empty after sanitization');
        }

        return $content;
    }

    public function getLatestMemos(int $limit = 20): array
    {
        return $this->retryOnBusy(function () use ($limit) {
            $stmt = $this->db->prepare('
                SELECT m.content, m.created_at, u.username, u.color 
                FROM memos m 
                JOIN users u ON m.user_id = u.id 
                ORDER BY m.created_at DESC 
                LIMIT ?
            ');
            $stmt->bindValue(1, $limit, SQLITE3_INTEGER);
            $result = $stmt->execute();

            $memos = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $memos[] = $row;
            }

            return $memos;
        });
    }
}

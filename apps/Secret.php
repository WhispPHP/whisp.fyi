<?php

namespace Apps;

// This isn't a model representing a single secret, this is just a helper class to handle the database, encryption, decryption, etc.. for both secrets.php & secret-[hashid].php
// It can't be called 'secrets' because the app is called that
use Dotenv\Dotenv;
use Hidehalo\Nanoid\Client;

class Secret
{
    private \SQLite3 $db;

    private string $secretEncryptionKey;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        $this->secretEncryptionKey = $_ENV['SECRETS_SECRET_ENCRYPTION_KEY'];

        $dbExists = file_exists($_ENV['SECRETS_DB_PATH']);
        $this->db = new \SQLite3(filename: $_ENV['SECRETS_DB_PATH'], encryptionKey: $_ENV['SECRETS_DB_ENCRYPTION_KEY']);

        if (! $dbExists) {
            $this->dbFresh();
        }

        $this->pragmas();
    }

    /*
    * Sets up the SQlite database to be more performant and useful
    * Thank you Aaron ∙ https://highperformancesqlite.com/articles/sqlite-recommended-pragmas
    */
    private function pragmas(): void
    {
        $this->db->exec('PRAGMA journal_mode = WAL;');
        $this->db->exec('PRAGMA synchronous = NORMAL;');
        $this->db->exec('PRAGMA cache_size = 10000;');
        $this->db->exec('PRAGMA temp_store = MEMORY;');
        $this->db->exec('PRAGMA foreign_keys = ON;');
        $this->db->exec('PRAGMA mmap_size = 268435456;');
        $this->db->exec('PRAGMA busy_timeout = 1000;');
    }

    private function dbFresh()
    {
        $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('CREATE TABLE IF NOT EXISTS secrets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            hashid TEXT NOT NULL,
            secret BLOB,
            authorized_github_username TEXT NOT NULL,
            inserted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            viewed_at DATETIME
        )');
    }

    /**
     * Encrypts a secret using Sodium's secretbox with key derivation
     *
     * @param  string  $secret  The secret to encrypt
     * @param  string  $gitHubUsername  The GitHub username used for key derivation
     * @return string The encrypted secret in base64 format
     */
    public function encryptSecret(string $secret, string $gitHubUsername): string
    {
        // Generate a random nonce
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Create a properly sized salt from the GitHub username
        $salt = str_pad(
            substr($gitHubUsername, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES),
            SODIUM_CRYPTO_PWHASH_SALTBYTES,
            "\0"
        );

        // Derive a unique key for this user
        $derivedKey = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $this->secretEncryptionKey,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_DEFAULT
        );

        // Encrypt the secret with the derived key
        $encrypted = sodium_crypto_secretbox(
            $secret,
            $nonce,
            $derivedKey
        );

        // Combine nonce and encrypted data
        $combined = $nonce.$encrypted;

        // Return base64 encoded for safe storage
        return base64_encode($combined);
    }

    /**
     * Decrypts a secret using Sodium's secretbox with key derivation
     *
     * @param  string  $encryptedSecret  The encrypted secret in base64 format
     * @param  string  $gitHubUsername  The GitHub username used for key derivation
     * @return string|false The decrypted secret or false on failure
     */
    public function decryptSecret(string $encryptedSecret, string $gitHubUsername): string|false
    {
        try {
            // Decode from base64
            $combined = base64_decode($encryptedSecret);
            if ($combined === false) {
                return false;
            }

            // Extract nonce and encrypted data
            $nonce = substr($combined, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = substr($combined, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            // Create a properly sized salt from the GitHub username
            $salt = str_pad(
                substr($gitHubUsername, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES),
                SODIUM_CRYPTO_PWHASH_SALTBYTES,
                "\0"
            );

            // Derive the same key used for encryption
            $derivedKey = sodium_crypto_pwhash(
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
                $this->secretEncryptionKey,
                $salt,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_ALG_DEFAULT
            );

            // Decrypt with the derived key
            $decrypted = sodium_crypto_secretbox_open(
                $encrypted,
                $nonce,
                $derivedKey
            );

            return $decrypted !== false ? $decrypted : false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function create(string $secret, string $authorizedGitHubUsername)
    {
        // 1. Generate hashid using nanoid, which hasn't been used before - should be safe to be part of a 'username' when using the SSH client in bash
        $hashid = '';
        $exists = true;
        $maxAttempts = 10;
        $attempts = 0;
        while ($exists && $attempts < $maxAttempts) {
            $hashid = (new Client)->formattedId(alphabet: '0123456789abcdefghijklmnopqrstuvwxyz-_', size: 7);
            $exists = ! empty($this->db->querySingle('SELECT hashid FROM secrets WHERE hashid = ?', true));
            $attempts++;
        }

        if ($attempts === $maxAttempts) {
            throw new \Exception('Failed to generate hashid');
        }

        $secret = $this->encryptSecret($secret, $authorizedGitHubUsername);

        $stmt = $this->db->prepare('INSERT INTO secrets (hashid, secret, authorized_github_username) VALUES (:hashid, :secret, :authorized_github_username)');
        $stmt->bindValue(':hashid', $hashid, SQLITE3_TEXT);
        $stmt->bindValue(':secret', $secret, SQLITE3_BLOB);
        $stmt->bindValue(':authorized_github_username', $authorizedGitHubUsername, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result === false) {
            throw new \Exception('Failed to create secret');
        }

        return $hashid;
    }

    /**
     * @return array|bool Returns the keys if valid, false if it's not
     */
    public function verifyGitHubUsername(string $username): array|bool
    {
        $response = @file_get_contents("https://github.com/{$username}.keys");
        if ($response === false) {
            error_log("Failed to verify GitHub username: {$username}");

            return false;
        }

        error_log("GitHub username verified: {$username}, got response: {$response}");

        return explode("\n", $response);
    }

    /**
     * Retrieves a secret by hashid if it hasn't been viewed yet
     *
     * @param  string  $hashid  The hashid of the secret
     * @return array|false The secret data or false if not found/already viewed
     */
    public function getUnviewedSecret(string $hashid): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM secrets WHERE hashid = :hashid AND viewed_at IS NULL');
        $stmt->bindValue(':hashid', $hashid, SQLITE3_TEXT);
        $result = $stmt->execute();

        return $result->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * Marks a secret as viewed
     *
     * @param  string  $hashid  The hashid of the secret
     * @return bool True if successful, false otherwise
     */
    public function markSecretAsViewed(string $hashid): bool
    {
        $stmt = $this->db->prepare('UPDATE secrets SET viewed_at = CURRENT_TIMESTAMP, secret=NULL WHERE hashid = :hashid');
        $stmt->bindValue(':hashid', $hashid, SQLITE3_TEXT);

        return $stmt->execute() !== false;
    }

    /**
     * Verifies if a given SSH key is authorized for a secret
     *
     * @param  string  $hashid  The hashid of the secret
     * @param  string  $sshKey  The SSH key to verify
     * @return bool True if authorized, false otherwise
     */
    public function verifySecretAccess(string $hashid, string $sshKey): bool
    {
        $secret = $this->getUnviewedSecret($hashid);
        if (! $secret) {
            error_log("Secret doesn't exist or has already been viewed: {$hashid}");

            return false;
        }

        $authorizedKeys = $this->verifyGitHubUsername($secret['authorized_github_username']);
        if ($authorizedKeys === false) {
            error_log("Failed to verify GitHub username: {$secret['authorized_github_username']} for secret: {$hashid}");

            return false;
        }

        // Normalize the SSH key by removing any comment/username part
        $sshKeyParts = explode(' ', $sshKey);
        $normalizedKey = $sshKeyParts[0].' '.$sshKeyParts[1]; // Keep only type and key data

        return in_array($normalizedKey, $authorizedKeys);
    }

    /**
     * Retrieves and decrypts a secret
     *
     * @param  string  $hashid  The hashid of the secret
     * @param  string  $sshKey  The SSH key of the viewer
     * @return string|false The decrypted secret or false if access denied/decryption failed
     */
    public function viewSecret(string $hashid, string $sshKey): string|false
    {
        if (! $this->verifySecretAccess($hashid, $sshKey)) {
            return false;
        }

        $secret = $this->getUnviewedSecret($hashid);
        if (! $secret) {
            return false;
        }

        $decrypted = $this->decryptSecret($secret['secret'], $secret['authorized_github_username']);
        if ($decrypted === false) {
            return false;
        }

        if (! $this->markSecretAsViewed($hashid)) {
            return false;
        }

        return $decrypted;
    }

    /**
     * Draws a box with a title and content
     *
     * @param  string  $title  The title of the box
     * @param  string  $content  The content to display
     */
    public static function drawBox(string $title, string $content): void
    {
        $width = max(mb_strwidth($title) + 2, mb_strwidth($content) + 2);
        $titleLength = mb_strwidth($title);
        $titleLabel = $titleLength > 0 ? " {$title} " : '';
        $topBorder = str_repeat('─', $width - $titleLength - 2);
        $blankPaddingLine = str_repeat(' ', $width);

        echo "\n";
        echo "┌{$titleLabel}{$topBorder}┐\n";
        echo "│{$blankPaddingLine}│\n";
        echo "│ {$content}".str_repeat(' ', $width - mb_strwidth($content) - 1)."│\n";
        echo "│{$blankPaddingLine}│\n";
        echo '└'.str_repeat('─', $width)."┘\n";
        echo "\n";
    }
}

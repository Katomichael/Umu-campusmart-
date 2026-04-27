<?php
// includes/Database.php — PDO singleton

class Database {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // --- SSL Logic for Aiven/Render ---
            // If we are not on localhost, we assume we are online and need SSL
            if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
                // This is the default path for CA certs on Render's Linux environment
                $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
                // Aiven certificates are trusted by default CA, so we can verify or skip verification if needed
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Return JSON error for AJAX requests or die for standard ones
                header('Content-Type: application/json');
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        return self::$instance;
    }

    // Execute a query and return the statement
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Fetch all rows
    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    // Fetch a single row
    public static function fetchOne(string $sql, array $params = []): array|false {
        return self::query($sql, $params)->fetch();
    }

    // Insert and return the last insert ID
    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::connect()->lastInsertId();
    }
}
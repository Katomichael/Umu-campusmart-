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
            if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
                // We use the numeric value 1007 for PDO::MYSQL_ATTR_SSL_CA to prevent "Undefined constant" errors
                $options[1007] = '/etc/ssl/certs/ca-certificates.crt';
                // 1014 is the numeric value for PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
                $options[1014] = false;
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): array|false {
        return self::query($sql, $params)->fetch();
    }

    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::connect()->lastInsertId();
    }
}
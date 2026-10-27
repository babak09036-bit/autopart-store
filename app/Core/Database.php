<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/** Single shared PDO connection with small query helpers. Always uses prepared statements. */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('database');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['database'], $c['charset']);
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00', NAMES {$c['charset']} COLLATE {$c['collation']}");
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Inserts a row (column names are whitelisted by callers) and returns the new id. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', self::ident($table),
            implode(', ', array_map([self::class, 'ident'], $cols)),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols)));
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        if (!$data) {
            return 0;
        }
        $set = implode(', ', array_map(static fn ($c) => self::ident($c) . ' = :set_' . $c, array_keys($data)));
        $bind = [];
        foreach ($data as $k => $v) {
            $bind['set_' . $k] = $v;
        }
        return self::query(sprintf('UPDATE %s SET %s WHERE %s', self::ident($table), $set, $where), $bind + $params)->rowCount();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Builds "IN (:p0, :p1 ...)" placeholders for a list. */
    public static function in(string $prefix, array $values, array &$params): string
    {
        if (!$values) {
            return '(NULL)';
        }
        $names = [];
        foreach (array_values($values) as $i => $v) {
            $params[$prefix . $i] = $v;
            $names[] = ':' . $prefix . $i;
        }
        return '(' . implode(', ', $names) . ')';
    }

    /** Quotes an identifier that has already been validated against a whitelist. */
    public static function ident(string $name): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid identifier');
        }
        return '`' . $name . '`';
    }
}

<?php

declare(strict_types=1);

final class Migrator {
    public static function runPending(): array {
        $pdo = Database::connection();
        $dir = base_path('database/migrations');
        $files = glob($dir . '/*.sql') ?: [];
        sort($files, SORT_NATURAL);
        $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $applied = $pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $known = array_fill_keys(array_map('strval', $applied), true);
        $done = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (isset($known[$version])) continue;
            $sql = file_get_contents($file);
            if ($sql === false) throw new RuntimeException('خواندن Migration ممکن نشد: ' . $version);
            try {
                // MySQL/MariaDB DDL statements implicitly commit, so migrations are deliberately
                // not wrapped in a PDO transaction. A migration is marked applied only after exec succeeds.
                $pdo->exec($sql);
                $st = $pdo->prepare('INSERT INTO migrations(version, applied_at) VALUES(?, NOW())');
                $st->execute([$version]);
                $done[] = $version;
                Logger::system('migration.applied', ['version'=>$version]);
            } catch (Throwable $e) {
                Logger::error('migration.failed', ['version'=>$version,'error'=>$e->getMessage()]);
                throw $e;
            }
        }
        return $done;
    }

    public static function latest(): string {
        try {
            return (string)(Database::connection()->query('SELECT version FROM migrations ORDER BY applied_at DESC, version DESC LIMIT 1')->fetchColumn() ?: '-');
        } catch (Throwable) { return '-'; }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

// One-click database backup (.sql file) for the admin. Works on free hosting (no mysqldump needed).
// The file can be imported in phpMyAdmin (Import tab) to restore everything.
class BackupController extends Controller
{
    // Tables whose rows are temporary (logins, cache, queue): only their structure is saved
    private const SKIP_ROWS = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function download(): StreamedResponse
    {
        @set_time_limit(300);

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $filename = 'ccltc-backup-' . now()->format('Y-m-d-His') . '.sql';

        return response()->streamDownload(function () use ($connection, $driver) {
            $pdo = $connection->getPdo();
            $isMysql = in_array($driver, ['mysql', 'mariadb'], true);

            echo "-- CCLTC Tennis Records database backup\n";
            echo '-- Made: ' . now()->toDateTimeString() . ' (' . $driver . ")\n";
            echo "-- Restore: phpMyAdmin > your database > Import > choose this file > Go\n\n";

            if ($isMysql) {
                echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            }

            foreach ($this->tables($driver) as $table) {
                $q = $isMysql ? "`$table`" : "\"$table\"";

                echo "-- ------------------------------------------------\n";
                echo "-- Table $table\n";
                echo "DROP TABLE IF EXISTS $q;\n";
                echo $this->createStatement($driver, $table) . ";\n\n";

                if (in_array($table, self::SKIP_ROWS, true)) {
                    continue;
                }

                $columns = null;
                $orderBy = $this->hasColumn($driver, $table, 'id') ? 'id' : null;
                $query = DB::table($table);
                if ($orderBy) {
                    $query->orderBy($orderBy);
                }

                $offset = 0;
                $size = 200;
                while (true) {
                    $rows = (clone $query)->offset($offset)->limit($size)->get();
                    if ($rows->isEmpty()) {
                        break;
                    }

                    $columns ??= array_keys((array) $rows->first());
                    $colList = implode(', ', array_map(fn ($c) => $isMysql ? "`$c`" : "\"$c\"", $columns));

                    $values = $rows->map(function ($row) use ($pdo, $columns) {
                        $row = (array) $row;

                        return '(' . implode(', ', array_map(
                            fn ($c) => $row[$c] === null ? 'NULL' : $pdo->quote((string) $row[$c]),
                            $columns
                        )) . ')';
                    })->implode(",\n");

                    echo "INSERT INTO $q ($colList) VALUES\n$values;\n";
                    $offset += $size;
                }
                echo "\n";
            }

            if ($isMysql) {
                echo "SET FOREIGN_KEY_CHECKS=1;\n";
            }
            echo "-- End of backup\n";
        }, $filename, ['Content-Type' => 'application/sql; charset=UTF-8']);
    }

    private function tables(string $driver): array
    {
        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
                ->pluck('name')->all();
        }

        return collect(DB::select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0])->sort()->values()->all();
    }

    private function createStatement(string $driver, string $table): string
    {
        if ($driver === 'sqlite') {
            return DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table])->sql;
        }

        $row = (array) DB::selectOne("SHOW CREATE TABLE `$table`");

        return $row['Create Table'] ?? array_values($row)[1];
    }

    private function hasColumn(string $driver, string $table, string $column): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
    }
}
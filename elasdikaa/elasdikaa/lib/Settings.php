<?php
class AppSettings {
    private static $cache = [];
    private static $columns = null; // ['key' => 'key_col', 'value' => 'value_col']
    private static $table = 'app_settings'; // استخدم جدول منفصل حتى لا يتعارض مع جدول system_settings الأصلي الخاص بمعلومات الشركة

    private static function resolveColumns(mysqli $conn): array {
        if (self::$columns !== null) {
            return self::$columns;
        }

        $candidatesKey = ['key', 'setting_key', 'name', 'skey'];
        $candidatesVal = ['value', 'setting_value', 'val', 'svalue'];

        $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $available = [];
        if ($schemaStmt) {
            $schemaStmt->bind_param('s', self::$table);
            $schemaStmt->execute();
            $res = $schemaStmt->get_result();
            while ($row = $res->fetch_assoc()) { $available[] = strtolower($row['COLUMN_NAME']); }
            $schemaStmt->close();
        }

        $keyCol = null; $valCol = null;
        foreach ($candidatesKey as $c) { if (in_array(strtolower($c), $available, true)) { $keyCol = $c; break; } }
        foreach ($candidatesVal as $c) { if (in_array(strtolower($c), $available, true)) { $valCol = $c; break; } }

        // Default fallback
        if ($keyCol === null) { $keyCol = 'key'; }
        if ($valCol === null) { $valCol = 'value'; }

        self::$columns = ['key' => $keyCol, 'value' => $valCol];
        return self::$columns;
    }

    public static function get(mysqli $conn, string $key, $default = null) {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $cols = self::resolveColumns($conn);
        $sql = sprintf("SELECT `%s` AS v FROM %s WHERE `%s` = ? LIMIT 1", $cols['value'], self::$table, $cols['key']);
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $default;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $val = $row['v'] ?? $default;
        self::$cache[$key] = $val;
        return $val;
    }

    public static function set(mysqli $conn, string $key, string $value): bool {
        $cols = self::resolveColumns($conn);
        $sql = sprintf(
            "INSERT INTO %s(`%s`,`%s`) VALUES(?,?) ON DUPLICATE KEY UPDATE `%s` = VALUES(`%s`)",
            self::$table, $cols['key'], $cols['value'], $cols['value'], $cols['value']
        );
        $stmt = $conn->prepare($sql);
        if (!$stmt) { return false; }
        $stmt->bind_param('ss', $key, $value);
        $ok = $stmt->execute();
        $stmt->close();
        self::$cache[$key] = $value;
        return $ok;
    }
}
?>



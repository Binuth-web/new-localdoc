<?php

/** Map UI login tab roles to medconnect_db user roles */
function mapLoginRoleToDb(string $uiRole): string
{
    if ($uiRole === 'medical_staff') {
        return 'staff';
    }
    return $uiRole;
}

/** Split full_name into first and last for forms */
function splitFullName(string $fullName): array
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return ['first_name' => '', 'last_name' => ''];
    }
    $parts = preg_split('/\s+/', $fullName, 2);
    return [
        'first_name' => $parts[0],
        'last_name' => $parts[1] ?? '',
    ];
}

/** Whether opd_sessions / opd_tokens tables exist (MedConnect Kandy migrations) */
function hasOpdTables(PDO $pdo): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $pdo->query('SELECT 1 FROM opd_sessions LIMIT 1');
        $cached = true;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/** Parse "8:00 AM – 6:00 PM" style hours into open/close TIME strings */
function parseHoursRange(?string $hours): array
{
    if (!$hours || !preg_match(
        '/(\d{1,2}):(\d{2})\s*(AM|PM)?\s*[–\-—]\s*(\d{1,2}):(\d{2})\s*(AM|PM)?/i',
        $hours,
        $m
    )) {
        return ['08:00:00', '20:00:00'];
    }

    $to24 = static function (int $h, int $min, string $ampm): string {
        $ampm = strtoupper($ampm);
        if ($ampm === 'PM' && $h < 12) {
            $h += 12;
        }
        if ($ampm === 'AM' && $h === 12) {
            $h = 0;
        }
        return sprintf('%02d:%02d:00', $h, $min);
    };

    return [
        $to24((int) $m[1], (int) $m[2], $m[3] ?? 'AM'),
        $to24((int) $m[4], (int) $m[5], $m[6] ?? 'PM'),
    ];
}

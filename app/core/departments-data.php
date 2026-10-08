<?php

declare(strict_types=1);
require_once __DIR__ . '/database.php';

function dept_company_id(int $userId): int
{
    $q = database()->prepare("SELECT u.company_id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.status='active' AND r.role_key='hr'");
    $q->execute([$userId]);
    $id = $q->fetchColumn();
    if (!$id) throw new RuntimeException('HR authorization required.');
    return (int)$id;
}
function dept_list(int $companyId): array
{
    $q = database()->prepare("SELECT d.id,d.name,d.department_code,d.description,d.status,
      (SELECT COUNT(*) FROM employees e WHERE e.department_id=d.id AND e.company_id=d.company_id) employee_count,
      (SELECT COUNT(*) FROM job_positions p WHERE p.department_id=d.id AND p.company_id=d.company_id) position_count,
      (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') FROM users u JOIN roles r ON r.id=u.role_id AND r.role_key='department_head' WHERE u.company_id=d.company_id AND u.department_id=d.id AND u.status='active') manager_name
      FROM departments d WHERE d.company_id=? ORDER BY d.name");
    $q->execute([$companyId]);
    return $q->fetchAll();
}
function dept_positions(int $companyId): array
{
    $q = database()->prepare('SELECT p.id,p.department_id,p.name,p.status FROM job_positions p WHERE p.company_id=? ORDER BY p.name');
    $q->execute([$companyId]);
    return $q->fetchAll();
}
function dept_clean($v, int $max, string $label, bool $required = true): string
{
    $s = trim((string)$v);
    if (($required && $s === '') || (function_exists('mb_strlen') ? mb_strlen($s) : strlen($s)) > $max) throw new InvalidArgumentException("Invalid {$label} (maximum {$max} characters).");
    return $s;
}
function dept_valid_code($v): string
{
    $s = strtoupper(dept_clean($v, 24, 'department code'));
    if (!preg_match('/^[A-Z0-9_-]{2,24}$/', $s)) throw new InvalidArgumentException('Department code must be 2–24 letters, numbers, _ or -.');
    return $s;
}
function dept_new(int $userId, array $input): void
{
    $company = dept_company_id($userId);
    $name = dept_clean($input['name'] ?? '', 120, 'department name');
    $code = dept_valid_code($input['department_code'] ?? '');
    $description = dept_clean($input['description'] ?? '', 3000, 'description', false);
    $positions = array_values(array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($input['positions'] ?? ''))))));
    if (count($positions) > 50) throw new InvalidArgumentException('Maximum 50 positions per submission.');
    foreach ($positions as $p) dept_clean($p, 120, 'job position');
    $db = database();
    $db->beginTransaction();
    try {
        $q = $db->prepare("INSERT INTO departments(company_id,name,department_code,description,status) VALUES(?,?,?,?,'active')");
        $q->execute([$company, $name, $code, $description]);
        $department = (int)$db->lastInsertId();
        $p = $db->prepare('INSERT INTO job_positions(company_id,department_id,name) VALUES(?,?,?)');
        foreach ($positions as $position) $p->execute([$company, $department, $position]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        if ($e instanceof PDOException && ($e->errorInfo[1] ?? 0) === 1062) throw new InvalidArgumentException('Department name, code or position already exists.');
        throw $e;
    }
}
function dept_add_position(int $userId, array $input): void
{
    $company = dept_company_id($userId);
    $id = (int)($input['department_id'] ?? 0);
    $name = dept_clean($input['name'] ?? '', 120, 'position');
    $db = database();
    $q = $db->prepare("SELECT id FROM departments WHERE company_id=? AND id=? AND status='active'");
    $q->execute([$company, $id]);
    if (!$q->fetchColumn()) throw new InvalidArgumentException('Select an active department.');
    try {
        $q = $db->prepare('INSERT INTO job_positions(company_id,department_id,name) VALUES(?,?,?)');
        $q->execute([$company, $id, $name]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) throw new InvalidArgumentException('This position already exists in the department.');
        throw $e;
    }
}
function dept_parse_xlsx(string $path): array
{
    if (!class_exists('ZipArchive') || !class_exists('SimpleXMLElement')) throw new RuntimeException('PHP zip and SimpleXML extensions are required for Excel import.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new InvalidArgumentException('Invalid Excel workbook.');
    try {
        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            if (strlen($ss) > 3000000) throw new InvalidArgumentException('Shared strings are too large.');
            $xml = @simplexml_load_string($ss, 'SimpleXMLElement', LIBXML_NONET);
            if ($xml === false) throw new InvalidArgumentException('Invalid shared strings.');
            foreach ($xml->xpath('//*[local-name()="si"]') as $si) {
                $text = '';
                foreach ($si->xpath('.//*[local-name()="t"]') as $t) $text .= (string)$t;
                $shared[] = $text;
            }
        }
        $raw = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($raw === false || strlen($raw) > 5000000) throw new InvalidArgumentException('Excel worksheet missing or too large.');
        $xml = @simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET);
        if ($xml === false) throw new InvalidArgumentException('Unable to read Excel worksheet.');
        $rows = [];
        foreach ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
            $cells = [];
            foreach ($row->xpath('./*[local-name()="c"]') as $c) {
                $ref = (string)$c['r'];
                preg_match('/^[A-Z]+/', $ref, $m);
                $col = $m[0] ?? '';
                if (!in_array($col, ['A', 'B', 'C', 'D'], true)) continue;
                $kind = (string)$c['t'];
                $node = $kind === 'inlineStr' ? $c->xpath('./*[local-name()="is"]//*[local-name()="t"]') : $c->xpath('./*[local-name()="v"]');
                $value = '';
                foreach (($node ?: []) as $part) $value .= (string)$part;
                if ($kind === 's') $value = $shared[(int)$value] ?? '';
                $cells[$col] = trim($value);
            }
            if (array_filter($cells, fn($v) => $v !== '')) $rows[] = ['row' => (int)$row['r'], 'code' => $cells['A'] ?? '', 'name' => $cells['B'] ?? '', 'position' => $cells['C'] ?? '', 'description' => $cells['D'] ?? ''];
            if (count($rows) > 501) throw new InvalidArgumentException('Maximum 500 data rows.');
        }
        if (!$rows) throw new InvalidArgumentException('Worksheet is empty.');
        $header = array_shift($rows);
        if (strtolower($header['code']) !== 'department code' || strtolower($header['name']) !== 'department name' || strtolower($header['position']) !== 'job position') throw new InvalidArgumentException('Wrong template headings. Download and use the provided template.');
        return $rows;
    } finally {
        $zip->close();
    }
}
function dept_validate_import(int $company, array $rows): array
{
    if (!$rows) throw new InvalidArgumentException('No data rows to import.');
    $seen = [];
    $validated = [];
    foreach ($rows as $row) {
        try {
            $code = dept_valid_code($row['code']);
            $name = dept_clean($row['name'], 120, 'department name');
            $position = dept_clean($row['position'], 120, 'job position');
            $description = dept_clean($row['description'], 3000, 'description', false);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Excel row ' . $row['row'] . ': ' . $e->getMessage());
        }
        $k = strtolower($code);
        if (isset($seen[$k]) && strcasecmp($seen[$k], $name) !== 0) throw new InvalidArgumentException('Excel row ' . $row['row'] . ': department code maps to two names.');
        $seen[$k] = $name;
        $validated[] = ['code' => $code, 'name' => $name, 'position' => $position, 'description' => $description, 'row' => $row['row']];
    }
    $db = database();
    $q = $db->prepare('SELECT department_code,name FROM departments WHERE company_id=?');
    $q->execute([$company]);
    $existing = $q->fetchAll();
    foreach ($validated as $r) foreach ($existing as $old) {
        if (($old['department_code'] !== null && strcasecmp($old['department_code'], $r['code']) === 0 && strcasecmp($old['name'], $r['name']) !== 0) || (strcasecmp($old['name'], $r['name']) === 0 && $old['department_code'] !== null && strcasecmp($old['department_code'], $r['code']) !== 0)) throw new InvalidArgumentException('Excel row ' . $r['row'] . ': conflicts with an existing department.');
    }
    return $validated;
}
function dept_import_commit(int $actor, array $rows): array
{
    $company = dept_company_id($actor);
    $rows = dept_validate_import($company, $rows);
    $db = database();
    $db->beginTransaction();
    $depts = 0;
    $positions = 0;
    try {
        $find = $db->prepare('SELECT id,department_code FROM departments WHERE company_id=? AND name=? FOR UPDATE');
        $new = $db->prepare("INSERT INTO departments(company_id,name,department_code,description,status) VALUES(?,?,?,?,'active')");
        $codeUp = $db->prepare('UPDATE departments SET department_code=? WHERE id=? AND department_code IS NULL');
        $insert = $db->prepare('INSERT IGNORE INTO job_positions(company_id,department_id,name) VALUES(?,?,?)');
        $ids = [];
        foreach ($rows as $r) {
            $key = strtolower($r['name']);
            if (!isset($ids[$key])) {
                $find->execute([$company, $r['name']]);
                $d = $find->fetch();
                if ($d) {
                    $id = (int)$d['id'];
                    $codeUp->execute([$r['code'], $id]);
                } else {
                    $new->execute([$company, $r['name'], $r['code'], $r['description']]);
                    $id = (int)$db->lastInsertId();
                    $depts++;
                }
                $ids[$key] = $id;
            }
            $insert->execute([$company, $ids[$key], $r['position']]);
            $positions += $insert->rowCount();
        }
        $db->commit();
        return ['departments' => $depts, 'positions' => $positions];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

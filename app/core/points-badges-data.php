<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

const PB_MAX_POINTS_PER_AWARD = 1000;

function pb_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function pb_hr_company_id(int $actorId): int
{
    $q = database()->prepare(
        "SELECT u.company_id
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?
           AND u.status = 'active'
           AND r.role_key = 'hr'
         LIMIT 1"
    );
    $q->execute([$actorId]);
    $companyId = (int)$q->fetchColumn();
    if ($companyId < 1) {
        throw new RuntimeException('HR authorization required.');
    }
    return $companyId;
}

function pb_valid_date(string $value): string
{
    $value = trim($value);
    $timezone = new DateTimeZone('Asia/Manila');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);

    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Choose a valid award date.');
    }

    $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0, 0);
    $date = $date->setTime(0, 0, 0);

    if ($date > $today) {
        throw new InvalidArgumentException('Award date cannot be in the future.');
    }

    return $value;
}

function pb_clean_required(mixed $value, string $label, int $maxLength): string
{
    $value = trim((string)$value);
    if ($value === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    if (pb_text_length($value) > $maxLength) {
        throw new InvalidArgumentException($label . ' must be ' . $maxLength . ' characters or fewer.');
    }
    return $value;
}

function pb_clean_optional(mixed $value, string $label, int $maxLength): ?string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return null;
    }
    if (pb_text_length($value) > $maxLength) {
        throw new InvalidArgumentException($label . ' must be ' . $maxLength . ' characters or fewer.');
    }
    return $value;
}

function pb_employee(int $companyId, int $employeeId): array
{
    if ($employeeId < 1) {
        throw new InvalidArgumentException('Choose a valid employee.');
    }
    $q = database()->prepare(
        "SELECT e.id,e.company_id,e.employee_code,e.full_name,e.job_title,e.department_id,d.name AS department_name
         FROM employees e
         JOIN departments d ON d.id=e.department_id AND d.company_id=e.company_id
         WHERE e.id=? AND e.company_id=? AND e.employment_status='active'
         LIMIT 1"
    );
    $q->execute([$employeeId, $companyId]);
    $employee = $q->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        throw new InvalidArgumentException('Employee was not found or is not active.');
    }
    return $employee;
}

function pb_icon_options(): array
{
    return [
        'duck' => ['emoji' => '🦆', 'label' => 'Duck'],
        'fish' => ['emoji' => '🐟', 'label' => 'Fish'],
        'star' => ['emoji' => '⭐', 'label' => 'Star'],
        'trophy' => ['emoji' => '🏆', 'label' => 'Trophy'],
        'leaf' => ['emoji' => '🍃', 'label' => 'Leaf'],
        'flame' => ['emoji' => '🔥', 'label' => 'Flame'],
        'crown' => ['emoji' => '👑', 'label' => 'Crown'],
        'book' => ['emoji' => '📘', 'label' => 'Book'],
        'target' => ['emoji' => '🎯', 'label' => 'Target'],
        'medal' => ['emoji' => '🏅', 'label' => 'Medal'],
    ];
}

function pb_badge_icon(string $key): string
{
    $options = pb_icon_options();
    return (string)($options[$key]['emoji'] ?? '⭐');
}

function pb_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'badge_' . bin2hex(random_bytes(4));
    }
    return substr($slug, 0, 70);
}

function pb_award_points(int $actorId, array $input): string
{
    $companyId = pb_hr_company_id($actorId);
    $employee = pb_employee($companyId, (int)($input['employee_id'] ?? 0));
    $pointsRaw = trim((string)($input['points'] ?? ''));
    if ($pointsRaw === '' || !ctype_digit($pointsRaw)) {
        throw new InvalidArgumentException('Points must be a whole number.');
    }
    $points = (int)$pointsRaw;
    if ($points < 1 || $points > PB_MAX_POINTS_PER_AWARD) {
        throw new InvalidArgumentException('Points must be from 1 to ' . PB_MAX_POINTS_PER_AWARD . ' per award.');
    }
    $sourceName = pb_clean_required($input['source_name'] ?? '', 'Instructor / source', 120);
    $reason = pb_clean_optional($input['reason'] ?? null, 'Reason', 255);
    $awardedOn = pb_valid_date((string)($input['awarded_on'] ?? ''));

    $q = database()->prepare(
        "INSERT INTO employee_point_entries (company_id,employee_id,points,source_name,reason,awarded_on,posted_by)
         VALUES (?,?,?,?,?,?,?)"
    );
    $q->execute([$companyId,(int)$employee['id'],$points,$sourceName,$reason,$awardedOn,$actorId]);

    return sprintf('Posted %d point%s to %s.', $points, $points === 1 ? '' : 's', (string)$employee['full_name']);
}

function pb_create_badge(int $actorId, array $input): string
{
    $companyId = pb_hr_company_id($actorId);
    $name = pb_clean_required($input['badge_name'] ?? '', 'Badge name', 120);
    $description = pb_clean_optional($input['badge_description'] ?? null, 'Badge description', 255);
    $iconKey = trim((string)($input['icon_key'] ?? ''));
    if (!array_key_exists($iconKey, pb_icon_options())) {
        throw new InvalidArgumentException('Choose a valid badge icon.');
    }
    $badgeKey = pb_slug($name);
    $existing = database()->prepare("SELECT id FROM reward_badges WHERE company_id=? AND badge_key=? LIMIT 1");
    $existing->execute([$companyId,$badgeKey]);
    if ($existing->fetchColumn()) {
        throw new InvalidArgumentException('A badge with this name already exists.');
    }
    $q = database()->prepare(
        "INSERT INTO reward_badges (company_id,badge_key,badge_name,description,icon_key,status,created_by)
         VALUES (?,?,?,?,?,'active',?)"
    );
    $q->execute([$companyId,$badgeKey,$name,$description,$iconKey,$actorId]);
    return 'Badge "' . $name . '" created successfully.';
}

function pb_award_badge(int $actorId, array $input): string
{
    $companyId = pb_hr_company_id($actorId);
    $employee = pb_employee($companyId, (int)($input['employee_id'] ?? 0));
    $badgeId = (int)($input['badge_id'] ?? 0);
    $badgeQuery = database()->prepare(
        "SELECT id,badge_name FROM reward_badges
         WHERE id=? AND company_id=? AND status='active' LIMIT 1"
    );
    $badgeQuery->execute([$badgeId,$companyId]);
    $badge = $badgeQuery->fetch(PDO::FETCH_ASSOC);
    if (!$badge) {
        throw new InvalidArgumentException('Choose a valid active badge.');
    }
    $duplicate = database()->prepare("SELECT id FROM employee_badges WHERE employee_id=? AND badge_id=? LIMIT 1");
    $duplicate->execute([(int)$employee['id'],$badgeId]);
    if ($duplicate->fetchColumn()) {
        throw new InvalidArgumentException((string)$employee['full_name'] . ' already has this badge.');
    }
    $sourceName = pb_clean_required($input['source_name'] ?? '', 'Instructor / source', 120);
    $note = pb_clean_optional($input['note'] ?? null, 'Badge note', 255);
    $awardedOn = pb_valid_date((string)($input['awarded_on'] ?? ''));
    $q = database()->prepare(
        "INSERT INTO employee_badges (company_id,employee_id,badge_id,source_name,note,awarded_on,posted_by)
         VALUES (?,?,?,?,?,?,?)"
    );
    $q->execute([$companyId,(int)$employee['id'],$badgeId,$sourceName,$note,$awardedOn,$actorId]);
    return sprintf('Awarded %s to %s.', (string)$badge['badge_name'], (string)$employee['full_name']);
}

function pb_tree_stage(int $points): array
{
    $points = max(0, $points);
    $stages = [
        ['min'=>0,'label'=>'Seed'],
        ['min'=>10,'label'=>'Sprout'],
        ['min'=>25,'label'=>'Seedling'],
        ['min'=>40,'label'=>'Sapling'],
        ['min'=>60,'label'=>'Young Tree'],
        ['min'=>80,'label'=>'Flourishing Tree'],
        ['min'=>100,'label'=>'Full Growth'],
    ];
    $index = 0;
    foreach ($stages as $stageIndex => $stage) {
        if ($points >= (int)$stage['min']) {
            $index = $stageIndex;
        }
    }
    $current = $stages[$index];
    $next = $stages[$index + 1] ?? null;
    if ($next === null) {
        $progress = 100.0;
        $pointsToNext = 0;
    } else {
        $range = max(1, (int)$next['min'] - (int)$current['min']);
        $progress = round(100 * ($points - (int)$current['min']) / $range, 1);
        $progress = max(0.0, min(100.0, $progress));
        $pointsToNext = max(0, (int)$next['min'] - $points);
    }
    return [
        'index'=>$index,
        'label'=>(string)$current['label'],
        'min'=>(int)$current['min'],
        'next'=>$next,
        'progress'=>$progress,
        'points_to_next'=>$pointsToNext,
        'stages'=>$stages,
    ];
}

function pb_hr_data(int $actorId): array
{
    $db = database();
    $companyId = pb_hr_company_id($actorId);
    $employeesQuery = $db->prepare(
        "SELECT e.id,e.employee_code,e.full_name,e.job_title,d.name AS department_name,
                COALESCE(p.total_points,0) AS total_points,
                COALESCE(b.badge_count,0) AS badge_count
         FROM employees e
         JOIN departments d ON d.id=e.department_id AND d.company_id=e.company_id
         LEFT JOIN (
            SELECT employee_id,SUM(points) AS total_points
            FROM employee_point_entries WHERE company_id=? GROUP BY employee_id
         ) p ON p.employee_id=e.id
         LEFT JOIN (
            SELECT employee_id,COUNT(*) AS badge_count
            FROM employee_badges WHERE company_id=? GROUP BY employee_id
         ) b ON b.employee_id=e.id
         WHERE e.company_id=? AND e.employment_status='active'
         ORDER BY total_points DESC,e.full_name"
    );
    $employeesQuery->execute([$companyId,$companyId,$companyId]);
    $employees = $employeesQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($employees as $index => &$employee) {
        $employee['rank'] = $index + 1;
        $employee['total_points'] = (int)$employee['total_points'];
        $employee['badge_count'] = (int)$employee['badge_count'];
        $employee['tree'] = pb_tree_stage((int)$employee['total_points']);
    }
    unset($employee);

    $badgeQuery = $db->prepare(
        "SELECT id,badge_key,badge_name,description,icon_key
         FROM reward_badges WHERE company_id=? AND status='active' ORDER BY badge_name"
    );
    $badgeQuery->execute([$companyId]);
    $badges = $badgeQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($badges as &$badge) {
        $badge['icon'] = pb_badge_icon((string)$badge['icon_key']);
    }
    unset($badge);

    $summaryQuery = $db->prepare(
        "SELECT COALESCE(SUM(points),0) AS total_points,COUNT(DISTINCT employee_id) AS employees_with_points
         FROM employee_point_entries WHERE company_id=?"
    );
    $summaryQuery->execute([$companyId]);
    $pointSummary = $summaryQuery->fetch(PDO::FETCH_ASSOC) ?: [];

    $badgeSummaryQuery = $db->prepare("SELECT COUNT(*) FROM employee_badges WHERE company_id=?");
    $badgeSummaryQuery->execute([$companyId]);

    $recentPointsQuery = $db->prepare(
        "SELECT pe.id,pe.points,pe.source_name,pe.reason,pe.awarded_on,pe.created_at,e.full_name,e.employee_code
         FROM employee_point_entries pe
         JOIN employees e ON e.id=pe.employee_id AND e.company_id=pe.company_id
         WHERE pe.company_id=? ORDER BY pe.created_at DESC,pe.id DESC LIMIT 12"
    );
    $recentPointsQuery->execute([$companyId]);

    $recentBadgesQuery = $db->prepare(
        "SELECT eb.id,eb.source_name,eb.note,eb.awarded_on,eb.created_at,e.full_name,e.employee_code,rb.badge_name,rb.icon_key
         FROM employee_badges eb
         JOIN employees e ON e.id=eb.employee_id AND e.company_id=eb.company_id
         JOIN reward_badges rb ON rb.id=eb.badge_id AND rb.company_id=eb.company_id
         WHERE eb.company_id=? ORDER BY eb.created_at DESC,eb.id DESC LIMIT 12"
    );
    $recentBadgesQuery->execute([$companyId]);

    return [
        'company_id'=>$companyId,
        'employees'=>$employees,
        'badges'=>$badges,
        'summary'=>[
            'total_points'=>(int)($pointSummary['total_points'] ?? 0),
            'employees_with_points'=>(int)($pointSummary['employees_with_points'] ?? 0),
            'badge_awards'=>(int)$badgeSummaryQuery->fetchColumn(),
            'top_employee'=>$employees[0] ?? null,
        ],
        'recent_points'=>$recentPointsQuery->fetchAll(PDO::FETCH_ASSOC),
        'recent_badges'=>$recentBadgesQuery->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function pb_employee_growth_by_employee(int $companyId, int $employeeId): array
{
    $db = database();
    $employee = pb_employee($companyId,$employeeId);
    $pointsQuery = $db->prepare("SELECT COALESCE(SUM(points),0) FROM employee_point_entries WHERE company_id=? AND employee_id=?");
    $pointsQuery->execute([$companyId,$employeeId]);
    $totalPoints = (int)$pointsQuery->fetchColumn();

    $historyQuery = $db->prepare(
        "SELECT points,source_name,reason,awarded_on,created_at
         FROM employee_point_entries
         WHERE company_id=? AND employee_id=?
         ORDER BY awarded_on DESC,id DESC LIMIT 20"
    );
    $historyQuery->execute([$companyId,$employeeId]);

    $badgeQuery = $db->prepare(
        "SELECT rb.badge_name,rb.description,rb.icon_key,eb.source_name,eb.note,eb.awarded_on
         FROM employee_badges eb
         JOIN reward_badges rb ON rb.id=eb.badge_id AND rb.company_id=eb.company_id
         WHERE eb.company_id=? AND eb.employee_id=?
         ORDER BY eb.awarded_on DESC,eb.id DESC"
    );
    $badgeQuery->execute([$companyId,$employeeId]);
    $badges = $badgeQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($badges as &$badge) {
        $badge['icon'] = pb_badge_icon((string)$badge['icon_key']);
    }
    unset($badge);

    $leaderboardQuery = $db->prepare(
        "SELECT e.id,COALESCE(SUM(pe.points),0) AS total_points
         FROM employees e
         LEFT JOIN employee_point_entries pe ON pe.employee_id=e.id AND pe.company_id=e.company_id
         WHERE e.company_id=? AND e.employment_status='active'
         GROUP BY e.id,e.full_name
         ORDER BY total_points DESC,e.full_name"
    );
    $leaderboardQuery->execute([$companyId]);
    $rank = null;
    $employeeCount = 0;
    foreach ($leaderboardQuery->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
        $employeeCount++;
        if ((int)$row['id'] === $employeeId) {
            $rank = $index + 1;
        }
    }

    return [
        'employee'=>$employee,
        'total_points'=>$totalPoints,
        'rank'=>$rank,
        'employee_count'=>$employeeCount,
        'tree'=>pb_tree_stage($totalPoints),
        'badges'=>$badges,
        'point_history'=>$historyQuery->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function pb_employee_growth_for_user(int $userId): array
{
    $q = database()->prepare(
        "SELECT e.id,e.company_id
         FROM employees e
         JOIN users u ON u.id=e.user_id AND u.company_id=e.company_id
         JOIN roles r ON r.id=u.role_id
         WHERE u.id=? AND u.status='active' AND r.role_key='employee' AND e.employment_status='active'
         LIMIT 1"
    );
    $q->execute([$userId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Active employee profile was not found.');
    }
    return pb_employee_growth_by_employee((int)$row['company_id'],(int)$row['id']);
}

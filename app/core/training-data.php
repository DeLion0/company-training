<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

function training_company_id(int $actorId): int {
    $q = database()->prepare("SELECT u.company_id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.status='active' AND r.role_key='hr'");
    $q->execute([$actorId]);
    $id = $q->fetchColumn();
    if (!$id) throw new RuntimeException('HR authorization required.');
    return (int)$id;
}
function training_text(array $input, string $key, int $max): string {
    $value=trim((string)($input[$key] ?? ''));
    $length=function_exists('mb_strlen')?mb_strlen($value):strlen($value);
    if ($value==='' || $length>$max) throw new InvalidArgumentException('Invalid ' . $key . ' (maximum ' . $max . ' characters).');
    return $value;
}
function training_date(string $value): DateTimeImmutable {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if (!$d || $d->format('Y-m-d')!==$value) throw new InvalidArgumentException('Invalid training date.');
    return $d;
}
function training_time(string $value): string {
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$value)) throw new InvalidArgumentException('Invalid training time.');
    return $value;
}
function training_build_schedule(array $input): array {
    $start=training_date((string)($input['startDate']??''));
    $end=training_date((string)($input['endDate']??''));
    $days=(int)$start->diff($end)->format('%r%a');
    if ($days<0 || $days>366) throw new InvalidArgumentException('Select a date range of 0–366 days.');
    $rawDay=(string)($input['weekday']??'');
    $rawCount=(string)($input['meetingCount']??'');
    if (!preg_match('/^[0-6]$/',$rawDay) || !preg_match('/^[1-9][0-9]{0,2}$/',$rawCount)) throw new InvalidArgumentException('Invalid recurrence settings.');
    $weekday=(int)$rawDay; $count=(int)$rawCount;
    if ($count<1 || $count>100) throw new InvalidArgumentException('Meeting count must be 1–100.');
    $t1=training_time((string)($input['startTime']??''));
    $t2=training_time((string)($input['endTime']??''));
    if ($t1 >= $t2) throw new InvalidArgumentException('End time must be later than start time.');
    $dates=[];
    for ($date=$start;$date<=$end;$date=$date->modify('+1 day')) {
        if ((int)$date->format('w')===$weekday) $dates[]=$date->format('Y-m-d');
    }
    if (count($dates)<$count) throw new InvalidArgumentException('Not enough matching weekdays within date range.');
    return [array_slice($dates,0,$count),$start->format('Y-m-d'),$end->format('Y-m-d'),$weekday,$count,$t1,$t2];
}
function training_create(int $actorId, array $input): int {
    $company=training_company_id($actorId);
    $title=training_text($input,'title',160);
    $purpose=training_text($input,'purpose',10000);
    $objectives=training_text($input,'objectives',10000);
    $instructors=training_text($input,'instructors',500);
    $venue=training_text($input,'venue',255);
    $rawCapacity=(string)($input['capacity']??'');
    if (!preg_match('/^[1-9][0-9]{0,4}$/',$rawCapacity) || (int)$rawCapacity>10000) throw new InvalidArgumentException('Capacity must be 1–10000.');
    [$dates,$start,$end,$weekday,$count,$t1,$t2]=training_build_schedule($input);
    $db=database();
    $db->beginTransaction();
    try {
        $q=$db->prepare('INSERT INTO training_programs (company_id,created_by,title,purpose,objectives,instructors,venue,capacity,range_start,range_end,weekday,meeting_count,start_time,end_time,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'published\')');
        $q->execute([$company,$actorId,$title,$purpose,$objectives,$instructors,$venue,(int)$rawCapacity,$start,$end,$weekday,$count,$t1,$t2]);
        $programId=(int)$db->lastInsertId();
        $q=$db->prepare('INSERT INTO training_sessions (program_id,session_number,session_date,start_time,end_time) VALUES (?,?,?,?,?)');
        foreach ($dates as $i=>$date) $q->execute([$programId,$i+1,$date,$t1,$t2]);
        $db->commit();
        return $programId;
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        throw $ex;
    }
}
function training_overview(int $actorId): array {
    $company=training_company_id($actorId);
    $db=database();
    $q=$db->prepare("SELECT COUNT(*) AS programs,COALESCE(SUM(capacity),0) AS capacity FROM training_programs WHERE company_id=? AND status='published'");
    $q->execute([$company]); $stats=$q->fetch(PDO::FETCH_ASSOC);
    $q=$db->prepare("SELECT COUNT(*) FROM training_sessions s JOIN training_programs p ON p.id=s.program_id WHERE p.company_id=? AND p.status='published' AND s.session_date>=CURRENT_DATE()");
    $q->execute([$company]); $stats['upcoming']=(int)$q->fetchColumn();
    $q=$db->prepare('SELECT p.id,p.title,p.purpose,p.objectives,p.instructors,p.venue,p.capacity,p.range_start,p.range_end,p.weekday,p.meeting_count,p.start_time,p.end_time,p.status,p.created_at FROM training_programs p WHERE p.company_id=? ORDER BY p.created_at DESC,p.id DESC LIMIT 100');
    $q->execute([$company]); $programs=$q->fetchAll(PDO::FETCH_ASSOC);
    $ids=array_column($programs,'id'); $sessions=[];
    if ($ids) {
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $q=$db->prepare("SELECT program_id,session_number,session_date,start_time,end_time FROM training_sessions WHERE program_id IN ($ph) ORDER BY program_id,session_number");
        $q->execute($ids);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $s) $sessions[(int)$s['program_id']][]=$s;
    }
    foreach($programs as &$p) $p['sessions']=$sessions[(int)$p['id']]??[];
    unset($p);
    return ['stats'=>$stats,'programs'=>$programs];
}

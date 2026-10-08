<?php
declare(strict_types=1);
require_once __DIR__.'/database.php';

function hr_company_id(int $userId): int {
    $st=database()->prepare('SELECT company_id FROM users WHERE id=? AND status=?');
    $st->execute([$userId,'active']);
    $id=$st->fetchColumn();
    if (!$id) throw new RuntimeException('HR company not found.');
    return (int)$id;
}
function hr_departments(int $companyId): array {
    $st=database()->prepare('SELECT id,name FROM departments WHERE company_id=? ORDER BY name');
    $st->execute([$companyId]);return $st->fetchAll();
}
function hr_managers(int $companyId): array {
    $st=database()->prepare("SELECT u.id,u.full_name,u.department_id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.company_id=? AND u.status='active' AND r.role_key='department_head' ORDER BY u.full_name");
    $st->execute([$companyId]);return $st->fetchAll();
}
function hr_employees(int $companyId): array {
    $sql="SELECT e.id,e.employee_code,e.full_name,e.job_title,e.employment_status,e.joined_at,d.name department_name, u.email, u.status account_status,m.full_name manager_name FROM employees e JOIN departments d ON d.id=e.department_id AND d.company_id=e.company_id LEFT JOIN users u ON u.id=e.user_id AND u.company_id=e.company_id LEFT JOIN users m ON m.id=e.manager_user_id AND m.company_id=e.company_id WHERE e.company_id=? ORDER BY e.created_at DESC,e.id DESC LIMIT 1000";
    $st=database()->prepare($sql);$st->execute([$companyId]);return $st->fetchAll();
}
function employee_slug(string $s): string {
    if (function_exists('iconv')) { $converted=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);if($converted!==false)$s=$converted; }
    return trim((string)preg_replace('/[^a-z0-9]+/','',strtolower($s)));
}
function employee_email_candidate(string $first,string $last,string $domain,int $suffix=1): string {
    $local=employee_slug($first).'.'.employee_slug($last);
    if ($suffix>1)$local.='.'.$suffix;
    return $local.'@'.$domain;
}
function employee_domain(): string {
    $domain=strtolower(trim(db_env('WORK_EMAIL_DOMAIN','company.com')));
    if (!preg_match('/^(?=.{4,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])\.[a-z]{2,}$/',$domain)) throw new RuntimeException('Configure a valid WORK_EMAIL_DOMAIN.');
    return $domain;
}
function employee_demo_password(string $last): string {
    $letters=preg_replace('/[^a-zA-Z]/','', (string) (@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$last) ?: $last));
    if(strlen($letters)<2) throw new InvalidArgumentException('Last name needs at least two letters for demo password.');
    return '#'.ucfirst(strtolower(substr($letters,0,2))).'8080';
}
function employee_random_password(): string {return rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');}
function employee_make_password(string $last): string {
    // Weak patterned credentials are forbidden on public deployments.
    $mode=db_env('APP_ENV','production');
    if ($mode==='local' && db_env('ALLOW_DEMO_PASSWORD','0')==='1') return employee_demo_password($last);
    return employee_random_password();
}
function hr_create_employee(int $actorId,array $input): array {
    $companyId=hr_company_id($actorId);
    $first=trim((string)($input['first_name']??''));$last=trim((string)($input['last_name']??''));
    $code=trim((string)($input['employee_code']??''));$job=trim((string)($input['job_title']??''));
    $dept=(int)($input['department_id']??0);$manager=(int)($input['manager_user_id']??0);
    $joined=trim((string)($input['joined_at']??''));
    if(!preg_match('/^[\p{L} .\'-]{2,80}$/u',$first)||!preg_match('/^[\p{L} .\'-]{2,80}$/u',$last))throw new InvalidArgumentException('Enter valid first and last names (2–80 characters).');
    if(!preg_match('/^[A-Za-z0-9_-]{2,40}$/',$code))throw new InvalidArgumentException('Employee ID must be 2–40 letters, numbers, _ or -.');
    if($job===''||strlen($job)>120)throw new InvalidArgumentException('Enter a job title (up to 120 characters).');
    if($joined!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$joined)||date('Y-m-d',strtotime($joined))!==$joined))throw new InvalidArgumentException('Enter a valid joining date.');
    if(employee_slug($first)===''||employee_slug($last)==='')throw new InvalidArgumentException('Enter names using letters supported in an email address.');
    $db=database();
    $st=$db->prepare('SELECT id FROM departments WHERE id=? AND company_id=?');$st->execute([$dept,$companyId]);if(!$st->fetchColumn())throw new InvalidArgumentException('Choose a department in your company.');
    $st=$db->prepare("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.company_id=? AND u.department_id=? AND u.status='active' AND r.role_key='department_head'");
    $st->execute([$manager,$companyId,$dept]);if(!$st->fetchColumn())throw new InvalidArgumentException('Choose an active manager assigned to this department.');
    $st=$db->prepare('SELECT 1 FROM employees WHERE company_id=? AND employee_code=? LIMIT 1');$st->execute([$companyId,$code]);if($st->fetchColumn())throw new InvalidArgumentException('Employee ID already exists.');
    $domain=employee_domain();$password=employee_make_password($last);$hash=password_hash($password,PASSWORD_DEFAULT);
    $role=$db->query("SELECT id FROM roles WHERE role_key='employee' LIMIT 1")->fetchColumn();
    if(!$role)throw new RuntimeException('Employee role is missing. Run database/schema.sql.');
    $full=$first.' '.$last;
    for($attempt=1;$attempt<=100;$attempt++) {
        $email=employee_email_candidate($first,$last,$domain,$attempt);
        if(strlen($email)>254)throw new InvalidArgumentException('Generated email is too long.');
        $db->beginTransaction();
        try {
            $st=$db->prepare("INSERT INTO users (company_id,department_id,role_id,full_name,email,password_hash,status) VALUES (?,?,?,?,?,?,'inactive')");
            $st->execute([$companyId,$dept,$role,$full,$email,$hash]);
            $userId=(int)$db->lastInsertId();
            $st=$db->prepare("INSERT INTO employees (company_id,department_id,user_id,employee_code,full_name,job_title,employment_status,joined_at,first_name,last_name,manager_user_id,work_email) VALUES (?,?,?,?,?,?,'active',?,?,?,?,?)");
            $st->execute([$companyId,$dept,$userId,$code,$full,$job,$joined?:null,$first,$last,$manager,$email]);
            $db->commit();
            return ['email'=>$email,'temporary_password'=>$password,'employee_name'=>$full,'employee_code'=>$code,'manager_user_id'=>$manager];
        } catch(PDOException $ex) {
            if($db->inTransaction())$db->rollBack();
            // Duplicate email: retry with incremented suffix; duplicate employee code: report safely.
            if(($ex->errorInfo[1]??null)===1062) {
                $st=$db->prepare('SELECT 1 FROM employees WHERE company_id=? AND employee_code=?');$st->execute([$companyId,$code]);
                if($st->fetchColumn())throw new InvalidArgumentException('Employee ID already exists.');
                continue;
            }
            throw $ex;
        } catch(Throwable $ex) {if($db->inTransaction())$db->rollBack();throw $ex;}
    }
    throw new RuntimeException('Could not assign a unique email.');
}

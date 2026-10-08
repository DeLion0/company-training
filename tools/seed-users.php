<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit('CLI only');}
require_once dirname(__DIR__).'/app/core/database.php';
function ask(string $label): string {
    echo $label;
    $line=fgets(STDIN);
    if ($line===false) exit("\nCancelled.\n");
    return trim($line);
}
try {
    $db=database();
    $db->query('SELECT id FROM roles LIMIT 1');
    $company=(int)$db->query('SELECT id FROM companies ORDER BY id LIMIT 1')->fetchColumn();
    if (!$company) throw new RuntimeException('Import database/schema.sql first.');
    echo "Create or update demo HR / Department Head accounts. Password typing is visible in this console.\n";
    foreach (['hr'=>'HR Administrator','department_head'=>'Department Head'] as $role=>$label) {
        echo "\n--- $label ---\n";
        $name=ask('Full name: ');
        $email=strtolower(ask('Work email: '));
        $pass=ask('Password (min 12 characters): ');
        if (mb_strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pass)<12) {
            throw new RuntimeException('Invalid name, email, or password shorter than 12 characters. Nothing created for this account.');
        }
        $roleStmt=$db->prepare('SELECT id FROM roles WHERE role_key=?');
        $roleStmt->execute([$role]);
        $roleId=(int)$roleStmt->fetchColumn();
        $dept=null;
        if ($role==='department_head') {
            $rows=$db->query('SELECT id,name FROM departments WHERE company_id='.$company.' ORDER BY id')->fetchAll();
            foreach ($rows as $row) echo '  '.$row['id'].' - '.$row['name']."\n";
            $dept=(int)ask('Department ID: ');
            if (!in_array($dept,array_map(fn($r)=>(int)$r['id'],$rows),true)) throw new RuntimeException('Invalid department.');
        }
        $hash=password_hash($pass,PASSWORD_DEFAULT);
        $insert=$db->prepare("INSERT INTO users (company_id,department_id,role_id,full_name,email,password_hash,status)
            VALUES (?,?,?,?,?,?,'active') ON DUPLICATE KEY UPDATE department_id=VALUES(department_id),role_id=VALUES(role_id),full_name=VALUES(full_name),password_hash=VALUES(password_hash),status='active'");
        $insert->execute([$company,$dept,$roleId,$name,$email,$hash]);
        unset($pass,$hash);
        echo "Saved $label.\n";
    }
    echo "\nAccounts ready. Open http://localhost/company-training/public/\n";
} catch (Throwable $ex) {
    fwrite(STDERR,'Setup failed: '.$ex->getMessage()."\n");
    exit(1);
}

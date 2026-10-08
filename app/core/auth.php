<?php
declare(strict_types=1);
require_once __DIR__.'/database.php';

function secure_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('SKILLSPRING_SESSID');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    $now = time();
    if (isset($_SESSION['user']) && ($now-(int)($_SESSION['last_activity'] ?? $now)>1800 ||
        $now-(int)($_SESSION['created_at'] ?? $now)>28800)) {
        $_SESSION=[];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity']=$now;
}
function current_user(): ?array {
    $user = $_SESSION['user'] ?? null;
    return is_array($user) && isset($user['id'],$user['role'],$user['email'],$user['name']) ? $user : null;
}
function login_user(string $email,string $password): bool {
    $stmt=database()->prepare('SELECT u.id,u.full_name,u.email,u.password_hash,u.status,u.department_id,r.role_key FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.email = :email LIMIT 1');
    $stmt->execute(['email'=>trim($email)]);
    $row=$stmt->fetch();
    // Avoid skipping expensive password checks for unknown emails.
    $dummy='$2y$10$abcdefghijklmnopqrstuu.eH6W38FyADGSpEU9pOzFppLfHPpXNC';
    $valid=password_verify($password, $row['password_hash'] ?? $dummy);
    if (!$row || !$valid || $row['status']!=='active' || !in_array($row['role_key'],['hr','department_head'],true)) return false;
    session_regenerate_id(true);
    $_SESSION['user']=['id'=>(int)$row['id'],'name'=>$row['full_name'],'email'=>$row['email'],
        'role'=>$row['role_key'],'department_id'=>$row['department_id']===null?null:(int)$row['department_id']];
    $_SESSION['created_at']=$_SESSION['last_activity']=time();
    unset($_SESSION['attempts'],$_SESSION['blocked_until']);
    database()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
    return true;
}
function authenticated_user(): ?array {
    $user=current_user();
    if (!$user) return null;
    // Immediately respect account deactivation or role changes.
    try {
        $stmt=database()->prepare('SELECT u.status,u.department_id,r.role_key FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
        $stmt->execute([$user['id']]);
        $row=$stmt->fetch();
        if (!$row || $row['status']!=='active' || $row['role_key']!==$user['role']) {
            unset($_SESSION['user']);
            return null;
        }
        $user['department_id']=$row['department_id']===null?null:(int)$row['department_id'];
        $_SESSION['user']=$user;
        return $user;
    } catch (PDOException $ex) {
        error_log('Authentication database lookup failed: '.$ex->getMessage());
        http_response_code(503);
        exit('Authentication service temporarily unavailable.');
    }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_valid(?string $value): bool {return is_string($value) && hash_equals(csrf_token(),$value);}
function e(string $value): string {return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function redirect_to(string $url): never {header('Location: '.$url,true,303);exit;}
function logout_user(): void {
    $_SESSION=[];
    if (ini_get('session.use_cookies')) {
        $params=session_get_cookie_params();
        setcookie(session_name(),'',time()-3600,$params['path'],$params['domain'],$params['secure'],$params['httponly']);
    }
    session_destroy();
}

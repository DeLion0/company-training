<?php
declare(strict_types=1);
require_once __DIR__.'/../app/core/auth.php';
secure_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

$action=(string)($_GET['page']??'dashboard');
$allowed=['dashboard','training','employees','login','logout'];
if(!in_array($action,$allowed,true)){http_response_code(404);$action='not-found';}
$error='';
if($action==='logout'){
    if($_SERVER['REQUEST_METHOD']!=='POST'||!csrf_valid($_POST['csrf']??null)){http_response_code(405);exit('Invalid request');}
    logout_user();redirect_to('?page=login');
}
if($action==='login'){
    if(authenticated_user())redirect_to('?page=dashboard');
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!csrf_valid($_POST['csrf']??null)){$error='Session expired. Refresh the page and try again.';}
        elseif(time()<(int)($_SESSION['blocked_until']??0)){$error='Too many attempts. Please try again in a few minutes.';}
        else{
            $email=filter_var(trim((string)($_POST['email']??'')),FILTER_VALIDATE_EMAIL);
            $password=(string)($_POST['password']??'');
            if(is_string($email)&&strlen($password)<=1024){
                try{if(login_user($email,$password))redirect_to('?page=dashboard');}
                catch(PDOException $ex){error_log('Login DB error: '.$ex->getMessage());http_response_code(503);$error='Database unavailable.';require __DIR__.'/../app/pages/login.php';exit;}
            }
            $_SESSION['attempts']=(int)($_SESSION['attempts']??0)+1;
            if($_SESSION['attempts']>=5){$_SESSION['blocked_until']=time()+300;$_SESSION['attempts']=0;}
            $error='Invalid email or password.';
        }
    }
    require __DIR__.'/../app/pages/login.php';exit;
}
$user=authenticated_user();
if(!$user)redirect_to('?page=login');
if($action==='not-found'){echo 'Page not found';exit;}
if($action==='employees' && $user['role']!=='hr'){http_response_code(403);exit('Access denied. HR only.');}
if($action==='employees' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf']??null)){http_response_code(403);exit('Invalid CSRF token.');}
    if(($_POST['employee_action']??'')!=='create'){http_response_code(400);exit('Invalid action.');}
    require_once __DIR__.'/../app/core/employees-data.php';
    try{
        $_SESSION['employee_created']=hr_create_employee((int)$user['id'],$_POST);
    }catch(InvalidArgumentException $ex){$_SESSION['employee_error']=$ex->getMessage();}
    catch(Throwable $ex){error_log('Employee creation failed: '.$ex->getMessage());$_SESSION['employee_error']='Unable to create employee. Check the database migration and try again.';}
    redirect_to('?page=employees');
}
$page=$action;
require __DIR__.'/../app/partials/layout-start.php';
require __DIR__.'/../app/partials/sidebar.php';
echo '<div class="main-shell">';
require __DIR__.'/../app/partials/header.php';
require __DIR__.'/../app/pages/'.$page.'.php';
echo '</div>';
require __DIR__.'/../app/partials/layout-end.php';

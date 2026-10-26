<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

$cfg = app_config();
$clientId = trim((string)env_value('GOOGLE_CLIENT_ID', ''));
$clientSecret = trim((string)env_value('GOOGLE_CLIENT_SECRET', ''));
$redirectUri = rtrim((string)($cfg['app_url'] ?? ''), '/') . '/google-signin.php';
if ($clientId === '' || $clientSecret === '') {
    http_response_code(503);
    exit('Google signup is not configured yet. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to the server environment.');
}
if (isset($_GET['error'])) exit('Google sign-in was cancelled. You can use email and password instead.');
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(24)); $_SESSION['google_oauth_state'] = $state;
    $params = ['client_id'=>$clientId,'redirect_uri'=>$redirectUri,'response_type'=>'code','scope'=>'openid email profile','access_type'=>'online','state'=>$state,'prompt'=>'select_account'];
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params)); exit;
}
if (!hash_equals((string)($_SESSION['google_oauth_state'] ?? ''), (string)($_GET['state'] ?? ''))) { http_response_code(400); exit('Invalid Google sign-in state. Please try again.'); }
unset($_SESSION['google_oauth_state']);
$ch=curl_init('https://oauth2.googleapis.com/token');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['code'=>$_GET['code'],'client_id'=>$clientId,'client_secret'=>$clientSecret,'redirect_uri'=>$redirectUri,'grant_type'=>'authorization_code']),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);$token=json_decode((string)curl_exec($ch),true);curl_close($ch);$access=(string)($token['access_token']??'');if($access==='')exit('Google could not complete sign-in. Please try again.');
$ch=curl_init('https://openidconnect.googleapis.com/v1/userinfo');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$access]]);$profile=json_decode((string)curl_exec($ch),true);curl_close($ch);$email=strtolower(trim((string)($profile['email']??'')));$name=trim((string)($profile['name']??'Google user'));$sub=trim((string)($profile['sub']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$sub==='')exit('Google did not return a usable account.');
if(!database_ready())exit('The workspace service is unavailable.');
try{$q=db()->prepare('SELECT id FROM users WHERE email=:email OR firebase_uid=:uid LIMIT 1');$q->execute(['email'=>$email,'uid'=>'google:'.$sub]);$id=(int)$q->fetchColumn();if(!$id){$q=db()->prepare('INSERT INTO users (full_name,email,password_hash,preferred_language,currency_code,opening_balance,firebase_uid) VALUES (:name,:email,:hash,:lang,:currency,0,:uid)');$q->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'lang'=>current_lang(),'currency'=>'ZAR','uid'=>'google:'.$sub]);$id=(int)db()->lastInsertId();}login_user($id);redirect_to('dashboard.php');}catch(Throwable $e){http_response_code(500);exit('Could not create the Google-linked account.');}

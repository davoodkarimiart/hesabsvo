<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';require_once __DIR__.'/mcp_oauth_lib.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$ep=$_GET['endpoint']??'';$method=$_SERVER['REQUEST_METHOD']??'GET';
if($ep==='resource'&&$method==='GET')mo_json(['resource'=>SOLTAN_MCP_RESOURCE,'authorization_servers'=>[SOLTAN_OAUTH_ISSUER],'scopes_supported'=>['read:companies','read:panels','read:customers','read:reports','write:companies','write:customers','write:transfers','admin:diagnostics','admin:backup'],'bearer_methods_supported'=>['header']]);
if($ep==='metadata'&&$method==='GET')mo_json(['issuer'=>SOLTAN_OAUTH_ISSUER,'authorization_endpoint'=>SOLTAN_OAUTH_ISSUER.'/oauth.php?endpoint=authorize','token_endpoint'=>SOLTAN_OAUTH_ISSUER.'/oauth.php?endpoint=token','registration_endpoint'=>SOLTAN_OAUTH_ISSUER.'/oauth.php?endpoint=register','response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],'code_challenge_methods_supported'=>['S256'],'token_endpoint_auth_methods_supported'=>['none'],'scopes_supported'=>['read:companies','read:panels','read:customers','read:reports','write:companies','write:customers','write:transfers','admin:diagnostics','admin:backup']]);
if($ep==='register'&&$method==='POST'){
 $raw=file_get_contents('php://input',false,null,0,16385);if(strlen((string)$raw)>16384)mo_error('invalid_client_metadata',413);$j=json_decode((string)$raw,true);if(!is_array($j)||($j['token_endpoint_auth_method']??'none')!=='none')mo_error('invalid_client_metadata');$uris=$j['redirect_uris']??[];if(!is_array($uris)||count($uris)<1||count($uris)>5)mo_error('invalid_redirect_uri');foreach($uris as $u)if(!is_string($u)||!mo_valid_redirect($u))mo_error('invalid_redirect_uri');$id=mo_rand(24);$label=substr((string)($j['client_name']??'MCP Client'),0,100);
 mo_locked(function(&$d)use($id,$uris,$label){$d['clients'][$id]=['redirect_uris'=>$uris,'name'=>$label,'created_at'=>time()];});mo_json(['client_id'=>$id,'client_name'=>$label,'redirect_uris'=>$uris,'token_endpoint_auth_method'=>'none'],201);
}
if($ep==='authorize'&&$method==='GET'){
 $cid=(string)($_GET['client_id']??'');$client=mo_client($cid);$redirect=(string)($_GET['redirect_uri']??'');$challenge=(string)($_GET['code_challenge']??'');$state=(string)($_GET['state']??'');$resource=(string)($_GET['resource']??'');
 if(!$client||!in_array($redirect,$client['redirect_uris'],true)||!preg_match('/^[A-Za-z0-9_-]{43}$/D',$challenge)||($_GET['code_challenge_method']??'')!=='S256'||($_GET['response_type']??'')!=='code'||$resource!==SOLTAN_MCP_RESOURCE||strlen($state)>1024){http_response_code(400);exit('درخواست اتصال معتبر نیست.');}
 if(!Auth::check()){http_response_code(401);echo '<html lang="fa" dir="rtl"><meta charset="utf-8"><h2>ابتدا در سلطان حساب وارد شوید</h2><p>در تب دیگری وارد حساب خود شوید، سپس این صفحه را تازه‌سازی کنید.</p><a href="/index.php?page=login" target="_blank" rel="noopener">ورود به سایت</a></html>';exit;}
 $user=Auth::user();try{$scopes=mo_scopes((string)(isset($_GET['scope']) && trim((string)$_GET['scope'])!=='' ? $_GET['scope'] : mo_default_scopes((string)$user['role'])),(string)$user['role']);}catch(Throwable $e){http_response_code(403);exit('مجوز درخواست‌شده برای نقش شما مجاز نیست.');}
 // Diagnostic metadata only; never log PKCE, state, redirect URI, authorization codes or tokens.
 $rawScope=(string)($_GET['scope']??'');
 Logger::system('mcp.oauth_scope_request',[
   'client_name'=>substr((string)($client['name']??'unknown'),0,80),
   'client_id_fingerprint'=>substr(hash('sha256',$cid),0,12),
   'user_role'=>(string)$user['role'],
   'scope_parameter_present'=>array_key_exists('scope',$_GET),
   'requested_scopes'=>array_values(array_filter(explode(' ',preg_replace('/\s+/', ' ', trim($rawScope))))),
   'granted_scopes'=>$scopes,
 ]);
 $_SESSION['mcp_oauth_pending']=['client_id'=>$cid,'redirect_uri'=>$redirect,'challenge'=>$challenge,'state'=>$state,'scopes'=>$scopes,'expires'=>time()+600];
 $name=htmlspecialchars((string)$client['name'],ENT_QUOTES,'UTF-8');$dest=htmlspecialchars((string)parse_url($redirect,PHP_URL_HOST),ENT_QUOTES,'UTF-8');$sc=htmlspecialchars(implode(' ، ',$scopes),ENT_QUOTES,'UTF-8');$csrf=htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8');
 echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>تأیید اتصال MCP</title><style>body{font:16px system-ui;background:#f2f5fa;padding:30px;color:#172235}.card{max-width:540px;margin:8vh auto;background:white;border-radius:18px;padding:28px;box-shadow:0 8px 40px #1e29431c}button{background:#1659ca;color:white;padding:12px 25px;border:0;border-radius:10px;cursor:pointer}p{line-height:2}</style><div class="card"><h2>تأیید اتصال هوش مصنوعی</h2><p>برنامه: <b>'.$name.'</b><br>مقصد بازگشت: <b>'.$dest.'</b><br>دسترسی‌ها: <b>'.$sc.'</b></p><p>تنها در صورت اعتماد به برنامه و مقصد، اتصال را تأیید کنید.</p><form method="post" action="/oauth.php?endpoint=consent"><input type="hidden" name="_csrf" value="'.$csrf.'"><button name="decision" value="allow">تأیید اتصال</button> <button name="decision" value="deny" style="background:#777">رد</button></form></div></html>';exit;
}
if($ep==='consent'&&$method==='POST'){
 Auth::requireLogin();csrf_verify();$p=$_SESSION['mcp_oauth_pending']??null;unset($_SESSION['mcp_oauth_pending']);if(!is_array($p)||$p['expires']<time())mo_error('invalid_request');$sep=str_contains($p['redirect_uri'],'?')?'&':'?';if(($_POST['decision']??'')!=='allow'){header('Location: '.$p['redirect_uri'].$sep.http_build_query(['error'=>'access_denied','state'=>$p['state']]));exit;}
 $user=Auth::user();$code=mo_rand(32);mo_locked(function(&$d)use($code,$p,$user){$d['codes'][hash('sha256',$code)]=['client_id'=>$p['client_id'],'redirect_uri'=>$p['redirect_uri'],'challenge'=>$p['challenge'],'user_id'=>(int)$user['id'],'scopes'=>$p['scopes'],'expires'=>time()+120];});Logger::audit('mcp.oauth_authorized',['user_id'=>(int)$user['id']]);header('Location: '.$p['redirect_uri'].$sep.http_build_query(['code'=>$code,'state'=>$p['state']], '', '&', PHP_QUERY_RFC3986));exit;
}
if($ep==='token'&&$method==='POST'){
 $grant=(string)($_POST['grant_type']??'');$client=(string)($_POST['client_id']??'');$resource=(string)($_POST['resource']??'');if($resource!==SOLTAN_MCP_RESOURCE||!mo_client($client))mo_error('invalid_request');
 if($grant==='authorization_code'){$code=(string)($_POST['code']??'');$ver=(string)($_POST['code_verifier']??'');$redirect=(string)($_POST['redirect_uri']??'');if(!preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',$ver))mo_error('invalid_grant');$challenge=rtrim(strtr(base64_encode(hash('sha256',$ver,true)),'+/','-_'),'=');$row=mo_locked(function(&$d)use($code){$key=hash('sha256',$code);$r=$d['codes'][$key]??null;unset($d['codes'][$key]);return $r;});if(!$row||$row['expires']<time()||$row['client_id']!==$client||$row['redirect_uri']!==$redirect||!hash_equals($row['challenge'],$challenge))mo_error('invalid_grant');}
 elseif($grant==='refresh_token'){$token=(string)($_POST['refresh_token']??'');$row=mo_locked(function(&$d)use($token){$key=hash('sha256',$token);$r=$d['refresh'][$key]??null;unset($d['refresh'][$key]);return $r;});if(!$row||$row['expires']<time()||$row['client_id']!==$client)mo_error('invalid_grant');}
 else mo_error('unsupported_grant_type');
 $q=Database::connection()->prepare('SELECT id,role,active,security_version FROM users WHERE id=?');$q->execute([$row['user_id']]);$user=$q->fetch(PDO::FETCH_ASSOC);if(!$user||(int)$user['active']!==1)mo_error('invalid_grant');if(isset($row['security_version']) && (int)$row['security_version']!==(int)$user['security_version'])mo_error('invalid_grant');try {mo_scopes(implode(' ',$row['scopes']),(string)$user['role']);}catch(Throwable $e){mo_error('invalid_scope');}
 $access=mo_token((string)$user['id'],(string)$user['security_version'],$row['scopes'],$client);$refresh=mo_rand(48);$sv=(int)$user['security_version'];mo_locked(function(&$d)use($refresh,$row,$client,$sv){$d['refresh'][hash('sha256',$refresh)]=['user_id'=>$row['user_id'],'security_version'=>$sv,'scopes'=>$row['scopes'],'client_id'=>$client,'expires'=>time()+2592000];});mo_json(['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>900,'refresh_token'=>$refresh,'scope'=>implode(' ',$row['scopes'])]);
}
mo_error('not_found',404);

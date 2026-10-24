<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('{"ok":false}');}
if(!Auth::check()){http_response_code(401);exit('{"ok":false}');}
if(!hash_equals((string)($_SESSION['_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))){http_response_code(403);exit('{"ok":false}');}
if((int)($_SESSION['_client_error_count']??0)>=20 && time()-(int)($_SESSION['_client_error_since']??0)<3600){http_response_code(429);exit('{"ok":false}');}
if(time()-(int)($_SESSION['_client_error_since']??0)>=3600){$_SESSION['_client_error_count']=0;$_SESSION['_client_error_since']=time();}
$raw=file_get_contents('php://input',false,null,0,4096);$data=json_decode((string)$raw,true);
if(!is_array($data)){http_response_code(400);exit('{"ok":false}');}
$_SESSION['_client_error_count']=(int)($_SESSION['_client_error_count']??0)+1;
// Whitelist diagnostics fields. Never collect URLs, form values, request bodies or personal content.
Logger::error('frontend.javascript_error',[
 'kind'=>mb_substr((string)($data['kind']??'error'),0,32),
 'message'=>mb_substr((string)($data['message']??''),0,180),
 'source_file'=>mb_substr(basename(parse_url((string)($data['source']??''),PHP_URL_PATH)?:''),0,90),
 'line'=>(int)($data['line']??0),
 'column'=>(int)($data['column']??0),
 'page'=>preg_replace('/[^a-z0-9_-]/i','',substr((string)($data['page']??''),0,50)),
]);
echo '{"ok":true}';

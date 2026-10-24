<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/app/bootstrap.php';
$action=$argv[1]??'';$username=$argv[2]??'';
if(!in_array($action,['issue','revoke-all'],true)||!preg_match('/^[a-zA-Z0-9_.@-]{1,100}$/D',$username)){fwrite(STDERR,"Usage: php mcp_credential.php issue|revoke-all USERNAME\n");exit(1);}
$q=Database::connection()->prepare('SELECT id,role,active,security_version FROM users WHERE username=? LIMIT 1');$q->execute([$username]);$user=$q->fetch(PDO::FETCH_ASSOC);
if(!$user || (int)$user['active']!==1){fwrite(STDERR,"Active user not found\n");exit(1);}
// Phase 11.1 intentionally limits token issuance to developer, pending user-consent OAuth.
if($user['role']!=='developer'){fwrite(STDERR,"Only Developer is allowed in initial read-only pilot\n");exit(1);}
$dir=__DIR__.'/storage/mcp';if(!is_dir($dir)&&!mkdir($dir,0700,true)){exit("Cannot create storage\n");}
@chmod($dir,0700);file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\n");
$file=$dir.'/credentials.json';$db=is_file($file)?json_decode((string)file_get_contents($file),true):null;$entries=is_array($db['entries']??null)?$db['entries']:[];
$entries=array_values(array_filter($entries,static fn($e)=>($e['expires_at']??0)>time() && (int)($e['user_id']??0)!==(int)$user['id']));
if($action==='issue'){$token=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');$entries[]=['hash'=>hash('sha256',$token),'user_id'=>(int)$user['id'],'security_version'=>(int)$user['security_version'],'scopes'=>['read:companies','admin:diagnostics'],'expires_at'=>time()+7*86400];}
$tmp=$file.'.tmp.'.getmypid();if(file_put_contents($tmp,json_encode(['entries'=>$entries],JSON_PRETTY_PRINT),LOCK_EX)===false)exit("Could not save\n");chmod($tmp,0600);if(!rename($tmp,$file))exit("Could not commit\n");
Logger::audit('mcp.credential_'.$action,['user_id'=>(int)$user['id']]);
if($action==='issue')echo "One-time credential (7 days, read:companies + admin:diagnostics): $token\n";else echo "User MCP credentials revoked\n";

<?php
declare(strict_types=1);
// MCP Phase 11.1: authenticated, stateless, read-only JSON-RPC 2.0 endpoint.
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/Services/DeveloperBackup633.php';
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$reply=static function($id,?array $result=null,?array $error=null):never {
    echo json_encode(['jsonrpc'=>'2.0','id'=>$id]+($error!==null?['error'=>$error]:['result'=>$result]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;
};
$err=static function(int $status,string $msg)use($reply):never {http_response_code($status);$reply(null,null,['code'=>-32000,'message'=>$msg]);};
// Shared hosting must terminate TLS. Reject browser origins to prevent cross-site token usage.
if (($_SERVER['HTTPS']??'')==='off' || (!isset($_SERVER['HTTPS']) && ($_SERVER['HTTP_X_FORWARDED_PROTO']??'')!=='https')) $err(403,'HTTPS required');
if (!empty($_SERVER['HTTP_ORIGIN'])) $err(403,'Browser origins are not accepted');
if (($_SERVER['REQUEST_METHOD']??'')==='GET') {http_response_code(405);header('Allow: POST');exit;}
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') $err(405,'POST required');
if (stripos((string)($_SERVER['CONTENT_TYPE']??''),'application/json')!==0)$err(415,'application/json required');
if (!str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')&&!str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'*/*'))$err(406,'Accept application/json required');
$header=$_SERVER['HTTP_AUTHORIZATION']??($_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if (!preg_match('/^Bearer ([A-Za-z0-9_-]{32,128})$/D',(string)$header,$m)) {header('WWW-Authenticate: Bearer realm="soltan-mcp", resource_metadata="https://soltan.toseno.ir/.well-known/oauth-protected-resource"');$err(401,'Bearer token required');}
require_once __DIR__.'/mcp_oauth_lib.php';
$registry=__DIR__.'/storage/mcp/credentials.json';
$tokens=is_file($registry)?json_decode((string)file_get_contents($registry),true):null;
$found=null;
foreach (($tokens['entries']??[]) as $entry) {if(is_array($entry)&&hash_equals((string)($entry['hash']??''),hash('sha256',$m[1]))) {$found=$entry;break;}}
if (!$found || ($found['expires_at']??0)<time() || (isset($found['audience']) && $found['audience']!==SOLTAN_MCP_RESOURCE)) {header('WWW-Authenticate: Bearer realm="soltan-mcp", error="invalid_token", resource_metadata="https://soltan.toseno.ir/.well-known/oauth-protected-resource"');$err(401,'Invalid or expired credential');}
$q=Database::connection()->prepare('SELECT id,username,role,active,security_version FROM users WHERE id=? LIMIT 1');$q->execute([(int)$found['user_id']]);$user=$q->fetch(PDO::FETCH_ASSOC);
if (!$user || (int)$user['active']!==1 || (int)$user['security_version']!==(int)$found['security_version'])$err(401,'User inactive or access revoked');
// Scope checks are per-tool, not global; developer diagnostic scopes never belong to ordinary users.
$capabilities=$found['scopes']??[];
if(!is_array($capabilities))$err(403,'Invalid scope');
$canCompany=in_array('read:companies',$capabilities,true);
$canPanels=in_array('read:panels',$capabilities,true);
$canCustomers=in_array('read:customers',$capabilities,true);
$canReports=in_array('read:reports',$capabilities,true);
$canWriteCompany=in_array($user['role'],['developer','admin'],true) && in_array('write:companies',$capabilities,true);
$canWriteCustomers=in_array($user['role'],['developer','admin'],true) && in_array('write:customers',$capabilities,true);
$canDiag=$user['role']==='developer' && in_array('admin:diagnostics',$capabilities,true);
$canWriteTransfers=in_array($user['role'],['developer','admin'],true) && in_array('write:transfers',$capabilities,true);
$canBackup=false;
$canBackup=$user['role']==='developer' && in_array('admin:backup',$capabilities,true);
if(!$canCompany && !$canPanels && !$canCustomers && !$canReports && !$canDiag && !$canWriteCompany && !$canWriteCustomers && !$canWriteTransfers && !$canBackup)$err(403,'Insufficient scope');
$raw=file_get_contents('php://input',false,null,0,65537);
if($raw===false||strlen($raw)>65536)$err(413,'Request too large');
$req=json_decode($raw,true);
if(!is_array($req)||array_is_list($req)||($req['jsonrpc']??'')!=='2.0'||!is_string($req['method']??null))$reply(null,null,['code'=>-32600,'message'=>'Invalid JSON-RPC request']);
$id=$req['id']??null;$method=$req['method'];$params=$req['params']??[];
if($id===null){http_response_code(202);exit;}
if(!is_int($id)&&!is_string($id))$reply(null,null,['code'=>-32600,'message'=>'Invalid id']);
if($method==='initialize')$reply($id,['protocolVersion'=>'2025-11-25','capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'soltan-hesab','version'=>'0.6.53']]);
if($method==='ping')$reply($id,[]);
$toolDefs=[];
if($canCompany){
 $toolDefs[]=['name'=>'list_companies','title'=>'List companies','description'=>'Read-only list of active non-archived companies.','inputSchema'=>['type'=>'object','properties'=>new stdClass(),'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
}
if($canPanels){
 $toolDefs[]=['name'=>'list_panels','title'=>'List panels','description'=>'Read-only panels, optionally filtered by company ID.','inputSchema'=>['type'=>'object','properties'=>['company_id'=>['type'=>'integer','minimum'=>1],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100]],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
}
if($canCustomers){
 $toolDefs[]=['name'=>'list_customers','title'=>'List customers','description'=>'Read-only customer directory. Returns names and IDs without account balances or notes.','inputSchema'=>['type'=>'object','properties'=>['query'=>['type'=>'string','maxLength'=>100],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>50]],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
}
if($canReports){
 $toolDefs[]=['name'=>'list_daily_reports','title'=>'List daily reports','description'=>'Read-only report summary totals with company, date and status. No raw account rows.','inputSchema'=>['type'=>'object','properties'=>['company_id'=>['type'=>'integer','minimum'=>1],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>50]],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
}
if($canReports){
 $toolDefs[]=['name'=>'get_daily_report','title'=>'Get daily report','description'=>'Read a single saved daily report summary by numeric report ID, including totals and row count. Read-only.','inputSchema'=>['type'=>'object','properties'=>['report_id'=>['type'=>'integer','minimum'=>1]],'required'=>['report_id'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
 $toolDefs[]=['name'=>'get_daily_report_rows','title'=>'Get saved report rows','description'=>'Read a bounded page of immutable report row snapshots (customer names and financial figures). Only request when user explicitly asks for report details.','inputSchema'=>['type'=>'object','properties'=>['report_id'=>['type'=>'integer','minimum'=>1],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000]],'required'=>['report_id'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
}
if($canCustomers){
 $toolDefs[]=['name'=>'get_customer_accounts','title'=>'Customer linked accounts','description'=>'Read connected accounts of one customer through CustomerService. Includes account identifiers and labels.','inputSchema'=>['type'=>'object','properties'=>['customer_id'=>['type'=>'integer','minimum'=>1]],'required'=>['customer_id'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
 $toolDefs[]=['name'=>'get_customer_ledger','title'=>'Customer ledger','description'=>'Read bounded customer ledger and totals. Includes financial records; use only when user requests customer ledger.','inputSchema'=>['type'=>'object','properties'=>['customer_id'=>['type'=>'integer','minimum'=>1],'from'=>['type'=>'string','maxLength'=>12],'to'=>['type'=>'string','maxLength'=>12],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100]],'required'=>['customer_id'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
}
if($canReports){
 $toolDefs[]=['name'=>'list_transfers','title'=>'List transfers','description'=>'Read bounded transfer records using TransferService with company filter.','inputSchema'=>['type'=>'object','properties'=>['company_id'=>['type'=>'integer','minimum'=>1],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>50]],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
 $toolDefs[]=['name'=>'preview_transfer','title'=>'Preview transfer calculation','description'=>'Read-only transfer preview from TransferService; does not save or finalize anything.','inputSchema'=>['type'=>'object','properties'=>['company_id'=>['type'=>'integer','minimum'=>1],'period_type'=>['type'=>'string','enum'=>['weekly','monthly','custom']],'period_start'=>['type'=>'string','maxLength'=>10],'period_end'=>['type'=>'string','maxLength'=>10],'transfer_percent'=>['type'=>'string','maxLength'=>12],'usd_rate_toman'=>['type'=>'string','maxLength'=>25]],'required'=>['company_id','period_type','period_start','period_end'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
}
if($canWriteCustomers){
 $toolDefs[]=['name'=>'create_customer','title'=>'Create customer using existing accounts','description'=>'Creates a customer linked to existing unlinked accounts. Requires exact name confirmation and account IDs; no new financial rows.','inputSchema'=>['type'=>'object','properties'=>['name'=>['type'=>'string','minLength'=>1,'maxLength'=>190],'confirm_name'=>['type'=>'string','minLength'=>1,'maxLength'=>190],'account_ids'=>['type'=>'array','items'=>['type'=>'integer','minimum'=>1],'minItems'=>1,'maxItems'=>30]],'required'=>['name','confirm_name','account_ids'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false,'idempotentHint'=>false]];
}
if($canWriteCompany){
 $toolDefs[]=['name'=>'create_company','title'=>'Create company','description'=>'Creates a company using the same CompanyService as the website. Requires write:companies and explicit exact-name confirmation. Do not call unless user explicitly requested creation.','inputSchema'=>['type'=>'object','properties'=>['name'=>['type'=>'string','minLength'=>1,'maxLength'=>190],'company_role'=>['type'=>'string','enum'=>['agent','master']],'transfer_percent'=>['type'=>'string','pattern'=>'^[0-9]{1,3}(\.[0-9]{1,3})?$'],'confirm_name'=>['type'=>'string','minLength'=>1,'maxLength'=>190]],'required'=>['name','company_role','transfer_percent','confirm_name'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false,'idempotentHint'=>false]];
 $toolDefs[]=['name'=>'create_panel','title'=>'Create panel','description'=>'Creates a panel in an active company using CompanyService. Requires explicit confirmation of panel name and company ID.','inputSchema'=>['type'=>'object','properties'=>['company_id'=>['type'=>'integer','minimum'=>1],'name'=>['type'=>'string','minLength'=>1,'maxLength'=>190],'display_name_mode'=>['type'=>'string','enum'=>['display','original']],'confirm_name'=>['type'=>'string','minLength'=>1,'maxLength'=>190],'confirm_company_id'=>['type'=>'integer','minimum'=>1]],'required'=>['company_id','name','display_name_mode','confirm_name','confirm_company_id'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false,'idempotentHint'=>false]];
}
if($canWriteCompany){
 $toolDefs[]=['name'=>'update_company','title'=>'Update existing company','description'=>'Changes an existing company using CompanyService. Must have explicit user authorization for company ID and resulting fields.','inputSchema'=>['type'=>'object','properties'=>['company_id'=>['type'=>'integer','minimum'=>1],'confirm_company_id'=>['type'=>'integer','minimum'=>1],'name'=>['type'=>'string','minLength'=>1,'maxLength'=>190],'active'=>['type'=>'boolean'],'company_role'=>['type'=>'string','enum'=>['agent','master']],'transfer_percent'=>['type'=>'string','pattern'=>'^[0-9]{1,3}(\.[0-9]{1,3})?$']], 'required'=>['company_id','confirm_company_id','name','active','company_role','transfer_percent'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>true,'idempotentHint'=>true]];
 $toolDefs[]=['name'=>'update_panel','title'=>'Update existing panel','description'=>'Updates a panel using CompanyService; requires explicit exact target panel ID approval.','inputSchema'=>['type'=>'object','properties'=>['panel_id'=>['type'=>'integer','minimum'=>1],'confirm_panel_id'=>['type'=>'integer','minimum'=>1],'name'=>['type'=>'string','minLength'=>1,'maxLength'=>190],'active'=>['type'=>'boolean'],'display_name_mode'=>['type'=>'string','enum'=>['display','original']]],'required'=>['panel_id','confirm_panel_id','name','active','display_name_mode'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>true,'idempotentHint'=>true]];
}
if($canWriteCustomers){
 $toolDefs[]=['name'=>'update_customer','title'=>'Update customer profile','description'=>'Updates a customer name, notes and active status using CustomerService; requires exact customer ID confirmation. Does not modify account grouping or financial rows.','inputSchema'=>['type'=>'object','properties'=>['customer_id'=>['type'=>'integer','minimum'=>1],'confirm_customer_id'=>['type'=>'integer','minimum'=>1],'display_name'=>['type'=>'string','minLength'=>1,'maxLength'=>190],'notes'=>['type'=>'string','maxLength'=>1000],'active'=>['type'=>'boolean']], 'required'=>['customer_id','confirm_customer_id','display_name','notes','active'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>true,'idempotentHint'=>true]];
}
if($canCompany){
 $toolDefs[]=['name'=>'list_archived_companies','title'=>'Archived companies','description'=>'Read archived company identities and reference counts without changing records.','inputSchema'=>['type'=>'object','properties'=>new stdClass(),'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]];
}
if($canWriteCompany){
 $toolDefs[]=['name'=>'restore_company','title'=>'Restore archived company','description'=>'Restore previously archived company with explicit confirmation of exact company ID and name.','inputSchema'=>['type'=>'object','properties'=>['company_id'=>['type'=>'integer','minimum'=>1],'confirm_company_id'=>['type'=>'integer','minimum'=>1],'confirm_name'=>['type'=>'string','minLength'=>1,'maxLength'=>190]],'required'=>['company_id','confirm_company_id','confirm_name'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false]];
}
if($canCustomers){
 $toolDefs[]=['name'=>'list_unlinked_accounts','title'=>'Unlinked customer accounts','description'=>'Find up to 50 existing unlinked accounts for potential customer association.','inputSchema'=>['type'=>'object','properties'=>['query'=>['type'=>'string','maxLength'=>100]],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]];
}
if($canWriteCustomers){
 $toolDefs[]=['name'=>'link_customer_accounts','title'=>'Link existing accounts to customer','description'=>'Link exact existing account IDs with explicit confirmation; invokes CustomerService validation.','inputSchema'=>['type'=>'object','properties'=>['customer_id'=>['type'=>'integer','minimum'=>1],'confirm_customer_id'=>['type'=>'integer','minimum'=>1],'account_ids'=>['type'=>'array','items'=>['type'=>'integer','minimum'=>1],'minItems'=>1,'maxItems'=>30]],'required'=>['customer_id','confirm_customer_id','account_ids'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>true]];
}
if($canWriteTransfers){
 $toolDefs[]=['name'=>'save_transfer','title'=>'Save settlement transfer','description'=>'Financial write: save settlement using TransferService with exact company, period and amount confirmation. Never call without explicit user authorization. No manual override.','inputSchema'=>['type'=>'object','properties'=>['company_id'=>['type'=>'integer','minimum'=>1],'period_type'=>['type'=>'string','enum'=>['weekly','monthly','custom']],'period_start'=>['type'=>'string','maxLength'=>10],'period_end'=>['type'=>'string','maxLength'=>10],'transfer_percent'=>['type'=>'string','maxLength'=>12],'usd_rate_toman'=>['type'=>'string','maxLength'=>25],'confirm_company_id'=>['type'=>'integer','minimum'=>1],'confirm_period_end'=>['type'=>'string','maxLength'=>10],'confirm_total_toman'=>['type'=>'string','maxLength'=>40]],'required'=>['company_id','period_type','period_start','period_end','transfer_percent','usd_rate_toman','confirm_company_id','confirm_period_end','confirm_total_toman'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>true,'idempotentHint'=>false]];
 $toolDefs[]=['name'=>'void_transfer','title'=>'Void existing transfer','description'=>'Financial write: void final transfer with confirmed ID and reason. Irreversible event logged.','inputSchema'=>['type'=>'object','properties'=>['transfer_id'=>['type'=>'integer','minimum'=>1],'confirm_transfer_id'=>['type'=>'integer','minimum'=>1],'reason'=>['type'=>'string','minLength'=>3,'maxLength'=>500]],'required'=>['transfer_id','confirm_transfer_id','reason'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>true,'idempotentHint'=>false]];
}
if($canBackup){
 $toolDefs[]=['name'=>'create_backup','title'=>'Create database backup','description'=>'Developer-only: create logical database backup on server. Requires explicit confirmation string. Does not return SQL or credentials.','inputSchema'=>['type'=>'object','properties'=>['confirmation'=>['type'=>'string','enum'=>['ایجاد بکاپ سلطان حساب']]],'required'=>['confirmation'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false]];
 $toolDefs[]=['name'=>'send_backup_telegram','title'=>'Send backup to configured Telegram','description'=>'Developer-only: send existing named backup to configured Telegram destination after explicit exact filename confirmation.','inputSchema'=>['type'=>'object','properties'=>['filename'=>['type'=>'string','maxLength'=>100],'confirm_filename'=>['type'=>'string','maxLength'=>100]],'required'=>['filename','confirm_filename'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>true]];
}
$toolDefs[]=['name'=>'get_mcp_identity','title'=>'MCP identity','description'=>'Current credential username, role and authorized scopes.','inputSchema'=>['type'=>'object','properties'=>new stdClass(),'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
if($canDiag){
 $toolDefs[]=['name'=>'backup_status','title'=>'Backup status','description'=>'Developer-only read-only status of local database backups and cron file presence; never exposes SQL data, credentials, paths or Telegram token.','inputSchema'=>['type'=>'object','properties'=>new stdClass(),'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
 $toolDefs[]=['name'=>'diagnostics_health','title'=>'Site health','description'=>'Developer-only operational status of PHP, DB, log directory, latest errors; no credentials or user financial records.','inputSchema'=>['type'=>'object','properties'=>new stdClass(),'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
 $toolDefs[]=['name'=>'diagnostics_logs','title'=>'Search site diagnostics','description'=>'Developer-only bounded search of error/system/audit logs; returns timestamp, event, request ID and redacted context. Use request_id to trace a reported failure.','inputSchema'=>['type'=>'object','properties'=>['channel'=>['type'=>'string','enum'=>['error','system','audit']],'query'=>['type'=>'string','maxLength'=>120],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>30]],'required'=>['channel'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false]];
}
if($method==='tools/list')$reply($id,['tools'=>$toolDefs]);
if($method==='tools/call'){
 $name=$params['name']??'';$args=$params['arguments']??[];
 if(!is_string($name)||!is_array($args)||array_is_list($args)&&count($args)>0)$reply($id,null,['code'=>-32602,'message'=>'Invalid parameters']);
 if($name==='list_archived_companies' && $canCompany){
  $data=['companies'=>array_slice(CompanyService::archived(),0,100)];
 } elseif($name==='restore_company' && $canWriteCompany){
  $cid=$args['company_id']??null;$yes=$args['confirm_company_id']??null;$nm=$args['confirm_name']??null;
  if(!is_int($cid)||$cid<1||$cid!==$yes||!is_string($nm)||trim($nm)===''||strlen($nm)>190)$reply($id,null,['code'=>-32602,'message'=>'Missing explicit confirmation']);
  $archived=CompanyService::archived();$target=null;foreach($archived as $a){if((int)$a['id']===$cid){$target=$a;break;}}
  if(!$target || !hash_equals((string)$target['name'],trim($nm)))$reply($id,['content'=>[['type'=>'text','text'=>'Archived company or confirmed name not found']],'isError'=>true]);
  try{CompanyService::restoreCompany($cid,(int)$user['id']);$data=['restored'=>true,'company_id'=>$cid];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Restore rejected']],'isError'=>true]);}
 } elseif($name==='list_unlinked_accounts' && $canCustomers){
  $q=$args['query']??'';if(!is_string($q)||strlen($q)>100)$reply($id,null,['code'=>-32602,'message'=>'Invalid search']);
  $a=CustomerService::unlinkedAccounts($q);$data=['accounts'=>array_slice($a,0,50),'truncated'=>count($a)>50];
 } elseif($name==='link_customer_accounts' && $canWriteCustomers){
  $cid=$args['customer_id']??null;$yes=$args['confirm_customer_id']??null;$ids=$args['account_ids']??null;
  if(!is_int($cid)||$cid<1||$cid!==$yes||!is_array($ids)||count($ids)<1||count($ids)>30||count($ids)!==count(array_unique($ids))||array_filter($ids,static fn($x)=>!is_int($x)||$x<1))$reply($id,null,['code'=>-32602,'message'=>'Invalid or unconfirmed account linking']);
  if(!CustomerService::get($cid))$reply($id,['content'=>[['type'=>'text','text'=>'Customer not found']],'isError'=>true]);
  try{CustomerService::link($cid,$ids,(int)$user['id']);$data=['linked'=>true,'customer_id'=>$cid,'account_ids'=>$ids];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Account linking rejected']],'isError'=>true]);}
 } else
 if($name==='save_transfer' && $canWriteTransfers){
  $cid=$args['company_id']??null;$mode=$args['period_type']??null;$start=$args['period_start']??null;$end=$args['period_end']??null;$pct=$args['transfer_percent']??null;$rate=$args['usd_rate_toman']??null;
  if(!is_int($cid)||$cid<1||$cid!==($args['confirm_company_id']??null)||!is_string($mode)||!in_array($mode,['weekly','monthly','custom'],true)||!is_string($start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$start)||!is_string($end)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$end)||$end!==($args['confirm_period_end']??null)||!is_string($pct)||strlen($pct)>12||!is_string($rate)||strlen($rate)>25||!is_string($args['confirm_total_toman']??null))$reply($id,null,['code'=>-32602,'message'=>'Invalid transfer request or confirmation']);
  $input=['company_id'=>$cid,'period_type'=>$mode,'period_start'=>$start,'period_end'=>$end,'transfer_percent'=>$pct,'usd_rate_toman'=>$rate];
  try{
   $preview=TransferService::preview($input);
   // Guard against accidental financial creation when the confirmed numeric value is not identical to the preview.
   $computed=(string)($preview['total_toman']??($preview['calculation']['total_toman']??''));
   if($computed==='' || !is_numeric($computed) || !is_numeric($args['confirm_total_toman']) || abs((float)$computed-(float)$args['confirm_total_toman'])>0.000001)throw new RuntimeException('Transfer amount confirmation does not match current preview');
   $tid=TransferService::save($input,(int)$user['id']);$data=['created'=>true,'transfer_id'=>$tid];
  }catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Transfer write rejected; check preview, amount and period']],'isError'=>true]);}
 } elseif($name==='void_transfer' && $canWriteTransfers){
  $tid=$args['transfer_id']??null;$why=$args['reason']??null;
  if(!is_int($tid)||$tid<1||$tid!==($args['confirm_transfer_id']??null)||!is_string($why)||mb_strlen(trim($why))<3||mb_strlen($why)>500)$reply($id,null,['code'=>-32602,'message'=>'Invalid transfer cancellation confirmation']);
  try{TransferService::void($tid,$why,(int)$user['id']);$data=['voided'=>true,'transfer_id'=>$tid];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Transfer void rejected']],'isError'=>true]);}
 } else
 if($name==='create_backup' && $canBackup){
  if(($args['confirmation']??null)!=='ایجاد بکاپ سلطان حساب')$reply($id,null,['code'=>-32602,'message'=>'Explicit backup confirmation required']);
  try{$bk=DeveloperBackup633::backup();$data=['created'=>true,'filename'=>$bk['name'],'size_bytes'=>$bk['size']];Logger::audit('mcp.backup_created',['user_id'=>(int)$user['id'],'filename'=>$bk['name']]);}
  catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Backup rejected: check backup logs']],'isError'=>true]);}
 } elseif($name==='send_backup_telegram' && $canBackup){
  $file=$args['filename']??null;
  if(!is_string($file)||$file!==($args['confirm_filename']??null)||!preg_match('/^soltan-db-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.sql\.gz$/D',$file))$reply($id,null,['code'=>-32602,'message'=>'Exact backup filename confirmation required']);
  try{DeveloperBackup633::sendTelegram($file,(int)$user['id']);$data=['sent'=>true,'filename'=>$file];Logger::audit('mcp.backup_telegram_sent',['user_id'=>(int)$user['id'],'filename'=>$file]);}
  catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Telegram delivery rejected; check backup settings and logs']],'isError'=>true]);}
 } else
 if($name==='get_customer_accounts' && $canCustomers){
  $cid=$args['customer_id']??null;if(!is_int($cid)||$cid<1)$reply($id,null,['code'=>-32602,'message'=>'Invalid customer ID']);
  $customer=CustomerService::get($cid);if(!$customer)$reply($id,['content'=>[['type'=>'text','text'=>'Customer not found']],'isError'=>true]);
  $accounts=CustomerService::accounts($cid);$data=['customer_id'=>$cid,'customer_name'=>$customer['display_name']??'','accounts'=>array_slice($accounts,0,100),'truncated'=>count($accounts)>100];
 } elseif($name==='get_customer_ledger' && $canCustomers){
  $cid=$args['customer_id']??null;$from=$args['from']??'';$to=$args['to']??'';$limit=$args['limit']??50;
  if(!is_int($cid)||$cid<1||!is_string($from)||!is_string($to)||strlen($from)>12||strlen($to)>12||!is_int($limit)||$limit<1||$limit>100)$reply($id,null,['code'=>-32602,'message'=>'Invalid ledger request']);
  if(!CustomerService::get($cid))$reply($id,['content'=>[['type'=>'text','text'=>'Customer not found']],'isError'=>true]);
  try{$ledger=CustomerService::ledger($cid,$from?:null,$to?:null);$data=['customer_id'=>$cid,'totals'=>$ledger['totals'],'rows'=>array_slice($ledger['rows'],0,$limit),'returned'=>min(count($ledger['rows']),$limit),'has_more'=>count($ledger['rows'])>$limit];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Ledger request rejected']],'isError'=>true]);}
 } elseif($name==='list_transfers' && $canReports){
  $cid=$args['company_id']??0;$limit=$args['limit']??20;
  if(!is_int($cid)||$cid<0||!is_int($limit)||$limit<1||$limit>50)$reply($id,null,['code'=>-32602,'message'=>'Invalid transfer filters']);
  try{$rows=TransferService::listing('', $cid, '');$data=['transfers'=>array_slice($rows,0,$limit),'has_more'=>count($rows)>$limit];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Unable to list transfers']],'isError'=>true]);}
 } elseif($name==='preview_transfer' && $canReports){
  $cid=$args['company_id']??null;$mode=$args['period_type']??'';$start=$args['period_start']??'';$end=$args['period_end']??'';$percent=$args['transfer_percent']??'';$rate=$args['usd_rate_toman']??'';
  if(!is_int($cid)||$cid<1||!in_array($mode,['weekly','monthly','custom'],true)||!is_string($start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$start)||!is_string($end)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$end)||!is_string($percent)||strlen($percent)>12||!is_string($rate)||strlen($rate)>25)$reply($id,null,['code'=>-32602,'message'=>'Invalid preview fields']);
  try{$input=['company_id'=>$cid,'period_type'=>$mode,'period_start'=>$start,'period_end'=>$end];if($percent!=='')$input['transfer_percent']=$percent;if($rate!=='')$input['usd_rate_toman']=$rate;$data=['preview'=>TransferService::preview($input),'saved'=>false];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Transfer preview rejected: '.($e instanceof RuntimeException?$e->getMessage():'invalid request')]],'isError'=>true]);}
 } elseif($name==='create_customer' && $canWriteCustomers){
  $nm=$args['name']??null;$cn=$args['confirm_name']??null;$ids=$args['account_ids']??null;
  if(!is_string($nm)||!is_string($cn)||trim($nm)===''||strlen($nm)>190||trim($nm)!==trim($cn)||!is_array($ids)||count($ids)<1||count($ids)>30||array_filter($ids,static fn($v)=>!is_int($v)||$v<1))$reply($id,null,['code'=>-32602,'message'=>'Invalid or unconfirmed customer creation']);
  try{$newId=CustomerService::create($nm,$ids,(int)$user['id']);$data=['created'=>true,'customer_id'=>$newId,'name'=>trim($nm)];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Customer creation rejected: '.($e instanceof RuntimeException?$e->getMessage():'server error')]],'isError'=>true]);}
 } elseif($name==='update_company' && $canWriteCompany){
  $cid=$args['company_id']??null;$ccid=$args['confirm_company_id']??null;$nm=$args['name']??null;$active=$args['active']??null;$role=$args['company_role']??null;$percent=$args['transfer_percent']??null;
  if(!is_int($cid)||$cid<1||$cid!==$ccid||!is_string($nm)||trim($nm)===''||strlen($nm)>190||!is_bool($active)||!in_array($role,['agent','master'],true)||!is_string($percent)||!preg_match('/^[0-9]{1,3}(\.[0-9]{1,3})?$/D',$percent))$reply($id,null,['code'=>-32602,'message'=>'Invalid or unconfirmed company update']);
  try{CompanyService::updateCompany($cid,$nm,$active,$role,$percent);$data=['updated'=>true,'company_id'=>$cid,'name'=>trim($nm)];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Company update rejected: '.($e instanceof RuntimeException?$e->getMessage():'server error')]],'isError'=>true]);}
 } elseif($name==='update_panel' && $canWriteCompany){
  $pid=$args['panel_id']??null;$cpid=$args['confirm_panel_id']??null;$nm=$args['name']??null;$active=$args['active']??null;$mode=$args['display_name_mode']??null;
  if(!is_int($pid)||$pid<1||$pid!==$cpid||!is_string($nm)||trim($nm)===''||strlen($nm)>190||!is_bool($active)||!in_array($mode,['display','original'],true))$reply($id,null,['code'=>-32602,'message'=>'Invalid or unconfirmed panel update']);
  try{$existing=CompanyService::panel($pid);if(!$existing)throw new RuntimeException('Panel not found');CompanyService::updatePanel($pid,$nm,$mode,$active);$data=['updated'=>true,'panel_id'=>$pid,'name'=>trim($nm)];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Panel update rejected: '.($e instanceof RuntimeException?$e->getMessage():'server error')]],'isError'=>true]);}
 } elseif($name==='update_customer' && $canWriteCustomers){
  $cid=$args['customer_id']??null;$confirm=$args['confirm_customer_id']??null;$nm=$args['display_name']??null;$notes=$args['notes']??null;$active=$args['active']??null;
  if(!is_int($cid)||$cid<1||$cid!==$confirm||!is_string($nm)||trim($nm)===''||strlen($nm)>190||!is_string($notes)||strlen($notes)>1000||!is_bool($active))$reply($id,null,['code'=>-32602,'message'=>'Invalid or unconfirmed customer update']);
  try{$existing=CustomerService::get($cid);if(!$existing)throw new RuntimeException('Customer not found');CustomerService::update($cid,$nm,$notes,(int)$user['id'],$active,null);$data=['updated'=>true,'customer_id'=>$cid,'display_name'=>trim($nm)];}catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Customer update rejected: '.($e instanceof RuntimeException?$e->getMessage():'server error')]],'isError'=>true]);}
 } elseif($name==='create_company' && $canWriteCompany){
  $nm=$args['name']??null;$cn=$args['confirm_name']??null;$role=$args['company_role']??null;$percent=$args['transfer_percent']??null;
  if(!is_string($nm)||!is_string($cn)||!is_string($role)||!is_string($percent)||trim($nm)===''||strlen($nm)>190||!hash_equals(trim($nm),trim($cn))||!preg_match('/^[0-9]{1,3}(\.[0-9]{1,3})?$/D',$percent))$reply($id,null,['code'=>-32602,'message'=>'Invalid or unconfirmed company fields']);
  try{$newId=CompanyService::createCompany($nm,(int)$user['id'],$role,$percent);$data=['created'=>true,'company_id'=>$newId,'name'=>trim($nm)];}
  catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Company creation rejected: '.($e instanceof RuntimeException?$e->getMessage():'server error')]],'isError'=>true]);}
 } elseif($name==='create_panel' && $canWriteCompany){
  $cid=$args['company_id']??null;$confirmCid=$args['confirm_company_id']??null;$nm=$args['name']??null;$confirm=$args['confirm_name']??null;$mode=$args['display_name_mode']??null;
  if(!is_int($cid)||$cid<1||$cid!==$confirmCid||!is_string($nm)||!is_string($confirm)||trim($nm)===''||strlen($nm)>190||!hash_equals(trim($nm),trim($confirm))||!in_array($mode,['display','original'],true))$reply($id,null,['code'=>-32602,'message'=>'Invalid or unconfirmed panel fields']);
  try{$newId=CompanyService::createPanel($cid,$nm,$mode,(int)$user['id']);$data=['created'=>true,'panel_id'=>$newId,'company_id'=>$cid,'name'=>trim($nm)];}
  catch(Throwable $e){$reply($id,['content'=>[['type'=>'text','text'=>'Panel creation rejected: '.($e instanceof RuntimeException?$e->getMessage():'server error')]],'isError'=>true]);}
 } elseif($name==='get_mcp_identity')$data=['username'=>$user['username'],'role'=>$user['role'],'scopes'=>$capabilities];
 elseif($name==='list_companies' && $canCompany){
  $rows=Database::connection()->query('SELECT id,name,active FROM companies WHERE archived_at IS NULL ORDER BY name LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);$data=['companies'=>$rows];
 } elseif($name==='list_panels' && $canPanels){
  $limit=$args['limit']??50;$cid=$args['company_id']??null;
  if(!is_int($limit)||$limit<1||$limit>100||($cid!==null&&(!is_int($cid)||$cid<1)))$reply($id,null,['code'=>-32602,'message'=>'Invalid filters']);
  $sql='SELECT p.id,p.company_id,p.name,p.active FROM panels p JOIN companies c ON c.id=p.company_id WHERE c.archived_at IS NULL';
  $bind=[];if($cid!==null){$sql.=' AND p.company_id=?';$bind[]=$cid;}
  $sql.=' ORDER BY p.company_id,p.name LIMIT '.$limit;
  $st=Database::connection()->prepare($sql);$st->execute($bind);$data=['panels'=>$st->fetchAll(PDO::FETCH_ASSOC)];
 } elseif($name==='list_customers' && $canCustomers){
  $limit=$args['limit']??30;$search=$args['query']??'';
  if(!is_int($limit)||$limit<1||$limit>50||!is_string($search)||strlen($search)>100)$reply($id,null,['code'=>-32602,'message'=>'Invalid filters']);
  $sql='SELECT id,display_name,active FROM customers';$bind=[];
  if($search!==''){$sql.=' WHERE display_name LIKE ?';$bind[]='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';}
  $sql.=' ORDER BY display_name,id LIMIT '.$limit;
  $st=Database::connection()->prepare($sql);$st->execute($bind);$data=['customers'=>$st->fetchAll(PDO::FETCH_ASSOC)];
 } elseif($name==='list_daily_reports' && $canReports){
  $limit=$args['limit']??20;$cid=$args['company_id']??null;
  if(!is_int($limit)||$limit<1||$limit>50||($cid!==null&&(!is_int($cid)||$cid<1)))$reply($id,null,['code'=>-32602,'message'=>'Invalid filters']);
  $sql='SELECT r.id,r.company_id,c.name AS company_name,r.report_date,r.status,r.total_received,r.total_paid,r.total_commission,r.site_net,r.site_status FROM daily_reports r JOIN companies c ON c.id=r.company_id WHERE c.archived_at IS NULL';$bind=[];
  if($cid!==null){$sql.=' AND r.company_id=?';$bind[]=$cid;}
  $sql.=' ORDER BY r.report_date DESC,r.id DESC LIMIT '.$limit;
  $st=Database::connection()->prepare($sql);$st->execute($bind);$data=['reports'=>$st->fetchAll(PDO::FETCH_ASSOC)];
 } elseif($name==='get_daily_report' && $canReports){
  $rid=$args['report_id']??null;
  if(!is_int($rid)||$rid<1)$reply($id,null,['code'=>-32602,'message'=>'Invalid report_id']);
  $st=Database::connection()->prepare('SELECT r.id,r.company_id,c.name AS company_name,r.report_date,r.status,r.total_received,r.total_paid,r.total_commission,r.site_net,r.site_status FROM daily_reports r JOIN companies c ON c.id=r.company_id WHERE c.archived_at IS NULL AND r.id=? LIMIT 1');$st->execute([$rid]);$report=$st->fetch(PDO::FETCH_ASSOC);
  if(!$report)$reply($id,['content'=>[['type'=>'text','text'=>'Report not found or inaccessible']],'isError'=>true]);
  $st=Database::connection()->prepare('SELECT COUNT(*) FROM daily_report_rows WHERE daily_report_id=?');$st->execute([$rid]);$data=['report'=>$report,'row_count'=>(int)$st->fetchColumn()];
 } elseif($name==='get_daily_report_rows' && $canReports){
  $rid=$args['report_id']??null;$limit=$args['limit']??50;$offset=$args['offset']??0;
  if(!is_int($rid)||$rid<1||!is_int($limit)||$limit<1||$limit>100||!is_int($offset)||$offset<0||$offset>100000)$reply($id,null,['code'=>-32602,'message'=>'Invalid pagination']);
  $st=Database::connection()->prepare('SELECT r.id FROM daily_reports r JOIN companies c ON c.id=r.company_id WHERE r.id=? AND c.archived_at IS NULL LIMIT 1');$st->execute([$rid]);if(!$st->fetchColumn())$reply($id,['content'=>[['type'=>'text','text'=>'Report not found or inaccessible']],'isError'=>true]);
  $st=Database::connection()->prepare('SELECT id,panel_id,source_username,display_name_snapshot,member_win,rate_value_snapshot,loss_percent_snapshot,commission,received,paid,row_origin FROM daily_report_rows WHERE daily_report_id=? ORDER BY id LIMIT '.$limit.' OFFSET '.$offset);$st->execute([$rid]);$data=['report_id'=>$rid,'offset'=>$offset,'limit'=>$limit,'rows'=>$st->fetchAll(PDO::FETCH_ASSOC)];
 } elseif($name==='backup_status' && $canDiag){
  // Operational metadata only; no files or credentials returned.
  $dir=__DIR__.'/storage/backups';$files=[];
  foreach(glob($dir.'/soltan-db-*.sql.gz')?:[] as $path){
   if(!is_file($path)||is_link($path))continue;
   $nameOnly=basename($path);
   if(!preg_match('/^soltan-db-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.sql\.gz$/D',$nameOnly))continue;
   $files[]=['created_at'=>gmdate('c',(int)filemtime($path)),'size_bytes'=>(int)filesize($path),'file'=>$nameOnly];
  }
  usort($files,static fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
  $latest=array_slice($files,0,5);
  $data=['backups_count'=>count($files),'latest_backups'=>$latest,'backup_directory_readable'=>is_dir($dir)&&is_readable($dir),'cron_script_present'=>is_file(__DIR__.'/cron_soltan_backup.php'),'cron_execution_verified'=>false,'telegram_delivery_verified'=>false,'note'=>'Cron schedule and Telegram delivery cannot be verified from this endpoint; check logs and host scheduler.'];
 } elseif($name==='diagnostics_health' && $canDiag){
  $dbOk=false;try{Database::connection()->query('SELECT 1');$dbOk=true;}catch(Throwable $ignore){}
  $dir=__DIR__.'/storage/logs';$data=['version'=>trim((string)@file_get_contents(__DIR__.'/VERSION')),'php_version'=>PHP_VERSION,'database_ok'=>$dbOk,'log_directory_readable'=>is_dir($dir)&&is_readable($dir),'time'=>date(DATE_ATOM)];
 } elseif($name==='diagnostics_logs' && $canDiag){
  $channel=$args['channel']??'';$query=$args['query']??'';$limit=$args['limit']??15;
  if(!in_array($channel,['error','system','audit'],true)||!is_string($query)||strlen($query)>120||!is_int($limit)||$limit<1||$limit>30)$reply($id,null,['code'=>-32602,'message'=>'Invalid diagnostic filters']);
  $file=__DIR__.'/storage/logs/'.$channel.'.log';$results=[];$scanned=0;
  if(is_file($file)&&($handle=@fopen($file,'rb'))){
   $size=(int)(@filesize($file)?:0);$start=max(0,$size-1024*1024);fseek($handle,$start);if($start>0)fgets($handle);
   while(($line=fgets($handle))!==false){if(strlen($line)>16384)continue;$record=json_decode($line,true);if(!is_array($record))continue;$scanned++;
    $summary=(string)($record['message']??'');$rid=(string)($record['request_id']??'');
    if($query!==''&&!str_contains(strtolower($summary.' '.$rid),strtolower($query)))continue;
    $context=$record['context']??[];
    // Do not return arbitrary historical context: old audit entries may contain private data.
    $results[]=['time'=>substr((string)($record['ts']??''),0,35),'event'=>substr($summary,0,150),'request_id'=>substr($rid,0,90),'category'=>$channel,'error_type'=>is_array($context)?substr((string)($context['type']??''),0,80):''];
    if(count($results)>$limit)array_shift($results);
   }fclose($handle);
  }
  $data=['channel'=>$channel,'scanned_records'=>$scanned,'limited_to_last_bytes'=>1048576,'records'=>array_reverse($results)];
 }else{$reply($id,null,['code'=>-32602,'message'=>'Unknown or unauthorized tool']);}
 Logger::audit('mcp.tool_called',['user_id'=>(int)$user['id'],'tool'=>$name]);
 $reply($id,['content'=>[['type'=>'text','text'=>json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)]],'isError'=>false]);
}
$reply($id,null,['code'=>-32601,'message'=>'Method not found']);

<?php
declare(strict_types=1);
/** Offline fake-PDO tests. Each query is classified, not executed on a customer database. */
final class PdoFake628 {
    public array $company=['id'=>17,'name'=>'شرکت آزمایشی','archived_at'=>'2026-10-24 12:00:00','active'=>0];
    public array $counts=['active_reports'=>0,'active_transfers'=>0,'foreign_report_rows'=>0,'foreign_report_panels'=>0,'foreign_transfer_items'=>0,'deleted_reports'=>2,'voided_transfers'=>1];
    public array $queries=[];
    public bool $tx=false;
    public bool $failOnDelete=false;
    public int $commits=0;
    public int $rollbacks=0;
    public function beginTransaction():void{$this->tx=true;}
    public function inTransaction():bool{return $this->tx;}
    public function commit():void{$this->commits++;$this->tx=false;}
    public function rollBack():void{$this->rollbacks++;$this->tx=false;}
    public function prepare(string $sql):StmtFake628{return new StmtFake628($this,$sql);}
}
final class StmtFake628 {
    public int $changed=0;
    public mixed $result=null;
    public function __construct(public PdoFake628 $db,public string $sql){}
    public function execute(array $params=[]):bool {
        $this->db->queries[]=$this->sql;
        if(str_starts_with($this->sql,'SELECT id,name,archived_at FROM companies'))$this->result=$this->db->company;
        elseif(str_contains($this->sql,'COUNT(*) FROM daily_reports WHERE company_id=? AND NOT'))$this->result=$this->db->counts['active_reports'];
        elseif(str_contains($this->sql,'COUNT(*) FROM transfers WHERE company_id=? AND NOT'))$this->result=$this->db->counts['active_transfers'];
        elseif(str_contains($this->sql,'COUNT(*) FROM daily_report_rows rr JOIN daily_reports dr'))$this->result=$this->db->counts['foreign_report_rows'];
        elseif(str_contains($this->sql,'COUNT(*) FROM daily_reports dr JOIN panels p'))$this->result=$this->db->counts['foreign_report_panels'];
        elseif(str_contains($this->sql,'COUNT(*) FROM transfer_report_items tri'))$this->result=$this->db->counts['foreign_transfer_items'];
        elseif(str_contains($this->sql,'COUNT(*) FROM daily_reports WHERE company_id=? AND status='))$this->result=$this->db->counts['deleted_reports'];
        elseif(str_contains($this->sql,'COUNT(*) FROM transfers WHERE company_id=? AND status='))$this->result=$this->db->counts['voided_transfers'];
        elseif(str_starts_with($this->sql,'DELETE FROM')){
            if($this->db->failOnDelete && str_contains($this->sql,'daily_reports'))throw new RuntimeException('DB failure simulation');
            $this->changed=1;
        }
        elseif(str_starts_with($this->sql,'UPDATE companies SET active=1')){
            $this->changed=$this->db->company['archived_at']===null && $this->db->company['active']===0?1:0;
            if($this->changed)$this->db->company['active']=1;
        }
        elseif(str_starts_with($this->sql,'UPDATE companies SET archived_at=NULL')){
            $this->changed=$this->db->company['archived_at']===null?0:1;
            $this->db->company['archived_at']=null;$this->db->company['active']=1;
        }
        return true;
    }
    public function fetch(int $mode=0):mixed {return $this->result;}
    public function fetchColumn():mixed {return $this->result;}
    public function rowCount():int{return $this->changed;}
}
class Database {public static PdoFake628 $db;public static function connection():PdoFake628{return self::$db;}}
class Logger {public static array $events=[];public static function audit(string $action,array $data):void{self::$events[]=[$action,$data];}}
require dirname(__DIR__).'/app/Services/CompanyService.php';
function ok(bool $test,string $message):void{if(!$test)throw new RuntimeException('FAIL '.$message); echo "[OK] $message\n";}
function attempted(callable $fn,string $msg):void{try{$fn();throw new RuntimeException('UNEXPECTED PASS '.$msg);}catch(RuntimeException $e){if(str_starts_with($e->getMessage(),'UNEXPECTED PASS'))throw $e;ok(true,$msg);}}
$db=Database::$db=new PdoFake628();
attempted(fn()=>CompanyService::purgeArchivedCompany(17,'شرکت آزمایشی','wrong',1),'requires second confirmation');
ok(count($db->queries)===0,'no DB call before second confirmation');
attempted(fn()=>CompanyService::purgeArchivedCompany(17,'wrong name','حذف دائمی',1),'requires exact company name');
ok(!array_filter($db->queries,static fn($s)=>str_starts_with($s,'DELETE FROM')),'wrong name never deletes');
foreach(['active_reports','active_transfers','foreign_report_rows','foreign_report_panels','foreign_transfer_items'] as $block){
  $db=Database::$db=new PdoFake628();$db->counts[$block]=1;
  attempted(fn()=>CompanyService::purgeArchivedCompany(17,'شرکت آزمایشی','حذف دائمی',1),'blocks '.$block);
  ok(!array_filter($db->queries,static fn($s)=>str_starts_with($s,'DELETE FROM')),'no delete after '.$block);
  ok($db->rollbacks===1,'rolls back '.$block);
}
$db=Database::$db=new PdoFake628();
$r=CompanyService::purgeArchivedCompany(17,'شرکت آزمایشی','حذف دائمی',1);
ok($r['deleted_reports']===2&&$r['voided_transfers']===1,'reports purged counts');
$queries=array_values(array_filter($db->queries,static fn($s)=>str_starts_with($s,'DELETE FROM')));
ok(count($queries)===6,'purge cleans transfer/report/account/import/panel/company');
ok(str_contains($queries[0],'transfers')&&str_contains($queries[1],'daily_reports')&&str_contains($queries[2],'accounts')&&str_contains($queries[3],'account_settings_imports')&&str_contains($queries[4],'panels')&&str_contains($queries[5],'companies'),'foreign-key safe deletion order');
ok($db->commits===1&&$db->rollbacks===0,'commits valid purge');
$db=Database::$db=new PdoFake628();$db->failOnDelete=true;
attempted(fn()=>CompanyService::purgeArchivedCompany(17,'شرکت آزمایشی','حذف دائمی',1),'DB failure triggers exception');
ok($db->rollbacks===1&&$db->commits===0,'DB failure rolls back all work');
$db=Database::$db=new PdoFake628();$db->company['archived_at']=null;
attempted(fn()=>CompanyService::purgeArchivedCompany(17,'شرکت آزمایشی','حذف دائمی',1),'active company cannot be purged');
$db=Database::$db=new PdoFake628();CompanyService::restoreCompany(17,1);
ok($db->company['archived_at']===null&&$db->company['active']===1,'restored company is active');
ok(count(Logger::$events)===2,'purge and restore audited');
$db=Database::$db=new PdoFake628();$db->company['archived_at']=null;$db->company['active']=0;CompanyService::activateCompany(17,1);
ok($db->company['active']===1,'old restored company reactivated manually');
ok(count(Logger::$events)===3,'manual reactivation audited');
echo "All 0.6.28 fake-DB checks passed\n";

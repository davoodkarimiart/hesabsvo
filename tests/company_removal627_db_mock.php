<?php
declare(strict_types=1);
// Isolated deterministic DB mock: verifies SQL ordering and no destructive queries when financial history exists.
final class FakeDB627 {
    public array $company=['id'=>17,'name'=>'شرکت آزمایشی','archived_at'=>null,'active'=>1];
    public array $counts=['reports'=>0,'transfers'=>0,'report_rows'=>0,'panel_rows'=>0];
    public array $queries=[];
    public bool $transaction=false;
    public function beginTransaction():void {$this->transaction=true;}
    public function inTransaction():bool{return $this->transaction;}
    public function commit():void{$this->transaction=false;}
    public function rollBack():void{$this->transaction=false;}
    public function prepare(string $sql):FakeStmt627{return new FakeStmt627($this,$sql);}
}
final class FakeStmt627 {
    public mixed $value=null;
    public int $changed=0;
    public function __construct(public FakeDB627 $db,public string $sql) {}
    public function execute(array $params=[]):bool {
        $this->db->queries[]=$this->sql;
        if(str_starts_with($this->sql,'SELECT id,name,archived_at FROM companies')){
            $this->value=$this->db->company;
        }elseif(str_starts_with($this->sql,'SELECT COUNT(*) FROM daily_reports')){$this->value=$this->db->counts['reports'];}
        elseif(str_starts_with($this->sql,'SELECT COUNT(*) FROM transfers')){$this->value=$this->db->counts['transfers'];}
        elseif(str_starts_with($this->sql,'SELECT COUNT(*) FROM daily_report_rows rr JOIN accounts')){$this->value=$this->db->counts['report_rows'];}
        elseif(str_starts_with($this->sql,'SELECT COUNT(*) FROM daily_report_rows rr JOIN panels')){$this->value=$this->db->counts['panel_rows'];}
        elseif(str_starts_with($this->sql,'UPDATE companies SET archived_at=NOW()')){$this->db->company['archived_at']='2026-10-24 12:00:00';$this->db->company['active']=0;}
        elseif(str_starts_with($this->sql,'UPDATE companies SET archived_at=NULL')){
            $this->changed=$this->db->company['archived_at']!==null?1:0;
            $this->db->company['archived_at']=null;$this->db->company['active']=1;
        }
        return true;
    }
    public function fetch(int $mode=0):mixed{return $this->value;}
    public function fetchColumn():mixed{return $this->value;}
    public function rowCount():int{return $this->changed;}
}
class Database{public static FakeDB627 $db;public static function connection():FakeDB627{return self::$db;}}
class Logger {public static array $audits=[];public static function audit(string $event,array $data):void {self::$audits[]=[$event,$data];}}
require __DIR__.'/../app/Services/CompanyService.php';
function check(bool $condition,string $what):void {if(!$condition)throw new RuntimeException('FAILED: '.$what);echo '[OK] '.$what."\n";}
$db=Database::$db=new FakeDB627();
try {CompanyService::removeCompany(17,'اشتباه',1);throw new RuntimeException('Missing name check');}
catch(RuntimeException $e){check(str_contains($e->getMessage(),'نام تأییدشده'),'requires exact typed name');}
check(!in_array('DELETE FROM companies WHERE id=?',$db->queries,true),'no deletion on mismatched name');
$db=Database::$db=new FakeDB627();$db->counts['reports']=2;
check(CompanyService::removeCompany(17,'شرکت آزمایشی',1)==='archived','company with reports is archived');
check(!in_array('DELETE FROM companies WHERE id=?',$db->queries,true),'no financial deletion when reports exist');
check($db->company['archived_at']!==null,'archive timestamp saved');
CompanyService::restoreCompany(17,1);check($db->company['archived_at']===null && $db->company['active']===1,'restore clears archive and reactivates company');
$db=Database::$db=new FakeDB627();$db->counts['transfers']=1;
check(CompanyService::removeCompany(17,'شرکت آزمایشی',1)==='archived','company with transfer is archived');
$db=Database::$db=new FakeDB627();$db->counts['panel_rows']=1;
check(CompanyService::removeCompany(17,'شرکت آزمایشی',1)==='archived','company with cross-report panel references is archived');
$db=Database::$db=new FakeDB627();$db->counts['report_rows']=1;
check(CompanyService::removeCompany(17,'شرکت آزمایشی',1)==='archived','company with account financial references is archived');
$db=Database::$db=new FakeDB627();
check(CompanyService::removeCompany(17,'شرکت آزمایشی',1)==='deleted','company with no financial history can be removed');
$deletes=array_values(array_filter($db->queries,static fn($sql)=>str_starts_with($sql,'DELETE FROM')));
check(count($deletes)===4,'cleans accounts, imports, panels, company');
check(str_contains($deletes[0],'accounts')&&str_contains($deletes[1],'account_settings_imports')&&str_contains($deletes[2],'panels')&&str_contains($deletes[3],'companies'),'safe deletion order');
check(count(Logger::$audits)===6,'all successful actions audited');

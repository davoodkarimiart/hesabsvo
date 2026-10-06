<?php

declare(strict_types=1);

final class ReportingService {
    public static function filters(): array {
        $pdo=Database::connection();
        $panels=$pdo->query("SELECT p.id,p.name,p.company_id,c.name company_name FROM panels p JOIN companies c ON c.id=p.company_id WHERE p.active=1 AND c.active=1 ORDER BY c.name,p.name")->fetchAll();
        return ['companies'=>AccountingService::companiesForEntry(),'panels'=>$panels,'customers'=>CustomerService::list('',true)];
    }

    public static function normalizeFilters(array $f): array {
        $f['type']=in_array(($f['type']??'daily'),['daily','monthly','multi','detail'],true)?$f['type']:'daily';
        $f['company_id']=(int)($f['company_id']??0);$f['panel_id']=(int)($f['panel_id']??0);$f['customer_id']=(int)($f['customer_id']??0);
        $f['from']=trim((string)($f['from']??''));$f['to']=trim((string)($f['to']??''));
        if($f['company_id']>0 && $f['panel_id']>0){
            $st=Database::connection()->prepare('SELECT COUNT(*) FROM panels WHERE id=? AND company_id=? AND active=1');$st->execute([$f['panel_id'],$f['company_id']]);
            if(!(int)$st->fetchColumn())$f['panel_id']=0; // stale/foreign panel selection is reset, never accepted silently
        }
        return $f;
    }

    public static function report(array $input): array {
        $f=self::normalizeFilters($input);$type=$f['type'];$pdo=Database::connection();$where=["r.status='final'"];$p=[];
        if($f['company_id']>0){$where[]='r.company_id=?';$p[]=$f['company_id'];}
        if($f['panel_id']>0){
            // report.panel_id is historical convenience only; row ownership is authoritative.
            $where[]='EXISTS(SELECT 1 FROM daily_report_rows px WHERE px.daily_report_id=r.id AND px.panel_id=?)';$p[]=$f['panel_id'];
        }
        if($f['from']!==''){$where[]='r.report_date>=?';$p[]=self::date($f['from']);}
        if($f['to']!==''){$where[]='r.report_date<=?';$p[]=self::date($f['to']);}
        if($f['customer_id']>0){$where[]='EXISTS(SELECT 1 FROM daily_report_rows x JOIN customer_accounts ca ON ca.account_id=x.account_id WHERE x.daily_report_id=r.id AND ca.customer_id=?)';$p[]=$f['customer_id'];}
        $w=implode(' AND ',$where);

        if($type==='monthly'||$type==='multi'){
            $sql="SELECT r.id,r.report_date,CAST(r.total_received AS DECIMAL(24,6)) received,CAST(r.total_paid AS DECIMAL(24,6)) paid,CAST(r.total_commission AS DECIMAL(24,6)) commission,(CAST(r.total_received AS DECIMAL(24,6))-CAST(r.total_paid AS DECIMAL(24,6))) net FROM daily_reports r WHERE $w ORDER BY r.report_date DESC,r.id DESC LIMIT 5000";
        } elseif($type==='detail'){
            $whereDetail=$w;$pd=$p;
            if($f['customer_id']>0){$whereDetail.=' AND EXISTS(SELECT 1 FROM customer_accounts ca2 WHERE ca2.account_id=rr.account_id AND ca2.customer_id=?)';$pd[]=$f['customer_id'];}
            if($f['from']===''&&$f['to']==='')$whereDetail.=" AND r.id IN (SELECT z.id FROM (SELECT id FROM daily_reports WHERE status='final' ORDER BY report_date DESC,id DESC LIMIT 10) z)";
            $sql="SELECT r.id,r.report_date,c.name company_name,COALESCE(pn.name,(SELECT p2.name FROM daily_report_rows xr JOIN panels p2 ON p2.id=xr.panel_id WHERE xr.daily_report_id=r.id AND xr.panel_id IS NOT NULL GROUP BY xr.panel_id,p2.name ORDER BY xr.panel_id LIMIT 1)) panel_name,rr.source_username,COALESCE(NULLIF(rr.display_name_snapshot,''),NULLIF(rr.original_name_snapshot,''),rr.source_username) name,rr.member_win,rr.commission,rr.received,rr.paid FROM daily_reports r JOIN daily_report_rows rr ON rr.daily_report_id=r.id JOIN companies c ON c.id=r.company_id LEFT JOIN panels pn ON pn.id=rr.panel_id WHERE $whereDetail ORDER BY r.report_date DESC,r.id DESC,rr.id LIMIT 3000";$p=$pd;
        } else {
            $lim=($f['from']!==''||$f['to']!=='')?500:10;$hasSource=self::columnExists($pdo,'daily_reports','source_storage_path');$src=$hasSource?'r.source_storage_path':'NULL AS source_storage_path';
            $panelExpr="COALESCE(r.panel_id,(SELECT MIN(x.panel_id) FROM daily_report_rows x WHERE x.daily_report_id=r.id AND x.panel_id IS NOT NULL HAVING COUNT(DISTINCT x.panel_id)=1))";
            $sql="SELECT r.id,r.report_date,c.name company_name,COALESCE(pn.name,'—') panel_name,r.source_filename,$src,r.total_received received,r.total_paid paid,r.total_commission commission,(CAST(r.total_received AS DECIMAL(24,6))-CAST(r.total_paid AS DECIMAL(24,6))) net,r.site_status FROM daily_reports r JOIN companies c ON c.id=r.company_id LEFT JOIN panels pn ON pn.id=$panelExpr WHERE $w ORDER BY r.report_date DESC,r.id DESC LIMIT $lim";
        }
        $st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();
        // Normalize DB DECIMAL strings once here so rendering never calls numeric functions on strings.
        foreach($rows as &$r){foreach(['received','paid','commission','net','member_win'] as $k)if(array_key_exists($k,$r))$r[$k]=(float)$r[$k];}unset($r);

        if($type==='monthly'||$type==='multi'){
            $groups=[];foreach($rows as $r){$fa=AccountingService::displayBusinessDate((string)$r['report_date']);$period=substr($fa,0,7);if(!isset($groups[$period]))$groups[$period]=['period'=>$period,'reports'=>0,'received'=>0.0,'paid'=>0.0,'commission'=>0.0,'net'=>0.0];$groups[$period]['reports']++;$groups[$period]['received']+=$r['received'];$groups[$period]['paid']+=$r['paid'];$groups[$period]['commission']+=$r['commission'];$groups[$period]['net']+=$r['net'];}$rows=array_values($groups);usort($rows,fn($a,$b)=>strcmp($b['period'],$a['period']));$rows=array_slice($rows,0,($f['from']!==''||$f['to']!=='')?120:10);
        }
        $tot=['received'=>0.0,'paid'=>0.0,'commission'=>0.0,'net'=>0.0];foreach($rows as $r){foreach(['received','paid','commission'] as $k)$tot[$k]+=(float)($r[$k]??0);}$tot['net']=$tot['received']-$tot['paid'];
        return ['type'=>$type,'rows'=>$rows,'totals'=>$tot,'filters'=>$f];
    }
    private static function date(string $v): string {return AccountingService::normalizeBusinessDate($v);}
    private static function columnExists(PDO $pdo,string $table,string $column): bool {$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$st->execute([$table,$column]);return (bool)$st->fetchColumn();}
}

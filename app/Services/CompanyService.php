<?php

declare(strict_types=1);

final class CompanyService {
    public static function all(): array {
        $pdo = Database::connection();
        $companies = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM panels p WHERE p.company_id=c.id) panel_count, (SELECT COUNT(*) FROM accounts a JOIN panels p2 ON p2.id=a.panel_id WHERE p2.company_id=c.id) account_count FROM companies c ORDER BY c.active DESC, c.name")->fetchAll();
        foreach ($companies as &$c) {
            $st = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM accounts a WHERE a.panel_id=p.id) account_count, (SELECT COUNT(*) FROM accounts a WHERE a.panel_id=p.id AND a.active=1) active_account_count, (SELECT imported_at FROM account_settings_imports i WHERE i.panel_id=p.id AND i.status='completed' ORDER BY i.id DESC LIMIT 1) last_import_at FROM panels p WHERE p.company_id=? ORDER BY p.active DESC,p.name");
            $st->execute([$c['id']]);
            $c['panels'] = $st->fetchAll();
        }
        return $companies;
    }

    public static function createCompany(string $name, int $userId): int {
        $name = trim($name);
        if ($name === '') throw new RuntimeException('نام شرکت الزامی است.');
        $pdo = Database::connection();
        $st = $pdo->prepare('INSERT INTO companies(name,active,created_by) VALUES(?,1,?)');
        try { $st->execute([$name,$userId]); }
        catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('شرکتی با این نام از قبل وجود دارد.');
            throw $e;
        }
        $id = (int)$pdo->lastInsertId();
        Logger::audit('company.created',['company_id'=>$id,'name'=>$name]);
        return $id;
    }

    public static function createPanel(int $companyId, string $name, string $mode, int $userId): int {
        if (!in_array($mode,['display','original'],true)) $mode='display';
        $name=trim($name); if($name==='') throw new RuntimeException('نام پنل الزامی است.');
        $pdo=Database::connection();
        $st=$pdo->prepare('INSERT INTO panels(company_id,name,display_name_mode,active,created_by) VALUES(?,?,?,1,?)');
        try{$st->execute([$companyId,$name,$mode,$userId]);}
        catch(PDOException $e){if((int)($e->errorInfo[1]??0)===1062) throw new RuntimeException('در این شرکت پنلی با این نام وجود دارد.'); throw $e;}
        $id=(int)$pdo->lastInsertId(); Logger::audit('panel.created',['panel_id'=>$id,'company_id'=>$companyId,'name'=>$name,'display_name_mode'=>$mode]); return $id;
    }

    public static function updatePanel(int $panelId,string $name,string $mode,bool $active): void {
        if(!in_array($mode,['display','original'],true))$mode='display';
        $pdo=Database::connection();$st=$pdo->prepare('UPDATE panels SET name=?,display_name_mode=?,active=? WHERE id=?');$st->execute([trim($name),$mode,$active?1:0,$panelId]);
        Logger::audit('panel.updated',['panel_id'=>$panelId,'name'=>trim($name),'display_name_mode'=>$mode,'active'=>$active]);
    }

    public static function panel(int $id): ?array {
        $st=Database::connection()->prepare('SELECT p.*,c.name company_name FROM panels p JOIN companies c ON c.id=p.company_id WHERE p.id=?');$st->execute([$id]);$x=$st->fetch();return $x?:null;
    }

    public static function panelAccounts(int $panelId,string $q=''): array {
        $pdo=Database::connection();
        if($q!==''){$like='%'.$q.'%';$st=$pdo->prepare('SELECT * FROM accounts WHERE panel_id=? AND (source_username LIKE ? OR source_uuid LIKE ? OR original_name LIKE ? OR display_name LIKE ?) ORDER BY active DESC,display_name,original_name LIMIT 500');$st->execute([$panelId,$like,$like,$like,$like]);}
        else{$st=$pdo->prepare('SELECT * FROM accounts WHERE panel_id=? ORDER BY active DESC,display_name,original_name LIMIT 500');$st->execute([$panelId]);}
        return $st->fetchAll();
    }

    public static function setAccountRateType(int $accountId,string $type): void {
        if(!in_array($type,['general','special'],true))throw new RuntimeException('نوع نرخ نامعتبر است.');
        Database::connection()->prepare('UPDATE accounts SET rate_type=? WHERE id=?')->execute([$type,$accountId]);
        Logger::audit('account.rate_type_changed',['account_id'=>$accountId,'rate_type'=>$type]);
    }

    public static function setDisplayName(int $accountId,string $name): void {
        Database::connection()->prepare('UPDATE accounts SET display_name=? WHERE id=?')->execute([trim($name),$accountId]);
        Logger::audit('account.display_name_changed',['account_id'=>$accountId]);
    }
}

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

    /** Company transfer settings are company-level; no panel data is changed. */
    public static function validateTransferSettings(string $role, string $percent): array {
        if (!in_array($role, ['agent','master'], true)) {
            throw new RuntimeException('نوع شرکت را بین ایجنت و مستر انتخاب کن.');
        }
        $input = strtr(trim($percent), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.','،'=>'.']);
        if (!preg_match('/^(?:\d{1,3})(?:\.\d{1,3})?$/D', $input)) {
            throw new RuntimeException('درصد ترانسفر باید عددی از ۰ تا ۱۰۰ با حداکثر ۳ رقم اعشار باشد.');
        }
        $value = (float)$input;
        if ($value < 0 || $value > 100) throw new RuntimeException('درصد ترانسفر باید بین ۰ و ۱۰۰ باشد.');
        return [$role, number_format($value,3,'.','')];
    }

    public static function createCompany(string $name, int $userId, string $role, string $percent): int {
        $name = trim($name);
        if ($name === '') throw new RuntimeException('نام شرکت الزامی است.');
        [$role, $percent] = self::validateTransferSettings($role,$percent);
        $pdo = Database::connection();
        $st = $pdo->prepare('INSERT INTO companies(name,active,created_by,company_role,transfer_percent) VALUES(?,1,?,?,?)');
        try { $st->execute([$name,$userId,$role,$percent]); }
        catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('شرکتی با این نام از قبل وجود دارد.');
            throw $e;
        }
        $id = (int)$pdo->lastInsertId();
        Logger::audit('company.created',['company_id'=>$id,'name'=>$name,'company_role'=>$role,'transfer_percent'=>$percent]);
        return $id;
    }

    public static function updateCompany(int $id, string $name, bool $active, string $role, string $percent): void {
        $name=trim($name);
        if ($id<1 || $name==='') throw new RuntimeException('شناسه یا نام شرکت نامعتبر است.');
        [$role,$percent]=self::validateTransferSettings($role,$percent);
        $pdo=Database::connection();
        $check=$pdo->prepare('SELECT id FROM companies WHERE id=?');$check->execute([$id]);
        if (!$check->fetchColumn()) throw new RuntimeException('شرکت مورد نظر پیدا نشد.');
        try {
            $pdo->prepare('UPDATE companies SET name=?,active=?,company_role=?,transfer_percent=? WHERE id=?')
                ->execute([$name,$active?1:0,$role,$percent,$id]);
        } catch(PDOException $e) {
            if ((int)($e->errorInfo[1]??0)===1062) throw new RuntimeException('شرکتی با این نام از قبل وجود دارد.');
            throw $e;
        }
        Logger::audit('company.updated',['company_id'=>$id,'name'=>$name,'active'=>$active,'company_role'=>$role,'transfer_percent'=>$percent]);
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

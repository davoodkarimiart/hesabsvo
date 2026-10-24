<?php

declare(strict_types=1);

final class CompanyService {
    public static function all(): array {
        $pdo = Database::connection();
        $companies = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM panels p WHERE p.company_id=c.id) panel_count, (SELECT COUNT(*) FROM accounts a JOIN panels p2 ON p2.id=a.panel_id WHERE p2.company_id=c.id) account_count, (SELECT COUNT(*) FROM daily_reports r WHERE r.company_id=c.id) report_count, (SELECT COUNT(*) FROM transfers t WHERE t.company_id=c.id) transfer_count FROM companies c WHERE c.archived_at IS NULL ORDER BY c.active DESC, c.name")->fetchAll();
        foreach ($companies as &$c) {
            $st = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM accounts a WHERE a.panel_id=p.id) account_count, (SELECT COUNT(*) FROM accounts a WHERE a.panel_id=p.id AND a.active=1) active_account_count, (SELECT imported_at FROM account_settings_imports i WHERE i.panel_id=p.id AND i.status='completed' ORDER BY i.id DESC LIMIT 1) last_import_at FROM panels p WHERE p.company_id=? ORDER BY p.active DESC,p.name");
            $st->execute([$c['id']]);
            $c['panels'] = $st->fetchAll();
        }
        return $companies;
    }

    /** Archived companies keep their financial history but cannot be selected for new operations. */
    public static function archived(): array {
        return Database::connection()->query("SELECT c.id,c.name,c.archived_at,
            (SELECT COUNT(*) FROM daily_reports r WHERE r.company_id=c.id AND NOT (r.status='deleted' AND r.deleted_at IS NOT NULL)) pending_reports,
            (SELECT COUNT(*) FROM transfers t WHERE t.company_id=c.id AND NOT (t.status='void' AND t.voided_at IS NOT NULL)) pending_transfers,
            (SELECT COUNT(*) FROM daily_reports r WHERE r.company_id=c.id AND r.status='deleted' AND r.deleted_at IS NOT NULL) deleted_reports,
            (SELECT COUNT(*) FROM transfers t WHERE t.company_id=c.id AND t.status='void' AND t.voided_at IS NOT NULL) voided_transfers
            FROM companies c WHERE c.archived_at IS NOT NULL ORDER BY c.archived_at DESC,c.id DESC")->fetchAll();
    }

    /**
     * Safe company removal: purge only when no report/transfer/customer-ledger reference exists.
     * All other cases archive in place, preserving FK-linked financial history.
     * The exact company name is required to prevent accidental destructive actions.
     */
    public static function removeCompany(int $id, string $confirmedName, int $userId): string {
        if ($id < 1) throw new RuntimeException('شناسه شرکت نامعتبر است.');
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT id,name,archived_at FROM companies WHERE id=? FOR UPDATE');
            $q->execute([$id]);$company=$q->fetch(PDO::FETCH_ASSOC);
            if (!$company) throw new RuntimeException('شرکت موردنظر پیدا نشد.');
            if ($company['archived_at'] !== null) throw new RuntimeException('این شرکت قبلاً بایگانی شده است.');
            if (!hash_equals((string)$company['name'],trim($confirmedName))) throw new RuntimeException('نام تأییدشده با نام شرکت مطابقت ندارد.');
            $counts=[];
            $queries=[
                'reports'=>'SELECT COUNT(*) FROM daily_reports WHERE company_id=?',
                'transfers'=>'SELECT COUNT(*) FROM transfers WHERE company_id=?',
                'report_rows'=>'SELECT COUNT(*) FROM daily_report_rows rr JOIN accounts a ON a.id=rr.account_id JOIN panels p ON p.id=a.panel_id WHERE p.company_id=?',
                'panel_rows'=>'SELECT COUNT(*) FROM daily_report_rows rr JOIN panels p ON p.id=rr.panel_id WHERE p.company_id=?',
            ];
            foreach ($queries as $key=>$sql) {$st=$pdo->prepare($sql);$st->execute([$id]);$counts[$key]=(int)$st->fetchColumn();}
            $hasFinancialHistory=array_sum($counts)>0;
            if ($hasFinancialHistory) {
                // Do not mutate report rows, historical transfers, customer ledgers or archived source files.
                $pdo->prepare('UPDATE companies SET archived_at=NOW(),active=0 WHERE id=?')->execute([$id]);
                $outcome='archived';
            } else {
                // FKs on customer_accounts and customer_autogroup_exclusions cascade on account deletion.
                $panelScope='SELECT id FROM panels WHERE company_id=?';
                $pdo->prepare("DELETE FROM accounts WHERE panel_id IN ($panelScope)")->execute([$id]);
                $pdo->prepare("DELETE FROM account_settings_imports WHERE panel_id IN ($panelScope)")->execute([$id]);
                $pdo->prepare('DELETE FROM panels WHERE company_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM companies WHERE id=?')->execute([$id]);
                $outcome='deleted';
            }
            $pdo->commit();
            Logger::audit('company.'.($outcome==='deleted'?'deleted':'archived'),['company_id'=>$id,'by'=>$userId,'reason'=>'user_requested','financial_references'=>$counts]);
            return $outcome;
        } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function restoreCompany(int $id, int $userId): void {
        if ($id<1) throw new RuntimeException('شناسه شرکت نامعتبر است.');
        $pdo=Database::connection();
        // Restoration must make the company usable and visible in regular company selectors.
        $st=$pdo->prepare('UPDATE companies SET archived_at=NULL,active=1 WHERE id=? AND archived_at IS NOT NULL');
        $st->execute([$id]);
        if($st->rowCount()!==1)throw new RuntimeException('شرکت بایگانی‌شده پیدا نشد.');
        Logger::audit('company.restored',['company_id'=>$id,'by'=>$userId,'active'=>1]);
    }

    /**
     * Irreversible purge of an archived test company, explicitly confirmed by its exact name
     * and a second human phrase. Only previously soft-deleted reports and voided transfers
     * may be removed. Active financial records and cross-company references BLOCK the purge.
     * Foreign-key cascades remove report/transfer revisions and rows in the same transaction.
     */
    public static function purgeArchivedCompany(int $id, string $confirmedName, string $confirmation, int $userId): array {
        if ($id < 1) throw new RuntimeException('شناسه شرکت نامعتبر است.');
        if (trim($confirmation) !== 'حذف دائمی') throw new RuntimeException('برای تأیید نهایی عبارت «حذف دائمی» را وارد کن.');
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try {
            $st=$pdo->prepare('SELECT id,name,archived_at FROM companies WHERE id=? FOR UPDATE');
            $st->execute([$id]); $company=$st->fetch(PDO::FETCH_ASSOC);
            if (!$company || $company['archived_at']===null) throw new RuntimeException('شرکت موردنظر در بایگانی پیدا نشد.');
            if (!hash_equals((string)$company['name'],trim($confirmedName))) throw new RuntimeException('نام تأییدشده با نام شرکت مطابقت ندارد.');
            $queries=[
                'active_reports'=>["SELECT COUNT(*) FROM daily_reports WHERE company_id=? AND NOT (status='deleted' AND deleted_at IS NOT NULL)",[$id]],
                'active_transfers'=>["SELECT COUNT(*) FROM transfers WHERE company_id=? AND NOT (status='void' AND voided_at IS NOT NULL)",[$id]],
                // Cross-company report rows may reference this company's panels/accounts even if the row's report belongs elsewhere.
                'foreign_report_rows'=>["SELECT COUNT(*) FROM daily_report_rows rr JOIN daily_reports dr ON dr.id=rr.daily_report_id LEFT JOIN panels p ON p.id=rr.panel_id LEFT JOIN accounts a ON a.id=rr.account_id LEFT JOIN panels ap ON ap.id=a.panel_id WHERE dr.company_id<>? AND (p.company_id=? OR ap.company_id=?)",[$id,$id,$id]],
                'foreign_report_panels'=>["SELECT COUNT(*) FROM daily_reports dr JOIN panels p ON p.id=dr.panel_id WHERE p.company_id=? AND dr.company_id<>?",[$id,$id]],
                'foreign_transfer_items'=>["SELECT COUNT(*) FROM transfer_report_items tri JOIN daily_reports dr ON dr.id=tri.daily_report_id JOIN transfers t ON t.id=tri.transfer_id WHERE (dr.company_id=? AND t.company_id<>?) OR (t.company_id=? AND dr.company_id<>?)",[$id,$id,$id,$id]],
            ];
            $blocking=[];
            foreach($queries as $type=>[$sql,$args]){
                $check=$pdo->prepare($sql);$check->execute($args);
                $n=(int)$check->fetchColumn();if($n>0)$blocking[$type]=$n;
            }
            if ($blocking) {
                $labels=['active_reports'=>'گزارش حذف‌نشده','active_transfers'=>'ترانسفر ابطال‌نشده','foreign_report_rows'=>'ارجاع حساب در گزارش شرکت دیگر','foreign_report_panels'=>'ارجاع پنل در گزارش شرکت دیگر','foreign_transfer_items'=>'ارتباط ترانسفر با گزارش شرکت دیگر'];
                $pieces=[];foreach($blocking as $type=>$count)$pieces[]=($labels[$type]??$type).': '.$count;
                throw new RuntimeException('حذف دائمی مجاز نیست؛ ابتدا وابستگی‌ها را برطرف کن. '.implode('، ',$pieces));
            }
            $cReports=$pdo->prepare("SELECT COUNT(*) FROM daily_reports WHERE company_id=? AND status='deleted' AND deleted_at IS NOT NULL");$cReports->execute([$id]);$reportCount=(int)$cReports->fetchColumn();
            $cTransfers=$pdo->prepare("SELECT COUNT(*) FROM transfers WHERE company_id=? AND status='void' AND voided_at IS NOT NULL");$cTransfers->execute([$id]);$transferCount=(int)$cTransfers->fetchColumn();
            // Remove the voided settlement references first, because their report items have RESTRICT FKs.
            $pdo->prepare("DELETE FROM transfers WHERE company_id=? AND status='void' AND voided_at IS NOT NULL")->execute([$id]);
            $pdo->prepare("DELETE FROM daily_reports WHERE company_id=? AND status='deleted' AND deleted_at IS NOT NULL")->execute([$id]);
            $panelScope='SELECT id FROM panels WHERE company_id=?';
            $pdo->prepare("DELETE FROM accounts WHERE panel_id IN ($panelScope)")->execute([$id]);
            $pdo->prepare("DELETE FROM account_settings_imports WHERE panel_id IN ($panelScope)")->execute([$id]);
            $pdo->prepare('DELETE FROM panels WHERE company_id=?')->execute([$id]);
            $del=$pdo->prepare('DELETE FROM companies WHERE id=? AND archived_at IS NOT NULL');$del->execute([$id]);
            if($del->rowCount()!==1)throw new RuntimeException('حذف نهایی شرکت انجام نشد.');
            $pdo->commit();
            Logger::audit('company.purged',['company_id'=>$id,'by'=>$userId,'soft_deleted_reports_purged'=>$reportCount,'voided_transfers_purged'=>$transferCount]);
            return ['deleted_reports'=>$reportCount,'voided_transfers'=>$transferCount];
        } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    /** For companies restored before v0.6.28 that were left inactive, offer explicit reactivation. */
    public static function activateCompany(int $id, int $userId): void {
        if($id<1)throw new RuntimeException('شناسه شرکت نامعتبر است.');
        $st=Database::connection()->prepare('UPDATE companies SET active=1 WHERE id=? AND archived_at IS NULL AND active=0');
        $st->execute([$id]);
        if($st->rowCount()!==1)throw new RuntimeException('شرکت غیرفعال برای فعال‌سازی پیدا نشد.');
        Logger::audit('company.activated',['company_id'=>$id,'by'=>$userId]);
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
        $check=$pdo->prepare('SELECT id FROM companies WHERE id=? AND archived_at IS NULL');$check->execute([$id]);
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
        $check=$pdo->prepare('SELECT id FROM companies WHERE id=? AND active=1 AND archived_at IS NULL');$check->execute([$companyId]);
        if(!$check->fetchColumn())throw new RuntimeException('شرکت غیرفعال یا بایگانی‌شده است؛ ساخت پنل مجاز نیست.');
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

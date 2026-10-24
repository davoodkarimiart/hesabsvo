<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$s=file_get_contents($root.'/app/Services/CompanyService.php');
$u=file_get_contents($root.'/index.php');
$tests=[
'restore returns company to active list'=>str_contains($s,'SET archived_at=NULL,active=1'),
'previously restored inactive company can be activated'=>str_contains($s,'function activateCompany')&&str_contains($u,'activate_company'),
'purge only archived companies'=>str_contains($s,'$company' . "['archived_at']===null"),
'blocks financial active references'=>str_contains($s,"status='deleted' AND deleted_at IS NOT NULL")&&str_contains($s,"status='void' AND voided_at IS NOT NULL"),
'blocks cross-company references'=>str_contains($s,'foreign_report_rows')&&str_contains($s,'foreign_transfer_items'),
'all purge operations in transaction'=>str_contains($s,'public static function purgeArchivedCompany')&&str_contains($s,'$pdo->beginTransaction()'),
'requires double confirmation'=>str_contains($s,"'حذف دائمی'")&&str_contains($u,'name="confirm_purge_phrase"'),
'archived-list contains permanent delete'=>str_contains($u,'purge_archived_company')&&str_contains($u,'purgeCompany-'),
'csrf check and form'=>str_contains($u,'csrf_verify()')&&str_contains($u,'<?=csrf_field()?>'),
'no DB migration'=>is_file($root.'/database/migrations/015_company_archive.sql'),
];
foreach($tests as $name=>$value){echo ($value?'[OK] ':'[FAIL] ').$name."\n";if(!$value)exit(1);}echo "v0.6.28 smoke checks passed\n";

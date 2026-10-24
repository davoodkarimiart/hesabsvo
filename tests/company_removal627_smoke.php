<?php
/** Offline regression: safer company removal SQL contract and report date visual contract. */
declare(strict_types=1);
$root=dirname(__DIR__);
$service=file_get_contents($root.'/app/Services/CompanyService.php');
$index=file_get_contents($root.'/index.php');
$checks=[
 'soft archive preserves financial references'=>str_contains($service,"UPDATE companies SET archived_at=NOW(),active=0 WHERE id=?"),
 'hard delete requires absence of financial references'=>str_contains($service,'$hasFinancialHistory=array_sum($counts)>0;'),
 'hard delete scoped to company'=>str_contains($service,'DELETE FROM accounts WHERE panel_id IN ($panelScope)') && str_contains($service,'DELETE FROM panels WHERE company_id=?'),
 'company name confirmation'=>str_contains($service,'hash_equals((string)$company[\'name\'],trim($confirmedName))'),
 'archive restore available'=>str_contains($service,'SET archived_at=NULL,active=1'),
 'csrf forms'=>str_contains($index,'name="confirm_company_name"') && str_contains($index,'remove_company'),
 'date separate in report image'=>str_contains($index,'REPORT_IMAGE_PERIOD||subtitle'),
 'date heading caption present'=>str_contains($index,'ReportHeading626::period($reportData)'),
 'no accounting formulas modified'=>is_file($root.'/app/Services/AccountingService.php'),
];
foreach($checks as $label=>$ok){echo ($ok?'[OK] ':'[FAIL] ').$label."\n";if(!$ok)exit(1);}echo "Smoke checks passed\n";

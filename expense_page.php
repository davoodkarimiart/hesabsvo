<?php
/** Included by authenticated transfer_page619.php. */
if(!Auth::check())exit;
$tab=$transferTab;
$editing=null;$list=['rows'=>[],'total'=>'0','count'=>0];$expenseError='';
$q=trim((string)($_GET['q']??''));$company=(int)($_GET['company']??0);
$from=(string)($_GET['from']??'');$to=(string)($_GET['to']??'');$status=(string)($_GET['status']??'final');
$editId=(int)($_GET['edit']??0);
try{
 if($tab==='expense_new'&&$editId>0){$editing=ExpenseService::get($editId);if(!$editing||$editing['status']!=='final')throw new RuntimeException('هزینه قابل ویرایش نیست.');}
 elseif($tab==='expense_list')$list=ExpenseService::listing($company,$from,$to,$status,$q);
 if(!ExpenseService::ready())throw new RuntimeException('ابتدا Migration هزینه‌ها را از مرکز بروزرسانی اجرا کن.');
}catch(Throwable $ex){$expenseError=$ex->getMessage();}
$draft=($_SERVER['REQUEST_METHOD']??'')==='POST'&&($_POST['action']??'')==='expense_save'?$_POST:[];
$formCompany=(int)($editing['company_id']??$draft['company_id']??0);
$formDate=(string)($draft['expense_date']??$editing['expense_date']??date('Y-m-d'));
$formAmount=(string)($draft['amount_toman']??$editing['amount_toman']??'');
$formSubject=(string)($draft['subject']??$editing['subject']??'');
$rows=array_reverse($list['rows']);$pages=max(1,(int)ceil(count($rows)/25));$p=min($pages,max(1,(int)($_GET['p']??1)));$rows=array_slice($rows,($p-1)*25,25);
$base=['page'=>'transfers','tab'=>'expense_list','company'=>$company,'from'=>$from,'to'=>$to,'status'=>$status,'q'=>$q];
?>
<section class="section-title transfer-top-title"><div><h1>ترانسفر و هزینه‌ها</h1><p>تسویه با سایت مادر و مدیریت هزینه‌های شرکت</p></div></section>
<div class="transfer-tabs expense-tabs" role="navigation" aria-label="ترانسفر و هزینه">
 <a href="index.php?page=transfers&tab=new">＋ ثبت ترانسفر</a>
 <a href="index.php?page=transfers&tab=list">سوابق و مدیریت ترانسفرها</a>
 <a class="<?=$tab==='expense_new'?'active':''?>" href="index.php?page=transfers&tab=expense_new">＋ ثبت هزینه</a>
 <a class="<?=$tab==='expense_list'?'active':''?>" href="index.php?page=transfers&tab=expense_list">سوابق و مدیریت هزینه‌ها</a>
</div>
<?php if($expenseError!==''):?><div class="notice bad"><?=e($expenseError)?></div>
<?php elseif($tab==='expense_new'):?>
<section class="card transfer-wizard expense-card">
 <div class="transfer-step-head"><div><h2><?=$editing?'ویرایش هزینه #'.e($editing['id']):'ثبت هزینه جدید'?></h2><p>مبلغ به تومان کامل؛ هزینه فقط از نتیجه خالص شرکت کسر می‌شود.</p></div></div>
 <form class="expense-form expense-entry-form" method="post" action="index.php?page=transfers&tab=expense_new<?=$editing?'&edit='.(int)$editing['id']:''?>" autocomplete="off">
 <?=csrf_field()?><input type="hidden" name="action" value="expense_save"><input type="hidden" name="expense_id" value="<?=e($editing['id']??0)?>"><input type="hidden" name="revision_no" value="<?=e($editing['revision_no']??0)?>">
 <div class="expense-form-grid">
 <label>شرکت<?php if($editing):?><input value="<?=e($editing['company_name_snapshot'])?>" readonly><input type="hidden" name="company_id" value="<?=$formCompany?>"><?php else:?><select name="company_id" required><option value="">انتخاب شرکت</option><?php foreach($companies as $c):?><option value="<?=e($c['id'])?>" <?=$formCompany===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select><?php endif;?></label>
 <label>تاریخ هزینه<input id="expenseDate" name="expense_date" data-report-calendar="1" value="<?=e($formDate)?>" required></label>
 <label class="expense-amount-field">مبلغ هزینه (تومان)<input id="expenseAmount" name="amount_toman" dir="ltr" inputmode="numeric" value="<?=e($formAmount)?>" placeholder="1,500,000" maxlength="19" aria-describedby="expenseAmountHint" required></label>
 <label class="expense-subject-field">موضوع هزینه<input name="subject" value="<?=e($formSubject)?>" maxlength="250" placeholder="مثلاً هزینه سرور" required></label>
 </div><p class="expense-field-note" id="expenseAmountHint">مبلغ را به تومان، بدون اعشار وارد کن.</p>
 <?php if($editing):?><p class="transfer-hint">ویرایش با نگهداری نسخه قبلی در سابقه حسابرسی انجام می‌شود.</p><?php endif;?>
 <div class="expense-actions"><button class="primary" type="submit"><?=$editing?'ذخیره ویرایش':'ثبت نهایی هزینه'?></button><a class="btn ghost" href="index.php?page=transfers&tab=expense_list">انصراف</a></div>
 </form>
</section>
<?php else:?>
<section class="card transfer-history expense-card"><div class="transfer-step-head"><div><h2>سوابق و مدیریت هزینه‌ها</h2><p>جستجو، ویرایش و ابطال اسناد هزینه</p></div><a class="btn primary" href="index.php?page=transfers&tab=expense_new">＋ هزینه</a></div>
<form method="get" action="index.php" class="expense-form expense-filter-form">
 <input type="hidden" name="page" value="transfers"><input type="hidden" name="tab" value="expense_list">
 <div class="expense-form-grid">
 <label>شرکت<select name="company"><option value="0">همه شرکت‌ها</option><?php foreach($companies as $c):?><option value="<?=e($c['id'])?>" <?=$company===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></label>
 <label>موضوع هزینه<input type="search" name="q" value="<?=e($q)?>" placeholder="جست‌وجو در موضوع"></label>
 <label>از تاریخ<input name="from" data-report-calendar="1" value="<?=e($from)?>"></label>
 <label>تا تاریخ<input name="to" data-report-calendar="1" value="<?=e($to)?>"></label>
 <label class="expense-status-field">وضعیت<select name="status"><?php foreach(['final'=>'ثبت‌شده','void'=>'ابطال‌شده',''=>'همه'] as $key=>$label):?><option value="<?=e($key)?>" <?=$key===$status?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
 <div class="expense-actions expense-filter-actions"><button class="primary" type="submit">جست‌وجو</button><a class="btn ghost" href="index.php?page=transfers&tab=expense_list">پاک‌کردن</a></div>
 </div>
</form>
<div class="expense-stats"><div><small>جمع هزینه‌ها (تومان)</small><b dir="ltr"><?=e(TransferService::formatToman($list['total'],$uiSettings))?></b></div><div><small>تعداد هزینه‌ها</small><b><?=e($list['count'])?></b></div></div>
<div class="expense-records">
<?php foreach($rows as $row):?>
<article class="expense-record <?=$row['status']==='void'?'expense-voided':''?>">
 <div class="expense-record-main"><strong><?=e($row['subject'])?></strong><b dir="ltr"><?=e(TransferService::formatToman($row['amount_toman'],$uiSettings))?> <small>تومان</small></b></div>
 <div class="expense-record-meta"><span><?=e($row['company_name_snapshot'])?></span><span dir="ltr"><?=e(AccountingService::displayBusinessDate($row['expense_date']))?></span><span><?=$row['status']==='final'?'ثبت‌شده':'ابطال‌شده'?> · #<?=e($row['id'])?></span></div>
 <?php if($row['status']==='final'):?><div class="expense-actions"><a class="btn ghost" href="index.php?page=transfers&tab=expense_new&edit=<?=(int)$row['id']?>">ویرایش</a><form method="post" action="index.php?page=transfers&tab=expense_list" onsubmit="return confirm('هزینه ابطال شود؟ سابقه آن باقی می‌ماند.')"><?=csrf_field()?><input type="hidden" name="action" value="expense_void"><input type="hidden" name="expense_id" value="<?=(int)$row['id']?>"><input type="hidden" name="revision_no" value="<?=(int)$row['revision_no']?>"><button class="ghost expense-danger" type="submit">ابطال</button></form></div><?php endif;?>
</article>
<?php endforeach;?>
<?php if(!$rows):?><p class="transfer-hint">هزینه‌ای با این فیلتر پیدا نشد.</p><?php endif;?>
</div>
<?php if($pages>1):?><div class="expense-actions"><?php if($p>1):?><a class="btn ghost" href="<?=e('index.php?'.http_build_query($base+['p'=>$p-1]))?>">قبلی</a><?php endif;?><span><?=e($p)?> / <?=e($pages)?></span><?php if($p<$pages):?><a class="btn ghost" href="<?=e('index.php?'.http_build_query($base+['p'=>$p+1]))?>">بعدی</a><?php endif;?></div><?php endif;?>
</section>
<?php endif;?>
<script defer src="assets/transfer-calendar620.js?v=663"></script>
<script>(()=>{const el=document.getElementById('expenseAmount');if(!el)return;const digits=s=>s.replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g,d=>'٠١٢٣٤٥٦٧٨٩'.indexOf(d));const render=()=>{const v=digits(el.value).replace(/[,٬]/g,'');const valid=/^[0-9]{1,15}$/.test(v)&&!/^0+$/.test(v);el.setCustomValidity(valid||v===''?'':'مبلغ صحیح مثبت، حداکثر ۱۵ رقم');if(valid)el.value=v.replace(/\B(?=(\d{3})+(?!\d))/g,',');};el.addEventListener('input',render);if(el.value)render();})();</script>

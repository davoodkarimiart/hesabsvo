<?php
/** Included only from authenticated index.php?page=transfers */
$transferTab=(string)($_GET['tab']??'new');
$transferTab=in_array($transferTab,['new','list'],true)?$transferTab:'new';
$transferQ=trim((string)($_GET['q']??''));
$transferCompanyFilter=(int)($_GET['company']??0);
$transferStatusFilter=(string)($_GET['status']??'');
$transferFrom=trim((string)($_GET['from']??''));
$transferTo=trim((string)($_GET['to']??''));
$transferPage=max(1,(int)($_GET['p']??1));
$transferList=TransferService::listingPaginated($transferQ,$transferCompanyFilter,$transferStatusFilter,$transferFrom,$transferTo,$transferPage,25);
$transferRows=$transferList['rows'];
$transferBaseQuery=['page'=>'transfers','tab'=>'list','q'=>$transferQ,'company'=>$transferCompanyFilter,'status'=>$transferStatusFilter,'from'=>$transferFrom,'to'=>$transferTo];
$transferView=(int)($_GET['view']??0);
$transferEdit=(int)($_GET['edit']??0);
$viewTransfer=$transferView>0?TransferService::get($transferView):null;
$editTransfer=$transferEdit>0?TransferService::get($transferEdit):null;
if($editTransfer&&$editTransfer['status']!=='final')$editTransfer=null;
if($editTransfer)$transferTab='new';
$today=new DateTimeImmutable('yesterday');
$defaultEnd=$today->format('Y-m-d');
$defaultStart=$today->modify('-6 days')->format('Y-m-d');
$transferFmtUsd=fn($v)=>number_format((float)$v,2,'.',',');
$transferFmtToman=fn($v)=>number_format(abs((float)$v),0,'.',',');
$transferFmtSigned=fn($v)=>(float)$v>0?'+'.number_format((float)$v,2,'.',','):number_format((float)$v,2,'.',',');
$transferDayTotals=[];
if($viewTransfer){
 foreach($viewTransfer['reports'] as $rr){$d=(string)$rr['report_date'];$transferDayTotals[$d]=($transferDayTotals[$d]??0.0)+(float)$rr['member_win_snapshot'];}
}
$transferDirectionLabel=['to_master'=>'پرداخت به طرف تسویه','from_master'=>'دریافت از طرف تسویه','none'=>'تسویه صفر'];
$companiesForJs=array_map(fn($c)=>['id'=>(int)$c['id'],'name'=>(string)$c['name'],'role'=>(string)($c['company_role']??''),'percent'=>(string)($c['transfer_percent']??'')],$companies);
?>
<section class="section-title transfer-top-title"><div><h1>ترانسفر</h1><p>تسویه دلاری با سایت مادر، مستقل از نرخ حساب مشتری‌ها</p></div></section>
<div class="transfer-tabs" role="navigation" aria-label="بخش‌های ترانسفر">
 <a class="<?=($transferTab==='new'?'active':'')?>" href="index.php?page=transfers&tab=new">＋ ثبت ترانسفر</a>
 <a class="<?=($transferTab==='list'?'active':'')?>" href="index.php?page=transfers&tab=list">سوابق و مدیریت ترانسفرها</a>
</div>
<?php if($transferTab==='new'): ?>
<section class="card transfer-wizard" id="transferWizard">
 <div class="transfer-step-head"><div><h2><?= $editTransfer?'ویرایش ترانسفر '.e($editTransfer['transfer_code']):'ثبت ترانسفر جدید' ?></h2><p>ثبت مرحله‌ای، با کنترل تکراری نبودن گزارش‌های تسویه‌شده</p></div><span id="transferStepCount">مرحله ۱ از ۵</span></div>
 <?php if($editTransfer):?><div class="notice warn">ویرایش این ترانسفر با نگهداری نسخه قبلی در سابقه حسابرسی انجام می‌شود. شرکت آن قابل تغییر نیست.</div><?php endif;?>
 <div class="transfer-progress" aria-hidden="true"><span class="active"></span><span></span><span></span><span></span><span></span></div>
 <form id="transferWizardForm" method="post" action="index.php?page=transfers&tab=new" class="transfer-form" autocomplete="off">
  <?=csrf_field()?><input type="hidden" name="action" value="transfer_save"><input type="hidden" name="transfer_id" value="<?=e((string)($editTransfer['id']??''))?>">
  <fieldset data-step="1" class="transfer-step"><legend>۱. شرکت و دوره تسویه</legend>
   <label>شرکت
     <select name="company_id" id="transferCompany" required <?=$editTransfer?'disabled':''?>><option value="">انتخاب شرکت…</option><?php foreach($companies as $c):?><option value="<?=e($c['id'])?>" <?=($editTransfer&&(int)$editTransfer['company_id']===(int)$c['id'])?'selected':''?>><?=e($c['name'])?> • <?=e($c['company_role']?:'نوع نامشخص')?></option><?php endforeach;?></select>
   </label>
   <?php if($editTransfer):?><input type="hidden" name="company_id" value="<?=e($editTransfer['company_id'])?>"><?php endif;?>
   <label>بازه زمانی<select id="transferPeriodType" name="period_type"><option value="weekly">۷ روز اخیر (شناور)</option><option value="monthly">یک ماه اخیر (شناور)</option><option value="custom">از تاریخ تا تاریخ (دلخواه)</option></select></label>
   <div class="transfer-dates">
    <div class="tc-date-field"><span>از تاریخ</span><input id="transferStart" type="hidden" data-transfer-iso name="period_start" value="<?=e($defaultStart)?>"><button type="button" class="tc-date-open" data-transfer-date="transferStart" data-mode="wizard" data-company-input="transferCompany" data-edit="<?=e((string)($editTransfer['id']??''))?>"><strong class="tc-date-main">انتخاب تاریخ</strong><small class="tc-date-secondary">شمسی / میلادی</small><span class="tc-date-icon">▦</span></button></div>
    <div class="tc-date-field"><span>تا تاریخ</span><input id="transferEnd" type="hidden" data-transfer-iso name="period_end" value="<?=e($defaultEnd)?>"><button type="button" class="tc-date-open" data-transfer-date="transferEnd" data-mode="wizard" data-company-input="transferCompany" data-edit="<?=e((string)($editTransfer['id']??''))?>"><strong class="tc-date-main">انتخاب تاریخ</strong><small class="tc-date-secondary">شمسی / میلادی</small><span class="tc-date-icon">▦</span></button></div>
   </div>
   <div class="transfer-period-help" id="transferPeriodHelp" aria-live="polite"></div>
   <div class="transfer-period-overlap" id="transferPeriodOverlap" role="status" hidden></div>
   <p class="transfer-hint">هر دو تاریخ در بازه لحاظ می‌شوند. ۷ روزه و ماهانه نسبت به تاریخ پایان انتخابی محاسبه می‌شوند، نه روز خاصی از هفته. روز قرمز در تقویم، تسویه‌شده است.</p>
  </fieldset>
  <fieldset data-step="2" class="transfer-step" hidden><legend>۲. کنترل مبلغ دلاری گزارش‌ها</legend>
   <div class="transfer-stat-grid"><div><span>Member Win دقیق بازه</span><b dir="ltr" id="transferMemberWin">—</b></div><div><span>برد / باخت دلاری سایت</span><b dir="ltr" id="transferSiteUsd">—</b></div><div><span>گزارش‌های مبنا</span><b id="transferReportCount">—</b></div></div>
   <div id="transferReportList" class="transfer-source-list"></div><div id="transferUsdAuditWarnings" class="notice warn" hidden></div>
   <label class="transfer-toggle"><input type="checkbox" id="transferChangeAmount"> اصلاح دستی مبلغ دلاری (در صورت مغایرت با تسویه مادر)</label>
   <div id="transferAmountEdit" hidden><label>مبلغ دلاری جایگزین (علامت از دید سایت، با دقت تا ۶ رقم)<input id="transferUsdOverride" name="site_usd_override" inputmode="decimal" placeholder="مثلاً 4560.113671"></label><label>دلیل اصلاح<input id="transferOverrideReason" name="override_reason" placeholder="علت اختلاف با جمع گزارش‌های مادر"></label></div>
   <label class="transfer-confirm"><input type="checkbox" id="transferConfirmAmount"> مبلغ دلاری دوره و گزارش‌های منبع را بررسی و تأیید کردم.</label>
  </fieldset>
  <fieldset data-step="3" class="transfer-step" hidden><legend>۳. درصد سهم ترانسفر</legend>
   <div class="transfer-info" id="transferCompanyInfo">درصد پیش‌فرض از تنظیمات شرکت خوانده می‌شود.</div>
   <label>درصد ترانسفر<input id="transferPercent" name="transfer_percent" type="text" inputmode="decimal" required placeholder="مثلاً 30"></label>
   <p class="transfer-hint">درصد این ترانسفر ذخیره می‌شود؛ تغییر درصد شرکت در آینده، رسیدهای قدیمی را تغییر نمی‌دهد.</p>
  </fieldset>
  <fieldset data-step="4" class="transfer-step" hidden><legend>۴. نرخ روز دلار و تسویه</legend>
   <label>نرخ روز دلار به تومان کامل<input id="transferRate" name="usd_rate_toman" type="text" inputmode="numeric" dir="ltr" placeholder="مثلاً 105,000" required><small id="transferRateWords" class="transfer-rate-words" aria-live="polite">مبلغ نرخ به حروف اینجا نمایش داده می‌شود.</small></label>
   <label>توضیحات اختیاری<textarea id="transferNotes" name="notes" rows="2" placeholder="توضیح روش تسویه یا یادداشت مالی"></textarea></label>
   <?php if($editTransfer):?><label>دلیل ویرایش ترانسفر نهایی<input id="transferEditReason" name="edit_reason" placeholder="علت تغییر ترانسفر قبلی" required></label><?php endif;?>
   <div class="transfer-hint">محاسبات اینجا از نرخ دلار روز واردشده استفاده می‌کنند، نه نرخ عمومی/ویژه حساب مشتریان.</div>
  </fieldset>
  <fieldset data-step="5" class="transfer-step" hidden><legend>۵. رسید و تأیید نهایی</legend>
   <div id="transferDraftReceipt" class="transfer-receipt"></div>
   <label class="transfer-confirm"><input id="transferConfirmFinal" type="checkbox"> مبلغ، علامت برد/باخت، درصد، نرخ و تاریخ‌ها را تأیید می‌کنم.</label>
   <p class="transfer-hint">این عمل یک سند نهایی ثبت می‌کند. ویرایش بعدی فقط همراه با ثبت سابقه انجام می‌شود.</p>
  </fieldset>
  <div id="transferWizardError" class="notice bad" hidden role="alert"></div>
  <div class="transfer-wizard-actions"><button type="button" id="transferPrev" class="ghost" hidden>مرحله قبل</button><button type="button" id="transferNext" class="primary">ادامه</button><button id="transferSave" type="submit" class="primary" hidden><?= $editTransfer?'ثبت نهایی ویرایش':'ثبت نهایی ترانسفر' ?></button><a id="transferCancel" class="transfer-cancel btn ghost" href="index.php?page=transfers&tab=list" data-editing="<?= $editTransfer?'1':'0' ?>"><?= $editTransfer?'انصراف از ویرایش':'انصراف' ?></a></div>
 </form>
</section>
<script>
window.TRANSFER_SETUP619=<?=json_encode(['companies'=>$companiesForJs,'defaultStart'=>$defaultStart,'defaultEnd'=>$defaultEnd,'edit'=>$editTransfer?[
'id'=>(int)$editTransfer['id'],'company_id'=>(int)$editTransfer['company_id'],'period_type'=>$editTransfer['period_type'],'period_start'=>$editTransfer['period_start'],'period_end'=>$editTransfer['period_end'],'site_usd_applied'=>$editTransfer['site_usd_applied'],'site_usd_calculated'=>$editTransfer['site_usd_calculated'],'override_reason'=>$editTransfer['override_reason'],'transfer_percent'=>$editTransfer['transfer_percent'],'usd_rate_toman'=>$editTransfer['usd_rate_toman'],'notes'=>$editTransfer['notes']
]:null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG)?>;
</script><script defer src="assets/transfer-calendar620.js?v=620"></script><script defer src="assets/transfer619.js?v=623"></script>
<?php else: ?>
<section class="card transfer-history">
 <h2>سوابق و مدیریت ترانسفرها</h2>
 <form method="get" class="transfer-advanced-form" action="index.php">
  <input type="hidden" name="page" value="transfers"><input type="hidden" name="tab" value="list">
  <div class="transfer-quick-search"><input type="search" name="q" value="<?=e($transferQ)?>" placeholder="جست‌وجوی شماره رسید، شرکت، توضیحات…" aria-label="جست‌وجو"><button class="primary" type="submit">جست‌وجو</button><button class="ghost" id="transferAdvancedToggle" type="button" aria-expanded="<?=($transferCompanyFilter||$transferStatusFilter||$transferFrom||$transferTo)?'true':'false'?>" aria-controls="transferAdvancedPanel">⚙ فیلتر پیشرفته</button></div>
  <div class="transfer-advanced-panel" id="transferAdvancedPanel" <?=($transferCompanyFilter||$transferStatusFilter||$transferFrom||$transferTo)?'':'hidden'?>>
   <div class="transfer-filter-grid">
    <label>شرکت<select name="company" id="historyCompany"><option value="0">تمام شرکت‌ها</option><?php foreach($companies as $c):?><option value="<?=e($c['id'])?>" <?=$transferCompanyFilter===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></label>
    <label>وضعیت<select name="status"><option value="">همه وضعیت‌ها</option><option value="final" <?=$transferStatusFilter==='final'?'selected':''?>>نهایی</option><option value="void" <?=$transferStatusFilter==='void'?'selected':''?>>باطل‌شده</option></select></label>
    <div class="tc-date-field"><span>از تاریخ پوشش</span><input type="hidden" data-transfer-iso id="historyFrom" name="from" value="<?=e($transferFrom)?>"><button type="button" class="tc-date-open" data-transfer-date="historyFrom" data-mode="history" data-company-input="historyCompany"><strong class="tc-date-main">انتخاب تاریخ</strong><small class="tc-date-secondary">شمسی / میلادی</small><span class="tc-date-icon">▦</span></button></div>
    <div class="tc-date-field"><span>تا تاریخ پوشش</span><input type="hidden" data-transfer-iso id="historyTo" name="to" value="<?=e($transferTo)?>"><button type="button" class="tc-date-open" data-transfer-date="historyTo" data-mode="history" data-company-input="historyCompany"><strong class="tc-date-main">انتخاب تاریخ</strong><small class="tc-date-secondary">شمسی / میلادی</small><span class="tc-date-icon">▦</span></button></div>
   </div>
   <div class="transfer-filter-actions"><button class="primary" type="submit">اعمال فیلترها</button><a class="btn ghost" href="index.php?page=transfers&tab=list">پاک‌کردن فیلترها</a><button class="ghost" type="button" id="transferClearDates">پاک‌کردن تاریخ‌ها</button></div>
   <p class="transfer-hint">فیلتر تاریخ براساس هم‌پوشانی بازه ترانسفر با تاریخ‌های انتخاب‌شده است. روزهای تسویه‌شده در تقویم قابل مشاهده‌اند.</p>
  </div>
 </form>
 <div class="transfer-list-meta"><?=e((string)$transferList['total'])?> ترانسفر پیدا شد • صفحه <?=e((string)$transferList['page'])?> از <?=e((string)$transferList['pages'])?></div>
 <?php if(!$transferRows):?><div class="notice warn">ترانسفری برای این جست‌وجو پیدا نشد.</div><?php endif;?>
 <div class="transfer-table-scroll"><table class="transfer-manage-table"><thead><tr><th>شرکت / دوره</th><th>مبلغ نهایی (تومان)</th><th>عملیات</th></tr></thead><tbody>
 <?php foreach($transferRows as $r):?>
 <tr>
 <td class="transfer-history-ident"><b><?=e($r['company_name_snapshot'])?></b><span><?=e(AccountingService::displayBusinessDate($r['period_start']).' تا '.AccountingService::displayBusinessDate($r['period_end']))?></span><small dir="ltr"><?=e($r['period_start'].' → '.$r['period_end'])?></small><small dir="ltr"><?=e($r['transfer_code'])?></small><span class="transfer-status <?=$r['status']==='final'?'ok':'void'?>"><?=$r['status']==='final'?'نهایی':'ابطال‌شده'?></span></td>
 <td class="transfer-history-money" dir="ltr"><b><?=e($transferFmtToman($r['total_toman']))?></b><small>تومان</small></td>
 <td class="transfer-history-actions"><button type="button" class="transfer-action-trigger ghost" data-id="<?=e($r['id'])?>" data-code="<?=e($r['transfer_code'])?>" data-company="<?=e($r['company_name_snapshot'])?>" data-status="<?=e($r['status'])?>" aria-label="عملیات <?=e($r['transfer_code'])?>">عملیات <span aria-hidden="true">⋯</span></button></td>
 </tr>
 <?php endforeach;?>
 </tbody></table></div>
 <dialog id="transferOperationsDialog" class="app-dialog transfer-actions-modal" aria-label="عملیات ترانسفر"><div class="dialog-card transfer-actions-sheet"><div class="dialog-head"><div><b id="transferActionHeading">عملیات ترانسفر</b><small id="transferActionCompany">رسید و مدیریت سند</small></div><button class="ghost icon-btn" type="button" id="transferActionsClose">×</button></div><div class="transfer-actions-grid"><a id="transferActionView" class="btn primary" href="#">مشاهده رسید و جزئیات</a><a id="transferActionEdit" class="btn ghost" href="#">ویرایش ترانسفر</a></div><section id="transferActionVoidSection" class="transfer-action-void"><details><summary>ابطال سند با حفظ سابقه</summary><form method="post" action="index.php?page=transfers&tab=list" data-confirm="ترانسفر نهایی با ذکر دلیل باطل شود؟ سابقه حذف نمی‌شود."><?=csrf_field()?><input type="hidden" name="action" value="transfer_void"><input type="hidden" name="transfer_id" id="transferActionVoidId"><label>دلیل ابطال<input name="void_reason" minlength="3" required placeholder="دلیل ابطال"></label><button type="submit" class="danger">تأیید ابطال</button></form></details></section></div></dialog>
 <nav class="transfer-pagination" aria-label="صفحه‌بندی سوابق"><?php if($transferList['page']>1):?><a href="index.php?<?=e(http_build_query($transferBaseQuery+['p'=>$transferList['page']-1]))?>">صفحه قبل</a><?php endif;?><span>صفحه <?=e((string)$transferList['page'])?> از <?=e((string)$transferList['pages'])?></span><?php if($transferList['page']<$transferList['pages']):?><a href="index.php?<?=e(http_build_query($transferBaseQuery+['p'=>$transferList['page']+1]))?>">صفحه بعد</a><?php endif;?></nav>
 <script defer src="assets/transfer-calendar620.js?v=620"></script>
 <script defer src="assets/transfer-history620.js?v=621"></script>
</section>
<?php if($viewTransfer): ?>
<dialog id="transferReceiptDialog" class="app-dialog transfer-saved-dialog" aria-label="رسید تسویه">
<div class="dialog-card transfer-receipt-dialog"><div class="dialog-head"><div><b>رسید ترانسفر</b><small>ثبت نهایی و سابقه قابل پیگیری</small></div><button type="button" class="ghost icon-btn" onclick="document.getElementById('transferReceiptDialog').close()">×</button></div>
<div id="savedTransferReceipt" class="transfer-receipt transfer-receipt-final">
 <div class="transfer-receipt-title">رسید تسویه ترانسفر</div><div class="transfer-receipt-subtitle">سند رسمی تسویه دوره</div>
 <div class="transfer-receipt-code" dir="ltr"><?=e($viewTransfer['transfer_code'])?></div>
 <div class="transfer-receipt-line transfer-company-line"><span>طرف تسویه</span><b><?=e($viewTransfer['company_name_snapshot'].' • '.($viewTransfer['company_role_snapshot']==='master'?'مستر':($viewTransfer['company_role_snapshot']==='agent'?'ایجنت':'تعیین‌نشده')))?></b></div>
 <div class="transfer-receipt-line"><span>بازه میلادی</span><b dir="ltr"><?=e($viewTransfer['period_start'].' تا '.$viewTransfer['period_end'])?></b></div>
 <div class="transfer-receipt-line"><span>بازه شمسی</span><b><?=e(AccountingService::displayBusinessDate($viewTransfer['period_start']).' تا '.AccountingService::displayBusinessDate($viewTransfer['period_end']))?></b></div>
 <div class="transfer-receipt-line transfer-member-win"><span>Member Win</span><b class="<?=((float)$viewTransfer['member_win_total']<0)?'negative':(((float)$viewTransfer['member_win_total']>0)?'positive':'')?>" dir="ltr"><?=e($transferFmtSigned($viewTransfer['member_win_total']))?> $</b></div>
 <div class="transfer-receipt-line"><span>درصد سهم</span><b><?=e($viewTransfer['transfer_percent'])?>٪</b></div>
 <div class="transfer-receipt-line"><span>سهم دلاری</span><b dir="ltr"><?=e($transferFmtUsd($viewTransfer['usd_share']))?> $</b></div>
 <div class="transfer-receipt-line"><span>نرخ تسویه (تومان)</span><b dir="ltr"><?=e($transferFmtToman($viewTransfer['usd_rate_toman']))?></b></div>
 <div class="transfer-receipt-line transfer-receipt-total"><span>مبلغ نهایی (تومان)</span><b dir="ltr"><?=e($transferFmtToman($viewTransfer['total_toman']))?></b></div>
 <div class="transfer-receipt-line transfer-settlement-direction"><span>نوع تسویه</span><b><?=e(abs((float)$viewTransfer['total_toman'])<0.000001?'تسویه صفر':((float)$viewTransfer['member_win_total']>0?'پرداخت به طرف تسویه':((float)$viewTransfer['member_win_total']<0?'دریافت از طرف تسویه':($transferDirectionLabel[$viewTransfer['direction']]??'نامشخص'))))?></b></div>
 <div class="transfer-receipt-line"><span>شماره گزارش‌های منبع</span><b><?=e(implode('، ',array_column($viewTransfer['reports'],'daily_report_id')))?></b></div>
 <div class="transfer-daily-breakdown"><h3>ریز روزانه Member Win</h3><table><thead><tr><th>#</th><th>شمسی / میلادی</th><th>Member Win ($)</th></tr></thead><tbody>
 <?php $dCounter=0;for($dt=new DateTimeImmutable($viewTransfer['period_start']);$dt<=new DateTimeImmutable($viewTransfer['period_end']);$dt=$dt->modify('+1 day')):$iso=$dt->format('Y-m-d');$daily=$transferDayTotals[$iso]??null;$dCounter++;?>
 <tr><td><?=e((string)$dCounter)?></td><td><span><?=e(AccountingService::displayBusinessDate($iso))?></span><small dir="ltr"><?=e($iso)?></small></td><td class="<?=($daily!==null&&$daily<0)?'negative':(($daily!==null&&$daily>0)?'positive':'')?>" dir="ltr"><?=($daily===null)?'بدون گزارش':e($transferFmtSigned($daily)).' $'?></td></tr>
 <?php endfor;?></tbody><tfoot><tr class="transfer-daily-total"><th colspan="2">جمع Member Win دوره</th><th class="<?=((float)$viewTransfer['member_win_total']<0)?'negative':(((float)$viewTransfer['member_win_total']>0)?'positive':'')?>" dir="ltr"><?=e($transferFmtSigned($viewTransfer['member_win_total']))?> $</th></tr></tfoot></table></div>
 <?php if(trim((string)$viewTransfer['override_reason'])!==''):?><div class="notice warn">مبلغ تسویه دستی اصلاح شده است: <?=e($viewTransfer['override_reason'])?></div><?php endif;?>
 <div class="transfer-receipt-line"><span>ثبت‌شده در</span><b dir="ltr"><?=e($viewTransfer['created_at'])?></b></div>
 <?php if($viewTransfer['status']==='void'):?><div class="notice bad">باطل‌شده: <?=e($viewTransfer['void_reason'])?></div><?php endif;?>
</div>
<div class="transfer-receipt-actions"><button type="button" class="primary" id="transferReceiptImage">ذخیره عکس PNG</button><button type="button" class="primary" id="transferReceiptShare">ارسال / اشتراک رسید</button><button type="button" class="ghost" id="transferReceiptCopy">کپی عکس</button><button type="button" class="ghost" id="transferReceiptPrint">چاپ / PDF</button></div>
</div>
</dialog><script defer src="assets/transfer-receipt619.js?v=625"></script>
<?php endif; ?>
<?php endif; ?>

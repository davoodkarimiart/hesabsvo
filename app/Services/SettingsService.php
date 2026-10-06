<?php

declare(strict_types=1);

final class SettingsService {
    public static function defaults(): array {
        return [
            'default_rate'=>'100','special_rate'=>'200','default_loss_percent'=>'10',
            'money_scale'=>'trim3','digit_mode'=>'fa',
            'font_global'=>'vazirmatn','font_heading'=>'vazirmatn','font_form'=>'vazirmatn','font_table'=>'vazirmatn','font_print'=>'vazirmatn',
            'theme_bg'=>'#f4f7fb','theme_card'=>'#ffffff','theme_text'=>'#172033','theme_primary'=>'#3559e0',
            'theme_recv'=>'#ff5d60','theme_pay'=>'#92cdd9','theme_comm'=>'#78fa70','theme_site_win'=>'#92cdd9','theme_site_loss'=>'#ff5d60','theme_opacity'=>'100',
        ];
    }

    public static function all(): array {
        $out=self::defaults();
        try {
            foreach(Database::connection()->query('SELECT setting_key,setting_value FROM settings')->fetchAll() as $r){$out[(string)$r['setting_key']]=(string)$r['setting_value'];}
        } catch(Throwable) {}
        return $out;
    }

    public static function save(array $input,int $userId): void {
        $allowed=array_keys(self::defaults());$pdo=Database::connection();$pdo->beginTransaction();
        try{
            $st=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            foreach($allowed as $k){if(!array_key_exists($k,$input))continue;$v=trim((string)$input[$k]);self::validate($k,$v);$st->execute([$k,$v]);}
            $pdo->commit();Logger::audit('settings.updated',['user_id'=>$userId,'keys'=>array_values(array_intersect(array_keys($input),$allowed))]);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function validate(string $k,string $v): void {
        if(str_starts_with($k,'theme_') && $k!=='theme_opacity' && !preg_match('/^#[0-9a-fA-F]{6}$/',$v))throw new RuntimeException('رنگ انتخاب‌شده معتبر نیست.');
        if($k==='theme_opacity' && (!is_numeric($v)||(float)$v<20||(float)$v>100))throw new RuntimeException('شفافیت تم باید بین ۲۰ تا ۱۰۰ باشد.');
        if($k==='money_scale' && !in_array($v,['full','trim3','trim4'],true))throw new RuntimeException('حالت نمایش مبلغ معتبر نیست.');
        if($k==='digit_mode' && !in_array($v,['fa','en'],true))throw new RuntimeException('نوع رقم معتبر نیست.');
        if(str_starts_with($k,'font_') && !in_array($v,['vazirmatn','iransans','system'],true))throw new RuntimeException('فونت انتخاب‌شده معتبر نیست.');
        if(in_array($k,['default_rate','special_rate','default_loss_percent'],true) && (!is_numeric($v)||(float)$v<0))throw new RuntimeException('مقدار عددی تنظیمات معتبر نیست.');
    }

    public static function cssVars(array $s): string {
        $font=function(string $v):string{return match($v){'iransans'=>'"IRANSansLocal","Vazirmatn",Tahoma,Arial,sans-serif','system'=>'Tahoma,Arial,sans-serif',default=>'"Vazirmatn",Tahoma,Arial,sans-serif'};};
        return ':root{--bg:'.self::color($s['theme_bg']??'#f4f7fb').';--card:'.self::color($s['theme_card']??'#fff').';--ink:'.self::color($s['theme_text']??'#172033').';--p:'.self::color($s['theme_primary']??'#3559e0').';--recv:'.self::color($s['theme_recv']??'#ff5d60').';--pay:'.self::color($s['theme_pay']??'#92cdd9').';--comm:'.self::color($s['theme_comm']??'#78fa70').';--site-win:'.self::color($s['theme_site_win']??($s['theme_pay']??'#92cdd9')).';--site-loss:'.self::color($s['theme_site_loss']??($s['theme_recv']??'#ff5d60')).';--theme-opacity:'.((int)($s['theme_opacity']??100)/100).';--font-global:'.$font($s['font_global']??'vazirmatn').';--font-heading:'.$font($s['font_heading']??'vazirmatn').';--font-form:'.$font($s['font_form']??'vazirmatn').';--font-table:'.$font($s['font_table']??'vazirmatn').';--font-print:'.$font($s['font_print']??'vazirmatn').';}';
    }

    private static function color(string $v): string {return preg_match('/^#[0-9a-fA-F]{6}$/',$v)?$v:'#3559e0';}

    public static function themeFill(string $color, array $settings, ?float $opacity=null): string {
        $hex=self::color($color);
        $alpha=$opacity ?? max(0.2,min(1.0,((float)($settings['theme_opacity']??100))/100));
        $r=hexdec(substr($hex,1,2));$g=hexdec(substr($hex,3,2));$b=hexdec(substr($hex,5,2));
        // Outputs have a white paper/canvas background; preblend keeps PDF/image appearance stable.
        $rr=(int)round(255-(255-$r)*$alpha);$gg=(int)round(255-(255-$g)*$alpha);$bb=(int)round(255-(255-$b)*$alpha);
        return sprintf('#%02x%02x%02x',$rr,$gg,$bb);
    }

    /** Canonical accounting storage is full Toman. money_scale controls input/display convention only. */
    public static function scaleFactor(?array $settings=null): float {
        $s=$settings??self::all();
        return match($s['money_scale']??'trim3'){'trim3'=>1000.0,'trim4'=>10000.0,default=>1.0};
    }

    public static function rateToCanonical(float|int|string $value,?array $settings=null): float {
        return round(((float)$value) * self::scaleFactor($settings),6);
    }

    public static function rateFromCanonical(float|int|string $value,?array $settings=null): float {
        $factor=self::scaleFactor($settings);return $factor>0?((float)$value)/$factor:(float)$value;
    }

    public static function moneyNumber(float|int|string $value,?array $settings=null): float {
        $factor=self::scaleFactor($settings);return $factor>0?((float)$value)/$factor:(float)$value;
    }

    public static function money(float|int|string $value,?array $settings=null): string {
        $s=$settings??self::all();$n=self::moneyNumber($value,$s);
        $str=number_format(round($n,2),2,'.',',');$str=rtrim(rtrim($str,'0'),'.');
        return self::digits($str,(string)($s['digit_mode']??'fa'));
    }

    public static function decimal(float|int|string $value,?array $settings=null,int $max=6): string {
        $s=$settings??self::all();$str=number_format((float)$value,$max,'.',',');$str=rtrim(rtrim($str,'0'),'.');return self::digits($str,(string)($s['digit_mode']??'fa'));
    }

    public static function digits(string $v,string $mode): string {return $mode==='fa'?strtr($v,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']):strtr($v,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);}

    public static function moneyUnit(array $s): string {return match($s['money_scale']??'trim3'){'trim4'=>'ده‌هزار تومان','trim3'=>'هزار تومان',default=>'تومان'};}
}

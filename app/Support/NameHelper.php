<?php

declare(strict_types=1);

final class NameHelper {
    private const FIRST = [
        'SHAHIN'=>'شاهین','YASER'=>'یاسر','MEHDI'=>'مهدی','FARAMARZ'=>'فرامرز','AMIRREZA'=>'امیررضا','HADI'=>'هادی','MOHAMMAD'=>'محمد','ALI'=>'علی','YASHIN'=>'یاشین','BIJAN'=>'بیژن','POUAN'=>'پویان','FAKHRADDIN'=>'فخرالدین','SHERVIN'=>'شروین','AHMAD'=>'احمد','SASAN'=>'ساسان','MILAD'=>'میلاد','HAMID'=>'حمید','MASOUD'=>'مسعود','ALIREZA'=>'علیرضا','SINA'=>'سینا','MEHRAN'=>'مهران','AYDIN'=>'آیدین','MOHSEN'=>'محسن','MAZIYAR'=>'مازیار','SOHEIL'=>'سهیل','POUYA'=>'پویا','MOBIN'=>'مبین','REZA'=>'رضا','BABAK'=>'بابک','AMIRKIA'=>'امیرکیا','HASAN'=>'حسن','PEDRAM'=>'پدرام','RAMSIN'=>'رامسین','YOUSEF'=>'یوسف','SADEGH'=>'صادق',
    ];

    private const LAST = [
        'MEHDIPOUR'=>'مهدی پور','MASADEGH'=>'مصادق','MAJLESI'=>'مجلسی','MOHAMMADI'=>'محمدی','SHAMSINEZHAD'=>'شمسی نژاد','ALIZADEH'=>'علیزاده','DERAKHSHAN'=>'درخشان','BALALI'=>'بلالی','FEIZOLLAHIAN'=>'فیض الهیان','ALAMSA'=>'عالم سا','SHAHVAR'=>'شاهوار','PARIAN'=>'پریان','NAZARI'=>'نظری','HASHEMI'=>'هاشمی','MORADI'=>'مرادی','HASANZADEH'=>'حسن زاده','POURNIYAZ'=>'پورنیاز','HATAMLI'=>'حاتملی','ABEDINI'=>'عابدینی','SHAHABIYAN'=>'شهابیان','KAMRANPEY'=>'کامران پی','JAHEDI'=>'جاهدی','BONAKDAR'=>'بنکدار','SALMANNEZHAD'=>'سلمان نژاد','BAGHERZADEH'=>'باقرزاده','LOTFI'=>'لطفی','ROUHI'=>'روحی','KHALEDI'=>'خالدی','HEYRANIAN'=>'حیرانیان','SADEGHI'=>'صادقی','NAZARINIYA'=>'نظری نیا','HAEZ'=>'حائز','ANJOMANI'=>'انجمنی','SHAGHAGHI'=>'شقاقی','FEKRI'=>'فکری','KHANZADEH'=>'خانزاده','VASLI'=>'وصلی','ZAREIKOHAN'=>'زارعی کهن','NAJAFINIYA'=>'نجفی نیا','SHIRVAN'=>'شیروان','PARVAEIAN'=>'پروائیان','JAFARZADEH'=>'جعفرزاده',
    ];

    public static function displayName(string $first, string $last, string $original=''): string {
        $first = trim($first);
        $last = trim($last);
        $original = trim($original);
        $source = trim($first . ' ' . $last);
        if ($source === '') $source = $original;
        if ($source === '') return '';
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $source)) return self::persianDigits(self::spaces($source));

        if ($first === '' && $last === '' && $original !== '') {
            $parts = preg_split('/\s+/u', self::spaces($original), 2) ?: [];
            $first = (string)($parts[0] ?? '');
            $last = (string)($parts[1] ?? '');
        }

        [$lastBase,$suffix] = self::suffix($last);
        $fKey = self::key($first);
        $lKey = self::key($lastBase);
        $f = self::FIRST[$fKey] ?? self::transliterateToken($first);
        $l = self::LAST[$lKey] ?? self::transliterateWords($lastBase);
        $result = self::spaces(trim($f . ' ' . $l . $suffix));
        return $result !== '' ? $result : self::persianDigits($source);
    }

    private static function suffix(string $value): array {
        $v = self::spaces($value);
        if (preg_match('/^(.*?)(?:\s+([0-9۰-۹٠-٩]+))$/u', $v, $m)) {
            return [trim($m[1]), ' ' . self::persianDigits($m[2])];
        }
        return [$v, ''];
    }

    private static function transliterateWords(string $value): string {
        $parts = preg_split('/\s+/u', trim($value)) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $k = self::key($p);
            $out[] = self::LAST[$k] ?? self::FIRST[$k] ?? self::transliterateToken($p);
        }
        return implode(' ', array_filter($out, fn($x)=>$x!==''));
    }

    private static function transliterateToken(string $value): string {
        $v = strtolower(trim($value));
        if ($v === '') return '';
        $v = preg_replace('/[^a-z0-9]+/', '', $v) ?? $v;
        if ($v === '') return '';

        $pairs = [
            'kh'=>'خ','gh'=>'غ','sh'=>'ش','ch'=>'چ','zh'=>'ژ','ph'=>'ف','th'=>'ت','aa'=>'ا','ee'=>'ی','oo'=>'و','ou'=>'و','ei'=>'ی','ey'=>'ی','ai'=>'ای','ay'=>'ای',
        ];
        foreach ($pairs as $latin=>$fa) $v = str_replace($latin,$fa,$v);
        $map = [
            'a'=>'ا','b'=>'ب','c'=>'ک','d'=>'د','e'=>'','f'=>'ف','g'=>'گ','h'=>'ه','i'=>'ی','j'=>'ج','k'=>'ک','l'=>'ل','m'=>'م','n'=>'ن','o'=>'و','p'=>'پ','q'=>'ق','r'=>'ر','s'=>'س','t'=>'ت','u'=>'و','v'=>'و','w'=>'و','x'=>'کس','y'=>'ی','z'=>'ز',
            '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',
        ];
        $out='';
        $len=mb_strlen($v,'UTF-8');
        for($i=0;$i<$len;$i++){
            $ch=mb_substr($v,$i,1,'UTF-8');
            $out.=$map[$ch]??$ch;
        }
        $out=preg_replace('/ا{2,}/u','ا',$out)??$out;
        return trim($out);
    }

    private static function key(string $value): string {
        $v = strtoupper(trim($value));
        return preg_replace('/[^A-Z0-9]+/', '', $v) ?? $v;
    }

    private static function spaces(string $value): string {
        return trim(preg_replace('/\s+/u',' ',$value) ?? $value);
    }

    private static function persianDigits(string $value): string {
        return strtr($value,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹','٠'=>'۰','١'=>'۱','٢'=>'۲','٣'=>'۳','٤'=>'۴','٥'=>'۵','٦'=>'۶','٧'=>'۷','٨'=>'۸','٩'=>'۹']);
    }
}

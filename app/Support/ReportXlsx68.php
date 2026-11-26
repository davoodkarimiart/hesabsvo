<?php
declare(strict_types=1);
/** Lightweight XLSX writer for right-to-left, Unicode Persian accounting exports. */
final class ReportXlsx68 {
    /** Android and older Excel readers reliably recognize strings through the sharedStrings part. */
    private static array $sharedStrings=[];
    private static array $sharedIndex=[];
    private static int $sharedReferences=0;
    private static function xml(mixed $v): string {
        $s=(string)$v;
        // XML 1.0 forbidden codepoints must not invalidate an otherwise valid workbook.
        $s=preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',$s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    private static function cell(mixed $value,string $col,int $row,int $style):string {
        $at=' r="'.$col.$row.'" s="'.$style.'"';
        if(is_int($value)||is_float($value)) {
            if(!is_finite((float)$value))throw new RuntimeException('عدد نامعتبر در خروجی اکسل.');
            return '<c'.$at.'><v>'.self::xml((string)$value).'</v></c>';
        }
        $text=(string)$value;
        if(!array_key_exists($text,self::$sharedIndex)){
            self::$sharedIndex[$text]=count(self::$sharedStrings);
            self::$sharedStrings[]=$text;
        }
        self::$sharedReferences++;
        return '<c'.$at.' t="s"><v>'.self::$sharedIndex[$text].'</v></c>';
    }
    private static function col(int $index):string {
        $s='';do {$s=chr(65+$index%26).$s;$index=intdiv($index,26)-1;}while($index>=0);
        return $s;
    }
    /** row item: ['values'=>[...],'kind'=>'data|total']; type strings: text, integer, dollar, received, paid, commission, member */
    public static function download(string $title,string $subtitle,array $headers,array $widths,array $types,array $rows,string $filename,string $sheetName='گزارش',bool $emphasizeNumbers=false,array $options=[]):never {
        if(!class_exists('ZipArchive'))throw new RuntimeException('افزونه ZIP روی هاست فعال نیست.');
        self::$sharedStrings=[];self::$sharedIndex=[];self::$sharedReferences=0;
        $n=count($headers);
        if($n<2||$n>20||count($widths)!==$n||count($types)!==$n||count($rows)>50000)throw new RuntimeException('ساختار اکسل نامعتبر است.');
        $end=self::col($n-1);$modern=($options['settlement']??false)===true;$merges=['A1:'.$end.'1','A2:'.$end.'2'];$filterEnd=3;$filterOpen=true;
        if($modern){
            $headers=array_reverse($headers);$widths=array_reverse($widths);$types=array_reverse($types);
            foreach($rows as &$item){$original=$item['values'];$v=array_reverse($original);$styles=[];$merge=[];foreach(($item['styles']??[]) as $j=>$st)$styles[$n-1-$j]=$st;foreach(($item['merge']??[]) as $start=>$finish){$left=$n-1-$finish;$right=$n-1-$start;$merge[$left]=$right;for($j=$left;$j<=$right;$j++){$v[$j]='';unset($styles[$j]);}$v[$left]=$original[$start];if(isset($item['styles'][$start]))$styles[$left]=$item['styles'][$start];}$item['values']=$v;$item['styles']=$styles;$item['merge']=$merge;}unset($item);
        }
        $palette=['received'=>'FEDFE3','paid'=>'D6EDF6','commission'=>'D8EEE4','win'=>'DDECC8','loss'=>'FECACA'];foreach($palette as $key=>$fallback){$v=ltrim((string)($options['palette'][$key]??$fallback),'#');if(preg_match('/^[a-fA-F0-9]{6}$/D',$v))$palette[$key]=strtoupper($v);}
        $body='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0" rightToLeft="0" showGridLines="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="21"/><cols>';
        foreach($widths as $j=>$width){$w=max(8,min(38,(float)$width));$body.='<col min="'.($j+1).'" max="'.($j+1).'" width="'.$w.'" customWidth="1"/>';}
        $body.='</cols><sheetData>';
        $body.='<row r="1" ht="36" customHeight="1">'.self::cell($title,'A',1,1).'</row>';
        if($modern){
            // Separate date strings from Persian labels: some Excel viewers reverse even bidi-marked mixed strings.
            $merges=['A1:'.$end.'1'];$dates=$options['dates']??[];$unit=(string)($options['unit']??'تومان');
            $body.='<row r="2" ht="32" customHeight="1">';$unitEnd=$n-5;
            $body.=self::cell('مبالغ به '.$unit,'A',2,2);if($unitEnd>0)$merges[]='A2:'.self::col($unitEnd).'2';
            $body.=self::cell((string)($dates['to']??'—'),self::col($n-4),2,21).self::cell('تا تاریخ',self::col($n-3),2,2).self::cell((string)($dates['from']??'—'),self::col($n-2),2,21).self::cell('از تاریخ',self::col($n-1),2,2).'</row>';
        }else $body.='<row r="2" ht="32" customHeight="1">'.self::cell($subtitle,'A',2,2).'</row>';
        $body.='<row r="3" ht="27" customHeight="1">';
        foreach($headers as $j=>$h)$body.=self::cell($h,self::col($j),3,$modern?match($types[$j]){'received'=>34,'paid'=>35,'commission'=>36,'net'=>28,default=>3}:3);
        $body.='</row>';
        $r=4;
        foreach($rows as $item){
            $values=$item['values']??[];if(count($values)!==$n)throw new RuntimeException('تعداد ستون‌های یکی از ردیف‌های اکسل نامعتبر است.');
            $kind=$item['kind']??'data';$isTotal=$kind==='total';$covered=[];if($modern){if($kind!=='data')$filterOpen=false;if($filterOpen)$filterEnd=$r;foreach(($item['merge']??[]) as $start=>$finish){if(!is_int($start)||!is_int($finish)||$start<0||$finish>=$n||$finish<=$start)throw new RuntimeException('Invalid merge');for($k=$start;$k<=$finish;$k++){if(isset($covered[$k]))throw new RuntimeException('Overlapping merge');$covered[$k]=$start;}$merges[]=self::col($start).$r.':'.self::col($finish).$r;}}
            $body.='<row r="'.$r.'" ht="'.($modern?($kind==='section'?32:($isTotal?30:26)):($emphasizeNumbers?29:24)).'" customHeight="1">';
            foreach($values as $j=>$value){
                $type=$types[$j];$style=$isTotal?4:0;
                if(is_int($value)||is_float($value)){
                    $style=match($type){
                        'member'=>((float)$value<0?7:((float)$value>0?8:5)),
                        'received'=>9,
                        'paid'=>10,
                        'commission'=>11,
                        'dollar'=>5,
                        default=>6,
                    };
                    if($isTotal){$style=match($type){'member'=>((float)$value<0?12:((float)$value>0?13:4)),default=>14};}
                    if ($emphasizeNumbers) {
                        $style=$isTotal
                           ? ($type==='member' ? ((float)$value<0?19:((float)$value>0?20:18)) : 18)
                           : ($type==='member' ? ((float)$value<0?16:((float)$value>0?17:15)) : 15);
                    }
                }
                if($modern){
                    if(isset($item['styles'][$j]))$style=(int)$item['styles'][$j];
                    elseif(isset($covered[$j])&&isset($item['styles'][$covered[$j]]))$style=(int)$item['styles'][$covered[$j]];
                    elseif($type==='net'&&is_numeric($value))$style=(float)$value>0?23:((float)$value<0?24:6);
                    elseif($type==='ltr')$style=21;
                    elseif(is_numeric($value)&&in_array($type,['received','paid','commission'],true))$style=match($type){'received'=>25,'paid'=>26,default=>27};
                    if($style<0||$style>47)throw new RuntimeException('Invalid style');
                    if(isset($covered[$j])&&$covered[$j]!==$j)$value='';
                }
                $body.=self::cell($value,self::col($j),$r,$style);
            }
            $body.='</row>';$r++;
        }
        // ECMA-376 CT_Worksheet requires autoFilter BEFORE mergeCells.
        // Reversing these elements caused Excel 2010 to report unreadable worksheet content.
        $body.='</sheetData>';
        $body.='<autoFilter ref="A3:'.$end.($modern?$filterEnd:max(3,$r-1)).'"/>';
        $body.='<mergeCells count="'.count($merges).'">';foreach($merges as $merge)$body.='<mergeCell ref="'.$merge.'"/>';$body.='</mergeCells>';
        $body.='</worksheet>';
        $shared='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.self::$sharedReferences.'" uniqueCount="'.count(self::$sharedStrings).'">';
        foreach(self::$sharedStrings as $value){$shared.='<si><t xml:space="preserve">'.self::xml($value).'</t></si>';}
        $shared.='</sst>';
        $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $styles='<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="'.$ns.'">'
          .'<fonts count="'.($modern?10:9).'">'
          .'<font><sz val="11"/><name val="Tahoma"/></font>'
          .'<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font>'
          .'<font><b/><sz val="13"/><color rgb="FF1E4268"/><name val="Tahoma"/></font>'
          .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font>'
          .'<font><b/><sz val="11"/><color rgb="FFB91C1C"/><name val="Tahoma"/></font>'
          .'<font><b/><sz val="11"/><color rgb="FF1456AD"/><name val="Tahoma"/></font>'
          .'<font><b/><sz val="13"/><name val="Tahoma"/></font>'
          .'<font><b/><sz val="13"/><color rgb="FFB91C1C"/><name val="Tahoma"/></font>'
          .'<font><b/><sz val="13"/><color rgb="FF1456AD"/><name val="Tahoma"/></font>'
          .''.($modern?'<font><b/><sz val="11"/><color rgb="FF15803D"/><name val="Tahoma"/></font>':'').'</fonts><fills count="'.($modern?14:7).'"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FF35456A"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.($modern?$palette['commission']:'D8EEE4').'"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.($modern?$palette['received']:'FEDFE3').'"/></patternFill></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.($modern?$palette['paid']:'D6EDF6').'"/></patternFill></fill>'
           .($modern?'<fill><patternFill patternType="solid"><fgColor rgb="FF'.$palette['win'].'"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF'.$palette['loss'].'"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEDE9FE"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2E1D0"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE0F1DE"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE3F2FD"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9F2DF"/></patternFill></fill>':'')
          .'</fills><borders count="2"><border/><border><left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right><top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom></border></borders>'
          .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $sty=[ // font,fill,border,numberFormat
          [0,0,1,0],[1,2,1,0],[2,3,1,0],[3,2,1,0],[0,4,1,0],
          [0,0,1,4],[0,0,1,3],[4,0,1,4],[5,0,1,4],[0,5,1,3],[0,6,1,3],[0,4,1,3],
          [4,4,1,4],[5,4,1,4],[0,4,1,3],
          [6,0,1,4],[7,0,1,4],[8,0,1,4],[6,4,1,4],[7,4,1,4],[8,4,1,4]
        ];
        if($modern)$sty=array_merge($sty,[[0,0,1,0],[0,9,1,0],[6,7,1,3],[6,8,1,3],[6,5,1,3],[6,6,1,3],[6,4,1,3],[6,7,1,0],[6,5,1,3],[6,6,1,3],[2,9,1,0],[6,5,1,0],[6,6,1,0],[6,5,1,0],[6,6,1,0],[6,4,1,0],
          [0,10,1,0],[0,11,1,0],[4,9,1,0],[9,9,1,0],[4,12,1,3],[9,12,1,3],[8,13,1,3],[8,13,1,0],[7,7,1,3],[8,7,1,3],[7,7,1,0]]);
        $styles.='<cellXfs count="'.count($sty).'">';
        foreach($sty as $id=>[$font,$fill,$border,$num]){
            $styles.='<xf fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" numFmtId="'.$num.'" xfId="0" applyAlignment="1" applyNumberFormat="1"><alignment horizontal="center" vertical="center" readingOrder="'.($modern&&in_array($id,[21,37,38],true)?1:2).'"'.($modern?' wrapText="1"':'').'/></xf>';
        }
        $styles.='</cellXfs></styleSheet>';
        $tmp=tempnam(sys_get_temp_dir(),'soltan68_');if(!$tmp)throw new RuntimeException('فایل موقت اکسل ساخته نشد.');
        $z=new ZipArchive();if($z->open($tmp,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('بازکردن ZIP اکسل ناموفق بود.');
        $parts=[
          '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
          '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
          'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="'.self::xml($sheetName).'" sheetId="1" r:id="rId1"/></sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>',
          'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>',
          'xl/styles.xml'=>$styles,
          'xl/sharedStrings.xml'=>$shared,
          'xl/worksheets/sheet1.xml'=>$body,
        ];
        foreach($parts as $path=>$contents)$z->addFromString($path,$contents);
        $z->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.filesize($tmp));
        header('Cache-Control: private, no-store, max-age=0');
        readfile($tmp);@unlink($tmp);exit;
    }
}

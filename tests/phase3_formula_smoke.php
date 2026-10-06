<?php
require dirname(__DIR__).'/app/Services/AccountingService.php';
$cases=[
 ['mw'=>-33.28,'rate'=>200,'pct'=>10,'expected'=>['commission'=>665.6,'received'=>5990.4,'paid'=>0.0]],
 ['mw'=>18.95,'rate'=>200,'pct'=>10,'expected'=>['commission'=>0.0,'received'=>0.0,'paid'=>3790.0]],
 ['mw'=>0,'rate'=>200,'pct'=>10,'expected'=>['commission'=>0.0,'received'=>0.0,'paid'=>0.0]],
];
$ok=true;foreach($cases as $i=>$c){$got=AccountingService::calculateRow($c['mw'],$c['rate'],$c['pct']);$pass=$got==$c['expected'];echo ($pass?'[OK] ':'[FAIL] ').'case '.($i+1).' '.json_encode($got).PHP_EOL;if(!$pass)$ok=false;}exit($ok?0:1);

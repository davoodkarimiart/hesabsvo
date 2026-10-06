<?php

declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

if(PHP_SAPI!=='cli'){Auth::requireDeveloper();}
Migrator::migrate();
$group=CustomerService::autoGroupUnlinked(Auth::check()?(int)(Auth::user()['id']??0):null);
echo "[OK] Migration: ".Migrator::latest().PHP_EOL;
echo "[OK] Auto customers created: ".$group['created'].PHP_EOL;
echo "[OK] Accounts linked: ".$group['linked'].PHP_EOL;

<?php
declare(strict_types=1);

spl_autoload_register(static function(string $class): void {
    $prefix='TalentHub\\'; if(!str_starts_with($class,$prefix)){return;}
    $relative=str_replace('\\','/',substr($class,strlen($prefix)));
    $roots=['Tests\\'=>dirname(__DIR__).'/tests/'];
    if(str_starts_with(substr($class,strlen($prefix)),'Tests\\')){$relative=str_replace('\\','/',substr($class,strlen('TalentHub\\Tests\\')));$path=dirname(__DIR__).'/tests/'.$relative.'.php';}
    else{$path=dirname(__DIR__).'/src/'.$relative.'.php';}
    if(is_file($path)){require $path;}
});

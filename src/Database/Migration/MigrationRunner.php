<?php
declare(strict_types=1);
namespace TalentHub\Database\Migration;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private MigrationRepository $repository;
    private MigrationContext $context;
    public function __construct(private readonly PDO $pdo, private readonly string $directory)
    { $this->repository=new MigrationRepository($pdo); $this->context=new MigrationContext($pdo); }

    /** @return list<MigrationDefinition> */
    public function definitions(): array
    {
        $files=glob(rtrim($this->directory,'/\\').'/*.php') ?: []; sort($files,SORT_STRING); $result=[]; $seen=[];
        foreach($files as $file){
            $base=basename($file);
            if(preg_match('/\A(\d{14})_([a-z0-9_]+)\.php\z/',$base,$m)!==1){throw new RuntimeException("Invalid migration filename: {$base}");}
            if(isset($seen[$m[1]])){throw new RuntimeException("Duplicate migration version: {$m[1]}");}
            $migration=require $file;
            if(!$migration instanceof Migration){throw new RuntimeException("Migration must implement contract: {$base}");}
            $result[]=new MigrationDefinition($m[1],$m[2],$file,hash_file('sha256',$file),$migration); $seen[$m[1]]=true;
        }
        return $result;
    }
    public function validate(): void
    { $this->withLock(function(){ $this->validateState(); return null; }); }
    private function validateState(): void
    {
        $this->repository->bootstrap(); $definitions=$this->definitions(); $applied=$this->repository->applied();
        foreach($definitions as $d){if(isset($applied[$d->version]) && (!hash_equals($applied[$d->version]['checksum'],$d->checksum)||$applied[$d->version]['name']!==$d->name)){throw new RuntimeException("Applied migration drift: {$d->version}");} unset($applied[$d->version]);}
        if($applied!==[]){throw new RuntimeException('Applied migration file is missing: '.array_key_first($applied));}
    }
    /** @return list<string> */
    public function status(): array
    { $this->repository->bootstrap(); $applied=$this->repository->applied(); return array_map(fn($d)=>$d->version.' '.$d->name.' '.(isset($applied[$d->version])?'applied':'pending'),$this->definitions()); }
    /** @return list<string> */
    public function migrate(?int $step=null): array
    {
        return $this->withLock(function() use($step){$this->validateState();$applied=$this->repository->applied();$pending=array_values(array_filter($this->definitions(),fn($d)=>!isset($applied[$d->version])));if($step!==null){$pending=array_slice($pending,0,$step);}if($pending===[]){return [];}$batch=$this->repository->nextBatch();$done=[];foreach($pending as $d){$d->migration->preflight($this->context);$start=hrtime(true);$d->migration->up($this->context);$ms=(int)((hrtime(true)-$start)/1_000_000);$this->repository->record($d,$batch,$ms);$done[]=$d->version;}return $done;});
    }
    /** @return list<string> */
    public function rollbackLastBatch(): array
    { return $this->rollback(); }
    /** @return list<string> */
    public function rollback(?int $steps=null, ?int $batch=null): array
    {
        return $this->withLock(function()use($steps,$batch){$this->validateState();$applied=$this->repository->applied();if($applied===[]){return [];}$targetBatch=$batch??max(array_column($applied,'batch'));$defs=[];foreach($this->definitions() as $d){if(isset($applied[$d->version])&&($steps!==null||$applied[$d->version]['batch']===$targetBatch)){$defs[]=$d;}}$defs=array_reverse($defs);if($steps!==null){$defs=array_slice($defs,0,$steps);}foreach($defs as $d){if(!$d->migration->isReversible()){throw new RuntimeException("Migration is irreversible: {$d->version}");}}$done=[];foreach($defs as $d){$d->migration->down($this->context);$this->repository->remove($d->version);$done[]=$d->version;}return $done;});
    }
    private function withLock(callable $operation): mixed
    {
        $s=$this->pdo->prepare('SELECT GET_LOCK(?,30)');$s->execute(['talenthub:schema_migrations']);if((int)$s->fetchColumn()!==1){throw new RuntimeException('Unable to acquire migration lock.');}
        try{return $operation();}finally{$s=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$s->execute(['talenthub:schema_migrations']);}
    }
}

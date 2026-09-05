<?php
declare(strict_types=1);
namespace TalentHub\Database\Migration;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    /** Checksums recorded by an earlier verified migration revision before a forward repair was committed. */
    private const COMPATIBLE_CHECKSUMS = [
        '20260816000100' => [
            '4898b6f710c6b014073a58a96b423c654cb401d85f98c0e150f7b01b466a0138',
            '25b7c4191dfcf41154fa4195007515ff681b7d0f7bccb6eb124a4a650597b625',
        ],
        '20260821000400' => [
            '475ffb17c426c92e96fcb66b9c5b04a0bd98f665bd697b3d0ea75942c966df80',
            '82c823601e730b8cb68862f6e2e4d855de6cb769b3261705be297f4e1ace66cb',
        ],
        '20260825000210' => [
            '5e06f6811336e87339ec73a3bc82eaf49b3a526fb5534e43a04d02df3bb7fd95',
        ],
    ];

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
            $result[]=new MigrationDefinition($m[1],$m[2],$file,$this->canonicalChecksum($file),$migration); $seen[$m[1]]=true;
        }
        return $result;
    }
    public function validate(): void
    { $this->withLock(function(){ $this->validateState(); return null; }); }
    private function validateState(): void
    {
        $this->repository->bootstrap(); $definitions=$this->definitions(); $applied=$this->repository->applied();
        foreach($definitions as $d){if(isset($applied[$d->version]) && (!$this->checksumMatches($d,$applied[$d->version]['checksum'])||$applied[$d->version]['name']!==$d->name)){throw new RuntimeException("Applied migration drift: {$d->version}");} unset($applied[$d->version]);}
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

    private function checksumMatches(MigrationDefinition $definition, string $stored): bool
    {
        if (hash_equals($definition->checksum, $stored)) {
            return true;
        }
        $raw = hash_file('sha256', $definition->path);
        if (is_string($raw) && hash_equals($raw, $stored)) {
            return true;
        }
        foreach (self::COMPATIBLE_CHECKSUMS[$definition->version] ?? [] as $compatible) {
            if (hash_equals($compatible, $stored)) {
                return true;
            }
        }
        return false;
    }

    private function canonicalChecksum(string $file): string
    {
        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read migration file: ' . basename($file));
        }
        return hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents));
    }
}

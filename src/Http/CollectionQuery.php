<?php
declare(strict_types=1);
namespace TalentHub\Http;

final class CollectionQuery
{
    /** @param list<string> $allowedSorts @param array<string,list<string>> $allowedFilters */
    public static function fromRequest(Request $request, array $allowedSorts, array $allowedFilters = [], int $defaultLimit = 20, int $maxLimit = 100): self
    {
        $limit=self::integer($request->queryParam('limit'),'limit',1,$maxLimit,$defaultLimit);
        $offset=self::integer($request->queryParam('offset'),'offset',0,1000000,0);
        $sort=$request->queryParam('sort') ?? ($allowedSorts[0] ?? 'createdAt');
        $direction=strtolower($request->queryParam('direction') ?? 'desc');
        if(!in_array($sort,$allowedSorts,true)){throw new ApiException(422,'VALIDATION_FAILED','Sort field không được hỗ trợ.');}
        if(!in_array($direction,['asc','desc'],true)){throw new ApiException(422,'VALIDATION_FAILED','Sort direction phải là asc hoặc desc.');}
        $filters=[];
        foreach($allowedFilters as $name=>$values){$value=$request->queryParam($name);if($value===null||$value===''){continue;}if(!in_array($value,$values,true)){throw new ApiException(422,'VALIDATION_FAILED',"Filter {$name} không hợp lệ.");}$filters[$name]=$value;}
        return new self($limit,$offset,$sort,$direction,$filters);
    }

    /** @param array<string,string> $filters */
    private function __construct(public readonly int $limit,public readonly int $offset,public readonly string $sort,public readonly string $direction,public readonly array $filters){}

    /** @return array{limit:int,offset:int,sort:string,direction:string,filters:array<string,string>} */
    public function meta(): array{return ['limit'=>$this->limit,'offset'=>$this->offset,'sort'=>$this->sort,'direction'=>$this->direction,'filters'=>$this->filters];}

    private static function integer(?string $value,string $field,int $min,int $max,int $default): int
    {if($value===null||$value===''){return $default;}if(!preg_match('/^\d+$/',$value)){throw new ApiException(422,'VALIDATION_FAILED',"{$field} phải là số nguyên.");}$parsed=(int)$value;if($parsed<$min||$parsed>$max){throw new ApiException(422,'VALIDATION_FAILED',"{$field} phải từ {$min} đến {$max}.");}return $parsed;}
}

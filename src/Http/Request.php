<?php
declare(strict_types=1);
namespace TalentHub\Http;

final class Request
{
    /** @param array<string,string> $headers */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $headers,
        private readonly string $rawBody,
    ) {}

    public static function fromGlobals(): self
    {
        $headers=[];
        foreach($_SERVER as $key=>$value){
            if(str_starts_with($key,'HTTP_')&&is_string($value)){$headers[strtolower(str_replace('_','-',substr($key,5)))]=$value;}
        }
        if(isset($_SERVER['CONTENT_TYPE'])){$headers['content-type']=(string)$_SERVER['CONTENT_TYPE'];}
        $uri=(string)($_SERVER['REQUEST_URI']??'/');
        return new self(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')),parse_url($uri,PHP_URL_PATH)?:'/', $headers, file_get_contents('php://input')?:'');
    }

    public function header(string $name): ?string { return $this->headers[strtolower($name)]??null; }

    /** @return array<string,mixed> */
    public function json(): array
    {
        if(!str_starts_with(strtolower($this->header('content-type')??''),'application/json')){throw new ApiException(415,'UNSUPPORTED_MEDIA_TYPE','Content-Type phải là application/json.');}
        try{$data=json_decode($this->rawBody,true,512,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new ApiException(400,'INVALID_JSON','JSON không hợp lệ.');}
        if(!is_array($data)){throw new ApiException(400,'INVALID_REQUEST','Body JSON phải là object.');}
        return $data;
    }
}

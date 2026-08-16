<?php
declare(strict_types=1);
namespace TalentHub\Http;

final class Request
{
    /**
     * @param array<string,string> $headers
     * @param array<string,string> $pathParams
     * @param array<string,string> $queryParams
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $headers,
        private readonly string $rawBody,
        private readonly array $pathParams = [],
        private readonly array $queryParams = [],
    ) {}

    public static function fromGlobals(): self
    {
        $headers=[];
        foreach($_SERVER as $key=>$value){
            if(str_starts_with($key,'HTTP_')&&is_string($value)){$headers[strtolower(str_replace('_','-',substr($key,5)))]=$value;}
        }
        if(isset($_SERVER['CONTENT_TYPE'])){$headers['content-type']=(string)$_SERVER['CONTENT_TYPE'];}
        $uri=(string)($_SERVER['REQUEST_URI']??'/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if (($apiPos = strpos($path, '/api/')) !== false && $apiPos > 0) {
            $path = substr($path, $apiPos);
        }
        $queryString = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');
        $queryParams = [];
        if ($queryString !== '') {
            foreach (explode('&', $queryString) as $pair) {
                if ($pair === '') { continue; }
                $eq = strpos($pair, '=');
                if ($eq === false) {
                    $name = $pair;
                    $value = '';
                } else {
                    $name = substr($pair, 0, $eq);
                    $value = substr($pair, $eq + 1);
                }
                $queryParams[urldecode($name)] = urldecode($value);
            }
        }
        $rawBody = isset($GLOBALS['__TALENTHUB_TEST_BODY__']) && is_string($GLOBALS['__TALENTHUB_TEST_BODY__'])
            ? $GLOBALS['__TALENTHUB_TEST_BODY__']
            : (file_get_contents('php://input') ?: '');
        return new self(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')), $path, $headers, $rawBody, [], $queryParams);
    }

    public function header(string $name): ?string { return $this->headers[strtolower($name)]??null; }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function pathParam(string $name): ?string
    {
        return $this->pathParams[$name] ?? null;
    }

    /** @return array<string,string> */
    public function pathParams(): array
    {
        return $this->pathParams;
    }

    public function queryParam(string $name): ?string
    {
        return $this->queryParams[$name] ?? null;
    }

    /** @return array<string,string> */
    public function queryParams(): array
    {
        return $this->queryParams;
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        if(!str_starts_with(strtolower($this->header('content-type')??''),'application/json')){throw new ApiException(415,'UNSUPPORTED_MEDIA_TYPE','Content-Type phải là application/json.');}
        try{$data=json_decode($this->rawBody,true,512,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new ApiException(400,'INVALID_JSON','JSON không hợp lệ.');}
        if(!is_array($data)){throw new ApiException(400,'INVALID_REQUEST','Body JSON phải là object.');}
        return $data;
    }
}

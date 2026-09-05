<?php

declare(strict_types=1);

namespace TalentHub\Modules\Contact\Exception;

use RuntimeException;

final class ConsultationValidationException extends RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Dữ liệu yêu cầu tư vấn chưa hợp lệ.');
    }
}

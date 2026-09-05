<?php

declare(strict_types=1);

namespace TalentHub\Modules\Contact\Service;

use RuntimeException;

final class ConsultationFormGuard
{
    public function assertCsrf(string $expected, string $provided): void
    {
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            throw new RuntimeException('Phiên bảo mật đã hết hạn. Vui lòng thử lại.');
        }
    }

    /** @param array<string,int> $validTokens */
    public function assertFormToken(array $validTokens, string $provided): void
    {
        if ($provided === '' || !isset($validTokens[$provided])) {
            throw new RuntimeException('Biểu mẫu đã được gửi hoặc hết hạn. Vui lòng tải lại trang.');
        }
    }
}

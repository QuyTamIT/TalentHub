<?php

declare(strict_types=1);

namespace TalentHub\Modules\Contact\Service;

use TalentHub\Modules\Contact\Exception\ConsultationValidationException;
use TalentHub\Modules\Contact\Repository\ConsultationRequestRepository;

final class ConsultationRequestService
{
    private const AUDIENCES = ['student', 'teacher', 'school', 'enterprise', 'other'];

    public function __construct(
        private readonly ConsultationRequestRepository $repository,
        private readonly ConsultationRateLimiter $rateLimiter,
    ) {
    }

    /** @param array<string,mixed> $input
     *  @return array{id:string,created:bool}
     */
    public function submit(array $input, string $formToken, ?string $ip): array
    {
        $data = $this->validate($input);
        if (!preg_match('/\A[a-f0-9]{64}\z/', $formToken)) {
            throw new ConsultationValidationException(['form' => 'Phiên biểu mẫu không hợp lệ. Vui lòng tải lại trang.']);
        }

        $this->rateLimiter->consume($data['email'], $ip);
        return $this->repository->create($data, hash('sha256', $formToken));
    }

    /** @param array<string,mixed> $input
     *  @return array{fullName:string,audience:string,email:string,phone:?string,message:string,contactConsent:int}
     */
    public function validate(array $input): array
    {
        $fullName = trim((string) ($input['fullName'] ?? ''));
        $audience = trim((string) ($input['audience'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));
        $consent = (string) ($input['contactConsent'] ?? '') === '1';
        $errors = [];

        if ($fullName === '') {
            $errors['fullName'] = 'Vui lòng nhập họ và tên.';
        } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
            $errors['fullName'] = 'Họ và tên cần từ 2 đến 120 ký tự.';
        }
        if (!in_array($audience, self::AUDIENCES, true)) {
            $errors['audience'] = 'Vui lòng chọn đối tượng phù hợp.';
        }
        if ($email === '') {
            $errors['email'] = 'Vui lòng nhập email.';
        } elseif (mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email chưa đúng định dạng.';
        }
        if ($phone !== '' && (mb_strlen($phone) > 30 || preg_match('/\A[0-9+().\-\s]{7,30}\z/u', $phone) !== 1)) {
            $errors['phone'] = 'Số điện thoại chưa đúng định dạng.';
        }
        if ($message === '') {
            $errors['message'] = 'Vui lòng cho biết nội dung cần tư vấn.';
        } elseif (mb_strlen($message) < 10 || mb_strlen($message) > 3000) {
            $errors['message'] = 'Nội dung cần từ 10 đến 3.000 ký tự.';
        }
        if (!$consent) {
            $errors['contactConsent'] = 'Bạn cần đồng ý để TalentHub liên hệ về yêu cầu này.';
        }
        if ($errors !== []) {
            throw new ConsultationValidationException($errors);
        }

        return [
            'fullName' => $fullName,
            'audience' => $audience,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'message' => $message,
            'contactConsent' => 1,
        ];
    }
}

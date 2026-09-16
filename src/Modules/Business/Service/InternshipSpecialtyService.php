<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\Business\Repository\InternshipSpecialtyRepository;

final class InternshipSpecialtyService
{
    public function __construct(
        private readonly InternshipRepository $internshipRepository,
        private readonly InternshipSpecialtyRepository $specialtyRepository
    ) {}

    /** @return list<array<string,mixed>> */
    public function listForUser(string $userId): array
    {
        $enterpriseId = $this->internshipRepository->enterpriseIdForUser($userId);
        return $this->specialtyRepository->listForEnterprise($enterpriseId);
    }

    /** @return array<string,mixed> */
    public function createForUser(string $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tên lĩnh vực phải từ 2 đến 150 ký tự.');
        }

        $category = trim((string) ($input['category'] ?? 'general'));
        if (mb_strlen($category) > 60) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Danh mục tối đa 60 ký tự.');
        }

        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 500) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mô tả tối đa 500 ký tự.');
        }

        $enterpriseId = $this->internshipRepository->enterpriseIdForUser($userId);
        return $this->specialtyRepository->createForEnterprise(
            $enterpriseId,
            $userId,
            [
                'name'        => $name,
                'category'    => $category !== '' ? $category : 'general',
                'description' => $description,
                'slug'        => (string) ($input['slug'] ?? ''),
            ]
        );
    }
}

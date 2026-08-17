<?php

declare(strict_types=1);

$learnerDataRoot = __DIR__;
$GLOBALS['learner_data_defaults'] ??= require $learnerDataRoot . '/config.php';

require_once $learnerDataRoot . '/Contracts/StudentRepository.php';
require_once $learnerDataRoot . '/Contracts/AssessmentRepository.php';
require_once $learnerDataRoot . '/Contracts/ActivityRepository.php';
require_once $learnerDataRoot . '/Contracts/EcosystemRepository.php';
require_once $learnerDataRoot . '/Contracts/ApplicationRepository.php';
require_once $learnerDataRoot . '/Enums/Statuses.php';
require_once $learnerDataRoot . '/Exceptions/LearnerDataConfigurationException.php';
require_once $learnerDataRoot . '/Exceptions/LearnerDataMappingException.php';
require_once $learnerDataRoot . '/Exceptions/LearnerDataQueryException.php';
require_once $learnerDataRoot . '/Support/KeyMapper.php';
require_once $learnerDataRoot . '/Support/Uuid.php';
require_once $learnerDataRoot . '/Support/MockRecordNormalizer.php';
require_once $learnerDataRoot . '/Support/LearnerViewAdapter.php';
require_once $learnerDataRoot . '/Support/SharedStudentAdapter.php';
require_once $learnerDataRoot . '/ReadModel/ReadModelDefaults.php';
require_once $learnerDataRoot . '/ReadModel/StudentReadModel.php';
require_once $learnerDataRoot . '/ReadModel/AssessmentReadModel.php';
require_once $learnerDataRoot . '/ReadModel/ActivityReadModel.php';
require_once $learnerDataRoot . '/ReadModel/EcosystemReadModel.php';
require_once $learnerDataRoot . '/ReadModel/ApplicationReadModel.php';
require_once $learnerDataRoot . '/Mock/MockStudentRepository.php';
require_once $learnerDataRoot . '/Mock/MockAssessmentRepository.php';
require_once $learnerDataRoot . '/Mock/MockActivityRepository.php';
require_once $learnerDataRoot . '/Mock/MockEcosystemRepository.php';
require_once $learnerDataRoot . '/Mock/MockApplicationRepository.php';
require_once $learnerDataRoot . '/Database/AbstractDatabaseRepository.php';
require_once $learnerDataRoot . '/Database/DatabaseStudentRepository.php';
require_once $learnerDataRoot . '/Database/DatabaseAssessmentRepository.php';
require_once $learnerDataRoot . '/Database/DatabaseActivityRepository.php';
require_once $learnerDataRoot . '/Database/DatabaseEcosystemRepository.php';
require_once $learnerDataRoot . '/Database/DatabaseApplicationRepository.php';
require_once $learnerDataRoot . '/Database/SchemaInspector.php';
require_once $learnerDataRoot . '/Readiness/ReadinessResult.php';
require_once $learnerDataRoot . '/Readiness/PhaseRequirements.php';
require_once $learnerDataRoot . '/Readiness/GitScopeGuard.php';
require_once $learnerDataRoot . '/Readiness/LearnerMigrationRunner.php';
require_once $learnerDataRoot . '/Readiness/ReadinessChecker.php';
require_once dirname($learnerDataRoot) . '/runtime/LearnerRuntime.php';
require_once $learnerDataRoot . '/RepositoryFactory.php';

unset($learnerDataRoot);

if (!function_exists('learner_configure_data')) {
    function learner_configure_data(array $config): void
    {
        $GLOBALS['learner_data_config'] = array_merge(
            $GLOBALS['learner_data_defaults'],
            $config
        );
    }
}

if (!function_exists('learner_data_config')) {
    function learner_data_config(): array
    {
        $config = $GLOBALS['learner_data_config'] ?? [];
        return array_merge($GLOBALS['learner_data_defaults'], $config);
    }
}

if (!function_exists('learner_repository_factory')) {
    function learner_repository_factory(): \TalentHub\Learner\Data\RepositoryFactory
    {
        $config = learner_data_config();
        $pdo = $config['pdo'] ?? null;
        if ($pdo !== null && !$pdo instanceof \PDO) {
            throw new \TalentHub\Learner\Data\Exceptions\LearnerDataConfigurationException(
                'Learner data PDO configuration must be an instance of PDO.'
            );
        }

        return new \TalentHub\Learner\Data\RepositoryFactory((string) $config['source'], $pdo);
    }
}

if (!function_exists('learner_safe_runtime_diagnostics')) {
    function learner_safe_runtime_diagnostics(): array
    {
        $config = learner_data_config();
        $source = strtolower(trim((string) ($config['source'] ?? '')));

        return [
            'source' => $source,
            'pdo_configured' => ($config['pdo'] ?? null) instanceof \PDO,
            'student_id_configured' => trim((string) ($config['student_id'] ?? '')) !== '',
        ];
    }
}

if (!function_exists('learner_runtime')) {
    function learner_runtime(): \TalentHub\Learner\Runtime\LearnerRuntime
    {
        return \TalentHub\Learner\Runtime\LearnerRuntime::fromConfig(learner_data_config());
    }
}

if (!function_exists('learner_current_student_id')) {
    function learner_current_student_id(): string
    {
        $config = learner_data_config();
        $studentId = trim((string) ($config['student_id'] ?? ''));
        if ($studentId !== '') {
            return $studentId;
        }
        $source = strtolower(trim((string) ($config['source'] ?? 'mock')));
        if ($source === 'database') {
            throw new \TalentHub\Learner\Data\Exceptions\LearnerDataConfigurationException(
                'Learner database source requires an explicit student_id configuration.'
            );
        }

        return 'student-demo-001';
    }
}

<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data;

use DateTimeImmutable;
use PDO;
use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Data\Contracts\ActivityRepository;
use TalentHub\Learner\Data\Contracts\ApplicationRepository;
use TalentHub\Learner\Data\Contracts\AssessmentRepository;
use TalentHub\Learner\Data\Contracts\AssessmentWriteRepository;
use TalentHub\Learner\Data\Contracts\BadgeRepository;
use TalentHub\Learner\Data\Contracts\EcosystemRepository;
use TalentHub\Learner\Data\Contracts\NotificationRepository;
use TalentHub\Learner\Data\Contracts\StatisticsRepository;
use TalentHub\Learner\Data\Contracts\SchoolCredentialRepository;
use TalentHub\Learner\Data\Contracts\StudentRepository;
use TalentHub\Learner\Data\Contracts\TalentPassportRepository;
use TalentHub\Learner\Data\Exceptions\LearnerDataConfigurationException;
use TalentHub\Learner\Data\Mock\MockActivityRepository;
use TalentHub\Learner\Data\Mock\MockApplicationRepository;
use TalentHub\Learner\Data\Mock\MockAssessmentRepository;
use TalentHub\Learner\Data\Mock\MockEcosystemRepository;
use TalentHub\Learner\Data\Mock\MockNotificationRepository;
use TalentHub\Learner\Data\Mock\MockStudentRepository;
use TalentHub\Learner\Data\Mock\MockTalentPassportRepository;

final class RepositoryFactory
{
    private string $source;

    public function __construct(string $source = 'mock', private readonly ?PDO $pdo = null)
    {
        $normalized = strtolower(trim($source));
        if (!in_array($normalized, ['mock', 'database'], true)) {
            throw new LearnerDataConfigurationException("Unsupported learner data source: {$source}");
        }
        if ($normalized === 'database' && $pdo === null) {
            throw new LearnerDataConfigurationException(
                'Learner database source requires an injected PDO instance; mock fallback is disabled.'
            );
        }

        $this->source = $normalized;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function student(array $students = []): StudentRepository
    {
        if ($this->source === 'database') {
            return new Database\DatabaseStudentRepository($this->pdo);
        }

        return new MockStudentRepository($students);
    }

    public function talentPassport(array $fixture = []): TalentPassportRepository
    {
        if ($this->source === 'database') {
            return new Database\DatabaseTalentPassportRepository($this->pdo);
        }

        return new MockTalentPassportRepository($fixture);
    }

    public function assessment(
        array $definitions = [],
        array $questions = [],
        array $attempts = [],
        array $evaluations = []
    ): AssessmentRepository {
        if ($this->source === 'database') {
            return new Database\DatabaseAssessmentRepository($this->pdo);
        }

        return new MockAssessmentRepository($definitions, $questions, $attempts, $evaluations);
    }

    public function assessmentWrite(): AssessmentWriteRepository
    {
        if ($this->source !== 'database') {
            throw new LearnerDataConfigurationException(
                'Versioned assessment persistence requires the canonical learner database source.'
            );
        }

        return new Database\DatabaseAssessmentWriteRepository($this->pdo, $this->scorerRegistry());
    }

    private function scorerRegistry(): ScorerRegistry
    {
        return new ScorerRegistry([
            'holland-riasec-1.0' => new HollandScorer(),
            'mbti-education-1.0' => new MbtiScorer(),
            'disc-education-1.0' => new DiscScorer(),
            'multiple-intelligence-1.0' => new MultipleIntelligenceScorer(),
        ]);
    }

    public function activity(array $activities = [], array $registrations = []): ActivityRepository
    {
        if ($this->source === 'database') {
            return new Database\DatabaseActivityRepository($this->pdo);
        }

        return new MockActivityRepository($activities, $registrations);
    }

    public function ecosystem(array $partners = [], array $opportunities = []): EcosystemRepository
    {
        if ($this->source === 'database') {
            return new Database\DatabaseEcosystemRepository($this->pdo);
        }

        return new MockEcosystemRepository($partners, $opportunities);
    }

    public function application(array $applications = []): ApplicationRepository
    {
        if ($this->source === 'database') {
            return new Database\DatabaseApplicationRepository($this->pdo);
        }

        return new MockApplicationRepository($applications);
    }

    public function notification(array $notifications = []): NotificationRepository
    {
        if ($this->source === 'database') {
            return new Database\DatabaseNotificationRepository($this->pdo);
        }

        return new MockNotificationRepository($notifications);
    }

    public function notificationService(array $notifications = []): Service\NotificationService
    {
        return new Service\NotificationService($this->notification($notifications));
    }

    public function statistics(): StatisticsRepository
    {
        if ($this->source !== 'database') {
            throw new LearnerDataConfigurationException(
                'Personal statistics requires the canonical learner database source.'
            );
        }

        return new Database\DatabaseStatisticsRepository($this->pdo);
    }

    public function statisticsService(?DateTimeImmutable $clock = null): Service\StatisticsService
    {
        return new Service\StatisticsService($this->statistics(), $clock);
    }

    public function badge(): BadgeRepository
    {
        if ($this->source !== 'database') {
            throw new LearnerDataConfigurationException(
                'Badge persistence requires the canonical learner database source.'
            );
        }

        return new Database\DatabaseBadgeRepository($this->pdo);
    }

    public function badgeAwardService(?DateTimeImmutable $clock = null): Service\BadgeAwardService
    {
        return new Service\BadgeAwardService(
            $this->badge(),
            $this->statistics(),
            new Service\BadgeRuleEngine(),
            $this->notificationService(),
            $clock
        );
    }

    public function badgeReadService(): Service\BadgeReadService
    {
        return new Service\BadgeReadService(
            $this->badge(),
            $this->statistics(),
            new Service\BadgeRuleEngine()
        );
    }

    public function schoolCredential(): SchoolCredentialRepository
    {
        if ($this->source !== 'database') {
            throw new LearnerDataConfigurationException(
                'School credential catalog requires the canonical learner database source.'
            );
        }

        return new Database\DatabaseSchoolCredentialRepository($this->pdo);
    }

    public function schoolCredentialService(): Service\SchoolCredentialService
    {
        return new Service\SchoolCredentialService(
            $this->schoolCredential(),
            $this->statistics(),
            $this->badge(),
            new Service\CredentialRecommendationMatcher()
        );
    }
}

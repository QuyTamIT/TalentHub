<?php

declare(strict_types=1);

$learnerAiRoot = __DIR__;

require_once $learnerAiRoot . '/Sources/StudentProfileSource.php';
require_once $learnerAiRoot . '/Sources/SkillSource.php';
require_once $learnerAiRoot . '/Sources/AssessmentSource.php';
require_once $learnerAiRoot . '/Sources/ActivityExperienceSource.php';
require_once $learnerAiRoot . '/Sources/PublishedEvaluationSource.php';
require_once $learnerAiRoot . '/Sources/OpportunitySource.php';
require_once $learnerAiRoot . '/Sources/ConsentSource.php';
require_once $learnerAiRoot . '/Sources/Database/DatabaseStudentProfileSource.php';
require_once $learnerAiRoot . '/Sources/Database/DatabaseSkillSource.php';
require_once $learnerAiRoot . '/Sources/Database/DatabaseAssessmentSource.php';
require_once $learnerAiRoot . '/Sources/Database/DatabaseActivityExperienceSource.php';
require_once $learnerAiRoot . '/Sources/Database/DatabasePublishedEvaluationSource.php';
require_once $learnerAiRoot . '/Sources/Database/DatabaseOpportunitySource.php';
require_once $learnerAiRoot . '/Sources/Database/DatabaseConsentSource.php';
require_once $learnerAiRoot . '/Consent/ConsentPolicy.php';
require_once $learnerAiRoot . '/Domain/RecommendationContext.php';
require_once $learnerAiRoot . '/Domain/RecommendationEvidence.php';
require_once $learnerAiRoot . '/Domain/RecommendationInput.php';
require_once $learnerAiRoot . '/Domain/RecommendationItem.php';
require_once $learnerAiRoot . '/Domain/RecommendationResult.php';
require_once $learnerAiRoot . '/Quality/DataQualityResult.php';
require_once $learnerAiRoot . '/Quality/DataQualityGate.php';
require_once $learnerAiRoot . '/Snapshot/RecommendationSnapshotBuilder.php';
require_once $learnerAiRoot . '/Contracts/RecommendationEngine.php';
require_once $learnerAiRoot . '/Contracts/RecommendationProvider.php';
require_once $learnerAiRoot . '/Config/RecommendationConfig.php';
require_once $learnerAiRoot . '/Provider/ProviderRequest.php';
require_once $learnerAiRoot . '/Provider/ProviderResponse.php';
require_once $learnerAiRoot . '/Provider/FakeRecommendationProvider.php';
require_once $learnerAiRoot . '/Provider/HttpRecommendationProvider.php';
require_once $learnerAiRoot . '/RateLimit/RecommendationRateLimitDecision.php';
require_once $learnerAiRoot . '/RateLimit/RecommendationRateLimiter.php';
require_once $learnerAiRoot . '/Model/PromptRegistry.php';
require_once $learnerAiRoot . '/Explanation/RecommendationExplainer.php';
require_once $learnerAiRoot . '/Rules/RuleDefinition.php';
require_once $learnerAiRoot . '/Rules/RuleSetV1.php';
require_once $learnerAiRoot . '/Rules/RuleRecommendationEngine.php';
require_once $learnerAiRoot . '/Persistence/RecommendationRepository.php';
require_once $learnerAiRoot . '/Persistence/DatabaseRecommendationRepository.php';
require_once $learnerAiRoot . '/Validation/RecommendationResultValidator.php';
require_once $learnerAiRoot . '/Model/ModelRecommendationEngine.php';
require_once $learnerAiRoot . '/Service/RecommendationResponseMapper.php';
require_once $learnerAiRoot . '/Service/RecommendationService.php';

unset($learnerAiRoot);

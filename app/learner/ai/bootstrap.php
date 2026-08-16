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

unset($learnerAiRoot);

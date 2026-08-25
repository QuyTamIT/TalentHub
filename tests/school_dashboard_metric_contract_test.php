<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/src/Modules/School/Repository/SchoolRepository.php');
$service = file_get_contents($root . '/src/Modules/School/Service/SchoolDashboardService.php');
$analyticsPage = file_get_contents($root . '/app/school/analytics.php');
$classesPage = file_get_contents($root . '/app/school/classes.php');

if ($repository === false || $service === false || $analyticsPage === false || $classesPage === false) {
    throw new RuntimeException('Cannot read School dashboard sources.');
}

$metrics = [
    'activeStudents',
    'activeTeachers',
    'publishedActivities',
    'approvedRegistrations',
    'confirmedCheckins',
    'publishedAssessments',
    'verifiedSkills',
    'approvedEnterprisePartners',
    'activeInternshipPosts',
    'acceptedInternshipApplications',
    'activeProjects',
    'paidSponsorshipAmount',
];

foreach ($metrics as $metric) {
    if (!str_contains($repository, "'{$metric}'")) {
        throw new RuntimeException("Missing School metric: {$metric}");
    }
    if (!str_contains($analyticsPage, "['{$metric}']")) {
        throw new RuntimeException("Analytics page does not display School metric: {$metric}");
    }
}

$requiredScopes = [
    "c.schoolId = :schoolId AND sp.studyStatus = 'active'",
    "tp.schoolId = :schoolId AND u.status = 'active'",
    "a.schoolId = :schoolId AND ar.status = 'approved'",
    "a.schoolId = :schoolId AND ci.status = 'confirmed'",
    "a.schoolId = :schoolId AND ass.status = 'published'",
    "c.schoolId = :schoolId AND ss.verificationStatus = 'verified'",
    "sep.schoolId = :schoolId AND sep.status = 'approved'",
    "p.schoolId = :schoolId AND ps.status = 'paid'",
];
foreach ($requiredScopes as $scope) {
    if (!str_contains($repository, $scope)) {
        throw new RuntimeException("Missing tenant/status scope: {$scope}");
    }
}

$forbiddenDemoFragments = [
    'topStudentsForDemo',
    'recentActivityForDemo',
    'completionRate',
    'talentDistribution',
    "98 - \$idx",
    "\$teachers * 4 + 12",
    "\$classesCount + 2",
];
$combined = $service . $analyticsPage . $classesPage;
foreach ($forbiddenDemoFragments as $fragment) {
    if (str_contains($combined, $fragment)) {
        throw new RuntimeException("Demo dashboard fragment remains: {$fragment}");
    }
}

echo "school_dashboard_metric_contract_test: OK\n";

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/bootstrap.php';

if (!function_exists('learner_project_repository')) {
    function learner_project_repository(): \TalentHub\Learner\Data\Contracts\ProjectRepository
    {
        return learner_repository_factory()->project();
    }
}

if (!function_exists('learner_projects')) {
    /** @return list<array<string,mixed>> */
    function learner_projects(): array
    {
        if (learner_repository_factory()->source() !== 'database') {
            return [];
        }

        return \TalentHub\Learner\Data\ReadModel\ProjectReadModel::projects(
            learner_project_repository()->listVisibleForStudent(learner_current_student_id())
        );
    }
}

if (!function_exists('learner_project')) {
    /** @return array<string,mixed>|null */
    function learner_project(string $projectId): ?array
    {
        if (learner_repository_factory()->source() !== 'database') {
            return null;
        }

        $project = learner_project_repository()->findVisibleForStudent(
            learner_current_student_id(),
            $projectId
        );

        return $project === null
            ? null
            : \TalentHub\Learner\Data\ReadModel\ProjectReadModel::project($project);
    }
}

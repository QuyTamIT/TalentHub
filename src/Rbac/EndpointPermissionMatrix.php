<?php
declare(strict_types=1);
namespace TalentHub\Rbac;

final class EndpointPermissionMatrix
{
    /** @return array<string,string> */
    public static function all(): array
    {
        return [
            'GET /api/v1/teachers/me'=>'teacher_profile.read_own','PATCH /api/v1/teachers/me'=>'teacher_profile.update_own','GET /api/v1/teachers/me/dashboard'=>'teacher_dashboard.read_own',
            'GET /api/v1/schools/me'=>'school_profile.read_own','PATCH /api/v1/schools/me'=>'school_profile.update_own','GET /api/v1/schools/me/dashboard'=>'school_dashboard.read_own','GET /api/v1/schools/me/analytics'=>'school_analytics.read_own',
            'GET /api/v1/schools/me/classes'=>'class.read_own_school','POST /api/v1/schools/me/classes'=>'class.create_own_school','PATCH /api/v1/schools/me/classes/{classId}'=>'class.update_own_school','POST /api/v1/schools/me/classes/{classId}/archive'=>'class.archive_own_school',
            'GET /api/v1/schools/me/teachers'=>'teacher_profile.read_own_school','GET /api/v1/schools/me/teachers/{profileId}'=>'teacher_profile.read_own_school','POST /api/v1/schools/me/teachers'=>'teacher_profile.invite_own_school','PATCH /api/v1/schools/me/teachers/{profileId}/admin'=>'teacher_profile.update_role_own_school','PATCH /api/v1/schools/me/teachers/{profileId}/status'=>'teacher_profile.deactivate_own_school',
            'GET /api/v1/schools/me/students'=>'student_profile.read_own_school','GET /api/v1/schools/me/students/{profileId}'=>'student_profile.read_own_school','POST /api/v1/schools/me/students'=>'student_profile.create_own_school','PATCH /api/v1/schools/me/students/{profileId}'=>'student_profile.update_own_school',
            'GET /api/v1/students/me'=>'student_profile.read_own','PATCH /api/v1/students/me'=>'student_profile.update_own','GET /api/v1/students/me/dashboard'=>'student_dashboard.read_own',
            'GET /api/v1/businesses/me'=>'business_profile.read_own','PATCH /api/v1/businesses/me'=>'business_profile.update_own','POST /api/v1/businesses/me/logo'=>'business_profile.update_own','GET /api/v1/businesses/me/dashboard'=>'business_dashboard.read_own',
            'GET /api/v1/schools/me/reports'=>'report.read_own_school','POST /api/v1/schools/me/reports'=>'report.create_own_school','GET /api/v1/schools/me/reports/{reportId}'=>'report.download_own_school',
            'GET /api/v1/businesses/me/internship-posts'=>'internship_post.read_own_business','POST /api/v1/businesses/me/internship-posts'=>'internship_post.create_own_business','PATCH /api/v1/businesses/me/internship-posts/{postId}'=>'internship_post.update_own_business','POST /api/v1/businesses/me/internship-posts/{postId}/publish'=>'internship_post.publish_own_business','POST /api/v1/businesses/me/internship-posts/{postId}/close'=>'internship_post.close_own_business',
            'GET /api/v1/internship-posts'=>'internship_post.read_available','POST /api/v1/internship-posts/{postId}/applications'=>'internship_application.create_own','GET /api/v1/students/me/internship-applications'=>'internship_application.read_own','POST /api/v1/students/me/internship-applications/{applicationId}/withdraw'=>'internship_application.withdraw_own',
            'GET /api/v1/businesses/me/internship-applications'=>'internship_application.read_own_business','GET /api/v1/businesses/me/internship-applications/{applicationId}'=>'internship_application.read_own_business','PATCH /api/v1/businesses/me/internship-applications/{applicationId}'=>'internship_application.review_own_business','GET /api/v1/businesses/me/internship-posts/{postId}/applications'=>'internship_application.read_own_business','PATCH /api/v1/businesses/me/internship-applications/{applicationId}/review'=>'internship_application.review_own_business',
            'GET /api/v1/businesses/me/partnerships'=>'partnership.read_own_business','POST /api/v1/businesses/me/partnership-requests'=>'partnership.create_own_business','GET /api/v1/schools/me/partnerships'=>'partnership.read_own_school','PATCH /api/v1/schools/me/partnerships/{partnershipId}'=>'partnership.review_own_school',
            'GET /api/v1/businesses/me/talents'=>'talent.search_consented','GET /api/v1/businesses/me/talents/{studentId}'=>'talent.read_consented','POST /api/v1/businesses/me/talents/{studentId}/contact-requests'=>'contact_request.create_own_business',
            'GET /api/v1/students/me/enterprise-profile-grants'=>'privacy_consent.read_own','POST /api/v1/students/me/enterprise-profile-grants'=>'privacy_consent.manage_own','DELETE /api/v1/students/me/enterprise-profile-grants/{grantId}'=>'privacy_consent.manage_own',
            'GET /api/v1/projects'=>'project.read_sponsorable','POST /api/v1/businesses/me/sponsorships'=>'sponsorship.create_own_business','GET /api/v1/businesses/me/sponsorships'=>'sponsorship.read_own_business','POST /api/v1/businesses/me/sponsorships/{sponsorshipId}/cancel'=>'sponsorship.cancel_own_business',
            'POST /api/v1/businesses/me/payments'=>'payment.create_own_business','POST /api/v1/businesses/me/payments/{orderId}/confirm'=>'payment.create_own_business','GET /api/v1/businesses/me/payments'=>'payment.read_own_business',
            'POST /api/v1/schools/me/projects'=>'project.create_own_school','GET /api/v1/schools/me/projects'=>'project.read_own_school','PATCH /api/v1/schools/me/projects/{projectId}'=>'project.update_own_school',
            'POST /api/v1/teachers/me/projects/{projectId}/members'=>'project_member.create_managed','GET /api/v1/teachers/me/projects/{projectId}/members'=>'project_member.read_managed',
            'GET /api/v1/notifications'=>'notification.read_own','POST /api/v1/notifications/{notificationId}/read'=>'notification.mark_read_own',
            'GET /api/v1/admin/dashboard'=>'admin.dashboard.read','GET /api/v1/admin/users'=>'admin.user.read','POST /api/v1/admin/users'=>'admin.user.create','PATCH /api/v1/admin/users/{userId}'=>'admin.user.update','DELETE /api/v1/admin/users/{userId}'=>'admin.user.delete','PATCH /api/v1/admin/users/{userId}/status'=>'admin.user.suspend','GET /api/v1/admin/organizations'=>'admin.organization.read','PATCH /api/v1/admin/organizations/{type}/{organizationId}/verification'=>'admin.organization.verify','GET /api/v1/admin/audit'=>'admin.audit.read','GET /api/v1/admin/rbac'=>'admin.rbac.read','GET /api/v1/admin/system'=>'admin.system.health.read','GET /api/v1/admin/resources/{resource}'=>'admin.dashboard.read',
        ];
    }

    public static function permission(string $method,string $path): string
    {return self::all()[strtoupper($method).' '.$path]??throw new \LogicException("Endpoint permission is not declared: {$method} {$path}");}
}

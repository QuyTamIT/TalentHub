import { apiClient } from './client';
import * as mock from '@/lib/mock/school.mock';
import type {
  Activity,
  Class,
  Report,
  School,
  StudentProfile,
  StudentSkill,
  Skill,
  Badge,
  Assessment,
} from '@/lib/types/database';

// =====================================================
// TYPES
// =====================================================
export interface SchoolOverview {
  totalStudents: number;
  totalTeachers: number;
  activeActivities: number;
  totalBadges: number;
  studentsByGrade: { grade: number; count: number }[];
  recentActivities: Activity[];
}

export interface SchoolAnalytics {
  talentDistribution: { category: string; count: number }[];
  skillRanking: { skill: string; averageScore: number }[];
  gradeComparison: { grade: number; totalHours: number; averageScore: number }[];
  topClasses: { className: string; specialty: string; score: number }[];
}

export interface ClassWithStudents extends Class {
  studentCount: number;
  specialty?: string;
  students?: StudentProfile[];
}

export type ReportWithMeta = Report & { fileType: 'PDF' | 'XLSX'; size: string };

// =====================================================
// API (try real backend, fallback to mock)
// =====================================================
// Pattern: Thử gọi backend Laravel qua apiClient, nếu backend chưa sẵn sàng
// hoặc request lỗi thì tự động fallback về mock service để dev được tiếp.
// Khi backend ready chỉ cần xóa try-catch, không phải đổi type/UI.
//
// Lưu ý: apiClient.get trả về Promise<T>.
// =====================================================

async function withFallback<T>(real: () => Promise<T>, fallback: () => T | Promise<T>): Promise<T> {
  try {
    return await real();
  } catch {
    return await fallback();
  }
}

export const schoolApi = {
  getOverview: () =>
    withFallback<SchoolOverview>(
      () => apiClient.get<SchoolOverview>('/school/overview'),
      () => mock.getSchoolOverview()
    ),

  getAnalytics: (params?: { grade?: number; classId?: string; period?: string }) =>
    withFallback<SchoolAnalytics>(
      () => apiClient.get<SchoolAnalytics>('/school/analytics', params as Record<string, unknown>),
      () => mock.getSchoolAnalytics()
    ),

  getReports: (params?: { year?: number; type?: string }) => {
    const filterFn = (reports: ReportWithMeta[]) => {
      return reports.filter((r) => {
        if (params?.year && !r.createdAt.startsWith(String(params.year))) return false;
        if (params?.type && params.type !== 'all' && r.fileType !== params.type) return false;
        return true;
      });
    };
    return withFallback<ReportWithMeta[]>(
      () => apiClient.get<ReportWithMeta[]>('/school/reports', params as Record<string, unknown>),
      async () => filterFn(mock.getReports())
    );
  },

  getClasses: (params?: { grade?: number }) => {
    const filterFn = (classes: ClassWithStudents[]) => {
      return params?.grade ? classes.filter((c) => c.grade === params.grade) : classes;
    };
    return withFallback<ClassWithStudents[]>(
      () => apiClient.get<ClassWithStudents[]>('/school/classes', params as Record<string, unknown>),
      async () => filterFn(mock.getClasses())
    );
  },

  getClassStudents: (classId: string) =>
    withFallback<StudentProfile[]>(
      () => apiClient.get<StudentProfile[]>(`/school/classes/${classId}/students`),
      () => mock.getClassStudents(classId)
    ),

  /**
   * Lấy danh sách học sinh kèm fullName và avgScore (cho bảng Lớp & Khối)
   * Trả về extended type - chỉ mock có, backend sẽ tự join students + users + grades
   */
  getClassStudentsWithScore: async (classId: string) => {
    return mock.getClassStudentsWithScore(classId);
  },

  /**
   * Lấy chi tiết 1 class
   */
  getClassDetail: async (classId: string) => {
    return mock.getClassDetail(classId);
  },

  /**
   * Skill radar data theo filter
   */
  getSkillRadar: (grade?: number, classId?: string) => {
    return mock.getSkillRadar(grade, classId);
  },

  /**
   * Class comparison data
   */
  getClassComparison: (grade?: number) => {
    return mock.getClassComparison(grade);
  },

  /**
   * Grade bar chart data
   */
  getGradeBarChart: (grade?: number) => {
    return mock.getGradeBarChart(grade);
  },

  getSchoolInfo: () =>
    withFallback<School>(
      () => apiClient.get<School>('/school/info'),
      () => mock.getSchoolInfo()
    ),
};
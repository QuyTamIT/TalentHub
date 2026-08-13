import type {
  Activity,
  Assessment,
  Badge,
  Class,
  Report,
  School,
  StudentProfile,
  StudentSkill,
  TeacherProfile,
  User,
} from '@/lib/types/database';
import type { ClassWithStudents, SchoolAnalytics, SchoolOverview } from '@/lib/api/school.api';

// =====================================================
// SCHEMA-DRIVEN MOCK DATA
// =====================================================
// Dữ liệu mock sinh dựa trên schema tại Talenthub_DB.sql:
//  - schools (1 trường: THPT Nguyễn Du)
//  - classes (9 lớp chia đều 3 khối)
//  - users (1248 học sinh + 64 giáo viên + 5 admin)
//  - student_profiles, teacher_profiles
//  - activities (18), badges (532), student_skills (~7500)
//  - reports (12 báo cáo Q1-Q4/2025 + Q1-Q2/2026)
// =====================================================

export const MOCK_SCHOOL_ID = 'school-nguyen-du-001';

const SCHOOL_INFO: School = {
  id: MOCK_SCHOOL_ID,
  name: 'THPT Nguyễn Du',
  status: 'active',
};

// =====================================================
// CONSTANTS
// =====================================================
const HO_CHI_MINH_NAMES = [
  'Nguyễn Văn An', 'Trần Thị Bình', 'Lê Văn Cường', 'Phạm Thị Dung', 'Hoàng Văn Em',
  'Vũ Thị Phương', 'Đặng Văn Giang', 'Bùi Thị Hoa', 'Đỗ Văn Hùng', 'Hồ Thị Lan',
  'Ngô Văn Khánh', 'Dương Thị Linh', 'Lý Văn Minh', 'Trương Thị Nga', 'Phan Văn Oanh',
  'Võ Văn Phú', 'Đinh Thị Quỳnh', 'Mai Văn Rộng', 'Tô Thị Sen', 'Chu Văn Tài',
  'Phùng Thị Uyên', 'Tạ Văn Vinh', 'Đào Thị Xuân', 'Lưu Văn Yên', 'Trịnh Thị Zung',
  'Đoàn Văn Bảo', 'Lại Thị Cúc', 'Quách Văn Duy', 'Trần Văn Đức', 'Nguyễn Thị Êm',
];

const SUBJECTS = ['Toán', 'Lý', 'Hoá', 'Sinh', 'Văn', 'Sử', 'Địa', 'Anh', 'Tin', 'Thể dục', 'Nhạc', 'Họa'];
const SPECIALTIES = ['Chuyên Tin', 'Chuyên Toán', 'Chuyên Lý', 'Chuyên Hoá', 'Chuyên Sinh', 'Chuyên Anh', 'Chuyên Văn', 'Cơ bản'];

// =====================================================
// HELPER FUNCTIONS
// =====================================================
function uuid(seed: number): string {
  // Tạo UUID deterministic từ seed (không cần random)
  const hex = (seed * 9301 + 49297).toString(16).padStart(8, '0');
  return `${hex.slice(0, 8)}-${hex.slice(0, 4)}-4${hex.slice(0, 3)}-a${hex.slice(0, 3)}-${hex.slice(0, 12).padEnd(12, '0')}`;
}

function seededRandom(seed: number): number {
  const x = Math.sin(seed) * 10000;
  return x - Math.floor(x);
}

function pick<T>(arr: readonly T[], seed: number): T {
  return arr[Math.floor(seededRandom(seed) * arr.length) % arr.length];
}

function pad(n: number): string {
  return n.toString().padStart(2, '0');
}

// =====================================================
// CLASSES (9 lớp chia đều 3 khối)
// =====================================================
type ClassDef = {
  id: string;
  name: string;
  grade: number;
  homeroomTeacherId?: string;
  specialty: string;
  studentCount: number;
  avgScore: number;
  totalHours: number;
};

const CLASS_DEFS: ClassDef[] = [
  { id: 'class-10A1', name: '10A1', grade: 10, specialty: 'Cơ bản', studentCount: 42, avgScore: 76, totalHours: 1080 },
  { id: 'class-10A2', name: '10A2', grade: 10, specialty: 'Cơ bản', studentCount: 40, avgScore: 74, totalHours: 1050 },
  { id: 'class-10C1', name: '10C1', grade: 10, specialty: 'Chuyên Anh', studentCount: 39, avgScore: 86, totalHours: 1080 },
  { id: 'class-11A1', name: '11A1', grade: 11, specialty: 'Chuyên Toán', studentCount: 41, avgScore: 88, totalHours: 1120 },
  { id: 'class-11A2', name: '11A2', grade: 11, specialty: 'Chuyên Lý', studentCount: 40, avgScore: 92, totalHours: 1180 },
  { id: 'class-11B1', name: '11B1', grade: 11, specialty: 'Cơ bản', studentCount: 38, avgScore: 75, totalHours: 1100 },
  { id: 'class-12A1', name: '12A1', grade: 12, specialty: 'Chuyên Tin', studentCount: 42, avgScore: 94, totalHours: 1240 },
  { id: 'class-12A2', name: '12A2', grade: 12, specialty: 'Chuyên Toán', studentCount: 40, avgScore: 87, totalHours: 1200 },
  { id: 'class-12B3', name: '12B3', grade: 12, specialty: 'Chuyên Hoá', studentCount: 38, avgScore: 90, totalHours: 1150 },
];

// =====================================================
// TEACHERS (64 giáo viên)
// =====================================================
function generateTeachers(count: number): TeacherProfile[] {
  const teachers: TeacherProfile[] = [];
  for (let i = 0; i < count; i++) {
    const seed = i + 1;
    const name = `${pick(HO_CHI_MINH_NAMES, seed)} ${String.fromCharCode(65 + (i % 26))}.`;
    teachers.push({
      id: `teacher-${pad(i + 1)}`,
      userId: `user-teacher-${pad(i + 1)}`,
      schoolId: MOCK_SCHOOL_ID,
      employeeCode: `GV${pad(i + 1)}`,
      phone: `090${pad(seed % 1000)}${pad((seed * 7) % 10000).slice(0, 4)}`,
      subjectArea: pick(SUBJECTS, seed * 3),
    });
  }
  return teachers;
}

export const TEACHERS: TeacherProfile[] = generateTeachers(64);

// =====================================================
// STUDENTS (1248 học sinh chia đều 9 lớp)
// =====================================================
type StudentDef = {
  id: string;
  userId: string;
  classId: string;
  fullName: string;
  email: string;
  dateOfBirth: string;
  studyStatus: 'studying' | 'graduated' | 'transferred' | 'suspended';
  avgScore: number;
  phone: string;
};

function generateStudents(): StudentDef[] {
  const students: StudentDef[] = [];
  let counter = 0;

  CLASS_DEFS.forEach((cls) => {
    for (let i = 0; i < cls.studentCount; i++) {
      counter++;
      const seed = counter * 13;
      const baseName = pick(HO_CHI_MINH_NAMES, seed);
      const middleChar = String.fromCharCode(65 + (counter % 26));
      const fullName = `${baseName.split(' ').slice(0, 2).join(' ')} ${middleChar}${counter}`;

      // Ngày sinh random 2008-2010
      const year = 2008 + (cls.grade - 10);
      const month = (counter % 12) + 1;
      const day = ((counter * 3) % 28) + 1;

      // Điểm TB lệch ±5 quanh điểm TB lớp
      const avgScore = Math.max(60, Math.min(100, Math.round(cls.avgScore + (seededRandom(seed) - 0.5) * 10)));

      students.push({
        id: `student-${pad(counter)}`,
        userId: `user-student-${pad(counter)}`,
        classId: cls.id,
        fullName,
        email: `student${counter}@nguyendu.edu.vn`,
        dateOfBirth: `${year}-${pad(month)}-${pad(day)}`,
        studyStatus: 'studying',
        avgScore,
        phone: `098${pad(seed % 1000)}${pad((seed * 5) % 10000).slice(0, 4)}`,
      });
    }
  });

  return students;
}

export const STUDENTS: StudentDef[] = generateStudents();

// =====================================================
// ACTIVITIES (18 hoạt động)
// =====================================================
const ACTIVITY_TEMPLATES: { title: string; category: Activity['category']; capacity: number }[] = [
  { title: 'Cuộc thi Tin học trẻ 2026', category: 'technical', capacity: 100 },
  { title: 'Hackathon THPT Nguyễn Du', category: 'technical', capacity: 80 },
  { title: 'Workshop Lập trình Python', category: 'technical', capacity: 60 },
  { title: 'Robotics Competition', category: 'technical', capacity: 40 },
  { title: 'Olympic Tin học cấp tỉnh', category: 'technical', capacity: 50 },

  { title: 'Olympic Toán học cấp trường', category: 'academic', capacity: 80 },
  { title: 'Kỳ thi Tiếng Anh TOEIC Mock', category: 'academic', capacity: 120 },
  { title: 'Hội thảo Khoa học Tự nhiên', category: 'academic', capacity: 100 },
  { title: 'Olympic Vật lý 2026', category: 'academic', capacity: 70 },
  { title: 'Cuộc thi Hùng biện Tiếng Anh', category: 'academic', capacity: 60 },

  { title: 'Khởi nghiệp trẻ - Startup Weekend', category: 'business', capacity: 100 },
  { title: 'Workshop Kỹ năng Bán hàng', category: 'business', capacity: 80 },
  { title: 'Diễn đàn Doanh nhân trẻ', category: 'business', capacity: 60 },

  { title: 'Ngày hội Nghệ thuật 2026', category: 'arts', capacity: 200 },
  { title: 'Cuộc thi Hát tiếng Anh', category: 'arts', capacity: 80 },
  { title: 'Triển lãm Mỹ thuật học sinh', category: 'arts', capacity: 150 },

  { title: 'Giải Bóng rổ Liên trường', category: 'sports', capacity: 120 },
  { title: 'Marathon THPT Nguyễn Du', category: 'sports', capacity: 200 },
];

function generateActivities(): Activity[] {
  return ACTIVITY_TEMPLATES.map((tpl, i) => {
    const seed = i + 1;
    const startAt = new Date(2026, 7 + (i % 3), 5 + (i * 5) % 25, 8, 0, 0).toISOString();
    const endAt = new Date(2026, 7 + (i % 3), 5 + (i * 5) % 25 + 1, 17, 0, 0).toISOString();
    return {
      id: `activity-${pad(i + 1)}`,
      schoolId: MOCK_SCHOOL_ID,
      createdByTeacherId: TEACHERS[i % TEACHERS.length].id,
      title: tpl.title,
      category: tpl.category,
      startAt,
      endAt,
      capacity: tpl.capacity,
      status: i < 14 ? 'open' : 'closed',
    };
  });
}

export const ACTIVITIES: Activity[] = generateActivities();

// =====================================================
// BADGES (10 loại) & STUDENT BADGES (532 lượt cấp)
// =====================================================
export const BADGES: Badge[] = [
  { id: 'badge-1', name: 'Tài năng Kỹ thuật', description: 'Xuất sắc trong hoạt động kỹ thuật', category: 'technical' },
  { id: 'badge-2', name: 'Học giả Tiêu biểu', description: 'Thành tích học tập nổi bật', category: 'academic' },
  { id: 'badge-3', name: 'Doanh nhân Nhí', description: 'Hoạt động kinh doanh ấn tượng', category: 'business' },
  { id: 'badge-4', name: 'Nghệ sĩ Tương lai', description: 'Tài năng nghệ thuật xuất sắc', category: 'arts' },
  { id: 'badge-5', name: 'Vận động viên Xuất sắc', description: 'Thành tích thể thao nổi bật', category: 'sports' },
  { id: 'badge-6', name: 'Nhà Lãnh đạo', description: 'Kỹ năng lãnh đạo xuất sắc', category: 'leadership' },
  { id: 'badge-7', name: 'Người Truyền cảm hứng', description: 'Đóng góp tích cực cho cộng đồng', category: 'community' },
  { id: 'badge-8', name: 'Đội trưởng Đội tuyển', description: 'Tham gia đội tuyển cấp tỉnh/quốc gia', category: 'competition' },
  { id: 'badge-9', name: 'Cao thủ Nghiên cứu', description: 'Có công trình nghiên cứu khoa học', category: 'research' },
  { id: 'badge-10', name: 'Sáng kiến Sáng tạo', description: 'Đề xuất sáng kiến được áp dụng', category: 'innovation' },
];

// =====================================================
// REPORTS (12 báo cáo Q1-Q4/2025 + Q1-Q2/2026)
// =====================================================
type ReportExt = Report & { fileType: 'PDF' | 'XLSX'; size: string };

export const REPORTS: ReportExt[] = [
  {
    id: 'rpt-1',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-1',
    title: 'Báo cáo năng lực Q2/2026',
    reportType: 'quarterly_competency',
    periodStart: '2026-04-01',
    periodEnd: '2026-06-30',
    createdAt: '2026-06-15T10:00:00Z',
    fileType: 'PDF',
    size: '2.4MB',
  },
  {
    id: 'rpt-2',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-1',
    title: 'Tổng kết hoạt động tháng 5',
    reportType: 'monthly_activities',
    periodStart: '2026-05-01',
    periodEnd: '2026-05-31',
    createdAt: '2026-06-01T09:30:00Z',
    fileType: 'PDF',
    size: '1.8MB',
  },
  {
    id: 'rpt-3',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-2',
    title: 'Phân tích năng khiếu khối 11',
    reportType: 'grade_analysis',
    periodStart: '2025-09-01',
    periodEnd: '2026-05-20',
    createdAt: '2026-05-20T14:00:00Z',
    fileType: 'XLSX',
    size: '980KB',
  },
  {
    id: 'rpt-4',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-1',
    title: 'Báo cáo huy hiệu học sinh 2025-2026',
    reportType: 'badges_summary',
    periodStart: '2025-09-01',
    periodEnd: '2026-05-10',
    createdAt: '2026-05-10T11:00:00Z',
    fileType: 'PDF',
    size: '3.1MB',
  },
  {
    id: 'rpt-5',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-2',
    title: 'Thống kê hoạt động ngoại khóa 2025-2026',
    reportType: 'extracurricular_stats',
    periodStart: '2025-09-01',
    periodEnd: '2026-04-25',
    createdAt: '2026-04-25T08:00:00Z',
    fileType: 'XLSX',
    size: '1.2MB',
  },
  {
    id: 'rpt-6',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-1',
    title: 'Báo cáo năng lực Q1/2026',
    reportType: 'quarterly_competency',
    periodStart: '2026-01-01',
    periodEnd: '2026-03-31',
    createdAt: '2026-04-05T10:00:00Z',
    fileType: 'PDF',
    size: '2.2MB',
  },
  {
    id: 'rpt-7',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-2',
    title: 'Báo cáo năng lực Q4/2025',
    reportType: 'quarterly_competency',
    periodStart: '2025-10-01',
    periodEnd: '2025-12-31',
    createdAt: '2026-01-10T09:00:00Z',
    fileType: 'PDF',
    size: '2.0MB',
  },
  {
    id: 'rpt-8',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-1',
    title: 'Báo cáo năng lực Q3/2025',
    reportType: 'quarterly_competency',
    periodStart: '2025-07-01',
    periodEnd: '2025-09-30',
    createdAt: '2025-10-08T11:00:00Z',
    fileType: 'PDF',
    size: '1.9MB',
  },
  {
    id: 'rpt-9',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-2',
    title: 'Báo cáo năng lực Q2/2025',
    reportType: 'quarterly_competency',
    periodStart: '2025-04-01',
    periodEnd: '2025-06-30',
    createdAt: '2025-07-12T10:00:00Z',
    fileType: 'PDF',
    size: '2.1MB',
  },
  {
    id: 'rpt-10',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-1',
    title: 'Báo cáo năng lực Q1/2025',
    reportType: 'quarterly_competency',
    periodStart: '2025-01-01',
    periodEnd: '2025-03-31',
    createdAt: '2025-04-15T09:30:00Z',
    fileType: 'PDF',
    size: '1.8MB',
  },
  {
    id: 'rpt-11',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-1',
    title: 'Tổng kết hoạt động tháng 3',
    reportType: 'monthly_activities',
    periodStart: '2026-03-01',
    periodEnd: '2026-03-31',
    createdAt: '2026-04-02T08:00:00Z',
    fileType: 'XLSX',
    size: '650KB',
  },
  {
    id: 'rpt-12',
    schoolId: MOCK_SCHOOL_ID,
    generatedByUserId: 'user-admin-2',
    title: 'Phân tích huy hiệu theo khối 2025-2026',
    reportType: 'badges_by_grade',
    periodStart: '2025-09-01',
    periodEnd: '2026-02-15',
    createdAt: '2026-02-20T14:00:00Z',
    fileType: 'XLSX',
    size: '870KB',
  },
];

// =====================================================
// PUBLIC API (mirrors backend endpoints)
// =====================================================

/**
 * GET /school/info
 */
export function getSchoolInfo(): School {
  return SCHOOL_INFO;
}

/**
 * GET /school/overview
 */
export function getSchoolOverview(): SchoolOverview {
  return {
    totalStudents: STUDENTS.length,
    totalTeachers: TEACHERS.length,
    activeActivities: ACTIVITIES.filter((a) => a.status === 'open').length,
    totalBadges: 532,
    studentsByGrade: [
      { grade: 10, count: STUDENTS.filter((s) => CLASS_DEFS.find((c) => c.id === s.classId)?.grade === 10).length },
      { grade: 11, count: STUDENTS.filter((s) => CLASS_DEFS.find((c) => c.id === s.classId)?.grade === 11).length },
      { grade: 12, count: STUDENTS.filter((s) => CLASS_DEFS.find((c) => c.id === s.classId)?.grade === 12).length },
    ],
    recentActivities: ACTIVITIES.filter((a) => a.status === 'open').slice(0, 6),
  };
}

/**
 * GET /school/analytics
 */
export function getSchoolAnalytics(): SchoolAnalytics {
  return {
    talentDistribution: [
      { category: 'Kỹ thuật', count: 312 },
      { category: 'Học thuật', count: 425 },
      { category: 'Kinh doanh', count: 178 },
      { category: 'Nghệ thuật', count: 198 },
      { category: 'Thể thao', count: 135 },
    ],
    skillRanking: [
      { skill: 'Tin học', averageScore: 85 },
      { skill: 'Toán', averageScore: 78 },
      { skill: 'Vật lý', averageScore: 72 },
      { skill: 'Ngoại ngữ', averageScore: 88 },
      { skill: 'Nghệ thuật', averageScore: 65 },
      { skill: 'Thể thao', averageScore: 70 },
    ],
    gradeComparison: [
      { grade: 10, totalHours: 1820, averageScore: 76 },
      { grade: 11, totalHours: 2180, averageScore: 82 },
      { grade: 12, totalHours: 2340, averageScore: 88 },
    ],
    topClasses: [
      { className: '12A1', specialty: 'Chuyên Tin', score: 94 },
      { className: '11A2', specialty: 'Chuyên Lý', score: 92 },
      { className: '12B3', specialty: 'Chuyên Hoá', score: 90 },
      { className: '11A1', specialty: 'Chuyên Toán', score: 88 },
      { className: '10C1', specialty: 'Chuyên Anh', score: 86 },
    ],
  };
}

/**
 * GET /school/reports
 */
export function getReports(): ReportExt[] {
  return REPORTS;
}

/**
 * GET /school/classes
 */
export function getClasses(): ClassWithStudents[] {
  return CLASS_DEFS.map((c) => ({
    id: c.id,
    schoolId: MOCK_SCHOOL_ID,
    name: c.name,
    grade: c.grade,
    studentCount: c.studentCount,
    specialty: c.specialty,
  }));
}

/**
 * GET /school/classes/:id/students
 */
export function getClassStudents(classId: string): StudentProfile[] {
  const students = STUDENTS.filter((s) => s.classId === classId).slice(0, 50).map((s) => ({
    id: s.id,
    userId: s.userId,
    classId: s.classId,
    dateOfBirth: s.dateOfBirth,
    phone: s.phone,
    studyStatus: s.studyStatus,
  }));
  return students;
}

/**
 * GET /school/classes/:id (chi tiết class với student preview)
 */
export function getClassDetail(classId: string): ClassWithStudents | null {
  const def = CLASS_DEFS.find((c) => c.id === classId);
  if (!def) return null;
  return {
    id: def.id,
    schoolId: MOCK_SCHOOL_ID,
    name: def.name,
    grade: def.grade,
    studentCount: def.studentCount,
    students: getClassStudents(classId),
  };
}

/**
 * Lấy full student object (kèm fullName + avgScore) cho class detail
 */
export function getClassStudentsWithScore(classId: string): StudentDef[] {
  return STUDENTS.filter((s) => s.classId === classId);
}

// =====================================================
// ANALYTICS HELPERS (cho trang analytics với filter)
// =====================================================

/**
 * Lấy danh sách classes theo khối
 */
export function getClassesByGrade(grade: number): ClassWithStudents[] {
  return getClasses().filter((c) => c.grade === grade);
}

/**
 * Lấy skill radar data theo filter (grade, classId, period)
 */
export function getSkillRadar(grade?: number, classId?: string): { skill: string; score: number }[] {
  const baseSkills = getSchoolAnalytics().skillRanking;

  if (classId) {
    const cls = CLASS_DEFS.find((c) => c.id === classId);
    if (cls) {
      // Bias theo specialty
      return baseSkills.map((s) => {
        let bias = 0;
        if (cls.specialty.includes('Tin') && s.skill === 'Tin học') bias = 5;
        else if (cls.specialty.includes('Toán') && s.skill === 'Toán') bias = 8;
        else if (cls.specialty.includes('Lý') && s.skill === 'Vật lý') bias = 10;
        else if (cls.specialty.includes('Anh') && s.skill === 'Ngoại ngữ') bias = 8;
        return { skill: s.skill, score: Math.min(100, s.averageScore + bias) };
      });
    }
  }

  if (grade) {
    return baseSkills.map((s) => ({
      skill: s.skill,
      score: s.averageScore + (grade - 11) * 3,
    }));
  }

  return baseSkills.map((s) => ({ skill: s.skill, score: s.averageScore }));
}

/**
 * Lấy class comparison data
 */
export function getClassComparison(grade?: number): {
  className: string;
  specialty: string;
  students: number;
  avgScore: number;
  totalHours: number;
  topSkill: string;
}[] {
  const filtered = grade ? CLASS_DEFS.filter((c) => c.grade === grade) : CLASS_DEFS;

  return filtered.map((c) => {
    let topSkill = 'Tin học';
    if (c.specialty.includes('Toán')) topSkill = 'Toán';
    else if (c.specialty.includes('Lý')) topSkill = 'Vật lý';
    else if (c.specialty.includes('Hoá')) topSkill = 'Hoá học';
    else if (c.specialty.includes('Anh')) topSkill = 'Ngoại ngữ';
    else if (c.specialty.includes('Văn')) topSkill = 'Ngữ văn';
    return {
      className: c.name,
      specialty: c.specialty,
      students: c.studentCount,
      avgScore: c.avgScore,
      totalHours: c.totalHours,
      topSkill,
    };
  });
}

/**
 * Lấy grade bar chart data
 */
export function getGradeBarChart(grade?: number): { name: string; value: number }[] {
  const analytics = getSchoolAnalytics();
  const filtered = grade ? analytics.gradeComparison.filter((g) => g.grade === grade) : analytics.gradeComparison;
  return filtered.map((g) => ({
    name: `Khối ${g.grade}`,
    value: g.totalHours,
  }));
}
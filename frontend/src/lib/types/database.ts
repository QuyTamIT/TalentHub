export type UserRole = 'student' | 'teacher' | 'school' | 'enterprise';
export type UserStatus = 'active' | 'inactive' | 'pending' | 'suspended';

export interface User {
  id: string;
  email: string;
  fullName: string;
  roles: UserRole;
  status: UserStatus;
  createdAt: string;
}

export interface School {
  id: string;
  name: string;
  status: string;
}

export interface Class {
  id: string;
  schoolId: string;
  name: string;
  grade: number;
  homeroomTeacherId?: string;
}

export interface StudentProfile {
  id: string;
  userId: string;
  classId: string;
  dateOfBirth: string;
  phone: string;
  studyStatus: string;
}

export interface TeacherProfile {
  id: string;
  userId: string;
  schoolId: string;
  employeeCode: string;
  phone: string;
  subjectArea: string;
}

export interface Enterprise {
  id: string;
  userId: string;
  name: string;
  taxCode: string;
  industry: string;
  address: string;
  contactEmail: string;
  contactPhone: string;
  verified: boolean;
}

export type ActivityStatus = 'draft' | 'open' | 'closed' | 'cancelled' | 'completed';
export type ActivityCategory = 'technical' | 'academic' | 'business' | 'arts' | 'sports';

export interface Activity {
  id: string;
  schoolId: string;
  createdByTeacherId: string;
  title: string;
  category: ActivityCategory;
  startAt: string;
  endAt?: string;
  capacity: number;
  status: ActivityStatus;
}

export interface ActivityRegistration {
  id: string;
  activityId: string;
  studentId: string;
  status: 'registered' | 'checked_in' | 'cancelled' | 'completed';
}

export interface Badge {
  id: string;
  name: string;
  description: string;
  iconUrl?: string;
  category: string;
}

export interface StudentBadge {
  id: string;
  studentId: string;
  badgeId: string;
  awardedAt: string;
  sourceEvent: string;
}

export interface Skill {
  id: string;
  name: string;
  category: string;
}

export interface StudentSkill {
  id: string;
  studentId: string;
  skillId: string;
  level: number;
  verified: boolean;
}

export interface Assessment {
  id: string;
  studentId: string;
  evaluatorId: string;
  type: string;
  score: number;
  notes?: string;
  assessedAt: string;
}

export interface Report {
  id: string;
  schoolId: string;
  generatedByUserId: string;
  title: string;
  reportType: string;
  periodStart: string;
  periodEnd: string;
  payload?: string;
  createdAt: string;
}

export interface TalentTest {
  id: string;
  title: string;
  description: string;
  duration: number;
  category: string;
}

export interface TestAttempt {
  id: string;
  testId: string;
  studentId: string;
  startedAt: string;
  completedAt?: string;
  status: string;
}

export interface TestResult {
  id: string;
  attemptId: string;
  resultCode: string;
  summary: string;
  dimensionScores: Record<string, number>;
}

export interface Project {
  id: string;
  schoolId: string;
  name: string;
  description: string;
  startDate: string;
  endDate?: string;
  status: string;
}

export interface ProjectMember {
  id: string;
  projectId: string;
  studentId: string;
  role: string;
  joinedAt: string;
}

export interface ProjectSponsorship {
  id: string;
  enterpriseId: string;
  projectId: string;
  amount: number;
  status: string;
}

export interface InternshipPost {
  id: string;
  enterpriseId: string;
  title: string;
  description: string;
  startDate: string;
  endDate?: string;
  capacity: number;
  status: string;
}

export interface InternshipApplication {
  id: string;
  postId: string;
  studentId: string;
  status: string;
  appliedAt: string;
  coverLetter?: string;
}
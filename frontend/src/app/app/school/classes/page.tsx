'use client';

import { useState, useEffect, useMemo } from 'react';
import { Card } from '@/components/ui/Card';
import { StatCard } from '@/components/ui/StatCard';
import { SectionHeader } from '@/components/ui/SectionHeader';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Select';
import {
  Search,
  Users,
  ChevronRight,
  ChevronDown,
  ArrowLeft,
  Layers,
  GraduationCap,
  UserCheck,
  Filter,
} from 'lucide-react';
import { PageHeader } from '@/components/layout/PageHeader';
import { schoolApi, type ClassWithStudents } from '@/lib/api/school.api';
import { formatDate } from '@/lib/utils';

const STUDY_STATUS_LABELS: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'default' }> = {
  studying: { label: 'Đang học', variant: 'success' },
  graduated: { label: 'Đã tốt nghiệp', variant: 'default' },
  transferred: { label: 'Chuyển trường', variant: 'warning' },
  suspended: { label: 'Tạm dừng', variant: 'danger' },
};

export default function SchoolClassesPage() {
  const [classes, setClasses] = useState<ClassWithStudents[]>([]);
  const [loading, setLoading] = useState(true);
  const [expandedGrades, setExpandedGrades] = useState<Set<number>>(new Set([10, 11, 12]));
  const [selectedClassId, setSelectedClassId] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [gradeFilter, setGradeFilter] = useState<string>('all');
  const [classStudents, setClassStudents] = useState<
    {
      id: string;
      fullName: string;
      email: string;
      dateOfBirth: string;
      studyStatus: string;
      avgScore: number;
    }[]
  >([]);
  const [loadingStudents, setLoadingStudents] = useState(false);

  useEffect(() => {
    let active = true;
    schoolApi
      .getClasses()
      .then((data) => active && setClasses(data))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    if (!selectedClassId) {
      setClassStudents([]);
      return;
    }
    let active = true;
    setLoadingStudents(true);
    schoolApi
      .getClassStudentsWithScore(selectedClassId)
      .then((data) => active && setClassStudents(data as typeof classStudents))
      .finally(() => active && setLoadingStudents(false));
    return () => {
      active = false;
    };
  }, [selectedClassId]);

  const grades = useMemo(() => {
    const grouped: Record<number, ClassWithStudents[]> = {};
    classes.forEach((c) => {
      if (!grouped[c.grade]) grouped[c.grade] = [];
      grouped[c.grade].push(c);
    });
    return Object.entries(grouped)
      .map(([grade, list]) => ({
        id: Number(grade),
        name: `Khối ${grade}`,
        classes: list.sort((a, b) => a.name.localeCompare(b.name)),
      }))
      .sort((a, b) => a.id - b.id);
  }, [classes]);

  const filteredGrades = useMemo(
    () => (gradeFilter === 'all' ? grades : grades.filter((g) => g.id === Number(gradeFilter))),
    [gradeFilter, grades]
  );

  const selectedClass = useMemo(
    () => classes.find((c) => c.id === selectedClassId) ?? null,
    [classes, selectedClassId]
  );

  const filteredStudents = useMemo(
    () =>
      classStudents.filter((s) => s.fullName.toLowerCase().includes(search.toLowerCase())),
    [classStudents, search]
  );

  const toggleGrade = (gradeId: number) => {
    setExpandedGrades((prev) => {
      const next = new Set(prev);
      if (next.has(gradeId)) next.delete(gradeId);
      else next.add(gradeId);
      return next;
    });
  };

  const handleSelectClass = (classId: string) => {
    setSelectedClassId(classId);
    setSearch('');
  };

  const overview = useMemo(() => {
    return {
      grades: grades.length,
      classes: classes.length,
      students: classes.reduce((sum, c) => sum + c.studentCount, 0),
    };
  }, [grades, classes]);

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-12 w-64" />
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <Skeleton className="h-96" />
          <Skeleton className="h-96 lg:col-span-2" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        icon={Users}
        title="Lớp & Khối"
        description={`Quản lý ${overview.classes} lớp • ${overview.students.toLocaleString()} học sinh`}
        breadcrumbs={[{ label: 'Nhà trường', href: '/app/school' }, { label: 'Lớp & Khối' }]}
      />

      <div className="grid grid-cols-3 gap-4">
        <StatCard
          label="Khối"
          value={overview.grades}
          icon={<Layers size={20} />}
          color="primary"
          hint="khối lớp đang hoạt động"
        />
        <StatCard
          label="Lớp"
          value={overview.classes}
          icon={<GraduationCap size={20} />}
          color="secondary"
          hint="lớp đang hoạt động"
        />
        <StatCard
          label="Tổng học sinh"
          value={overview.students.toLocaleString()}
          icon={<UserCheck size={20} />}
          color="accent"
          hint="đang theo học"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card className="lg:col-span-1">
          <SectionHeader
            title="Danh sách khối"
            description="Click để xem các lớp trong khối"
            icon={<Filter size={18} />}
          />
          <Select
            label="Lọc theo khối"
            value={gradeFilter}
            onChange={(e) => setGradeFilter(e.target.value)}
            options={[
              { value: 'all', label: 'Tất cả khối' },
              { value: '10', label: 'Khối 10' },
              { value: '11', label: 'Khối 11' },
              { value: '12', label: 'Khối 12' },
            ]}
          />
          <div className="mt-4 space-y-2">
            {filteredGrades.map((grade) => (
              <div key={grade.id} className="border border-border rounded-sm overflow-hidden">
                <button
                  onClick={() => toggleGrade(grade.id)}
                  className="w-full flex items-center justify-between p-3 hover:bg-background transition-colors"
                  aria-expanded={expandedGrades.has(grade.id)}
                >
                  <div className="flex items-center gap-2">
                    {expandedGrades.has(grade.id) ? (
                      <ChevronDown size={16} />
                    ) : (
                      <ChevronRight size={16} />
                    )}
                    <Users size={16} className="text-primary" />
                    <span className="font-medium text-text-primary">{grade.name}</span>
                  </div>
                  <Badge variant="outline">{grade.classes.length} lớp</Badge>
                </button>
                {expandedGrades.has(grade.id) && (
                  <div className="border-t border-border bg-background/50">
                    {grade.classes.map((cls) => (
                      <button
                        key={cls.id}
                        onClick={() => handleSelectClass(cls.id)}
                        className={`w-full flex items-center justify-between px-3 py-2 text-sm hover:bg-background transition-colors ${
                          selectedClassId === cls.id ? 'bg-primary-light text-primary font-medium' : ''
                        }`}
                      >
                        <span className="truncate text-left">{cls.name}</span>
                        <span className="text-xs text-text-secondary flex-shrink-0 ml-2">
                          {cls.studentCount} HS
                        </span>
                      </button>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        </Card>

        <Card className="lg:col-span-2">
          <SectionHeader
            title={
              selectedClass
                ? `Lớp ${selectedClass.name} - ${selectedClass.specialty ?? 'Cơ bản'}`
                : 'Chọn lớp để xem chi tiết'
            }
            description={
              selectedClass
                ? `Sĩ số: ${selectedClass.studentCount} học sinh • Khối ${selectedClass.grade}`
                : 'Chọn một lớp từ danh sách bên trái'
            }
            icon={<Users size={18} />}
            action={
              selectedClass && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setSelectedClassId(null)}
                  aria-label="Quay lại"
                >
                  <ArrowLeft size={14} className="mr-1" /> Quay lại
                </Button>
              )
            }
          />

          {selectedClass ? (
            <>
              <div className="mb-4">
                <Input
                  placeholder="Tìm học sinh theo tên..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  startIcon={<Search size={16} />}
                />
              </div>

              {loadingStudents ? (
                <div className="space-y-2">
                  {Array.from({ length: 5 }).map((_, i) => (
                    <Skeleton key={i} className="h-10" />
                  ))}
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-12">STT</TableHead>
                      <TableHead>Họ tên</TableHead>
                      <TableHead className="hidden md:table-cell">Ngày sinh</TableHead>
                      <TableHead>Trạng thái</TableHead>
                      <TableHead className="text-right">Điểm TB</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredStudents.length > 0 ? (
                      filteredStudents.map((student, idx) => {
                        const statusInfo = STUDY_STATUS_LABELS[student.studyStatus] ?? {
                          label: student.studyStatus,
                          variant: 'default' as const,
                        };
                        return (
                          <TableRow key={student.id}>
                            <TableCell>{idx + 1}</TableCell>
                            <TableCell className="font-medium">{student.fullName}</TableCell>
                            <TableCell className="hidden md:table-cell">
                              {formatDate(student.dateOfBirth)}
                            </TableCell>
                            <TableCell>
                              <Badge variant={statusInfo.variant}>{statusInfo.label}</Badge>
                            </TableCell>
                            <TableCell className="text-right">
                              <Badge
                                variant={
                                  student.avgScore >= 90
                                    ? 'success'
                                    : student.avgScore >= 80
                                      ? 'primary'
                                      : 'warning'
                                }
                              >
                                {student.avgScore}
                              </Badge>
                            </TableCell>
                          </TableRow>
                        );
                      })
                    ) : (
                      <TableRow>
                        <TableCell colSpan={5} className="text-center text-text-secondary py-8">
                          Không tìm thấy học sinh nào
                        </TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              )}
            </>
          ) : (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <Users size={48} className="text-text-secondary mb-3" />
              <p className="text-sm text-text-secondary">
                Chọn một lớp từ danh sách bên trái để xem chi tiết
              </p>
            </div>
          )}
        </Card>
      </div>
    </div>
  );
}

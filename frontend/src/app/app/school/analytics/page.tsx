'use client';

import { useState, useEffect, useMemo } from 'react';
import { Card } from '@/components/ui/Card';
import { StatCard } from '@/components/ui/StatCard';
import { SectionHeader } from '@/components/ui/SectionHeader';
import { Select } from '@/components/ui/Select';
import { StatBarChart, SkillRadarChart } from '@/components/charts/Charts';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { PageHeader } from '@/components/layout/PageHeader';
import { Skeleton } from '@/components/ui/Select';
import { BarChart3, TrendingUp, Award, Users, Filter, BarChart3 as BarIcon, Radar as RadarIcon, Table as TableIcon } from 'lucide-react';
import { schoolApi, type ClassWithStudents } from '@/lib/api/school.api';

export default function SchoolAnalyticsPage() {
  const [grade, setGrade] = useState<string>('all');
  const [classId, setClassId] = useState<string>('all');
  const [period, setPeriod] = useState<string>('2026');
  const [loading, setLoading] = useState(true);

  const [classes, setClasses] = useState<ClassWithStudents[]>([]);
  const [analytics, setAnalytics] = useState<Awaited<ReturnType<typeof schoolApi.getAnalytics>> | null>(null);
  const [skillRadar, setSkillRadar] = useState<{ skill: string; score: number }[]>([]);
  const [classComparison, setClassComparison] = useState<
    { className: string; specialty: string; students: number; avgScore: number; totalHours: number; topSkill: string }[]
  >([]);
  const [gradeBar, setGradeBar] = useState<{ name: string; value: number }[]>([]);

  useEffect(() => {
    let active = true;
    Promise.all([schoolApi.getClasses(), schoolApi.getAnalytics()])
      .then(([c, a]) => {
        if (!active) return;
        setClasses(c);
        setAnalytics(a);
      })
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    let active = true;
    const g = grade === 'all' ? undefined : Number(grade);
    const cId = classId === 'all' ? undefined : classId;
    Promise.all([
      Promise.resolve(schoolApi.getSkillRadar(g, cId)),
      Promise.resolve(schoolApi.getClassComparison(g)),
      Promise.resolve(schoolApi.getGradeBarChart(g).map((x) => ({ name: x.name, value: x.value }))),
    ]).then(([sk, cmp, gb]) => {
      if (!active) return;
      setSkillRadar(sk);
      setClassComparison(cmp);
      setGradeBar(gb);
    });
    return () => {
      active = false;
    };
  }, [grade, classId]);

  const classesByGrade = useMemo(() => {
    const g = grade === 'all' ? undefined : Number(grade);
    return g ? classes.filter((c) => c.grade === g) : classes;
  }, [grade, classes]);

  const summaryStats = useMemo(() => {
    const totalStudents = classComparison.reduce((s, c) => s + c.students, 0);
    const totalHours = classComparison.reduce((s, c) => s + c.totalHours, 0);
    const avgScore =
      classComparison.length > 0
        ? Math.round(classComparison.reduce((s, c) => s + c.avgScore, 0) / classComparison.length)
        : 0;
    return {
      totalStudents,
      totalHours,
      avgScore,
      classCount: classComparison.length,
    };
  }, [classComparison]);

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-12 w-64" />
        <Skeleton className="h-32" />
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Skeleton className="h-80" />
          <Skeleton className="h-80" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        icon={BarChart3}
        title="Phân tích năng lực"
        description="So sánh năng khiếu theo khối, lớp, nhóm ngành"
        breadcrumbs={[{ label: 'Nhà trường', href: '/app/school' }, { label: 'Phân tích năng lực' }]}
      />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Tổng học sinh"
          value={summaryStats.totalStudents.toLocaleString()}
          icon={<Users size={20} />}
          color="primary"
          hint="trong các lớp đang xem"
        />
        <StatCard
          label="Điểm TB"
          value={summaryStats.avgScore || '—'}
          icon={<TrendingUp size={20} />}
          color="accent"
          hint={summaryStats.avgScore >= 80 ? 'Xuất sắc' : 'Cần cải thiện'}
        />
        <StatCard
          label="Tổng giờ học"
          value={`${summaryStats.totalHours.toLocaleString()}h`}
          icon={<BarChart3 size={20} />}
          color="secondary"
          hint="tích lũy trong kỳ"
        />
        <StatCard
          label="Số lớp"
          value={summaryStats.classCount}
          icon={<Award size={20} />}
          color="warning"
          hint="đang phân tích"
        />
      </div>

      <Card>
        <SectionHeader
          title="Bộ lọc phân tích"
          description="Chọn khối, lớp hoặc khoảng thời gian để xem chi tiết"
          icon={<Filter size={18} />}
        />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Select
            label="Khối"
            value={grade}
            onChange={(e) => {
              setGrade(e.target.value);
              setClassId('all');
            }}
            options={[
              { value: 'all', label: 'Tất cả khối' },
              { value: '10', label: 'Khối 10' },
              { value: '11', label: 'Khối 11' },
              { value: '12', label: 'Khối 12' },
            ]}
          />
          <Select
            label="Lớp"
            value={classId}
            onChange={(e) => setClassId(e.target.value)}
            options={[
              { value: 'all', label: 'Tất cả lớp' },
              ...classesByGrade.map((c) => ({
                value: c.id,
                label: `${c.name} - ${c.specialty ?? 'Cơ bản'}`,
              })),
            ]}
          />
          <Select
            label="Khoảng thời gian"
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
            options={[
              { value: '2026', label: 'Năm học 2025-2026' },
              { value: '2025', label: 'Năm học 2024-2025' },
              { value: 'q2-2026', label: 'Q2/2026' },
              { value: 'q1-2026', label: 'Q1/2026' },
            ]}
          />
        </div>
      </Card>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <SectionHeader
            title="Tổng giờ học theo khối"
            description="So sánh tổng giờ hoạt động giữa các khối lớp"
            icon={<BarIcon size={18} />}
          />
          <StatBarChart data={gradeBar} color="#2563EB" />
        </Card>

        <Card>
          <SectionHeader
            title="Phổ năng lực"
            description={
              classId !== 'all'
                ? 'Điểm các kỹ năng theo lớp đã chọn'
                : grade !== 'all'
                  ? 'Điểm các kỹ năng theo khối đã chọn'
                  : 'Điểm các kỹ năng chính toàn trường'
            }
            icon={<RadarIcon size={18} />}
          />
          <SkillRadarChart data={skillRadar} />
        </Card>
      </div>

      <Card>
        <SectionHeader
          title="Bảng so sánh chi tiết theo lớp"
          description={
            grade === 'all' ? 'Tất cả các lớp trong trường' : `Các lớp thuộc khối ${grade}`
          }
          icon={<TableIcon size={18} />}
        />
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Lớp</TableHead>
              <TableHead>Chuyên ngành</TableHead>
              <TableHead className="text-right">Sĩ số</TableHead>
              <TableHead className="text-right">Điểm TB</TableHead>
              <TableHead className="text-right hidden md:table-cell">Tổng giờ</TableHead>
              <TableHead>Năng khiếu nổi bật</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {classComparison.map((cls) => (
              <TableRow key={cls.className}>
                <TableCell className="font-medium">{cls.className}</TableCell>
                <TableCell className="text-text-secondary">{cls.specialty}</TableCell>
                <TableCell className="text-right">{cls.students}</TableCell>
                <TableCell className="text-right">
                  <Badge variant={cls.avgScore >= 90 ? 'success' : 'primary'}>{cls.avgScore}</Badge>
                </TableCell>
                <TableCell className="text-right hidden md:table-cell">
                  {cls.totalHours.toLocaleString()}h
                </TableCell>
                <TableCell className="text-text-secondary">{cls.topSkill}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </Card>
    </div>
  );
}

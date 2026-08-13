'use client';

import { useState, useEffect, useMemo } from 'react';
import { Card, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { StatCard } from '@/components/ui/StatCard';
import { SectionHeader } from '@/components/ui/SectionHeader';
import {
  Users,
  GraduationCap,
  Calendar,
  Award,
  ChevronRight,
  TrendingUp,
  PieChart as PieIcon,
  BarChart3 as BarIcon,
  Trophy,
  Clock,
} from 'lucide-react';
import { DistributionPieChart, StatBarChart } from '@/components/charts/Charts';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Spinner, EmptyState, Skeleton } from '@/components/ui/Select';
import { schoolApi } from '@/lib/api/school.api';
import type { SchoolOverview, SchoolAnalytics } from '@/lib/api/school.api';
import { formatNumber } from '@/lib/utils';
import { PageHeader } from '@/components/layout/PageHeader';
import Link from 'next/link';

export default function SchoolDashboardPage() {
  const [overview, setOverview] = useState<SchoolOverview | null>(null);
  const [analytics, setAnalytics] = useState<SchoolAnalytics | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    const fetchData = async () => {
      try {
        const [ov, an] = await Promise.all([schoolApi.getOverview(), schoolApi.getAnalytics()]);
        if (active) {
          setOverview(ov);
          setAnalytics(an);
        }
      } finally {
        if (active) setLoading(false);
      }
    };
    fetchData();
    return () => {
      active = false;
    };
  }, []);

  const stats = useMemo(
    () => [
      {
        label: 'Tổng học sinh',
        value: formatNumber(overview?.totalStudents ?? 0),
        icon: <Users size={20} />,
        color: 'primary' as const,
        trend: 4.2,
        trendLabel: 'so với học kỳ trước',
        href: '/app/school/classes',
      },
      {
        label: 'Tổng giáo viên',
        value: formatNumber(overview?.totalTeachers ?? 0),
        icon: <GraduationCap size={20} />,
        color: 'secondary' as const,
        trend: 1.5,
        trendLabel: 'so với năm ngoái',
        href: '/app/school/analytics',
      },
      {
        label: 'Hoạt động đang diễn ra',
        value: formatNumber(overview?.activeActivities ?? 0),
        icon: <Calendar size={20} />,
        color: 'accent' as const,
        trend: 12,
        trendLabel: 'tăng trong tháng này',
        href: '/app/school/analytics',
      },
      {
        label: 'Huy hiệu đã cấp',
        value: formatNumber(overview?.totalBadges ?? 0),
        icon: <Award size={20} />,
        color: 'warning' as const,
        trend: 8.6,
        trendLabel: 'so với học kỳ trước',
        href: '/app/school/reports',
      },
    ],
    [overview]
  );

  const talentData = useMemo(
    () =>
      analytics?.talentDistribution.map((t) => ({
        name: t.category,
        value: t.count,
      })) ?? [],
    [analytics]
  );

  const gradeHoursData = useMemo(
    () =>
      analytics?.gradeComparison.map((g) => ({
        name: `Khối ${g.grade}`,
        value: g.totalHours,
      })) ?? [],
    [analytics]
  );

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-12 w-64" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-28" />
          ))}
        </div>
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
        title="Tổng quan Nhà trường"
        description="Dashboard chính, tổng quan năng lực toàn trường THPT Nguyễn Du"
        breadcrumbs={[{ label: 'Nhà trường', href: '/app/school' }, { label: 'Tổng quan' }]}
      />

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((stat) => (
          <Link key={stat.label} href={stat.href} className="block group">
            <StatCard
              label={stat.label}
              value={stat.value}
              icon={stat.icon}
              color={stat.color}
              trend={stat.trend}
              trendLabel={stat.trendLabel}
              className="group-hover:shadow-md transition-shadow"
            />
          </Link>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <SectionHeader
            title="Phân bổ năng khiếu"
            description="Số học sinh theo từng lĩnh vực năng khiếu"
            icon={<PieIcon size={18} />}
          />
          {talentData.length > 0 ? (
            <DistributionPieChart data={talentData} />
          ) : (
            <EmptyState title="Chưa có dữ liệu" />
          )}
        </Card>

        <Card>
          <SectionHeader
            title="Tổng giờ học theo khối"
            description="So sánh tổng giờ hoạt động của các khối lớp"
            icon={<BarIcon size={18} />}
          />
          {gradeHoursData.length > 0 ? (
            <StatBarChart data={gradeHoursData} color="#F97316" />
          ) : (
            <EmptyState title="Chưa có dữ liệu" />
          )}
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <SectionHeader
            title="Top 5 lớp xuất sắc"
            description="Xếp hạng các lớp có điểm tổng kết cao nhất"
            icon={<Trophy size={18} />}
            action={
              <Link
                href="/app/school/analytics"
                className="text-xs text-primary hover:underline flex items-center gap-1"
              >
                Xem chi tiết <ChevronRight size={12} />
              </Link>
            }
          />
          {analytics?.topClasses && analytics.topClasses.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-12">Hạng</TableHead>
                  <TableHead>Lớp</TableHead>
                  <TableHead className="hidden sm:table-cell">Chuyên ngành</TableHead>
                  <TableHead className="text-right">Điểm</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {analytics.topClasses.map((cls, idx) => (
                  <TableRow key={cls.className}>
                    <TableCell>
                      <span className="font-bold text-text-primary">#{idx + 1}</span>
                    </TableCell>
                    <TableCell className="font-medium">{cls.className}</TableCell>
                    <TableCell className="hidden sm:table-cell text-text-secondary">
                      {cls.specialty}
                    </TableCell>
                    <TableCell className="text-right">
                      <Badge variant={cls.score >= 90 ? 'success' : 'primary'}>{cls.score}</Badge>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <EmptyState title="Chưa có dữ liệu" />
          )}
        </Card>

        <Card>
          <SectionHeader
            title="Hoạt động sắp tới"
            description="Các hoạt động đang mở đăng ký"
            icon={<Clock size={18} />}
            action={
              <Link
                href="/app/school/analytics"
                className="text-xs text-primary hover:underline flex items-center gap-1"
              >
                Tất cả <ChevronRight size={12} />
              </Link>
            }
          />
          {overview?.recentActivities && overview.recentActivities.length > 0 ? (
            <div className="space-y-3">
              {overview.recentActivities.map((activity) => (
                <div
                  key={activity.id}
                  className="flex items-start justify-between p-3 border border-border rounded-sm hover:bg-background transition-colors"
                >
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-text-primary truncate">{activity.title}</p>
                    <div className="flex items-center gap-2 mt-1 text-xs text-text-secondary">
                      <span>{activity.category}</span>
                      <span>•</span>
                      <span>Sức chứa: {activity.capacity}</span>
                    </div>
                  </div>
                  <Badge variant="success">Mở</Badge>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState title="Chưa có hoạt động" description="Chưa có hoạt động nào đang mở" />
          )}
        </Card>
      </div>
    </div>
  );
}

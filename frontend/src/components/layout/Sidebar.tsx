'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useState } from 'react';
import {
  LayoutDashboard,
  BarChart3,
  FileText,
  Users,
  Calendar,
  Award,
  Briefcase,
  ClipboardList,
  Building2,
  GraduationCap,
  ChevronLeft,
  Menu,
  Bell,
  Search,
  type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type Role = 'school' | 'student' | 'teacher' | 'enterprise';

type NavItem = {
  label: string;
  href: string;
  icon: LucideIcon;
};

const navByRole: Record<Role, NavItem[]> = {
  school: [
    { label: 'Tổng quan', href: '/app/school', icon: LayoutDashboard },
    { label: 'Phân tích năng lực', href: '/app/school/analytics', icon: BarChart3 },
    { label: 'Báo cáo', href: '/app/school/reports', icon: FileText },
    { label: 'Lớp & Khối', href: '/app/school/classes', icon: Users },
  ],
  student: [
    { label: 'Tổng quan', href: '/app/student', icon: LayoutDashboard },
    { label: 'Hoạt động', href: '/app/student/activities', icon: Calendar },
    { label: 'Huy hiệu', href: '/app/student/badges', icon: Award },
    { label: 'Trắc nghiệm', href: '/app/student/tests', icon: ClipboardList },
    { label: 'Dự án', href: '/app/student/projects', icon: Briefcase },
    { label: 'Thực tập', href: '/app/student/internships', icon: Building2 },
  ],
  teacher: [
    { label: 'Tổng quan', href: '/app/teacher', icon: LayoutDashboard },
    { label: 'Hoạt động', href: '/app/teacher/activities', icon: Calendar },
    { label: 'Đánh giá', href: '/app/teacher/assessments', icon: ClipboardList },
    { label: 'Lớp phụ trách', href: '/app/teacher/classes', icon: Users },
  ],
  enterprise: [
    { label: 'Tổng quan', href: '/app/enterprise', icon: LayoutDashboard },
    { label: 'Bài đăng tuyển', href: '/app/enterprise/posts', icon: Briefcase },
    { label: 'Đơn ứng tuyển', href: '/app/enterprise/applications', icon: FileText },
    { label: 'Dự án tài trợ', href: '/app/enterprise/projects', icon: Award },
  ],
};

const roleLabels: Record<Role, { name: string; icon: LucideIcon; color: string }> = {
  school: { name: 'Nhà trường', icon: GraduationCap, color: 'text-primary' },
  student: { name: 'Học sinh', icon: GraduationCap, color: 'text-secondary' },
  teacher: { name: 'Giáo viên', icon: Users, color: 'text-accent' },
  enterprise: { name: 'Doanh nghiệp', icon: Building2, color: 'text-warning' },
};

export function Sidebar() {
  const pathname = usePathname();
  const [collapsed, setCollapsed] = useState(false);

  const role: Role = (pathname?.split('/')[2] as Role) || 'school';
  const navItems = navByRole[role];
  const roleMeta = roleLabels[role];
  const RoleIcon = roleMeta.icon;

  return (
    <aside
      className={cn(
        'h-screen sticky top-0 bg-surface border-r border-border transition-all duration-300 flex flex-col',
        collapsed ? 'w-16' : 'w-64'
      )}
    >
      <div className="p-4 border-b border-border flex items-center justify-between">
        {!collapsed && (
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 bg-primary rounded-md flex items-center justify-center text-white font-bold">
              T
            </div>
            <span className="font-bold text-text-primary">TalentHub</span>
          </div>
        )}
        <button
          onClick={() => setCollapsed(!collapsed)}
          className="p-1.5 rounded hover:bg-background text-text-secondary"
          aria-label="Toggle sidebar"
        >
          {collapsed ? <Menu size={18} /> : <ChevronLeft size={18} />}
        </button>
      </div>

      {!collapsed && (
        <div className="px-4 py-3 border-b border-border">
          <div className="flex items-center gap-2">
            <RoleIcon size={16} className={roleMeta.color} />
            <span className={cn('text-sm font-semibold', roleMeta.color)}>{roleMeta.name}</span>
          </div>
        </div>
      )}

      <nav className="flex-1 py-4 overflow-y-auto">
        <ul className="space-y-1 px-2">
          {navItems.map((item) => {
            const Icon = item.icon;
            const isActive = pathname === item.href;
            return (
              <li key={item.href}>
                <Link
                  href={item.href}
                  className={cn(
                    'flex items-center gap-3 px-3 py-2 rounded-sm text-sm transition-colors',
                    isActive
                      ? 'bg-primary-light text-primary font-semibold'
                      : 'text-text-secondary hover:bg-background hover:text-text-primary',
                    collapsed && 'justify-center'
                  )}
                  title={collapsed ? item.label : undefined}
                >
                  <Icon size={18} />
                  {!collapsed && <span>{item.label}</span>}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>
    </aside>
  );
}

export function Header({ userName = 'Người dùng' }: { userName?: string }) {
  const [searchOpen, setSearchOpen] = useState(false);

  return (
    <header className="h-16 bg-surface border-b border-border px-6 flex items-center justify-between sticky top-0 z-10">
      <div className="flex items-center gap-4 flex-1 max-w-md">
        <div className="relative w-full">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
          <input
            type="text"
            placeholder="Tìm kiếm..."
            className="w-full pl-9 pr-3 py-2 bg-background border border-border rounded-sm text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
          />
        </div>
      </div>

      <div className="flex items-center gap-3">
        <button
          className="relative p-2 rounded-sm hover:bg-background text-text-secondary"
          aria-label="Notifications"
        >
          <Bell size={18} />
          <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-danger rounded-full"></span>
        </button>

        <div className="flex items-center gap-2 pl-3 border-l border-border">
          <div className="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-xs font-semibold">
            {userName.slice(0, 2).toUpperCase()}
          </div>
          <div className="hidden sm:block">
            <p className="text-sm font-medium text-text-primary">{userName}</p>
            <p className="text-xs text-text-secondary">Đã đăng nhập</p>
          </div>
        </div>
      </div>
    </header>
  );
}
'use client';

import { useState, useEffect, useMemo } from 'react';
import { Card } from '@/components/ui/Card';
import { StatCard } from '@/components/ui/StatCard';
import { SectionHeader } from '@/components/ui/SectionHeader';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { EmptyState, Skeleton } from '@/components/ui/Select';
import {
  Download,
  Eye,
  FileText,
  FileSpreadsheet,
  FileDown,
  Calendar,
  FileType,
  Filter,
  Files,
  FileIcon,
  CalendarDays,
  Plus,
} from 'lucide-react';
import { formatDate } from '@/lib/utils';
import { PageHeader } from '@/components/layout/PageHeader';
import { schoolApi, type ReportWithMeta } from '@/lib/api/school.api';

const REPORT_TYPE_LABELS: Record<string, string> = {
  quarterly_competency: 'Báo cáo năng lực quý',
  monthly_activities: 'Tổng kết hoạt động tháng',
  grade_analysis: 'Phân tích theo khối',
  badges_summary: 'Tổng kết huy hiệu',
  badges_by_grade: 'Huy hiệu theo khối',
  extracurricular_stats: 'Thống kê ngoại khóa',
};

export default function SchoolReportsPage() {
  const [year, setYear] = useState<string>('all');
  const [type, setType] = useState<string>('all');
  const [reports, setReports] = useState<ReportWithMeta[]>([]);
  const [loading, setLoading] = useState(true);
  const [previewReport, setPreviewReport] = useState<ReportWithMeta | null>(null);

  useEffect(() => {
    let active = true;
    schoolApi
      .getReports()
      .then((data) => active && setReports(data))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, []);

  const filtered = useMemo(() => {
    return reports.filter((r) => {
      if (year !== 'all' && !r.createdAt.startsWith(year)) return false;
      if (type !== 'all' && r.fileType !== type) return false;
      return true;
    });
  }, [reports, year, type]);

  const stats = useMemo(() => {
    return {
      total: reports.length,
      pdf: reports.filter((r) => r.fileType === 'PDF').length,
      xlsx: reports.filter((r) => r.fileType === 'XLSX').length,
      thisYear: reports.filter((r) => r.createdAt.startsWith('2026')).length,
    };
  }, [reports]);

  const handleDownload = (report: ReportWithMeta) => {
    const content = `Báo cáo: ${report.title}\nLoại: ${report.reportType}\nTạo: ${formatDate(report.createdAt, 'long')}\nKỳ: ${formatDate(report.periodStart)} - ${formatDate(report.periodEnd)}\n\nĐây là file mô phỏng từ mock service.\nKhi backend Laravel sẵn sàng, file sẽ được tải từ /api/school/reports/${report.id}/download`;
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${report.title}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-6">
      <PageHeader
        icon={FileText}
        title="Báo cáo"
        description="Xem và tải các báo cáo định kỳ"
        breadcrumbs={[{ label: 'Nhà trường', href: '/app/school' }, { label: 'Báo cáo' }]}
        actions={
          <Button>
            <Plus size={16} className="mr-2" />
            Tạo báo cáo mới
          </Button>
        }
      />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Tổng báo cáo"
          value={stats.total}
          icon={<Files size={20} />}
          color="primary"
          hint="tất cả thời gian"
        />
        <StatCard
          label="PDF"
          value={stats.pdf}
          icon={<FileText size={20} />}
          color="danger"
          hint="báo cáo dạng PDF"
        />
        <StatCard
          label="Excel"
          value={stats.xlsx}
          icon={<FileSpreadsheet size={20} />}
          color="success"
          hint="báo cáo dạng XLSX"
        />
        <StatCard
          label="Năm 2026"
          value={stats.thisYear}
          icon={<CalendarDays size={20} />}
          color="warning"
          hint="báo cáo 2026"
        />
      </div>

      <Card>
        <SectionHeader title="Bộ lọc" description="Lọc theo năm hoặc định dạng" icon={<Filter size={18} />} />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Select
            label="Năm"
            value={year}
            onChange={(e) => setYear(e.target.value)}
            options={[
              { value: 'all', label: 'Tất cả' },
              { value: '2026', label: '2026' },
              { value: '2025', label: '2025' },
              { value: '2024', label: '2024' },
            ]}
          />
          <Select
            label="Định dạng"
            value={type}
            onChange={(e) => setType(e.target.value)}
            options={[
              { value: 'all', label: 'Tất cả' },
              { value: 'PDF', label: 'PDF' },
              { value: 'XLSX', label: 'Excel (XLSX)' },
            ]}
          />
        </div>
      </Card>

      <Card>
        <SectionHeader
          title={`Danh sách báo cáo (${filtered.length})`}
          description="Báo cáo năng lực và hoạt động của nhà trường"
          icon={<FileIcon size={18} />}
        />

        {loading ? (
          <div className="space-y-3">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="h-12" />
            ))}
          </div>
        ) : filtered.length === 0 ? (
          <EmptyState
            icon={<FileText size={48} className="text-text-secondary" />}
            title="Không có báo cáo"
            description="Không có báo cáo nào khớp với bộ lọc hiện tại"
          />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-12 hidden sm:table-cell">STT</TableHead>
                <TableHead>Tên báo cáo</TableHead>
                <TableHead className="hidden md:table-cell">Ngày tạo</TableHead>
                <TableHead>Định dạng</TableHead>
                <TableHead className="hidden sm:table-cell">Dung lượng</TableHead>
                <TableHead className="text-right">Thao tác</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filtered.map((report, idx) => (
                <TableRow key={report.id}>
                  <TableCell className="hidden sm:table-cell">{idx + 1}</TableCell>
                  <TableCell className="font-medium">
                    <div className="flex items-center gap-2">
                      {report.fileType === 'PDF' ? (
                        <FileText size={16} className="text-danger flex-shrink-0" />
                      ) : (
                        <FileSpreadsheet size={16} className="text-success flex-shrink-0" />
                      )}
                      <span className="truncate">{report.title}</span>
                    </div>
                  </TableCell>
                  <TableCell className="hidden md:table-cell">{formatDate(report.createdAt)}</TableCell>
                  <TableCell>
                    <Badge variant={report.fileType === 'PDF' ? 'danger' : 'success'}>
                      {report.fileType}
                    </Badge>
                  </TableCell>
                  <TableCell className="hidden sm:table-cell">{report.size}</TableCell>
                  <TableCell className="text-right">
                    <div className="flex items-center justify-end gap-2">
                      <Button variant="ghost" size="sm" onClick={() => setPreviewReport(report)}>
                        <Eye size={14} className="mr-1" /> Xem
                      </Button>
                      <Button variant="outline" size="sm" onClick={() => handleDownload(report)}>
                        <Download size={14} className="mr-1" /> Tải
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>

      <Modal
        open={!!previewReport}
        onClose={() => setPreviewReport(null)}
        title={previewReport?.title ?? 'Xem trước báo cáo'}
        size="lg"
      >
        {previewReport && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div className="flex items-center gap-2">
                <Calendar size={14} className="text-text-secondary" />
                <span className="text-text-secondary">Tạo:</span>
                <span className="font-medium">{formatDate(previewReport.createdAt, 'long')}</span>
              </div>
              <div className="flex items-center gap-2">
                <FileType size={14} className="text-text-secondary" />
                <span className="text-text-secondary">Định dạng:</span>
                <Badge variant={previewReport.fileType === 'PDF' ? 'danger' : 'success'}>
                  {previewReport.fileType}
                </Badge>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-text-secondary">Kỳ:</span>
                <span className="font-medium">
                  {formatDate(previewReport.periodStart)} - {formatDate(previewReport.periodEnd)}
                </span>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-text-secondary">Loại:</span>
                <span className="font-medium">
                  {REPORT_TYPE_LABELS[previewReport.reportType] ?? previewReport.reportType}
                </span>
              </div>
            </div>

            <div className="border-t border-border pt-4">
              <div className="bg-background border border-border rounded-sm p-6 text-center">
                {previewReport.fileType === 'PDF' ? (
                  <FileText size={48} className="text-danger mx-auto mb-3" />
                ) : (
                  <FileSpreadsheet size={48} className="text-success mx-auto mb-3" />
                )}
                <p className="text-sm text-text-secondary mb-1">Preview báo cáo</p>
                <p className="text-xs text-text-secondary">
                  File {previewReport.fileType} ({previewReport.size}) - Bấm Tải để lưu về máy
                </p>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-border">
              <Button variant="outline" onClick={() => setPreviewReport(null)}>
                Đóng
              </Button>
              <Button onClick={() => handleDownload(previewReport)}>
                <FileDown size={14} className="mr-2" /> Tải xuống
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}

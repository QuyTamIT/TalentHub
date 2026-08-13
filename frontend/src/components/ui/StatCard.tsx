import { HTMLAttributes, ReactNode } from 'react';
import { Card } from './Card';
import { cn } from '@/lib/utils';
import { ArrowDown, ArrowUp, Minus } from 'lucide-react';

export type StatCardColor = 'primary' | 'secondary' | 'accent' | 'warning' | 'danger' | 'success';

export interface StatCardProps extends HTMLAttributes<HTMLDivElement> {
  label: string;
  value: number | string;
  icon?: ReactNode;
  color?: StatCardColor;
  trend?: number;
  trendLabel?: string;
  hint?: string;
  loading?: boolean;
}

const COLOR_MAP: Record<StatCardColor, { iconBg: string; iconFg: string; accent: string }> = {
  primary: {
    iconBg: 'bg-primary-light',
    iconFg: 'text-primary',
    accent: 'bg-primary',
  },
  secondary: {
    iconBg: 'bg-secondary-light',
    iconFg: 'text-secondary',
    accent: 'bg-secondary',
  },
  accent: {
    iconBg: 'bg-green-50',
    iconFg: 'text-accent',
    accent: 'bg-accent',
  },
  warning: {
    iconBg: 'bg-yellow-50',
    iconFg: 'text-warning',
    accent: 'bg-warning',
  },
  danger: {
    iconBg: 'bg-red-50',
    iconFg: 'text-danger',
    accent: 'bg-danger',
  },
  success: {
    iconBg: 'bg-green-50',
    iconFg: 'text-success',
    accent: 'bg-success',
  },
};

export function StatCard({
  label,
  value,
  icon,
  color = 'primary',
  trend,
  trendLabel,
  hint,
  loading,
  className,
  ...props
}: StatCardProps) {
  const tokens = COLOR_MAP[color];

  const trendNode = (() => {
    if (trend === undefined || trend === null) return null;
    const isUp = trend > 0;
    const isFlat = trend === 0;
    const Icon = isFlat ? Minus : isUp ? ArrowUp : ArrowDown;
    const colorClass = isFlat
      ? 'text-text-secondary bg-background'
      : isUp
        ? 'text-success bg-green-50'
        : 'text-danger bg-red-50';
    return (
      <span
        className={cn('inline-flex items-center gap-1 text-xs font-medium px-1.5 py-0.5 rounded', colorClass)}
      >
        <Icon size={12} />
        {Math.abs(trend)}%
      </span>
    );
  })();

  return (
    <Card className={cn('relative overflow-hidden p-5', className)} {...props}>
      <div className={cn('absolute left-0 top-0 h-full w-1', tokens.accent)} aria-hidden />
      <div className="flex items-start justify-between gap-3 pl-1">
        <div className="min-w-0 flex-1">
          <p className="text-xs font-medium text-text-secondary uppercase tracking-wider">{label}</p>
          {loading ? (
            <div className="mt-2 h-8 w-24 rounded bg-background animate-pulse" />
          ) : (
            <p className="text-2xl font-bold text-text-primary mt-1.5 leading-none">{value}</p>
          )}
          {(trendNode || hint) && (
            <div className="mt-2 flex items-center gap-2 flex-wrap">
              {trendNode}
              {trendLabel && <span className="text-xs text-text-secondary">{trendLabel}</span>}
              {!trendLabel && hint && <span className="text-xs text-text-secondary">{hint}</span>}
            </div>
          )}
        </div>
        {icon && (
          <div
            className={cn(
              'w-11 h-11 rounded-md flex items-center justify-center flex-shrink-0',
              tokens.iconBg,
              tokens.iconFg
            )}
          >
            {icon}
          </div>
        )}
      </div>
    </Card>
  );
}

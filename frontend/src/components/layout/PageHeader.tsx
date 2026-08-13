import Link from 'next/link';
import { ChevronRight, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

export interface PageHeaderProps {
  title: string;
  description?: string;
  breadcrumbs?: { label: string; href?: string }[];
  icon?: LucideIcon;
  actions?: ReactNode;
  className?: string;
}

export function PageHeader({ title, description, breadcrumbs, icon: Icon, actions, className }: PageHeaderProps) {
  return (
    <div className={cn('space-y-2', className)}>
      {breadcrumbs && breadcrumbs.length > 0 && (
        <nav aria-label="Breadcrumb" className="flex items-center gap-1 text-xs text-text-secondary">
          {breadcrumbs.map((b, i) => {
            const isLast = i === breadcrumbs.length - 1;
            return (
              <span key={i} className="flex items-center gap-1">
                {b.href && !isLast ? (
                  <Link href={b.href} className="hover:text-primary transition-colors">
                    {b.label}
                  </Link>
                ) : (
                  <span className={cn(isLast && 'text-text-primary font-medium')}>{b.label}</span>
                )}
                {!isLast && <ChevronRight size={12} />}
              </span>
            );
          })}
        </nav>
      )}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div className="flex items-start gap-3">
          {Icon && (
            <div className="w-10 h-10 bg-primary-light text-primary rounded-md flex items-center justify-center flex-shrink-0">
              <Icon size={20} />
            </div>
          )}
          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-text-primary">{title}</h1>
            {description && <p className="text-sm text-text-secondary mt-1">{description}</p>}
          </div>
        </div>
        {actions && <div className="flex items-center gap-2 flex-shrink-0">{actions}</div>}
      </div>
    </div>
  );
}
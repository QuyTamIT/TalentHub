import { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export type BadgeVariant = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline';

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: BadgeVariant;
}

export function Badge({ className, variant = 'default', children, ...props }: BadgeProps) {
  const variants: Record<BadgeVariant, string> = {
    default: 'bg-background text-text-secondary border-border',
    primary: 'bg-primary-light text-primary border-primary/20',
    secondary: 'bg-secondary-light text-secondary border-secondary/20',
    success: 'bg-green-50 text-success border-success/20',
    warning: 'bg-yellow-50 text-warning border-warning/20',
    danger: 'bg-red-50 text-danger border-danger/20',
    outline: 'bg-transparent text-text-primary border-border',
  };

  return (
    <span
      className={cn(
        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
        variants[variant],
        className
      )}
      {...props}
    >
      {children}
    </span>
  );
}
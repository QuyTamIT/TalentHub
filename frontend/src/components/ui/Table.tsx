'use client';

import { TableHTMLAttributes, ThHTMLAttributes, TdHTMLAttributes, forwardRef, HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const Table = forwardRef<HTMLTableElement, TableHTMLAttributes<HTMLTableElement>>(
  ({ className, children, ...props }, ref) => (
    <div className="w-full overflow-x-auto">
      <table ref={ref} className={cn('w-full text-sm', className)} {...props}>
        {children}
      </table>
    </div>
  )
);
Table.displayName = 'Table';

export const TableHeader = ({ className, children, ...props }: HTMLAttributes<HTMLTableSectionElement>) => (
  <thead className={cn('bg-background border-b border-border', className)} {...props}>
    {children}
  </thead>
);

export const TableBody = ({ className, children, ...props }: HTMLAttributes<HTMLTableSectionElement>) => (
  <tbody className={cn('divide-y divide-border', className)} {...props}>
    {children}
  </tbody>
);

export const TableRow = ({ className, children, ...props }: HTMLAttributes<HTMLTableRowElement>) => (
  <tr className={cn('hover:bg-background transition-colors', className)} {...props}>
    {children}
  </tr>
);

export const TableHead = ({ className, children, ...props }: ThHTMLAttributes<HTMLTableCellElement>) => (
  <th
    className={cn(
      'px-6 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider',
      className
    )}
    {...props}
  >
    {children}
  </th>
);

export const TableCell = ({ className, children, ...props }: TdHTMLAttributes<HTMLTableCellElement>) => (
  <td className={cn('px-6 py-4 text-text-primary whitespace-nowrap', className)} {...props}>
    {children}
  </td>
);

export { Table };
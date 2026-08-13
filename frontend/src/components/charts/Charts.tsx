'use client';

import { BarChart, Bar, LineChart, Line, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, RadarChart, PolarGrid, PolarAngleAxis, PolarRadiusAxis, Radar } from 'recharts';

const COLORS = {
  primary: '#F97316',
  secondary: '#2563EB',
  success: '#16A34A',
  warning: '#F59E0B',
  danger: '#DC2626',
  accent: '#16A34A',
};

const PIE_COLORS = ['#F97316', '#2563EB', '#16A34A', '#F59E0B', '#DC2626', '#8B5CF6', '#EC4899'];

interface BarChartData {
  name: string;
  value: number;
  [key: string]: string | number;
}

export function StatBarChart({ data, dataKey = 'value', color = COLORS.primary, height = 300 }: { data: BarChartData[]; dataKey?: string; color?: string; height?: number }) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={data} margin={{ top: 5, right: 20, left: 0, bottom: 5 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
        <XAxis dataKey="name" tick={{ fontSize: 12, fill: '#64748B' }} />
        <YAxis tick={{ fontSize: 12, fill: '#64748B' }} />
        <Tooltip contentStyle={{ borderRadius: 8, border: '1px solid #E2E8F0' }} />
        <Bar dataKey={dataKey} fill={color} radius={[4, 4, 0, 0]} />
      </BarChart>
    </ResponsiveContainer>
  );
}

export function StatLineChart({ data, dataKey = 'value', color = COLORS.primary, height = 300 }: { data: BarChartData[]; dataKey?: string; color?: string; height?: number }) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <LineChart data={data} margin={{ top: 5, right: 20, left: 0, bottom: 5 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
        <XAxis dataKey="name" tick={{ fontSize: 12, fill: '#64748B' }} />
        <YAxis tick={{ fontSize: 12, fill: '#64748B' }} />
        <Tooltip contentStyle={{ borderRadius: 8, border: '1px solid #E2E8F0' }} />
        <Line type="monotone" dataKey={dataKey} stroke={color} strokeWidth={2} dot={{ r: 4 }} />
      </LineChart>
    </ResponsiveContainer>
  );
}

export function DistributionPieChart({ data, height = 300 }: { data: { name: string; value: number }[]; height?: number }) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <PieChart>
        <Pie
          data={data}
          cx="50%"
          cy="50%"
          labelLine={false}
          label={({ name, percent }) => `${name}: ${((percent ?? 0) * 100).toFixed(0)}%`}
          outerRadius={90}
          dataKey="value"
        >
          {data.map((_, idx) => (
            <Cell key={idx} fill={PIE_COLORS[idx % PIE_COLORS.length]} />
          ))}
        </Pie>
        <Tooltip contentStyle={{ borderRadius: 8, border: '1px solid #E2E8F0' }} />
        <Legend wrapperStyle={{ fontSize: 12 }} />
      </PieChart>
    </ResponsiveContainer>
  );
}

export function SkillRadarChart({ data, height = 300 }: { data: { skill: string; score: number }[]; height?: number }) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <RadarChart data={data}>
        <PolarGrid stroke="#E2E8F0" />
        <PolarAngleAxis dataKey="skill" tick={{ fontSize: 12, fill: '#64748B' }} />
        <PolarRadiusAxis tick={{ fontSize: 11, fill: '#64748B' }} />
        <Radar name="Điểm" dataKey="score" stroke={COLORS.primary} fill={COLORS.primary} fillOpacity={0.4} />
        <Tooltip contentStyle={{ borderRadius: 8, border: '1px solid #E2E8F0' }} />
      </RadarChart>
    </ResponsiveContainer>
  );
}
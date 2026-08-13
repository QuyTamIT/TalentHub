'use client';

import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { UserRole } from '@/lib/types/database';

interface AuthState {
  token: string | null;
  userId: string | null;
  fullName: string | null;
  email: string | null;
  role: UserRole | null;
  login: (data: { token: string; userId: string; fullName: string; email: string; role: UserRole }) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      userId: null,
      fullName: null,
      email: null,
      role: null,
      login: (data) => set(data),
      logout: () =>
        set({
          token: null,
          userId: null,
          fullName: null,
          email: null,
          role: null,
        }),
    }),
    { name: 'talenthub-auth' }
  )
);
<template>
  <aside class="flex w-64 shrink-0 flex-col bg-[#0F172A] text-white border-r border-slate-800 h-screen sticky top-0">
    <!-- Logo -->
    <div class="flex items-center gap-3 p-6">
      <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-violet-600 shadow-lg shadow-blue-500/20">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 text-white">
          <path fill-rule="evenodd" d="M12.516 2.17a.75.75 0 00-1.032 0 11.209 11.209 0 01-7.877 3.08.75.75 0 00-.722.515A12.74 12.74 0 002.25 9.75c0 5.942 4.064 10.933 9.563 12.348a.749.749 0 00.374 0c5.499-1.415 9.563-6.406 9.563-12.348 0-1.39-.223-2.73-.635-3.985a.75.75 0 00-.722-.516 11.209 11.209 0 01-7.877-3.08z" clip-rule="evenodd" />
        </svg>
      </div>
      <div class="flex flex-col">
        <span class="text-base font-bold tracking-tight leading-none">AEGIS MAILING</span>
        <span class="mt-1 text-[10px] font-medium uppercase tracking-widest text-slate-400">Premium B2B</span>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-4 py-4 custom-scrollbar">
      <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Menu Principal</p>
      <ul class="space-y-1">
        <SidebarItem
          v-for="item in navItems"
          :key="item.id"
          :item="item"
          :active="current === item.id"
        />
      </ul>

      <p class="mt-6 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Configuration</p>
      <ul class="space-y-1">
        <SidebarItem
          v-for="item in bottomItems"
          :key="item.id"
          :item="item"
          :active="current === item.id"
        />
      </ul>
    </nav>

    <!-- User card -->
    <div class="border-t border-slate-800 bg-slate-900/50 p-4">
      <div class="flex items-center gap-3 rounded-xl border border-slate-700/50 bg-slate-800/50 p-2">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-slate-600 bg-slate-700 text-sm font-bold text-white">
          {{ initials }}
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-bold text-white">{{ userName }}</p>
          <p class="text-[10px] font-medium text-slate-400">Administrateur</p>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SidebarItem from './SidebarItem.vue';

defineProps({
  current: { type: String, default: '' },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name || 'Utilisateur');
const initials = computed(() => {
  const name = userName.value;
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
});

const navItems = [
  { id: 'dashboard', label: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
  { id: 'contacts', label: 'Contacts', href: '/contacts', icon: 'contacts' },
  { id: 'organizations', label: 'Organisations', href: '/organizations', icon: 'organizations' },
  { id: 'mails', label: 'Mails', href: '/mails', icon: 'mail' },
  { id: 'templates', label: 'Modèles', href: '/templates', icon: 'template' },
  { id: 'campaigns', label: 'Campagnes', href: '/campaigns', icon: 'campaign' },
  { id: 'activity', label: 'Activité', href: '/activity', icon: 'activity' },
  { id: 'import-export', label: 'Import / Export', href: '/import-export', icon: 'import_export' },
];

const bottomItems = [
  { id: 'settings', label: 'Réglages', href: '/settings', icon: 'settings' },
  { id: 'users', label: 'Utilisateurs', href: '/users', icon: 'users' },
];
</script>

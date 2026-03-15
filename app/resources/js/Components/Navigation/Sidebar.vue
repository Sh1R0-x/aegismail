<template>
  <aside class="flex w-56 shrink-0 flex-col border-r border-gray-200 bg-white">
    <!-- Logo -->
    <div class="flex h-14 items-center border-b border-gray-200 px-4">
      <span class="text-base font-bold tracking-tight text-gray-900">AEGIS</span>
      <span class="ml-1 text-base font-light text-gray-500">Mailing</span>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-3 px-2">
      <ul class="space-y-0.5">
        <SidebarItem
          v-for="item in navItems"
          :key="item.id"
          :item="item"
          :active="current === item.id"
        />
      </ul>

      <div class="my-3 border-t border-gray-100" />

      <ul class="space-y-0.5">
        <SidebarItem
          v-for="item in bottomItems"
          :key="item.id"
          :item="item"
          :active="current === item.id"
        />
      </ul>
    </nav>

    <!-- Footer -->
    <div class="border-t border-gray-200 px-4 py-3">
      <p class="text-xs text-gray-400">AEGIS Mailing v1.0</p>
    </div>
  </aside>
</template>

<script setup>
import SidebarItem from './SidebarItem.vue';

defineProps({
  current: { type: String, default: '' },
});

const navItems = [
  { id: 'dashboard', label: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
  { id: 'contacts', label: 'Contacts', href: '/contacts', icon: 'contacts' },
  { id: 'organizations', label: 'Organisations', href: '/organizations', icon: 'organizations' },
  { id: 'mails', label: 'Mails', href: '/mails', icon: 'mail' },
  { id: 'drafts', label: 'Brouillons', href: '/drafts', icon: 'draft' },
  { id: 'templates', label: 'Modèles', href: '/templates', icon: 'template' },
  { id: 'campaigns', label: 'Campagnes', href: '/campaigns', icon: 'campaign' },
  { id: 'activity', label: 'Activité', href: '/activity', icon: 'activity' },
];

const bottomItems = [
  { id: 'settings', label: 'Réglages', href: '/settings', icon: 'settings' },
  { id: 'users', label: 'Utilisateurs', href: '/users', icon: 'users' },
];
</script>

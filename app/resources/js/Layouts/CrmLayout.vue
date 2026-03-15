<template>
  <div class="flex h-screen overflow-hidden">
    <Sidebar :current="currentPage" />
    <div class="flex flex-1 flex-col overflow-hidden">
      <header class="flex h-14 shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6">
        <div class="flex items-center gap-3">
          <h1 class="text-lg font-semibold text-gray-900">{{ title }}</h1>
          <slot name="header-meta" />
        </div>
        <div class="flex items-center gap-4">
          <!-- Global search -->
          <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
            </svg>
            <input
              type="text"
              placeholder="Recherche…"
              class="w-56 rounded-md border border-gray-200 bg-gray-50 py-1.5 pl-8 pr-3 text-sm placeholder:text-gray-400 focus:border-gray-300 focus:bg-white focus:outline-none"
            />
          </div>
          <!-- Contextual actions -->
          <slot name="header-actions" />
          <!-- Settings shortcut -->
          <Link href="/settings" class="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
              <path fill-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.992 6.992 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
            </svg>
          </Link>
          <!-- Profile -->
          <div class="flex items-center gap-2 rounded-md px-2 py-1 hover:bg-gray-50 cursor-pointer">
            <div class="h-7 w-7 rounded-full bg-gray-200 flex items-center justify-center text-xs font-medium text-gray-600">
              {{ initials }}
            </div>
            <span class="text-sm text-gray-600">{{ userName }}</span>
          </div>
        </div>
      </header>
      <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Sidebar from '@/Components/Navigation/Sidebar.vue';

const props = defineProps({
  title: { type: String, default: '' },
  currentPage: { type: String, default: '' },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name || 'Utilisateur');
const initials = computed(() => {
  const name = userName.value;
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
});
</script>

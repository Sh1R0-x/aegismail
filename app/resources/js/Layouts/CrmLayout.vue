<template>
  <div class="flex h-screen overflow-hidden bg-slate-50 text-slate-900">
    <Sidebar :current="currentPage" />
    <div class="flex flex-1 flex-col overflow-hidden">
      <header class="flex h-20 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-8 sticky top-0 z-10">
        <div class="flex flex-1 items-center gap-6">
          <div>
            <h1 class="text-xl font-bold leading-tight text-slate-900">{{ title }}</h1>
            <slot name="header-meta">
              <p v-if="subtitle" class="mt-0.5 text-xs font-medium text-slate-500">{{ subtitle }}</p>
            </slot>
          </div>
          <!-- Global search -->
          <div class="relative ml-8 hidden max-w-md flex-1 md:block">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
            </svg>
            <input
              type="text"
              placeholder="Rechercher..."
              class="w-full rounded-xl border-none bg-slate-100 py-2 pl-10 pr-4 text-sm transition-all placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30"
            />
          </div>
        </div>
        <div class="flex items-center gap-4">
          <!-- Contextual actions -->
          <slot name="header-actions" />
          <!-- Settings shortcut -->
          <Link href="/settings" class="rounded-xl bg-slate-100 p-2.5 text-slate-600 transition-colors hover:text-blue-600">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
              <path fill-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.992 6.992 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
            </svg>
          </Link>
        </div>
      </header>
      <main class="flex-1 overflow-y-auto bg-slate-50 p-8 custom-scrollbar">
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
  subtitle: { type: String, default: '' },
  currentPage: { type: String, default: '' },
});
</script>

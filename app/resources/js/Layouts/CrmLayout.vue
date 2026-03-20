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
          <!-- Global navigation search -->
          <div class="relative ml-8 hidden max-w-md flex-1 md:block" ref="searchWrapper">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
            </svg>
            <input
              ref="searchInput"
              v-model="searchQuery"
              type="text"
              placeholder="Rechercher une page…"
              class="w-full rounded-xl border border-slate-200 bg-slate-100 py-2 pl-10 pr-4 text-sm transition-all placeholder:text-slate-500 focus:ring-2 focus:ring-blue-500/30"
              @focus="searchOpen = true"
              @keydown.escape="closeSearch"
              @keydown.down.prevent="highlightNext"
              @keydown.up.prevent="highlightPrev"
              @keydown.enter.prevent="goToHighlighted"
            />
            <div
              v-if="searchOpen && searchQuery.trim() && searchResults.length > 0"
              class="absolute left-0 top-full z-50 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg overflow-hidden"
            >
              <Link
                v-for="(item, idx) in searchResults"
                :key="item.href"
                :href="item.href"
                :class="[
                  'flex items-center gap-3 px-4 py-2.5 text-sm font-medium transition-colors',
                  idx === highlightedIndex ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50',
                ]"
                @click="closeSearch"
              >
                <span class="text-xs text-slate-400">→</span>
                {{ item.label }}
              </Link>
            </div>
            <div
              v-else-if="searchOpen && searchQuery.trim() && searchResults.length === 0"
              class="absolute left-0 top-full z-50 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg px-4 py-3 text-xs font-medium text-slate-400"
            >
              Aucun résultat pour « {{ searchQuery }} »
            </div>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <!-- Contextual actions -->
          <slot name="header-actions" />
          <!-- Settings shortcut -->
          <Link href="/settings" class="rounded-xl border border-slate-200 bg-slate-100 p-2.5 text-slate-600 transition-colors hover:text-blue-600">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
              <path fill-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.992 6.992 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
            </svg>
          </Link>
        </div>
      </header>
      <div v-if="page.props.gatewayDriver === 'stub'" class="shrink-0 border-b border-amber-200 bg-amber-50 px-8 py-2 text-center text-xs font-semibold text-amber-700">
        ⚠ Pilote d'envoi : <span class="font-black">stub</span> — aucun e-mail n'est réellement envoyé. Passez <code class="rounded bg-amber-100 px-1">MAIL_GATEWAY_DRIVER=http</code> dans .env pour activer les envois réels.
      </div>
      <main class="flex-1 overflow-y-auto bg-slate-50 p-8 custom-scrollbar">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Sidebar from '@/Components/Navigation/Sidebar.vue';

const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  currentPage: { type: String, default: '' },
});

const page = usePage();

// ── Navigation search ──────────────────────────────────────
const searchQuery = ref('');
const searchOpen = ref(false);
const highlightedIndex = ref(0);
const searchWrapper = ref(null);
const searchInput = ref(null);

const allPages = [
  { label: 'Tableau de bord', href: '/dashboard' },
  { label: 'Contacts', href: '/contacts' },
  { label: 'Organisations', href: '/organizations' },
  { label: 'Mails', href: '/mails' },
  { label: 'Mails envoyés', href: '/mails?tab=sent' },
  { label: 'Brouillons', href: '/mails?tab=drafts' },
  { label: 'Mails programmés', href: '/mails?tab=scheduled' },
  { label: 'Modèles', href: '/templates' },
  { label: 'Campagnes', href: '/campaigns' },
  { label: 'Activité', href: '/activity' },
  { label: 'Import / Export', href: '/import-export' },
  { label: 'Réglages', href: '/settings' },
  { label: 'Utilisateurs', href: '/users' },
];

const searchResults = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return [];
  return allPages.filter((p) => p.label.toLowerCase().includes(q));
});

function closeSearch() {
  searchOpen.value = false;
  searchQuery.value = '';
  highlightedIndex.value = 0;
  searchInput.value?.blur();
}

function highlightNext() {
  if (searchResults.value.length === 0) return;
  highlightedIndex.value = (highlightedIndex.value + 1) % searchResults.value.length;
}

function highlightPrev() {
  if (searchResults.value.length === 0) return;
  highlightedIndex.value = (highlightedIndex.value - 1 + searchResults.value.length) % searchResults.value.length;
}

function goToHighlighted() {
  const item = searchResults.value[highlightedIndex.value];
  if (item) {
    router.visit(item.href);
    closeSearch();
  }
}

function onClickOutside(e) {
  if (searchWrapper.value && !searchWrapper.value.contains(e.target)) {
    searchOpen.value = false;
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));
</script>

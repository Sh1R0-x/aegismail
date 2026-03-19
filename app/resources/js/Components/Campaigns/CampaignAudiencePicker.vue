<template>
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <!-- Tab bar + count -->
    <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 flex items-center justify-between gap-3">
      <div class="flex gap-1">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="[
            'rounded-lg px-3 py-1.5 text-xs font-bold transition-colors flex items-center gap-1.5',
            activeTab === tab.id
              ? 'bg-white text-slate-900 shadow-sm border border-slate-200'
              : 'text-slate-500 hover:text-slate-700',
          ]"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
          <span
            v-if="tab.badge !== null"
            class="rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-black text-slate-600"
          >
            {{ tab.badge }}
          </span>
        </button>
      </div>
      <span
        :class="[
          'shrink-0 text-xs font-bold',
          selectedCount > 0 ? 'text-blue-600' : 'text-slate-400',
        ]"
      >
        {{ selectedCount > 0 ? `${selectedCount} sélectionné(s)` : 'Aucun sélectionné' }}
      </span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="px-6 py-8 text-center text-xs font-medium text-slate-400">
      Chargement de l'audience…
    </div>

    <!-- Error -->
    <div v-else-if="loadError" class="px-6 py-5 text-xs font-medium text-red-600">
      {{ loadError }}
    </div>

    <!-- ── Contacts tab ───────────────────────────────────── -->
    <template v-else-if="activeTab === 'contacts'">
      <div class="border-b border-slate-100 px-4 py-3">
        <input
          v-model="contactSearch"
          type="text"
          placeholder="Rechercher par nom, e-mail, organisation…"
          class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
        />
      </div>

      <div class="max-h-64 overflow-y-auto divide-y divide-slate-100">
        <!-- Empty: no contacts at all -->
        <div v-if="audiences.contacts.length === 0" class="px-6 py-10 text-center space-y-2">
          <p class="text-sm font-medium text-slate-500">Aucun contact disponible.</p>
          <p class="text-xs text-slate-400">
            Les contacts doivent être rattachés à une organisation pour être inclus dans une campagne.
          </p>
          <div class="flex items-center justify-center gap-3 mt-3">
            <a href="/contacts" class="text-xs font-bold text-blue-600 hover:text-blue-800">
              Ajouter un contact
            </a>
            <span class="text-slate-300">·</span>
            <a href="/contacts/imports" class="text-xs font-bold text-blue-600 hover:text-blue-800">
              Importer des contacts
            </a>
          </div>
        </div>

        <!-- Empty: no results for search -->
        <div
          v-else-if="filteredContacts.length === 0"
          class="px-6 py-8 text-center text-xs font-medium text-slate-400"
        >
          Aucun résultat pour cette recherche.
        </div>

        <!-- Contact rows -->
        <label
          v-for="contact in filteredContacts"
          :key="contact.contactId"
          :class="[
            'flex items-center gap-3 px-4 py-2.5 transition-colors',
            contact.email ? 'cursor-pointer hover:bg-slate-50' : 'opacity-50 cursor-not-allowed',
          ]"
        >
          <input
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 accent-blue-600 shrink-0"
            :checked="isContactSelected(contact.contactId)"
            :disabled="!contact.email"
            @change="toggleContact(contact)"
          />
          <div class="min-w-0 flex-1">
            <p class="text-xs font-bold text-slate-900 truncate">{{ contact.name || '—' }}</p>
            <p class="text-[11px] text-slate-400 truncate">
              {{ contact.email || 'Pas d\'email principal' }}
              <span v-if="contact.organizationName"> · {{ contact.organizationName }}</span>
            </p>
          </div>
          <span
            v-if="!contact.email"
            class="shrink-0 rounded-md border border-red-200 bg-red-50 px-2 py-0.5 text-[10px] font-black uppercase text-red-600"
          >
            Sans email
          </span>
        </label>
      </div>

      <!-- Bulk actions bar -->
      <div
        v-if="filteredContacts.length > 0"
        class="border-t border-slate-100 bg-slate-50 px-4 py-2 flex items-center gap-4 text-xs"
      >
        <button
          class="font-bold text-blue-600 hover:text-blue-800 transition-colors"
          @click="selectAllFiltered"
        >
          Tout sélectionner
        </button>
        <button
          class="font-bold text-slate-500 hover:text-slate-700 transition-colors"
          @click="deselectAllFiltered"
        >
          Tout désélectionner
        </button>
        <span class="text-slate-400 ml-auto">
          {{ filteredContacts.filter((c) => c.email).length }} avec email
        </span>
      </div>
    </template>

    <!-- ── Organizations tab ──────────────────────────────── -->
    <template v-else-if="activeTab === 'organizations'">
      <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
        <div v-if="audiences.organizations.length === 0" class="px-6 py-10 text-center">
          <p class="text-sm font-medium text-slate-500">Aucune organisation disponible.</p>
          <p class="mt-1 text-xs text-slate-400">
            Créez des organisations et rattachez-leur des contacts pour les inclure ici.
          </p>
        </div>

        <div
          v-for="org in audiences.organizations"
          :key="org.organizationId"
          class="px-4 py-3 space-y-2"
        >
          <div class="flex items-center gap-3">
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-slate-300 accent-blue-600 shrink-0"
              :checked="isOrganizationFullySelected(org)"
              :disabled="org.contacts.filter((c) => c.email).length === 0"
              @change="toggleOrganization(org)"
            />
            <div class="min-w-0 flex-1">
              <p class="text-xs font-bold text-slate-900">{{ org.organizationName }}</p>
              <p class="text-[11px] text-slate-400">
                {{ org.contacts.filter((c) => c.email).length }} contact(s) avec email
                <span v-if="org.domain"> · {{ org.domain }}</span>
              </p>
            </div>
            <span
              v-if="org.contacts.filter((c) => c.email).length === 0"
              class="text-[10px] font-bold text-slate-400"
            >
              Vide
            </span>
            <span
              v-else-if="isOrganizationPartiallySelected(org)"
              class="text-[10px] font-bold text-blue-500"
            >
              Partiel
            </span>
            <span
              v-else-if="isOrganizationFullySelected(org)"
              class="text-[10px] font-bold text-emerald-600"
            >
              Complet
            </span>
          </div>
        </div>
      </div>
    </template>


  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import { formatDateFR } from '@/Utils/formatDate.js';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  initialAudiences: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);

// ── Local state ────────────────────────────────────────────
const activeTab = ref('organizations');
const contactSearch = ref('');
const loading = ref(false);
const loadError = ref(null);

const audiences = ref({
  contacts: [],
  organizations: [],
});

// Track selected contacts by contactId
const selectedContactIds = ref(new Set());

// ── Tabs config ────────────────────────────────────────────
const tabs = computed(() => [
  {
    id: 'contacts',
    label: 'Contacts',
    badge: audiences.value.contacts.length || null,
  },
  {
    id: 'organizations',
    label: 'Organisations',
    badge: audiences.value.organizations.length || null,
  },
]);

// ── Computed ───────────────────────────────────────────────
const filteredContacts = computed(() => {
  const q = contactSearch.value.trim().toLowerCase();
  if (!q) return audiences.value.contacts;
  return audiences.value.contacts.filter(
    (c) =>
      (c.name || '').toLowerCase().includes(q) ||
      (c.email || '').toLowerCase().includes(q) ||
      (c.organizationName || '').toLowerCase().includes(q),
  );
});

const selectedCount = computed(() => selectedContactIds.value.size);

// ── Helpers for selection state ────────────────────────────
function isContactSelected(contactId) {
  return selectedContactIds.value.has(contactId);
}

function isOrganizationFullySelected(org) {
  const emailContacts = org.contacts.filter((c) => c.email);
  if (emailContacts.length === 0) return false;
  return emailContacts.every((c) => selectedContactIds.value.has(c.contactId));
}

function isOrganizationPartiallySelected(org) {
  const emailContacts = org.contacts.filter((c) => c.email);
  if (emailContacts.length === 0) return false;
  const selected = emailContacts.filter((c) => selectedContactIds.value.has(c.contactId));
  return selected.length > 0 && selected.length < emailContacts.length;
}

// ── Toggle actions ─────────────────────────────────────────
function toggleContact(contact) {
  if (!contact.email) return;
  const next = new Set(selectedContactIds.value);
  if (next.has(contact.contactId)) {
    next.delete(contact.contactId);
  } else {
    next.add(contact.contactId);
  }
  selectedContactIds.value = next;
}

function toggleOrganization(org) {
  const emailContacts = org.contacts.filter((c) => c.email);
  const allSelected = isOrganizationFullySelected(org);
  const next = new Set(selectedContactIds.value);
  emailContacts.forEach((c) => {
    if (allSelected) {
      next.delete(c.contactId);
    } else {
      next.add(c.contactId);
    }
  });
  selectedContactIds.value = next;
}

function selectAllFiltered() {
  const next = new Set(selectedContactIds.value);
  filteredContacts.value.filter((c) => c.email).forEach((c) => next.add(c.contactId));
  selectedContactIds.value = next;
}

function deselectAllFiltered() {
  const next = new Set(selectedContactIds.value);
  filteredContacts.value.forEach((c) => next.delete(c.contactId));
  selectedContactIds.value = next;
}

// ── Derive recipients for autosave payload ─────────────────
function buildRecipients() {
  // Build a lookup map of all contacts across all sources
  const allContacts = new Map();

  audiences.value.contacts.forEach((c) => {
    if (!allContacts.has(c.contactId)) allContacts.set(c.contactId, c);
  });
  audiences.value.organizations.forEach((org) => {
    org.contacts.forEach((c) => {
      if (!allContacts.has(c.contactId)) allContacts.set(c.contactId, c);
    });
  });
  const recipients = [];
  selectedContactIds.value.forEach((id) => {
    const contact = allContacts.get(id);
    if (!contact || !contact.email) return;
    recipients.push({
      email: contact.email,
      contactId: contact.contactId,
      contactEmailId: contact.contactEmailId ?? null,
      organizationId: contact.organizationId ?? null,
      organizationName: contact.organizationName ?? null,
      name: contact.name || null,
    });
  });

  return recipients;
}

// ── Emit recipients when selection changes ─────────────────
watch(
  selectedContactIds,
  () => {
    emit('update:modelValue', buildRecipients());
  },
  { deep: true },
);

// ── Load audiences ─────────────────────────────────────────
async function loadAudiences() {
  if (props.initialAudiences) {
    audiences.value = props.initialAudiences;
    return;
  }

  loading.value = true;
  loadError.value = null;

  try {
    const { data } = await axios.get('/api/campaigns/audiences');
    audiences.value = {
      contacts: data.contacts ?? [],
      organizations: data.organizations ?? [],
    };
  } catch {
    loadError.value = 'Impossible de charger l\'audience. Vérifiez votre connexion.';
  } finally {
    loading.value = false;
  }
}

// ── Initialize selected from modelValue ────────────────────
function initSelectionFromModelValue() {
  if (!props.modelValue || props.modelValue.length === 0) return;
  const next = new Set();
  props.modelValue.forEach((r) => {
    if (r.contactId) next.add(r.contactId);
  });
  selectedContactIds.value = next;
}

onMounted(async () => {
  await loadAudiences();
  initSelectionFromModelValue();
});
</script>

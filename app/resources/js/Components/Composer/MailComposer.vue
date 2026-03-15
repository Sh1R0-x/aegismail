<template>
  <!-- Backdrop -->
  <div class="fixed inset-0 z-40 bg-black/30" @click="close" />

  <!-- Slide-over panel -->
  <div class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col bg-white shadow-2xl ring-1 ring-black/5">

    <!-- Header -->
    <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-5 py-3">
      <div class="flex min-w-0 items-center gap-3">
        <!-- Mode toggle -->
        <div class="flex rounded border border-gray-200 bg-gray-50 p-0.5">
          <button
            :class="[
              'rounded px-3 py-1 text-xs font-medium transition-colors',
              form.mode === 'single' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700',
            ]"
            @click="setMode('single')"
          >
            Simple
          </button>
          <button
            :class="[
              'rounded px-3 py-1 text-xs font-medium transition-colors',
              form.mode === 'multiple' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700',
            ]"
            @click="setMode('multiple')"
          >
            Multiple
          </button>
        </div>
        <span v-if="draftId" class="truncate text-xs text-gray-400">Brouillon #{{ draftId }}</span>
        <span v-if="savedAt" class="shrink-0 text-xs text-green-600">Sauvegardé {{ savedAtLabel }}</span>
      </div>
      <button
        class="ml-3 shrink-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
        @click="close"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Error banner -->
    <div
      v-if="error"
      class="flex shrink-0 items-center justify-between gap-3 border-b border-red-200 bg-red-50 px-5 py-2"
    >
      <p class="text-sm text-red-700">{{ error }}</p>
      <button class="shrink-0 text-xs text-red-600 hover:text-red-800" @click="error = null">✕</button>
    </div>

    <!-- Scrollable form body -->
    <div class="flex-1 overflow-y-auto px-5 py-5 space-y-5">

      <!-- Recipients -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700">
          {{ form.mode === 'multiple' ? 'Destinataires' : 'Destinataire' }}
        </label>

        <!-- Single mode -->
        <div v-if="form.mode === 'single'" class="flex gap-2">
          <input
            v-model="singleEmail"
            type="email"
            placeholder="adresse@exemple.fr"
            class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
          />
          <input
            v-model="singleName"
            type="text"
            placeholder="Nom (optionnel)"
            class="w-44 rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
          />
        </div>

        <!-- Multiple mode -->
        <div v-else>
          <textarea
            v-model="multipleText"
            rows="4"
            placeholder="Un destinataire par ligne&#10;Format accepté : adresse@exemple.fr ou Prénom Nom <adresse@exemple.fr>"
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
          />
          <p class="mt-1 text-xs text-gray-400">
            {{ parsedRecipients.length }} destinataire(s) identifié(s) · Chaque destinataire reçoit un mail individuel
          </p>
        </div>
      </div>

      <!-- Subject -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700">Sujet</label>
        <input
          v-model="form.subject"
          type="text"
          placeholder="Objet du message"
          class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
        />
      </div>

      <!-- Template selector -->
      <div v-if="activeTemplates.length > 0">
        <label class="mb-1.5 block text-sm font-medium text-gray-700">Modèle (optionnel)</label>
        <select
          v-model="selectedTemplateId"
          class="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-400"
          @change="applyTemplate"
        >
          <option value="">— Aucun modèle —</option>
          <option v-for="tpl in activeTemplates" :key="tpl.id" :value="tpl.id">
            {{ tpl.name }}{{ tpl.subject ? ' — ' + tpl.subject : '' }}
          </option>
        </select>
        <p v-if="templateApplied" class="mt-1 text-xs text-green-600">Modèle appliqué.</p>
      </div>

      <!-- HTML body -->
      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label class="text-sm font-medium text-gray-700">Contenu HTML</label>
          <span class="text-xs text-gray-400">{{ htmlSizeKb }} Ko</span>
        </div>
        <textarea
          v-model="form.htmlBody"
          rows="12"
          placeholder="<p>Votre message…</p>"
          class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
        />
      </div>

      <!-- Text version -->
      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label class="text-sm font-medium text-gray-700">Version texte</label>
          <span v-if="!form.textBody" class="text-xs text-amber-600">
            Recommandé pour la délivrabilité
          </span>
        </div>
        <textarea
          v-model="form.textBody"
          rows="4"
          placeholder="Version texte brut du message (sans balises HTML)…"
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
        />
      </div>

      <!-- Attachments note -->
      <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-xs text-gray-500">
        Les pièces jointes peuvent être ajoutées après sauvegarde du brouillon via l'API.
      </div>

    </div>

    <!-- Preflight result -->
    <div v-if="preflight" class="shrink-0 border-t border-gray-100 max-h-72 overflow-y-auto">
      <PreflightResult :result="preflight" />
    </div>

    <!-- Schedule inline form -->
    <div
      v-if="showSchedule"
      class="shrink-0 border-t border-gray-100 bg-gray-50 px-5 py-4 space-y-3"
    >
      <p class="text-sm font-medium text-gray-700">Planifier l'envoi</p>
      <div>
        <label class="text-xs text-gray-600">Date et heure d'envoi</label>
        <input
          v-model="scheduledAt"
          type="datetime-local"
          :min="minScheduledAt"
          class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-gray-400"
        />
      </div>
      <div class="flex items-center gap-2">
        <button
          :disabled="!scheduledAt || scheduling"
          class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-40"
          @click="scheduleDraft"
        >
          {{ scheduling ? 'Planification…' : 'Confirmer la planification' }}
        </button>
        <button
          class="text-sm text-gray-500 hover:text-gray-700"
          @click="showSchedule = false"
        >
          Annuler
        </button>
      </div>
      <p v-if="schedule_error" class="text-xs text-red-600">{{ schedule_error }}</p>
    </div>

    <!-- Footer actions -->
    <div class="shrink-0 flex items-center gap-2 border-t border-gray-100 bg-white px-5 py-3">
      <button
        :disabled="saving || !form.subject"
        class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-40"
        @click="saveDraft"
      >
        {{ saving ? 'Sauvegarde…' : draftId ? 'Mettre à jour' : 'Sauvegarder brouillon' }}
      </button>

      <button
        :disabled="!draftId || preflightLoading"
        :title="!draftId ? 'Sauvegardez d\'abord le brouillon' : ''"
        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40"
        @click="runPreflight"
      >
        {{ preflightLoading ? 'Vérification…' : 'Vérifier (preflight)' }}
      </button>

      <button
        v-if="preflight?.ok && !showSchedule"
        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
        @click="showSchedule = true"
      >
        Planifier
      </button>

      <button
        class="ml-auto text-sm text-gray-500 hover:text-gray-700"
        @click="close"
      >
        Fermer
      </button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import PreflightResult from '@/Components/Preflight/PreflightResult.vue';

const props = defineProps({
  mode: { type: String, default: 'single' }, // 'single' | 'multiple'
  draft: { type: Object, default: null },     // existing draft (from DraftService.serialize())
  templates: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved', 'scheduled']);

// ── Form state ─────────────────────────────────────────────
const form = ref({
  mode: props.draft ? props.draft.type : props.mode,
  subject: props.draft?.subject ?? '',
  htmlBody: props.draft?.htmlBody ?? '',
  textBody: props.draft?.textBody ?? '',
  templateId: props.draft?.templateId ?? null,
});

// Recipients
const singleEmail = ref('');
const singleName = ref('');
const multipleText = ref('');

// Init recipient fields from existing draft
if (props.draft) {
  const recipients = props.draft.recipients ?? [];
  if (form.value.mode === 'single' && recipients.length > 0) {
    singleEmail.value = recipients[0].email ?? '';
    singleName.value = recipients[0].name ?? '';
  } else if (form.value.mode === 'multiple') {
    multipleText.value = recipients
      .map(r => r.name ? `${r.name} <${r.email}>` : r.email)
      .join('\n');
  }
}

// ── Draft state ────────────────────────────────────────────
const draftId = ref(props.draft?.id ?? null);
const saving = ref(false);
const savedAt = ref(null);
const error = ref(null);

// ── Preflight state ────────────────────────────────────────
const preflight = ref(null);
const preflightLoading = ref(false);

// ── Schedule state ─────────────────────────────────────────
const showSchedule = ref(false);
const scheduledAt = ref('');
const scheduling = ref(false);
const schedule_error = ref(null);

// ── Template ───────────────────────────────────────────────
const selectedTemplateId = ref(props.draft?.templateId ?? '');
const templateApplied = ref(false);

// ── Computed ───────────────────────────────────────────────
const activeTemplates = computed(() =>
  props.templates.filter(t => t.active !== false),
);

const parsedRecipients = computed(() => {
  if (form.value.mode !== 'multiple') return [];
  return multipleText.value
    .split('\n')
    .map(line => line.trim())
    .filter(line => line.length > 0)
    .map(line => {
      const match = line.match(/^(.+?)\s*<([^>]+)>$/);
      if (match) return { name: match[1].trim(), email: match[2].trim() };
      return { name: null, email: line };
    });
});

const currentRecipients = computed(() => {
  if (form.value.mode === 'single') {
    if (!singleEmail.value.trim()) return [];
    return [{ email: singleEmail.value.trim(), name: singleName.value.trim() || null }];
  }
  return parsedRecipients.value;
});

const htmlSizeKb = computed(() => {
  const bytes = new TextEncoder().encode(form.value.htmlBody ?? '').length;
  return bytes < 1024 ? '< 1' : (bytes / 1024).toFixed(1);
});

const savedAtLabel = computed(() => {
  if (!savedAt.value) return '';
  return savedAt.value.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
});

const minScheduledAt = computed(() => {
  const d = new Date(Date.now() + 5 * 60 * 1000);
  return d.toISOString().slice(0, 16);
});

// ── Methods ────────────────────────────────────────────────
function setMode(mode) {
  form.value.mode = mode;
  // Reset preflight when mode changes
  preflight.value = null;
}

function applyTemplate() {
  const tpl = props.templates.find(t => t.id === Number(selectedTemplateId.value));
  if (!tpl) return;
  if (!form.value.subject) form.value.subject = tpl.subject ?? '';
  if (!form.value.htmlBody) form.value.htmlBody = tpl.htmlBody ?? '';
  if (!form.value.textBody) form.value.textBody = tpl.textBody ?? '';
  form.value.templateId = tpl.id;
  templateApplied.value = true;
  setTimeout(() => { templateApplied.value = false; }, 3000);
}

async function saveDraft() {
  saving.value = true;
  error.value = null;

  const payload = {
    type: form.value.mode === 'multiple' ? 'bulk' : 'single',
    subject: form.value.subject,
    htmlBody: form.value.htmlBody || null,
    textBody: form.value.textBody || null,
    templateId: form.value.templateId || null,
    recipients: currentRecipients.value,
  };

  try {
    let resp;
    if (draftId.value) {
      resp = await axios.put(`/api/drafts/${draftId.value}`, payload);
    } else {
      resp = await axios.post('/api/drafts', payload);
    }
    draftId.value = resp.data.draft.id;
    savedAt.value = new Date();
    emit('saved', resp.data.draft);
    // Reset preflight since content changed
    preflight.value = null;
  } catch (e) {
    const msg = e.response?.data?.message
      ?? e.response?.data?.errors?.mailbox?.[0]
      ?? 'Erreur lors de la sauvegarde.';
    error.value = msg;
  } finally {
    saving.value = false;
  }
}

async function runPreflight() {
  if (!draftId.value) return;
  preflightLoading.value = true;
  error.value = null;
  try {
    const resp = await axios.post(`/api/drafts/${draftId.value}/preflight`);
    preflight.value = resp.data.preflight;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erreur lors du preflight.';
  } finally {
    preflightLoading.value = false;
  }
}

async function scheduleDraft() {
  if (!scheduledAt.value || !draftId.value) return;
  scheduling.value = true;
  schedule_error.value = null;
  try {
    const resp = await axios.post(`/api/drafts/${draftId.value}/schedule`, {
      scheduledAt: scheduledAt.value,
    });
    emit('scheduled', resp.data.draft);
    close();
  } catch (e) {
    schedule_error.value =
      e.response?.data?.errors?.preflight?.[0]
      ?? e.response?.data?.message
      ?? 'Erreur lors de la planification.';
  } finally {
    scheduling.value = false;
  }
}

function close() {
  emit('close');
}
</script>

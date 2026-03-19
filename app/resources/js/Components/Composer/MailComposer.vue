<template>
  <!-- Full-page inline composer — no backdrop, no fixed positioning -->
  <div class="flex flex-col gap-6">

    <!-- Action bar -->
    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <!-- Mode toggle -->
      <div class="flex rounded-xl border border-slate-200 bg-slate-50 p-0.5">
        <button
          :class="[
            'rounded-lg px-4 py-1.5 text-xs font-bold transition-colors',
            form.mode === 'single' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700',
          ]"
          @click="setMode('single')"
        >
          Simple
        </button>
        <button
          :class="[
            'rounded-lg px-4 py-1.5 text-xs font-bold transition-colors',
            form.mode === 'multiple' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700',
          ]"
          @click="setMode('multiple')"
        >
          Multiple
        </button>
      </div>
      <span v-if="draftId" class="text-xs font-medium text-slate-400">Brouillon #{{ draftId }}</span>
      <span class="text-xs font-medium text-slate-400">
        {{ form.mode === 'single' ? 'Un seul destinataire — message personnel' : 'Plusieurs destinataires — chacun reçoit un mail individuel' }}
      </span>
      <span v-if="savedAt" class="text-xs font-bold text-emerald-600">Sauvegardé {{ savedAtLabel }}</span>

      <!-- Action buttons -->
      <div class="ml-auto flex flex-wrap items-center gap-3">
        <button
          :disabled="saving || !form.subject"
          class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all disabled:opacity-40"
          @click="saveDraft"
        >
          {{ saving ? 'Sauvegarde…' : draftId ? 'Mettre à jour' : 'Sauvegarder brouillon' }}
        </button>
        <button
          :disabled="!draftId || preflightLoading"
          :title="!draftId ? 'Sauvegardez d\'abord le brouillon' : ''"
          class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 shadow-sm disabled:opacity-40 transition-all"
          @click="runPreflight"
        >
          {{ preflightLoading ? 'Vérification…' : 'Vérification avant envoi' }}
        </button>
        <button
          v-if="preflight?.ok && !showSchedule"
          class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition-all"
          @click="showSchedule = true"
        >
          Planifier
        </button>
        <button
          v-if="preflight?.ok"
          :disabled="sendingNow"
          class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 shadow-sm transition-all disabled:opacity-40"
          @click="sendNow"
        >
          {{ sendingNow ? 'Envoi…' : 'Envoyer maintenant' }}
        </button>
      </div>
    </div>

    <!-- Error banner -->
    <div
      v-if="error"
      class="flex items-start justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-6 py-4"
    >
      <p class="text-sm font-semibold text-red-700">{{ error }}</p>
      <button class="shrink-0 text-xs font-bold text-red-500 hover:text-red-800" @click="error = null">✕</button>
    </div>

    <!-- 2-column layout: form | right panel -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">

      <!-- ── Left: form fields ──────────────────────────────── -->
      <div class="space-y-6">

        <!-- Metadata card: recipients + subject + template -->
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm space-y-5">

          <!-- Recipients -->
          <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">
              {{ form.mode === 'multiple' ? 'Destinataires' : 'Destinataire' }}
            </label>
            <div v-if="form.mode === 'single'" class="flex gap-3">
              <div class="relative flex-1">
                <input
                  v-model="singleEmail"
                  type="email"
                  placeholder="adresse@exemple.fr — tapez pour rechercher un contact"
                  class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
                  @input="onRecipientSearch"
                  @blur="hideContactSuggestions"
                />
                <ul v-if="contactSuggestions.length > 0 && showSuggestions" class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                  <li
                    v-for="c in contactSuggestions"
                    :key="c.contactEmailId"
                    class="cursor-pointer px-4 py-2.5 text-sm hover:bg-slate-50"
                    @mousedown.prevent="selectContact(c)"
                  >
                    <span class="font-bold text-slate-900">{{ c.name }}</span>
                    <span class="ml-2 text-xs text-slate-400">{{ c.email }}</span>
                    <span v-if="c.organizationName" class="ml-2 text-xs text-slate-300">· {{ c.organizationName }}</span>
                  </li>
                </ul>
              </div>
              <input
                v-model="singleName"
                type="text"
                placeholder="Nom (optionnel)"
                class="w-44 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
              />
            </div>
            <div v-else>
              <textarea
                v-model="multipleText"
                rows="4"
                placeholder="Un destinataire par ligne&#10;Format accepté : adresse@exemple.fr ou Prénom Nom <adresse@exemple.fr>"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
              />
              <p class="mt-1.5 text-xs font-medium text-slate-400">
                {{ parsedRecipients.length }} destinataire(s) identifié(s) · Chaque destinataire reçoit un mail individuel
              </p>
            </div>
          </div>

          <!-- Subject -->
          <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">Sujet</label>
            <input
              v-model="form.subject"
              type="text"
              placeholder="Objet du message"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
            />
          </div>

          <!-- Template selector -->
          <div v-if="activeTemplates.length > 0">
            <label class="mb-2 block text-sm font-bold text-slate-700">Modèle (optionnel)</label>
            <select
              v-model="selectedTemplateId"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/30 outline-none"
              @change="applyTemplate"
            >
              <option value="">— Aucun modèle —</option>
              <option v-for="tpl in activeTemplates" :key="tpl.id" :value="tpl.id">
                {{ tpl.name }}{{ tpl.subject ? ' — ' + tpl.subject : '' }}
              </option>
            </select>
            <p v-if="templateApplied" class="mt-1.5 text-xs font-bold text-emerald-600">Modèle appliqué.</p>
          </div>
        </div>

        <!-- ── TEXT BODY (primary) ─────────────────────── -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="text-sm font-bold text-slate-900">Corps du message</h3>
            <p class="mt-0.5 text-xs font-medium text-slate-400">
              Texte brut — prioritaire pour la délivrabilité
              <span v-if="!form.textBody" class="ml-2 font-bold text-amber-600">Recommandé</span>
            </p>
          </div>
          <div class="px-6 py-5">
            <textarea
              v-model="form.textBody"
              rows="10"
              placeholder="Rédigez votre message ici, en texte simple (sans balises HTML)…"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-relaxed placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
            />
          </div>
        </div>

        <!-- ── HTML BODY (secondary, collapsible) ───────── -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <button
            type="button"
            class="flex w-full items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors"
            @click="htmlExpanded = !htmlExpanded"
          >
            <div class="flex items-center gap-3 text-left">
              <h3 class="text-sm font-bold text-slate-700">Version HTML</h3>
              <span class="rounded-md border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest text-slate-500">Optionnel</span>
              <span v-if="form.htmlBody" class="text-xs font-medium text-slate-400">{{ htmlSizeKb }} Ko</span>
            </div>
            <svg
              :class="['h-4 w-4 text-slate-400 transition-transform', htmlExpanded ? 'rotate-180' : '']"
              xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
            >
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
          </button>
          <div v-if="htmlExpanded" class="border-t border-slate-100 px-6 py-5 space-y-3">
            <p class="text-xs font-medium text-slate-400">
              Si vide, une version HTML est générée automatiquement à partir du texte brut lors de l'envoi.
            </p>
            <div class="flex items-center justify-end">
              <button
                type="button"
                :class="[
                  'rounded-lg px-3 py-1 text-xs font-bold transition-colors',
                  previewHtml ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50',
                ]"
                @click="previewHtml = !previewHtml"
              >
                {{ previewHtml ? 'Éditer' : 'Aperçu HTML' }}
              </button>
            </div>
            <textarea
              v-if="!previewHtml"
              v-model="form.htmlBody"
              rows="14"
              placeholder="<p>Votre message HTML optionnel…</p>"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 font-mono text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
            />
            <iframe
              v-else
              :srcdoc="form.htmlBody || '<p style=\'color:#9ca3af;font-family:sans-serif;padding:1rem\'>Aperçu vide — saisissez du HTML ci-dessus.</p>'"
              sandbox="allow-same-origin"
              class="w-full rounded-xl border border-slate-200 bg-white"
              style="height: 22rem;"
            />
          </div>
        </div>

        <!-- ── Attachments ─────────────────────────────── -->
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Pièces jointes</h3>
              <p class="mt-0.5 text-xs font-medium text-slate-400">10 Mo max par fichier</p>
            </div>
            <label class="cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
              Ajouter un fichier
              <input type="file" class="hidden" multiple @change="onFileSelected" />
            </label>
          </div>
          <p v-if="attachmentError" class="text-xs font-semibold text-red-600">{{ attachmentError }}</p>
          <div v-if="attachments.length > 0" class="space-y-2">
            <div v-for="att in attachments" :key="att.id" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5">
              <div class="flex items-center gap-3 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 text-slate-400">
                  <path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd" />
                </svg>
                <span class="truncate text-sm font-medium text-slate-700">{{ att.name }}</span>
                <span class="shrink-0 text-xs text-slate-400">{{ formatFileSize(att.size) }}</span>
              </div>
              <button class="shrink-0 text-xs font-bold text-red-500 hover:text-red-700" @click="removeAttachment(att.id)">
                Supprimer
              </button>
            </div>
          </div>
          <p v-if="uploadingAttachment" class="text-xs font-medium text-blue-600">Téléversement en cours…</p>
        </div>

      </div>

      <!-- ── Right panel ────────────────────────────────── -->
      <div class="space-y-4 lg:sticky lg:top-6 self-start">

        <!-- Preflight result or placeholder -->
        <template v-if="preflight">
          <PreflightResult :result="preflight" />
        </template>
        <div v-else class="rounded-2xl border border-slate-200 bg-slate-50 px-6 py-5 text-center">
          <p class="text-sm font-bold text-slate-500">Vérification avant envoi</p>
          <p class="mt-1 text-xs font-medium text-slate-400">Sauvegardez le brouillon, puis lancez la vérification pour évaluer la délivrabilité.</p>
          <button
            v-if="draftId"
            :disabled="preflightLoading"
            class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors disabled:opacity-40"
            @click="runPreflight"
          >
            {{ preflightLoading ? 'Analyse…' : 'Lancer la vérification' }}
          </button>
        </div>

        <!-- Schedule form -->
        <div class="rounded-2xl border border-blue-100 bg-blue-50 px-6 py-5 space-y-4">
          <div class="flex items-center justify-between">
            <p class="text-xs font-black uppercase tracking-[0.1em] text-blue-600">Planification</p>
            <button
              type="button"
              :class="['rounded-lg px-3 py-1 text-xs font-bold transition-colors', showSchedule ? 'bg-blue-600 text-white' : 'border border-blue-200 text-blue-600 hover:bg-blue-100']"
              @click="showSchedule = !showSchedule"
            >
              {{ showSchedule ? 'Masquer' : 'Planifier' }}
            </button>
          </div>
          <template v-if="showSchedule">
            <div class="space-y-3">
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="opt in quickScheduleOptions"
                  :key="opt.label"
                  class="rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-100 transition-colors"
                  @click="scheduledAt = opt.value"
                >
                  {{ opt.label }}
                </button>
              </div>
              <div>
                <label class="mb-1 block text-xs font-bold text-blue-700">Date et heure d'envoi</label>
                <input
                  v-model="scheduledAt"
                  type="datetime-local"
                  :min="minScheduledAt"
                  class="w-full rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/30 outline-none"
                />
              </div>
              <p v-if="schedule_error" class="text-xs font-semibold text-red-600">{{ schedule_error }}</p>
              <button
                :disabled="!scheduledAt || scheduling || !draftId"
                class="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 transition-colors disabled:opacity-40"
                @click="scheduleDraft"
              >
                {{ scheduling ? 'Planification…' : 'Confirmer la planification' }}
              </button>
              <p v-if="!draftId" class="text-center text-xs font-medium text-blue-400">Sauvegardez d'abord le brouillon.</p>
            </div>
          </template>
        </div>

        <!-- Draft info -->
        <div v-if="draftId" class="rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Brouillon</p>
          <p class="mt-1 text-xs font-medium text-slate-600">ID : {{ draftId }}</p>
          <p v-if="savedAt" class="mt-0.5 text-xs font-medium text-emerald-600">Sauvegardé {{ savedAtLabel }}</p>
        </div>

        <!-- Test mail card -->
        <div class="rounded-2xl border border-amber-100 bg-amber-50 px-6 py-5 space-y-3">
          <p class="text-xs font-black uppercase tracking-[0.1em] text-amber-700">Envoi de test</p>
          <p class="text-xs font-medium text-amber-600">Envoyez un mail de test pour vérifier le rendu réel.</p>
          <input
            v-model="testEmail"
            type="email"
            placeholder="adresse@exemple.fr"
            class="w-full rounded-xl border border-amber-200 bg-white px-4 py-2 text-sm font-medium placeholder:text-amber-300 focus:ring-2 focus:ring-amber-500/30 outline-none"
          />
          <button
            :disabled="!testEmail || !draftId || testSending"
            class="w-full rounded-xl bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700 transition-colors disabled:opacity-40"
            @click="sendTestMail"
          >
            {{ testSending ? 'Envoi du test…' : 'Envoyer un test' }}
          </button>
          <div v-if="testResult" :class="['rounded-lg px-3 py-2 text-xs font-semibold', testResult.success ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200']">
            {{ testResult.message }}
            <span v-if="testResult.driver === 'stub'" class="block mt-1 text-[10px] font-medium opacity-70">
              ⚠ Pilote stub — aucun envoi SMTP réel.
            </span>
          </div>
        </div>

        <!-- Email preview card -->
        <div v-if="form.textBody || form.htmlBody" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <button
            type="button"
            class="flex w-full items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors"
            @click="showEmailPreview = !showEmailPreview"
          >
            <span class="text-xs font-black uppercase tracking-[0.1em] text-slate-500">Aperçu du mail</span>
            <svg
              :class="['h-4 w-4 text-slate-400 transition-transform', showEmailPreview ? 'rotate-180' : '']"
              xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
            >
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
          </button>
          <div v-if="showEmailPreview" class="border-t border-slate-100 px-6 py-4 space-y-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-500">
              <strong>De :</strong> (boîte OVH configurée)<br />
              <strong>À :</strong> {{ currentRecipients.length > 0 ? currentRecipients.length + ' destinataire(s)' : '—' }}<br />
              <strong>Objet :</strong> {{ form.subject || '(Sans objet)' }}
            </div>
            <iframe
              v-if="form.htmlBody"
              :srcdoc="form.htmlBody"
              sandbox="allow-same-origin"
              class="w-full rounded-xl border border-slate-200 bg-white"
              style="height: 16rem"
            />
            <pre v-else class="whitespace-pre-wrap rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 leading-relaxed max-h-64 overflow-y-auto">{{ form.textBody }}</pre>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import PreflightResult from '@/Components/Preflight/PreflightResult.vue';
import { formatDateFR } from '@/Utils/formatDate.js';

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

// ── Preview state ──────────────────────────────────────────
const previewHtml = ref(false);
const htmlExpanded = ref(Boolean(props.draft?.htmlBody));

// ── Schedule state ─────────────────────────────────────────
const showSchedule = ref(false);
const scheduledAt = ref('');
const scheduling = ref(false);
const schedule_error = ref(null);

// ── Send now state ─────────────────────────────────────────
const sendingNow = ref(false);

// ── Test mail state ────────────────────────────────────────
const testEmail = ref('');
const testSending = ref(false);
const testResult = ref(null);

// ── Email preview state ────────────────────────────────────
const showEmailPreview = ref(false);

// ── Contact search state ───────────────────────────────────
const contactSuggestions = ref([]);
const showSuggestions = ref(false);
let searchTimeout = null;

function onRecipientSearch() {
  const q = singleEmail.value.trim();
  if (q.length < 2) {
    contactSuggestions.value = [];
    showSuggestions.value = false;
    return;
  }
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(async () => {
    try {
      const { data } = await axios.get('/api/contacts/search', { params: { q } });
      contactSuggestions.value = data.contacts ?? [];
      showSuggestions.value = contactSuggestions.value.length > 0;
    } catch {
      contactSuggestions.value = [];
      showSuggestions.value = false;
    }
  }, 300);
}

function selectContact(contact) {
  singleEmail.value = contact.email;
  singleName.value = contact.name || '';
  showSuggestions.value = false;
  contactSuggestions.value = [];
}

function hideContactSuggestions() {
  setTimeout(() => { showSuggestions.value = false; }, 200);
}

// ── Attachment state ───────────────────────────────────────
const attachments = ref(props.draft?.attachments ?? []);
const uploadingAttachment = ref(false);
const attachmentError = ref(null);

async function onFileSelected(event) {
  const files = event.target.files;
  if (!files || files.length === 0) return;
  attachmentError.value = null;

  // Auto-save draft if not yet saved
  if (!draftId.value) {
    await saveDraft();
    if (!draftId.value) return; // save failed
  }

  for (const file of files) {
    if (file.size > 10 * 1024 * 1024) {
      attachmentError.value = `Le fichier "${file.name}" dépasse la limite de 10 Mo.`;
      continue;
    }
    uploadingAttachment.value = true;
    try {
      const formData = new FormData();
      formData.append('file', file);
      const { data } = await axios.post(`/api/drafts/${draftId.value}/attachments`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      attachments.value.push(data.attachment);
    } catch (e) {
      attachmentError.value = e.response?.data?.message ?? `Impossible de téléverser "${file.name}".`;
    } finally {
      uploadingAttachment.value = false;
    }
  }
  event.target.value = '';
}

async function removeAttachment(attachmentId) {
  if (!draftId.value) return;
  try {
    await axios.delete(`/api/drafts/${draftId.value}/attachments/${attachmentId}`);
    attachments.value = attachments.value.filter(a => a.id !== attachmentId);
  } catch (e) {
    attachmentError.value = e.response?.data?.message ?? 'Impossible de supprimer la pièce jointe.';
  }
}

function formatFileSize(bytes) {
  if (!bytes) return '0 o';
  if (bytes < 1024) return bytes + ' o';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
  return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
}

// ── Quick schedule options ─────────────────────────────────
const quickScheduleOptions = computed(() => {
  const now = new Date();
  const today9h = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 9, 0);
  const today14h = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 14, 0);
  const tomorrow9h = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 9, 0);
  const tomorrow14h = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 14, 0);
  const apdem9h = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 2, 9, 0);

  const fmt = d => d.toISOString().slice(0, 16);
  const options = [];
  if (today9h > now) options.push({ label: "Aujourd'hui 9h", value: fmt(today9h) });
  if (today14h > now) options.push({ label: "Aujourd'hui 14h", value: fmt(today14h) });
  options.push({ label: 'Demain 9h', value: fmt(tomorrow9h) });
  options.push({ label: 'Demain 14h', value: fmt(tomorrow14h) });
  options.push({ label: 'Après-demain 9h', value: fmt(apdem9h) });
  return options;
});

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
  return formatDateFR(savedAt.value, { timeOnly: true });
});

const minScheduledAt = computed(() => {
  const d = new Date(Date.now() + 5 * 60 * 1000);
  return d.toISOString().slice(0, 16);
});

// ── Methods ────────────────────────────────────────────────
function setMode(mode) {
  form.value.mode = mode;
  // Reset preflight and preview when mode changes
  preflight.value = null;
  previewHtml.value = false;
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
    error.value = e.response?.data?.message ?? 'Erreur lors de la vérification.';
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

async function sendNow() {
  if (!draftId.value) return;
  sendingNow.value = true;
  try {
    const resp = await axios.post(`/api/drafts/${draftId.value}/send-now`);
    emit('scheduled', resp.data.draft);
    close();
  } catch (e) {
    schedule_error.value =
      e.response?.data?.errors?.preflight?.[0]
      ?? e.response?.data?.message
      ?? "Erreur lors de l'envoi immédiat.";
  } finally {
    sendingNow.value = false;
  }
}

async function sendTestMail() {
  if (!testEmail.value || !draftId.value) return;
  testSending.value = true;
  testResult.value = null;
  try {
    const resp = await axios.post(`/api/drafts/${draftId.value}/test-send`, {
      email: testEmail.value,
    });
    testResult.value = resp.data;
  } catch (e) {
    testResult.value = {
      success: false,
      message: e.response?.data?.message ?? "Erreur lors de l'envoi test.",
    };
  } finally {
    testSending.value = false;
  }
}

function close() {
  emit('close');
}
</script>

<template>
  <div class="flex flex-col gap-6">

    <!-- ── Status bar ──────────────────────────────────────── -->
    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <!-- Autosave status indicator -->
      <div class="flex items-center gap-2 min-w-0">
        <span
          v-if="autosaveStatus === 'saving'"
          class="flex items-center gap-1.5 text-xs font-medium text-slate-500"
        >
          <span class="h-2 w-2 rounded-full bg-blue-400 animate-pulse" />
          Sauvegarde en cours…
        </span>
        <span
          v-else-if="autosaveStatus === 'saved'"
          class="flex items-center gap-1.5 text-xs font-bold text-emerald-600"
        >
          <span class="h-2 w-2 rounded-full bg-emerald-500" />
          Sauvegardé {{ savedAtLabel }}
        </span>
        <span
          v-else-if="autosaveStatus === 'error'"
          class="flex items-center gap-1.5 text-xs font-bold text-red-600"
          :title="autosaveError || ''"
        >
          <span class="h-2 w-2 rounded-full bg-red-500" />
          Erreur de sauvegarde
        </span>
        <span
          v-else-if="autosaveStatus === 'pending'"
          class="text-xs font-medium text-slate-400"
        >
          Modifications non enregistrées…
        </span>
        <span v-else-if="campaignId" class="text-xs font-medium text-slate-400">
          Campagne #{{ campaignId }}
        </span>
      </div>

      <!-- Action buttons -->
      <div class="ml-auto flex flex-wrap items-center gap-3">
        <button
          :disabled="!draftId || preflightLoading || autosaveStatus === 'saving'"
          :title="!draftId ? 'En attente de la première sauvegarde automatique…' : ''"
          class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 shadow-sm disabled:opacity-40 transition-all"
          @click="triggerPreflightWithSave"
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
      <button class="shrink-0 text-xs font-bold text-red-500 hover:text-red-800" @click="error = null">
        ✕
      </button>
    </div>

    <!-- 2-column layout -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">

      <!-- ── Left: form fields ──────────────────────────────── -->
      <div class="space-y-6">

        <!-- Campaign name + Subject card -->
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm space-y-5">
          <!-- Campaign name -->
          <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">Nom de la campagne</label>
            <input
              v-model="campaignName"
              type="text"
              placeholder="ex. Campagne relance clients Q2"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
            />
          </div>

          <!-- Subject -->
          <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">Sujet du mail</label>
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
            <p v-if="templateApplied" class="mt-1.5 text-xs font-bold text-emerald-600">
              Modèle appliqué.
            </p>
          </div>
        </div>

        <!-- Audience card -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Destinataires</h3>
              <p class="mt-0.5 text-xs font-medium text-slate-400">
                Sélectionnez les contacts à inclure dans cette campagne.
              </p>
            </div>
            <span
              v-if="selectedRecipients.length > 0"
              class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700"
            >
              {{ selectedRecipients.length }} destinataire(s)
            </span>
          </div>
          <div class="px-0">
            <CampaignAudiencePicker
              v-model="selectedRecipients"
              :initial-audiences="initialAudiences || null"
            />
          </div>
        </div>

        <!-- Text body -->
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

        <!-- HTML body (collapsible) -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <button
            type="button"
            class="flex w-full items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors"
            @click="htmlExpanded = !htmlExpanded"
          >
            <div class="flex items-center gap-3 text-left">
              <h3 class="text-sm font-bold text-slate-700">Version HTML</h3>
              <span
                class="rounded-md border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest text-slate-500"
              >
                Optionnel
              </span>
              <span v-if="form.htmlBody" class="text-xs font-medium text-slate-400">
                {{ htmlSizeKb }} Ko
              </span>
            </div>
            <svg
              :class="['h-4 w-4 text-slate-400 transition-transform', htmlExpanded ? 'rotate-180' : '']"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                fill-rule="evenodd"
                d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                clip-rule="evenodd"
              />
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
                  previewHtml
                    ? 'bg-slate-900 text-white'
                    : 'border border-slate-200 text-slate-600 hover:bg-slate-50',
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
              style="height: 22rem"
            />
          </div>
        </div>
      </div>

      <!-- ── Right panel ────────────────────────────────────── -->
      <div class="space-y-4 lg:sticky lg:top-6 self-start">

        <!-- Preflight -->
        <template v-if="preflight">
          <PreflightResult :result="preflight" />
        </template>
        <div v-else class="rounded-2xl border border-slate-200 bg-slate-50 px-6 py-5 text-center">
          <p class="text-sm font-bold text-slate-500">Vérification avant envoi</p>
          <p class="mt-1 text-xs font-medium text-slate-400">
            La campagne est sauvegardée automatiquement. Lancez la vérification pour évaluer la
            délivrabilité avant planification.
          </p>
          <button
            v-if="draftId"
            :disabled="preflightLoading"
            class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors disabled:opacity-40"
            @click="triggerPreflightWithSave"
          >
            {{ preflightLoading ? 'Analyse…' : 'Lancer la vérification' }}
          </button>
        </div>

        <!-- Schedule card -->
        <div class="rounded-2xl border border-blue-100 bg-blue-50 px-6 py-5 space-y-4">
          <div class="flex items-center justify-between">
            <p class="text-xs font-black uppercase tracking-[0.1em] text-blue-600">Planification</p>
            <button
              type="button"
              :class="[
                'rounded-lg px-3 py-1 text-xs font-bold transition-colors',
                showSchedule
                  ? 'bg-blue-600 text-white'
                  : 'border border-blue-200 text-blue-600 hover:bg-blue-100',
              ]"
              @click="showSchedule = !showSchedule"
            >
              {{ showSchedule ? 'Masquer' : 'Planifier' }}
            </button>
          </div>
          <template v-if="showSchedule">
            <div class="space-y-3">
              <div>
                <label class="mb-1 block text-xs font-bold text-blue-700">Date et heure d'envoi</label>
                <input
                  v-model="scheduledAt"
                  type="datetime-local"
                  :min="minScheduledAt"
                  class="w-full rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/30 outline-none"
                />
              </div>
              <p v-if="schedule_error" class="text-xs font-semibold text-red-600">
                {{ schedule_error }}
              </p>
              <button
                :disabled="!scheduledAt || scheduling || !draftId"
                class="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 transition-colors disabled:opacity-40"
                @click="scheduleWithSave"
              >
                {{ scheduling ? 'Planification…' : 'Confirmer la planification' }}
              </button>
              <p v-if="!draftId" class="text-center text-xs font-medium text-blue-400">
                Sauvegarde automatique en cours…
              </p>
            </div>
          </template>
        </div>

        <!-- Recipients summary -->
        <div
          v-if="selectedRecipients.length > 0"
          class="rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm"
        >
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">
            Audience sélectionnée
          </p>
          <p class="mt-1 text-sm font-bold text-slate-900">
            {{ selectedRecipients.length }} destinataire(s)
          </p>
          <p class="mt-0.5 text-xs font-medium text-slate-400">
            Chaque destinataire reçoit un mail individuel.
          </p>
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
              <strong>À :</strong> {{ selectedRecipients.length > 0 ? selectedRecipients.length + ' destinataire(s)' : '—' }}<br />
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
import CampaignAudiencePicker from '@/Components/Campaigns/CampaignAudiencePicker.vue';
import { formatDateFR } from '@/Utils/formatDate.js';

const props = defineProps({
  campaignId: { type: Number, default: null },
  draftId: { type: Number, default: null },
  initialName: { type: String, default: '' },
  initialSubject: { type: String, default: '' },
  initialTextBody: { type: String, default: '' },
  initialHtmlBody: { type: String, default: '' },
  initialTemplateId: { type: Number, default: null },
  initialRecipients: { type: Array, default: () => [] },
  initialAudiences: { type: Object, default: null },
  templates: { type: Array, default: () => [] },
});

const emit = defineEmits(['autosaved', 'scheduled', 'close']);

// ── IDs (updated after autosave) ───────────────────────────
const campaignId = ref(props.campaignId);
const draftId = ref(props.draftId);

// ── Form state ─────────────────────────────────────────────
const campaignName = ref(props.initialName || '');
const form = ref({
  subject: props.initialSubject || '',
  textBody: props.initialTextBody || '',
  htmlBody: props.initialHtmlBody || '',
  templateId: props.initialTemplateId || null,
});
const selectedRecipients = ref([...props.initialRecipients]);

// ── Autosave state ─────────────────────────────────────────
// 'idle' | 'pending' | 'saving' | 'saved' | 'error'
const autosaveStatus = ref(props.campaignId ? 'saved' : 'idle');
const autosaveError = ref(null);
const savedAt = ref(null);
const expectedUpdatedAt = ref(null);
let autosaveTimer = null;

// ── Preflight state ────────────────────────────────────────
const preflight = ref(null);
const preflightLoading = ref(false);

// ── Preview / HTML ─────────────────────────────────────────
const previewHtml = ref(false);
const htmlExpanded = ref(Boolean(props.initialHtmlBody));

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

// ── Template ───────────────────────────────────────────────
const selectedTemplateId = ref(props.initialTemplateId ? String(props.initialTemplateId) : '');
const templateApplied = ref(false);

// ── Error ─────────────────────────────────────────────────
const error = ref(null);

// ── Computed ───────────────────────────────────────────────
const activeTemplates = computed(() => props.templates.filter((t) => t.active !== false));

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

// ── Autosave trigger (watch everything) ───────────────────
function scheduleAutosave() {
  clearTimeout(autosaveTimer);
  autosaveStatus.value = 'pending';
  autosaveTimer = setTimeout(() => {
    performAutosave();
  }, 1500);
}

watch([campaignName, form, selectedRecipients], scheduleAutosave, { deep: true });

// ── Autosave API call ──────────────────────────────────────
async function performAutosave() {
  if (!form.value.subject.trim()) return; // subject is required

  autosaveStatus.value = 'saving';
  autosaveError.value = null;
  error.value = null;

  try {
    const payload = {
      type: 'bulk',
      campaignId: campaignId.value ?? undefined,
      draftId: draftId.value ?? undefined,
      name: campaignName.value || form.value.subject || 'Nouvelle campagne',
      subject: form.value.subject,
      htmlBody: form.value.htmlBody || null,
      textBody: form.value.textBody || null,
      templateId: form.value.templateId || null,
      expectedUpdatedAt: expectedUpdatedAt.value ?? undefined,
      recipients: selectedRecipients.value,
    };

    const resp = await axios.post('/api/campaigns/autosave', payload);
    campaignId.value = resp.data.campaign.id;
    draftId.value = resp.data.draft.id;
    expectedUpdatedAt.value = resp.data.campaign.lastEditedAt ?? resp.data.campaign.updatedAt ?? null;
    savedAt.value = new Date();
    autosaveStatus.value = 'saved';
    // Reset preflight since content changed
    preflight.value = null;
    emit('autosaved', resp.data);
  } catch (e) {
    autosaveStatus.value = 'error';
    const msg =
      e.response?.data?.message ??
      e.response?.data?.errors?.mailbox?.[0] ??
      'Erreur lors de la sauvegarde automatique.';
    autosaveError.value = msg;
  }
}

// ── Immediate save (before preflight / schedule) ──────────
async function triggerImmediateSave() {
  clearTimeout(autosaveTimer);
  if (autosaveStatus.value !== 'pending' && autosaveStatus.value !== 'saving') return;
  await performAutosave();
}

// ── Template ───────────────────────────────────────────────
function applyTemplate() {
  const tpl = props.templates.find((t) => t.id === Number(selectedTemplateId.value));
  if (!tpl) return;
  if (!form.value.subject) form.value.subject = tpl.subject ?? '';
  if (!form.value.htmlBody) form.value.htmlBody = tpl.htmlBody ?? '';
  if (!form.value.textBody) form.value.textBody = tpl.textBody ?? '';
  form.value.templateId = tpl.id;
  templateApplied.value = true;
  setTimeout(() => {
    templateApplied.value = false;
  }, 3000);
}

// ── Preflight ─────────────────────────────────────────────
async function triggerPreflightWithSave() {
  await triggerImmediateSave();
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

// ── Schedule ───────────────────────────────────────────────
async function scheduleWithSave() {
  await triggerImmediateSave();
  if (!scheduledAt.value || !draftId.value) return;

  scheduling.value = true;
  schedule_error.value = null;
  try {
    await axios.post(`/api/drafts/${draftId.value}/schedule`, {
      scheduledAt: scheduledAt.value,
    });
    emit('scheduled');
  } catch (e) {
    schedule_error.value =
      e.response?.data?.errors?.preflight?.[0] ??
      e.response?.data?.message ??
      'Erreur lors de la planification.';
  } finally {
    scheduling.value = false;
  }
}

// ── Send now ───────────────────────────────────────────────
async function sendNow() {
  await triggerImmediateSave();
  if (!draftId.value) return;

  sendingNow.value = true;
  error.value = null;
  try {
    await axios.post(`/api/drafts/${draftId.value}/send-now`);
    emit('scheduled');
  } catch (e) {
    error.value =
      e.response?.data?.errors?.preflight?.[0] ??
      e.response?.data?.message ??
      'Erreur lors de l\'envoi immédiat.';
  } finally {
    sendingNow.value = false;
  }
}

// ── Test mail ──────────────────────────────────────────────
async function sendTestMail() {
  await triggerImmediateSave();
  if (!draftId.value || !testEmail.value) return;

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
      message: e.response?.data?.message ?? 'Erreur lors de l\'envoi de test.',
    };
  } finally {
    testSending.value = false;
  }
}
</script>

<template>
  <!-- Full-page inline template editor — no backdrop, no fixed positioning -->
  <div class="flex flex-col gap-6">

    <!-- Action bar -->
    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div class="flex min-w-0 items-center gap-3">
        <h2 class="text-lg font-bold text-slate-900">
          {{ template ? 'Modifier le modèle' : 'Nouveau modèle' }}
        </h2>
        <span v-if="savedAt" class="shrink-0 text-xs font-bold text-emerald-600">Sauvegardé {{ savedAtLabel }}</span>
      </div>
      <div class="ml-auto flex items-center gap-3">
        <span v-if="!canSave && !saving" class="text-xs font-medium text-slate-400">Nom et sujet requis</span>
        <button class="text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors" @click="close">Annuler</button>
        <button
          :disabled="saving || !canSave"
          class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all disabled:opacity-40"
          @click="save"
        >
          {{ saving ? 'Sauvegarde…' : template ? 'Mettre à jour' : 'Créer le modèle' }}
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

    <!-- 2-column layout -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">

      <!-- ── Left: form fields ────────────────────── -->
      <div class="space-y-6">

        <!-- Name + Subject card -->
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm space-y-5">
          <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">
              Nom du modèle <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              placeholder="Ex : Premier contact"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
            />
          </div>
          <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">
              Sujet <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.subject"
              type="text"
              placeholder="Objet du message"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
            />
          </div>
        </div>

        <!-- ── TEXT BODY (primary) ──────────────── -->
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
              rows="12"
              placeholder="Rédigez votre message ici, en texte simple (sans balises HTML)…"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-relaxed placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
            />
          </div>
        </div>

        <!-- ── HTML BODY (secondary, collapsible) ── -->
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
              Si vide, une version HTML sera générée automatiquement à partir du texte brut lors de l'envoi.
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
              style="height: 24rem;"
            />
          </div>
        </div>

      </div>

      <!-- ── Right panel ──────────────────────────── -->
      <div class="lg:sticky lg:top-6 self-start space-y-4">

        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm space-y-4">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">
            {{ template ? 'Modification' : 'Création' }}
          </p>
          <div v-if="template" class="space-y-2">
            <p class="text-xs font-bold text-slate-500">
              Utilisé <span class="text-slate-900">{{ template.usageCount ?? 0 }}</span> fois
            </p>
            <p class="text-xs font-medium text-slate-400">Modifié : {{ template.updatedAt ?? '—' }}</p>
          </div>
          <div class="border-t border-slate-100 pt-4">
            <p class="text-xs font-medium text-slate-400 leading-relaxed">
              Le modèle sera disponible dans le compositeur pour un envoi rapide.
              La signature globale est appliquée automatiquement à l'envoi.
            </p>
          </div>
        </div>

        <div v-if="savedAt" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-4 shadow-sm">
          <p class="text-sm font-bold text-emerald-700">Sauvegardé</p>
          <p class="mt-0.5 text-xs font-medium text-emerald-600">{{ savedAtLabel }}</p>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  template: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
  name:     props.template?.name     ?? '',
  subject:  props.template?.subject  ?? '',
  textBody: props.template?.textBody ?? '',
  htmlBody: props.template?.htmlBody ?? '',
});

const saving      = ref(false);
const error       = ref(null);
const savedAt     = ref(null);
const previewHtml = ref(false);
const htmlExpanded = ref(Boolean(props.template?.htmlBody));

const canSave = computed(() =>
  form.value.name.trim().length > 0 && form.value.subject.trim().length > 0,
);

const savedAtLabel = computed(() => {
  if (!savedAt.value) return '';
  const diff = Math.round((Date.now() - savedAt.value) / 1000);
  if (diff < 60) return "à l'instant";
  return `il y a ${Math.round(diff / 60)} min`;
});

const htmlSizeKb = computed(() =>
  Math.round((form.value.htmlBody?.length ?? 0) / 1024 * 10) / 10,
);

function close() {
  emit('close');
}

async function save() {
  if (!canSave.value || saving.value) return;
  saving.value = true;
  error.value = null;
  try {
    let response;
    if (props.template?.id) {
      response = await axios.put(`/api/templates/${props.template.id}`, form.value);
    } else {
      response = await axios.post('/api/templates', form.value);
    }
    savedAt.value = Date.now();
    emit('saved', response.data.template);
  } catch (err) {
    error.value = err.response?.data?.message ?? 'Une erreur est survenue lors de la sauvegarde.';
  } finally {
    saving.value = false;
  }
}
</script>

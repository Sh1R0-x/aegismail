<template>
  <div class="space-y-6">

    <!-- Banner -->
    <div
      v-if="banner"
      :class="[
        'flex items-center justify-between rounded-xl border px-5 py-3 text-sm font-medium',
        banner.type === 'success'
          ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
          : 'border-red-200 bg-red-50 text-red-800',
      ]"
    >
      <span>{{ banner.message }}</span>
      <button class="shrink-0 text-xs font-bold opacity-60 hover:opacity-100" @click="banner = null">✕</button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="flex items-center gap-3 text-sm text-slate-500 font-medium">
      <svg class="h-4 w-4 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
      Chargement des réglages de délivrabilité…
    </div>

    <template v-else>

      <!-- URLs publiques -->
      <SectionCard title="URLs publiques email" subtitle="Les URLs de base utilisées pour les liens, images et le tracking dans vos messages sortants">

        <!-- publicBaseUrl -->
        <div class="space-y-4">
          <div>
            <div class="mb-1 flex items-center justify-between">
              <label class="text-sm font-bold text-slate-700">URL publique email</label>
              <UrlStatusBadge :status="form.publicBaseUrlStatus" />
            </div>
            <input
              v-model="form.public_base_url"
              type="url"
              placeholder="https://votre-domaine.fr"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
            />
            <p class="mt-1 text-xs text-slate-400">
              URL de base résolue pour les liens relatifs et les images dans vos emails.
              Laissez vide pour utiliser <code class="font-mono bg-slate-100 px-1 rounded">APP_URL</code>.
            </p>
            <UrlIssueAlert v-if="form.publicBaseUrlIssue" :issue="form.publicBaseUrlIssue" :resolved="form.publicBaseUrlResolved" />
          </div>

          <!-- tracking_base_url -->
          <div class="border-t border-slate-100 pt-4">
            <div class="mb-1 flex items-center justify-between">
              <label class="text-sm font-bold text-slate-700">URL publique de tracking</label>
              <UrlStatusBadge :status="form.trackingBaseUrlStatus" />
            </div>
            <input
              v-model="form.tracking_base_url"
              type="url"
              placeholder="https://track.votre-domaine.fr"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
            />
            <p class="mt-1 text-xs text-slate-400">
              URL de base pour les pixels de tracking d'ouverture, les redirecteurs de clic et le désinscription bulk.
              Laissez vide pour réutiliser l'URL publique email ci-dessus.
            </p>
            <UrlIssueAlert v-if="form.trackingBaseUrlIssue" :issue="form.trackingBaseUrlIssue" :resolved="form.trackingBaseUrlResolved" />
          </div>
        </div>

        <!-- Resolved URL summary when valid -->
        <div v-if="form.publicBaseUrlStatus === 'valid' || form.trackingBaseUrlStatus === 'valid'" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 space-y-1">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-emerald-700 mb-1">URLs résolues actives</p>
          <div v-if="form.publicBaseUrlStatus === 'valid'" class="flex items-center gap-2 text-xs font-medium text-emerald-800">
            <span class="shrink-0 font-black">✓</span>
            <span>Email : <a :href="form.publicBaseUrlResolved" target="_blank" rel="noopener" class="underline break-all">{{ form.publicBaseUrlResolved }}</a></span>
          </div>
          <div v-if="form.trackingBaseUrlStatus === 'valid'" class="flex items-center gap-2 text-xs font-medium text-emerald-800">
            <span class="shrink-0 font-black">✓</span>
            <span>Tracking : <a :href="form.trackingBaseUrlResolved" target="_blank" rel="noopener" class="underline break-all">{{ form.trackingBaseUrlResolved }}</a></span>
          </div>
        </div>
      </SectionCard>

      <!-- Tracking options -->
      <SectionCard title="Options de tracking" subtitle="Activer ou désactiver le tracking d'ouverture et de clic">
        <ToggleRow label="Tracking des ouvertures" v-model="form.tracking_opens_enabled" />
        <ToggleRow label="Tracking des clics" v-model="form.tracking_clicks_enabled" class="mt-3" />
      </SectionCard>

      <!-- Thresholds -->
      <SectionCard title="Seuils de vigilance" subtitle="Valeurs au-dessus desquelles le preflight lève un avertissement">
        <div class="grid grid-cols-2 gap-4">
          <FieldGroup label="Nombre max de liens" v-model="form.max_links_warning_threshold" type="number" suffix="liens" />
          <FieldGroup label="Nombre max d'images distantes" v-model="form.max_remote_images_warning_threshold" type="number" suffix="images" />
          <FieldGroup label="Taille HTML maximum" v-model="form.html_size_warning_kb" type="number" suffix="Ko" />
          <FieldGroup label="Poids pièces jointes" v-model="form.attachment_size_warning_mb" type="number" suffix="Mo" />
        </div>
      </SectionCard>

      <!-- Save -->
      <div class="flex items-center gap-3">
        <button
          :disabled="saving"
          class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all disabled:opacity-40"
          @click="save"
        >
          {{ saving ? 'Sauvegarde…' : 'Enregistrer les réglages de délivrabilité' }}
        </button>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import SectionCard from './SectionCard.vue';
import FieldGroup from './FieldGroup.vue';
import ToggleRow from './ToggleRow.vue';

// ── Sub-components ────────────────────────────────────────────────────────────

/** Small badge showing the status of a base URL (valid/invalid/missing). */
const UrlStatusBadge = {
  props: { status: { type: String, default: null } },
  template: `
    <span v-if="status" :class="[
      'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
      status === 'valid'   ? 'bg-emerald-100 text-emerald-700' :
      status === 'missing' ? 'bg-slate-100 text-slate-500' :
                             'bg-red-100 text-red-700',
    ]">
      {{ status === 'valid' ? 'Valide' : status === 'missing' ? 'Non configurée' : 'Invalide' }}
    </span>
  `,
};

/** Alert shown below a URL field when the backend reports an issue. */
const UrlIssueAlert = {
  props: {
    issue: { type: String, required: true },
    resolved: { type: String, default: null },
  },
  setup(props) {
    const labelMap = {
      public_base_url_not_https: 'L\'URL doit être en HTTPS pour être utilisable dans les emails sortants.',
      public_base_url_not_public: 'L\'URL pointe vers un hôte local, privé ou non routable — elle ne sera pas accessible par les destinataires.',
      public_base_url_missing: 'Aucune URL publique n\'est configurée. Les liens relatifs et le tracking seront bloqués.',
    };
    const label = (key) => labelMap[key] ?? 'Cette URL présente un problème de délivrabilité.';
    return { label };
  },
  template: `
    <div class="mt-1.5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
      <span class="shrink-0 mt-0.5 font-black">!</span>
      <span>{{ label(issue) }}</span>
    </div>
  `,
};

// ── State ─────────────────────────────────────────────────────────────────────

const loading = ref(true);
const saving = ref(false);
const banner = ref(null);

const form = ref({
  public_base_url: '',
  tracking_base_url: '',
  tracking_opens_enabled: true,
  tracking_clicks_enabled: true,
  max_links_warning_threshold: 8,
  max_remote_images_warning_threshold: 3,
  html_size_warning_kb: 100,
  attachment_size_warning_mb: 10,
  // Read-only status fields (not sent to API):
  publicBaseUrlStatus: null,
  trackingBaseUrlStatus: null,
  publicBaseUrlIssue: null,
  trackingBaseUrlIssue: null,
  publicBaseUrlResolved: null,
  trackingBaseUrlResolved: null,
});

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(async () => {
  try {
    const resp = await axios.get('/api/settings');
    hydrateForm(resp.data.deliverability ?? {});
  } catch {
    banner.value = { type: 'error', message: 'Impossible de charger les réglages de délivrabilité.' };
  } finally {
    loading.value = false;
  }
});

// Auto-dismiss success banner
watch(banner, (val) => {
  if (val?.type === 'success') {
    setTimeout(() => { if (banner.value?.type === 'success') banner.value = null; }, 5000);
  }
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function hydrateForm(d) {
  form.value.public_base_url = d.public_base_url ?? '';
  form.value.tracking_base_url = d.tracking_base_url ?? '';
  form.value.tracking_opens_enabled = d.trackOpens ?? d.tracking_opens_enabled ?? true;
  form.value.tracking_clicks_enabled = d.trackClicks ?? d.tracking_clicks_enabled ?? true;
  form.value.max_links_warning_threshold = d.maxLinks ?? d.max_links_warning_threshold ?? 8;
  form.value.max_remote_images_warning_threshold = d.maxImages ?? d.max_remote_images_warning_threshold ?? 3;
  form.value.html_size_warning_kb = d.maxHtmlSizeKb ?? d.html_size_warning_kb ?? 100;
  form.value.attachment_size_warning_mb = d.maxAttachmentSizeMb ?? d.attachment_size_warning_mb ?? 10;
  // Status fields (read-only, computed by backend)
  form.value.publicBaseUrlStatus = d.publicBaseUrlStatus ?? null;
  form.value.trackingBaseUrlStatus = d.trackingBaseUrlStatus ?? null;
  form.value.publicBaseUrlIssue = d.publicBaseUrlIssue ?? null;
  form.value.trackingBaseUrlIssue = d.trackingBaseUrlIssue ?? null;
  form.value.publicBaseUrlResolved = d.publicBaseUrl ?? null;
  form.value.trackingBaseUrlResolved = d.trackingBaseUrl ?? null;
}

function toApiPayload() {
  return {
    tracking_opens_enabled: form.value.tracking_opens_enabled,
    tracking_clicks_enabled: form.value.tracking_clicks_enabled,
    max_links_warning_threshold: form.value.max_links_warning_threshold,
    max_remote_images_warning_threshold: form.value.max_remote_images_warning_threshold,
    html_size_warning_kb: form.value.html_size_warning_kb,
    attachment_size_warning_mb: form.value.attachment_size_warning_mb,
    public_base_url: form.value.public_base_url || null,
    tracking_base_url: form.value.tracking_base_url || null,
  };
}

// ── Actions ───────────────────────────────────────────────────────────────────

async function save() {
  saving.value = true;
  banner.value = null;
  try {
    const resp = await axios.put('/api/settings/deliverability', toApiPayload());
    hydrateForm(resp.data.deliverability ?? {});
    banner.value = { type: 'success', message: 'Réglages de délivrabilité enregistrés.' };
  } catch (e) {
    const errs = e.response?.data?.errors;
    const msg = errs
      ? Object.values(errs).flat().join(' ')
      : (e.response?.data?.message ?? 'Erreur lors de la sauvegarde.');
    banner.value = { type: 'error', message: msg };
  } finally {
    saving.value = false;
  }
}
</script>

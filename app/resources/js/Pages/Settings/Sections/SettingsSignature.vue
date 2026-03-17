<template>
  <div class="space-y-6">
    <SectionCard title="Signature globale" subtitle="Appliquée automatiquement à tous les e-mails sortants">

      <!-- Banner -->
      <div
        v-if="banner"
        :class="[
          'mb-3 flex items-center justify-between rounded-xl border px-5 py-3 text-sm font-medium',
          banner.type === 'success'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
            : 'border-red-200 bg-red-50 text-red-800',
        ]"
      >
        <span>{{ banner.message }}</span>
        <button class="shrink-0 text-xs font-bold opacity-60 hover:opacity-100" @click="banner = null">✕</button>
      </div>

      <div>
        <div class="mb-1 flex items-center justify-between">
          <label class="text-sm font-bold text-slate-700">Contenu de la signature (HTML)</label>
          <button
            type="button"
            :class="[
              'rounded-xl px-3 py-1 text-xs font-bold transition-colors',
              previewMode
                ? 'bg-slate-900 text-white'
                : 'border border-slate-200 text-slate-600 hover:bg-slate-50',
            ]"
            @click="previewMode = !previewMode"
          >
            {{ previewMode ? 'Éditer' : 'Aperçu' }}
          </button>
        </div>
        <textarea
          v-if="!previewMode"
          v-model="signatureHtml"
          rows="6"
          class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-mono placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          placeholder="<p>Cordialement,<br>AEGIS Network</p>"
        />
        <iframe
          v-else
          :srcdoc="signatureHtml || '<p style=\'color:#9ca3af;font-family:sans-serif;padding:1rem\'>Aperçu vide</p>'"
          sandbox="allow-same-origin"
          class="w-full rounded-xl border border-slate-200 bg-white"
          style="height: 8rem;"
        />
      </div>

      <div class="mt-4">
        <label class="mb-1 block text-sm font-bold text-slate-700">Version texte</label>
        <textarea
          v-model="signatureText"
          rows="3"
          class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          placeholder="Cordialement, AEGIS Network"
        />
      </div>

      <template #footer>
        <div class="flex items-center gap-3">
          <button
            :disabled="saving"
            class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all disabled:opacity-40"
            @click="save"
          >
            {{ saving ? 'Sauvegarde…' : 'Enregistrer la signature' }}
          </button>
          <span class="text-xs font-medium text-slate-400">Mergée dans tous vos e-mails sortants</span>
        </div>
      </template>
    </SectionCard>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import SectionCard from './SectionCard.vue';

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
});

const signatureHtml = ref(props.settings.global_signature_html ?? props.settings.html ?? '');
const signatureText = ref(props.settings.global_signature_text ?? props.settings.text ?? '');
const previewMode = ref(false);
const saving = ref(false);
const banner = ref(null);

watch(() => props.settings, (s) => {
  if (!s) return;
  signatureHtml.value = s.global_signature_html ?? s.html ?? signatureHtml.value;
  signatureText.value = s.global_signature_text ?? s.text ?? signatureText.value;
});

async function save() {
  saving.value = true;
  banner.value = null;
  try {
    // Fetch current mail settings first, then merge signature fields
    const { data: current } = await axios.get('/api/settings').catch(() => ({ data: { mail: {} } }));
    const mailSettings = current.mail ?? {};

    await axios.put('/api/settings/mail', {
      sender_email:          mailSettings.sender_email        ?? '',
      sender_name:           mailSettings.sender_name         ?? '',
      mailbox_username:      mailSettings.mailbox_username    ?? '',
      imap_host:             mailSettings.imap_host           ?? '',
      imap_port:             mailSettings.imap_port           ?? 993,
      imap_secure:           mailSettings.imap_secure         ?? true,
      smtp_host:             mailSettings.smtp_host           ?? '',
      smtp_port:             mailSettings.smtp_port           ?? 465,
      smtp_secure:           mailSettings.smtp_secure         ?? true,
      sync_enabled:          mailSettings.sync_enabled        ?? true,
      send_enabled:          mailSettings.send_enabled        ?? true,
      send_window_start:     mailSettings.send_window_start   ?? '08:00',
      send_window_end:       mailSettings.send_window_end     ?? '19:00',
      global_signature_html: signatureHtml.value || null,
      global_signature_text: signatureText.value || null,
    });
    banner.value = { type: 'success', message: 'Signature enregistrée.' };
  } catch (e) {
    const errs = e.response?.data?.errors;
    const msg = errs
      ? Object.values(errs).flat().join(' ')
      : (e.response?.data?.message ?? 'Erreur lors de la sauvegarde. Vérifiez que vos paramètres mail sont configurés.');
    banner.value = { type: 'error', message: msg };
  } finally {
    saving.value = false;
  }
}
</script>

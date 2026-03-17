<template>
  <div class="space-y-6">

    <!-- Status banner -->
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

    <!-- SMTP -->
    <SectionCard title="Connexion SMTP" subtitle="Configuration d'envoi OVH MX Plan">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Hôte SMTP</label>
          <input v-model="form.smtpHost" type="text" placeholder="ssl0.ovh.net"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Port SMTP</label>
          <input v-model.number="form.smtpPort" type="number" placeholder="465"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Identifiant</label>
          <input v-model="form.mailboxUsername" type="text" placeholder="contact@domaine.fr"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Mot de passe</label>
          <input v-model="form.mailboxPassword" type="password" placeholder="••••••••"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
        <div class="flex items-center gap-2 pt-1">
          <input id="smtpSecure" v-model="form.smtpSecure" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          <label for="smtpSecure" class="text-sm font-medium text-slate-700">SSL/TLS (port 465)</label>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center gap-3">
          <button
            :disabled="testingSmtp"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm disabled:opacity-40"
            @click="testSmtp"
          >
            {{ testingSmtp ? 'Test en cours…' : 'Tester SMTP' }}
          </button>
          <span v-if="smtpTestResult" :class="smtpTestResult.ok ? 'text-xs font-bold text-emerald-700' : 'text-xs font-bold text-red-700'">
            {{ smtpTestResult.ok ? '✓ ' : '✕ ' }}{{ smtpTestResult.message }}
          </span>
        </div>
      </template>
    </SectionCard>

    <!-- IMAP -->
    <SectionCard title="Connexion IMAP" subtitle="Synchronisation et lecture des réponses">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Hôte IMAP</label>
          <input v-model="form.imapHost" type="text" placeholder="ssl0.ovh.net"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Port IMAP</label>
          <input v-model.number="form.imapPort" type="number" placeholder="993"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
        <div class="flex items-center gap-2 pt-1">
          <input id="imapSecure" v-model="form.imapSecure" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          <label for="imapSecure" class="text-sm font-medium text-slate-700">SSL/TLS (port 993)</label>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center gap-3">
          <button
            :disabled="testingImap"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm disabled:opacity-40"
            @click="testImap"
          >
            {{ testingImap ? 'Test en cours…' : 'Tester IMAP' }}
          </button>
          <span v-if="imapTestResult" :class="imapTestResult.ok ? 'text-xs font-bold text-emerald-700' : 'text-xs font-bold text-red-700'">
            {{ imapTestResult.ok ? '✓ ' : '✕ ' }}{{ imapTestResult.message }}
          </span>
        </div>
      </template>
    </SectionCard>

    <!-- Expéditeur -->
    <SectionCard title="Expéditeur" subtitle="Identité des messages sortants">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Adresse d'envoi</label>
          <input v-model="form.senderEmail" type="email" placeholder="contact@domaine.fr"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Nom d'expéditeur</label>
          <input v-model="form.senderName" type="text" placeholder="AEGIS Network"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
      </div>
    </SectionCard>

    <!-- Fenêtre d'envoi -->
    <SectionCard title="Fenêtre d'envoi" subtitle="Heures autorisées pour l'envoi">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Début</label>
          <input v-model="form.sendWindowStart" type="time"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Fin</label>
          <input v-model="form.sendWindowEnd" type="time"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
        </div>
      </div>
      <div class="mt-3 flex items-center gap-4">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
          <input v-model="form.syncEnabled" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          Synchronisation IMAP activée
        </label>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
          <input v-model="form.sendEnabled" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          Envoi activé
        </label>
      </div>
    </SectionCard>

    <!-- Save footer -->
    <div class="flex items-center gap-3">
      <button
        :disabled="saving"
        class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all disabled:opacity-40"
        @click="save"
      >
        {{ saving ? 'Sauvegarde…' : 'Enregistrer les paramètres mail' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import SectionCard from './SectionCard.vue';

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
});

// ── Form state (camelCase display → snake_case for API) ────
const form = ref({
  smtpHost:        props.settings.smtp_host        ?? props.settings.smtpHost        ?? '',
  smtpPort:        props.settings.smtp_port        ?? props.settings.smtpPort        ?? 465,
  smtpSecure:      props.settings.smtp_secure      ?? props.settings.smtpSecure      ?? true,
  imapHost:        props.settings.imap_host        ?? props.settings.imapHost        ?? '',
  imapPort:        props.settings.imap_port        ?? props.settings.imapPort        ?? 993,
  imapSecure:      props.settings.imap_secure      ?? props.settings.imapSecure      ?? true,
  mailboxUsername: props.settings.mailbox_username ?? props.settings.mailboxUsername ?? '',
  mailboxPassword: '',  // never pre-filled for security
  senderEmail:     props.settings.sender_email     ?? props.settings.senderEmail     ?? '',
  senderName:      props.settings.sender_name      ?? props.settings.senderName      ?? '',
  sendWindowStart: props.settings.send_window_start ?? props.settings.sendWindowStart ?? '08:00',
  sendWindowEnd:   props.settings.send_window_end   ?? props.settings.sendWindowEnd   ?? '19:00',
  syncEnabled:     props.settings.sync_enabled     ?? props.settings.syncEnabled     ?? true,
  sendEnabled:     props.settings.send_enabled     ?? props.settings.sendEnabled     ?? true,
});

// Re-hydrate if parent updates settings
watch(() => props.settings, (s) => {
  if (!s) return;
  form.value.smtpHost        = s.smtp_host        ?? s.smtpHost        ?? form.value.smtpHost;
  form.value.smtpPort        = s.smtp_port        ?? s.smtpPort        ?? form.value.smtpPort;
  form.value.smtpSecure      = s.smtp_secure      ?? s.smtpSecure      ?? form.value.smtpSecure;
  form.value.imapHost        = s.imap_host        ?? s.imapHost        ?? form.value.imapHost;
  form.value.imapPort        = s.imap_port        ?? s.imapPort        ?? form.value.imapPort;
  form.value.imapSecure      = s.imap_secure      ?? s.imapSecure      ?? form.value.imapSecure;
  form.value.mailboxUsername = s.mailbox_username ?? s.mailboxUsername ?? form.value.mailboxUsername;
  form.value.senderEmail     = s.sender_email     ?? s.senderEmail     ?? form.value.senderEmail;
  form.value.senderName      = s.sender_name      ?? s.senderName      ?? form.value.senderName;
  form.value.sendWindowStart = s.send_window_start ?? s.sendWindowStart ?? form.value.sendWindowStart;
  form.value.sendWindowEnd   = s.send_window_end   ?? s.sendWindowEnd   ?? form.value.sendWindowEnd;
  form.value.syncEnabled     = s.sync_enabled     ?? s.syncEnabled     ?? form.value.syncEnabled;
  form.value.sendEnabled     = s.send_enabled     ?? s.sendEnabled     ?? form.value.sendEnabled;
}, { immediate: false });

const saving       = ref(false);
const testingSmtp  = ref(false);
const testingImap  = ref(false);
const smtpTestResult = ref(null);
const imapTestResult = ref(null);
const banner       = ref(null);

function toApiPayload() {
  return {
    sender_email:        form.value.senderEmail,
    sender_name:         form.value.senderName,
    mailbox_username:    form.value.mailboxUsername,
    ...(form.value.mailboxPassword ? { mailbox_password: form.value.mailboxPassword } : {}),
    imap_host:           form.value.imapHost,
    imap_port:           form.value.imapPort,
    imap_secure:         form.value.imapSecure,
    smtp_host:           form.value.smtpHost,
    smtp_port:           form.value.smtpPort,
    smtp_secure:         form.value.smtpSecure,
    sync_enabled:        form.value.syncEnabled,
    send_enabled:        form.value.sendEnabled,
    send_window_start:   form.value.sendWindowStart,
    send_window_end:     form.value.sendWindowEnd,
    global_signature_html: null,
    global_signature_text: null,
  };
}

async function save() {
  saving.value = true;
  banner.value = null;
  try {
    await axios.put('/api/settings/mail', toApiPayload());
    banner.value = { type: 'success', message: 'Paramètres mail enregistrés.' };
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

async function testSmtp() {
  testingSmtp.value = true;
  smtpTestResult.value = null;
  try {
    const resp = await axios.post('/api/settings/mail/test-smtp', {
      sender_email:     form.value.senderEmail  || undefined,
      mailbox_username: form.value.mailboxUsername || undefined,
      mailbox_password: form.value.mailboxPassword || undefined,
      smtp_host:        form.value.smtpHost      || undefined,
      smtp_port:        form.value.smtpPort      || undefined,
      smtp_secure:      form.value.smtpSecure,
    });
    smtpTestResult.value = { ok: true, message: resp.data.message ?? 'Connexion réussie' };
  } catch (e) {
    smtpTestResult.value = { ok: false, message: e.response?.data?.message ?? 'Connexion échouée' };
  } finally {
    testingSmtp.value = false;
  }
}

async function testImap() {
  testingImap.value = true;
  imapTestResult.value = null;
  try {
    const resp = await axios.post('/api/settings/mail/test-imap', {
      mailbox_username: form.value.mailboxUsername || undefined,
      mailbox_password: form.value.mailboxPassword || undefined,
      imap_host:        form.value.imapHost       || undefined,
      imap_port:        form.value.imapPort       || undefined,
      imap_secure:      form.value.imapSecure,
    });
    imapTestResult.value = { ok: true, message: resp.data.message ?? 'Connexion réussie' };
  } catch (e) {
    imapTestResult.value = { ok: false, message: e.response?.data?.message ?? 'Connexion échouée' };
  } finally {
    testingImap.value = false;
  }
}
</script>

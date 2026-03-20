<template>
  <div class="space-y-6">
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

    <SectionCard
      title="Provider SMTP actif"
      subtitle="Utilisé pour les nouveaux brouillons, campagnes et tests SMTP"
    >
      <div class="grid gap-3 md:grid-cols-2">
        <button
          v-for="provider in providerOrder"
          :key="provider"
          type="button"
          :class="[
            'rounded-xl border px-4 py-4 text-left transition-colors',
            form.activeProvider === provider
              ? 'border-slate-900 bg-slate-900 text-white'
              : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50',
          ]"
          @click="form.activeProvider = provider"
        >
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-sm font-bold">{{ providerLabel(provider) }}</p>
              <p
                :class="[
                  'mt-1 text-xs',
                  form.activeProvider === provider ? 'text-slate-300' : 'text-slate-500',
                ]"
              >
                {{ providerHelp(provider) }}
              </p>
            </div>
            <span
              :class="[
                'rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide',
                providerStatusClass(provider, form.activeProvider === provider),
              ]"
            >
              {{ providerStatusLabel(provider) }}
            </span>
          </div>
        </button>
      </div>
      <template #footer>
        <p class="text-xs font-medium text-slate-500">
          Le provider actif est figé sur chaque brouillon au moment de sa création. Changer ce réglage n’altère pas les brouillons déjà créés.
        </p>
      </template>
    </SectionCard>

    <SectionCard title="Boîte OVH et IMAP" subtitle="Identité mailbox et synchronisation des réponses">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Adresse d'envoi</label>
          <input
            v-model="form.senderEmail"
            type="email"
            placeholder="contact@domaine.fr"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Nom d'expéditeur</label>
          <input
            v-model="form.senderName"
            type="text"
            placeholder="AEGIS Network"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Identifiant mailbox</label>
          <input
            v-model="form.mailboxUsername"
            type="text"
            placeholder="contact@domaine.fr"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
          <p class="mt-1 text-xs text-slate-400">Utilisé pour l'IMAP OVH et, par défaut, pour le SMTP OVH.</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Mot de passe mailbox</label>
          <input
            v-model="form.mailboxPassword"
            type="password"
            placeholder="••••••••"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
          <p class="mt-1 text-xs text-slate-400">
            {{ mailboxPasswordHint }}
          </p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Hôte IMAP</label>
          <input
            v-model="form.imapHost"
            type="text"
            placeholder="imap.mail.ovh.net"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Port IMAP</label>
          <input
            v-model.number="form.imapPort"
            type="number"
            placeholder="993"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <label class="flex items-center gap-2 pt-1 text-sm font-medium text-slate-700">
          <input v-model="form.imapSecure" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          SSL/TLS IMAP
        </label>
        <label class="flex items-center gap-2 pt-1 text-sm font-medium text-slate-700">
          <input v-model="form.syncEnabled" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          Synchronisation IMAP activée
        </label>
      </div>
      <template #footer>
        <div class="space-y-2">
          <button
            :disabled="testingImap"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm disabled:opacity-40"
            @click="testImap"
          >
            {{ testingImap ? 'Test en cours…' : 'Tester IMAP OVH' }}
          </button>
          <div
            v-if="imapTestResult"
            :class="[
              'rounded-lg border px-3 py-2 text-xs font-medium max-w-lg',
              imapTestResult.ok
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-red-200 bg-red-50 text-red-800',
            ]"
          >
            <div class="flex items-start gap-2">
              <span class="shrink-0 text-sm leading-none mt-0.5">{{ imapTestResult.ok ? '✓' : '✕' }}</span>
              <span>{{ imapTestResult.message }}</span>
            </div>
            <div v-if="imapTestResult.details" class="mt-1.5 pl-5 space-y-0.5 text-[11px] opacity-80">
              <p v-if="imapTestResult.details.tested_host">Hôte : {{ imapTestResult.details.tested_host }}:{{ imapTestResult.details.tested_port }} {{ imapTestResult.details.tested_secure ? '(SSL)' : '(non chiffré)' }}</p>
              <p v-if="imapTestResult.details.provider_label">Provider : {{ imapTestResult.details.provider_label }}</p>
              <p v-if="imapTestResult.details.failure_stage">Phase d'échec : {{ imapTestResult.details.failure_stage }}</p>
              <p v-if="imapTestResult.details.technical_detail">Détail : {{ imapTestResult.details.technical_detail }}</p>
              <p v-if="imapTestResult.details.tested_at" class="text-[10px] opacity-60">Testé à {{ imapTestResult.details.tested_at }}</p>
            </div>
          </div>
        </div>
      </template>
    </SectionCard>

    <SectionCard title="SMTP OVH" subtitle="Provider SMTP historique lié à la boîte OVH">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Hôte SMTP</label>
          <input
            v-model="form.ovh.smtpHost"
            type="text"
            placeholder="smtp.mail.ovh.net"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Port SMTP</label>
          <input
            v-model.number="form.ovh.smtpPort"
            type="number"
            placeholder="465"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Identifiant SMTP OVH</label>
          <input
            :value="form.mailboxUsername"
            type="text"
            readonly
            class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-500 outline-none"
          />
        </div>
        <div class="flex items-center gap-2 pt-7">
          <input id="smtpSecureOvh" v-model="form.ovh.smtpSecure" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          <label for="smtpSecureOvh" class="text-sm font-medium text-slate-700">SSL/TLS SMTP</label>
        </div>
      </div>
      <template #footer>
        <div class="space-y-2">
          <button
            :disabled="testingSmtp.ovh_mx_plan"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm disabled:opacity-40"
            @click="testSmtp('ovh_mx_plan')"
          >
            {{ testingSmtp.ovh_mx_plan ? 'Test en cours…' : 'Tester SMTP OVH' }}
          </button>
          <div
            v-if="smtpTestResults.ovh_mx_plan"
            :class="[
              'rounded-lg border px-3 py-2 text-xs font-medium max-w-lg',
              smtpTestResults.ovh_mx_plan.ok
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-red-200 bg-red-50 text-red-800',
            ]"
          >
            <div class="flex items-start gap-2">
              <span class="shrink-0 text-sm leading-none mt-0.5">{{ smtpTestResults.ovh_mx_plan.ok ? '✓' : '✕' }}</span>
              <span>{{ smtpTestResults.ovh_mx_plan.message }}</span>
            </div>
            <div v-if="smtpTestResults.ovh_mx_plan.details" class="mt-1.5 pl-5 space-y-0.5 text-[11px] opacity-80">
              <p v-if="smtpTestResults.ovh_mx_plan.details.tested_host">Hôte : {{ smtpTestResults.ovh_mx_plan.details.tested_host }}:{{ smtpTestResults.ovh_mx_plan.details.tested_port }} {{ smtpTestResults.ovh_mx_plan.details.tested_secure ? '(SSL)' : '(non chiffré)' }}</p>
              <p v-if="smtpTestResults.ovh_mx_plan.details.provider_label">Provider : {{ smtpTestResults.ovh_mx_plan.details.provider_label }}</p>
              <p v-if="smtpTestResults.ovh_mx_plan.details.failure_stage">Phase d'échec : {{ smtpTestResults.ovh_mx_plan.details.failure_stage }}</p>
              <p v-if="smtpTestResults.ovh_mx_plan.details.technical_detail">Détail : {{ smtpTestResults.ovh_mx_plan.details.technical_detail }}</p>
              <p v-if="smtpTestResults.ovh_mx_plan.details.tested_at" class="text-[10px] opacity-60">Testé à {{ smtpTestResults.ovh_mx_plan.details.tested_at }}</p>
            </div>
          </div>
        </div>
      </template>
    </SectionCard>

    <SectionCard title="SMTP2GO" subtitle="Provider SMTP secondaire, séparé d'OVH">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Hôte SMTP</label>
          <input
            v-model="form.smtp2go.smtpHost"
            type="text"
            placeholder="mail.smtp2go.com"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Port SMTP</label>
          <input
            v-model.number="form.smtp2go.smtpPort"
            type="number"
            placeholder="587"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Identifiant SMTP2GO</label>
          <input
            v-model="form.smtp2go.smtpUsername"
            type="text"
            placeholder="smtp2go-user"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Mot de passe SMTP2GO</label>
          <input
            v-model="form.smtp2go.smtpPassword"
            type="password"
            placeholder="••••••••"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
          <p class="mt-1 text-xs text-slate-400">{{ smtp2goPasswordHint }}</p>
        </div>
        <label class="flex items-center gap-2 pt-1 text-sm font-medium text-slate-700">
          <input v-model="form.smtp2go.smtpSecure" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          TLS/SSL SMTP2GO
        </label>
        <label class="flex items-center gap-2 pt-1 text-sm font-medium text-slate-700">
          <input v-model="form.smtp2go.sendEnabled" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          Provider SMTP2GO activé
        </label>
      </div>
      <template #footer>
        <div class="space-y-2">
          <button
            :disabled="testingSmtp.smtp2go"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm disabled:opacity-40"
            @click="testSmtp('smtp2go')"
          >
            {{ testingSmtp.smtp2go ? 'Test en cours…' : 'Tester SMTP2GO' }}
          </button>
          <div
            v-if="smtpTestResults.smtp2go"
            :class="[
              'rounded-lg border px-3 py-2 text-xs font-medium max-w-lg',
              smtpTestResults.smtp2go.ok
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-red-200 bg-red-50 text-red-800',
            ]"
          >
            <div class="flex items-start gap-2">
              <span class="shrink-0 text-sm leading-none mt-0.5">{{ smtpTestResults.smtp2go.ok ? '✓' : '✕' }}</span>
              <span>{{ smtpTestResults.smtp2go.message }}</span>
            </div>
            <div v-if="smtpTestResults.smtp2go.details" class="mt-1.5 pl-5 space-y-0.5 text-[11px] opacity-80">
              <p v-if="smtpTestResults.smtp2go.details.tested_host">Hôte : {{ smtpTestResults.smtp2go.details.tested_host }}:{{ smtpTestResults.smtp2go.details.tested_port }} {{ smtpTestResults.smtp2go.details.tested_secure ? '(SSL)' : '(non chiffré)' }}</p>
              <p v-if="smtpTestResults.smtp2go.details.provider_label">Provider : {{ smtpTestResults.smtp2go.details.provider_label }}</p>
              <p v-if="smtpTestResults.smtp2go.details.failure_stage">Phase d'échec : {{ smtpTestResults.smtp2go.details.failure_stage }}</p>
              <p v-if="smtpTestResults.smtp2go.details.technical_detail">Détail : {{ smtpTestResults.smtp2go.details.technical_detail }}</p>
              <p v-if="smtpTestResults.smtp2go.details.tested_at" class="text-[10px] opacity-60">Testé à {{ smtpTestResults.smtp2go.details.tested_at }}</p>
            </div>
          </div>
        </div>
      </template>
    </SectionCard>

    <SectionCard title="Fenêtre d'envoi" subtitle="Paramètres opérationnels globaux">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Début</label>
          <input
            v-model="form.sendWindowStart"
            type="time"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-bold text-slate-700">Fin</label>
          <input
            v-model="form.sendWindowEnd"
            type="time"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
        </div>
      </div>
      <div class="mt-3 flex flex-wrap items-center gap-4">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
          <input v-model="form.sendEnabled" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          Envoi global activé
        </label>
      </div>
    </SectionCard>

    <div class="flex items-center gap-3">
      <button
        :disabled="saving"
        class="btn-primary-gradient rounded-xl px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-500/20 transition-all hover:opacity-90 disabled:opacity-40"
        @click="save"
      >
        {{ saving ? 'Sauvegarde…' : 'Enregistrer les paramètres mail' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import SectionCard from './SectionCard.vue';

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
});

const providerOrder = ['ovh_mx_plan', 'smtp2go'];

const form = ref({
  activeProvider: 'ovh_mx_plan',
  senderEmail: '',
  senderName: '',
  mailboxUsername: '',
  mailboxPassword: '',
  imapHost: '',
  imapPort: 993,
  imapSecure: true,
  syncEnabled: true,
  sendEnabled: true,
  sendWindowStart: '09:00',
  sendWindowEnd: '18:00',
  ovh: {
    smtpHost: 'smtp.mail.ovh.net',
    smtpPort: 465,
    smtpSecure: true,
  },
  smtp2go: {
    smtpHost: 'mail.smtp2go.com',
    smtpPort: 587,
    smtpSecure: false,
    smtpUsername: '',
    smtpPassword: '',
    sendEnabled: true,
  },
});

const banner = ref(null);
const saving = ref(false);
const testingImap = ref(false);
const imapTestResult = ref(null);
const testingSmtp = ref({
  ovh_mx_plan: false,
  smtp2go: false,
});
const smtpTestResults = ref({
  ovh_mx_plan: null,
  smtp2go: null,
});

const providerSnapshots = computed(() => props.settings?.providers ?? {});
const mailboxPasswordConfigured = computed(() => Boolean(props.settings?.mailbox_password_configured));
const smtp2goPasswordConfigured = computed(() => Boolean(providerSnapshots.value.smtp2go?.smtp_password_configured));
const mailboxPasswordHint = computed(() => (
  mailboxPasswordConfigured.value
    ? 'Laissez vide pour conserver le mot de passe mailbox enregistré.'
    : 'Renseignez le mot de passe mailbox OVH.'
));
const smtp2goPasswordHint = computed(() => (
  smtp2goPasswordConfigured.value
    ? 'Laissez vide pour conserver le mot de passe SMTP2GO enregistré.'
    : 'Renseignez le mot de passe SMTP2GO.'
));

function hydrateForm(settings = {}) {
  const ovh = settings.providers?.ovh_mx_plan ?? {};
  const smtp2go = settings.providers?.smtp2go ?? {};

  form.value.activeProvider = settings.active_provider ?? settings.activeProvider ?? 'ovh_mx_plan';
  form.value.senderEmail = settings.sender_email ?? settings.senderEmail ?? '';
  form.value.senderName = settings.sender_name ?? settings.senderName ?? '';
  form.value.mailboxUsername = settings.mailbox_username ?? settings.mailboxUsername ?? '';
  form.value.mailboxPassword = '';
  form.value.imapHost = settings.imap_host ?? settings.imapHost ?? '';
  form.value.imapPort = settings.imap_port ?? settings.imapPort ?? 993;
  form.value.imapSecure = settings.imap_secure ?? settings.imapSecure ?? true;
  form.value.syncEnabled = settings.sync_enabled ?? settings.syncEnabled ?? true;
  form.value.sendEnabled = settings.send_enabled ?? settings.sendEnabled ?? true;
  form.value.sendWindowStart = settings.send_window_start ?? settings.sendWindowStart ?? '09:00';
  form.value.sendWindowEnd = settings.send_window_end ?? settings.sendWindowEnd ?? '18:00';
  form.value.ovh.smtpHost = ovh.smtp_host ?? 'smtp.mail.ovh.net';
  form.value.ovh.smtpPort = ovh.smtp_port ?? 465;
  form.value.ovh.smtpSecure = ovh.smtp_secure ?? true;
  form.value.smtp2go.smtpHost = smtp2go.smtp_host ?? 'mail.smtp2go.com';
  form.value.smtp2go.smtpPort = smtp2go.smtp_port ?? 587;
  form.value.smtp2go.smtpSecure = smtp2go.smtp_secure ?? false;
  form.value.smtp2go.smtpUsername = smtp2go.smtp_username ?? '';
  form.value.smtp2go.smtpPassword = '';
  form.value.smtp2go.sendEnabled = smtp2go.send_enabled ?? true;
}

hydrateForm(props.settings);

watch(() => props.settings, (settings) => {
  hydrateForm(settings ?? {});
}, { deep: true });

watch(
  [
    () => form.value.ovh.smtpHost,
    () => form.value.ovh.smtpPort,
    () => form.value.ovh.smtpSecure,
    () => form.value.mailboxUsername,
    () => form.value.mailboxPassword,
    () => form.value.senderEmail,
  ],
  () => {
    smtpTestResults.value.ovh_mx_plan = null;
  },
);

watch(
  [
    () => form.value.smtp2go.smtpHost,
    () => form.value.smtp2go.smtpPort,
    () => form.value.smtp2go.smtpSecure,
    () => form.value.smtp2go.smtpUsername,
    () => form.value.smtp2go.smtpPassword,
    () => form.value.senderEmail,
  ],
  () => {
    smtpTestResults.value.smtp2go = null;
  },
);

watch(
  [
    () => form.value.imapHost,
    () => form.value.imapPort,
    () => form.value.imapSecure,
    () => form.value.mailboxUsername,
    () => form.value.mailboxPassword,
    () => form.value.senderEmail,
  ],
  () => {
    imapTestResult.value = null;
  },
);

watch(banner, (value) => {
  if (value?.type !== 'success') {
    return;
  }

  setTimeout(() => {
    if (banner.value?.type === 'success') {
      banner.value = null;
    }
  }, 5000);
});

function providerLabel(provider) {
  return providerSnapshots.value?.[provider]?.label
    ?? (provider === 'smtp2go' ? 'SMTP2GO' : 'OVH MX Plan');
}

function providerHelp(provider) {
  return provider === 'smtp2go'
    ? 'Relay SMTP secondaire, indépendant d’OVH.'
    : 'Provider SMTP historique de la boîte OVH.';
}

function providerStatusLabel(provider) {
  const snapshot = providerSnapshots.value?.[provider] ?? {};

  if (snapshot.activatable ?? snapshot.ready) {
    return 'Prêt';
  }

  if (snapshot.configured) {
    return 'Partiel';
  }

  return 'Vide';
}

function providerStatusClass(provider, selected) {
  const snapshot = providerSnapshots.value?.[provider] ?? {};
  const activatable = snapshot.activatable ?? snapshot.ready;

  if (selected && activatable) {
    return 'bg-emerald-500/20 text-emerald-100';
  }

  if (selected) {
    return 'bg-white/10 text-white';
  }

  if (activatable) {
    return 'bg-emerald-100 text-emerald-700';
  }

  if (snapshot.configured) {
    return 'bg-amber-100 text-amber-700';
  }

  return 'bg-slate-100 text-slate-500';
}

function toApiPayload() {
  return {
    active_provider: form.value.activeProvider,
    sender_email: form.value.senderEmail,
    sender_name: form.value.senderName,
    mailbox_username: form.value.mailboxUsername,
    ...(form.value.mailboxPassword ? { mailbox_password: form.value.mailboxPassword } : {}),
    imap_host: form.value.imapHost,
    imap_port: form.value.imapPort,
    imap_secure: form.value.imapSecure,
    sync_enabled: form.value.syncEnabled,
    send_enabled: form.value.sendEnabled,
    send_window_start: form.value.sendWindowStart,
    send_window_end: form.value.sendWindowEnd,
    global_signature_html: null,
    global_signature_text: null,
    providers: {
      ovh_mx_plan: {
        smtp_host: form.value.ovh.smtpHost,
        smtp_port: form.value.ovh.smtpPort,
        smtp_secure: form.value.ovh.smtpSecure,
      },
      smtp2go: {
        smtp_host: form.value.smtp2go.smtpHost || null,
        smtp_port: form.value.smtp2go.smtpPort || null,
        smtp_secure: form.value.smtp2go.smtpSecure,
        smtp_username: form.value.smtp2go.smtpUsername || null,
        ...(form.value.smtp2go.smtpPassword ? { smtp_password: form.value.smtp2go.smtpPassword } : {}),
        send_enabled: form.value.smtp2go.sendEnabled,
      },
    },
  };
}

async function save() {
  saving.value = true;
  banner.value = null;

  try {
    await axios.put('/api/settings/mail', toApiPayload());
    banner.value = { type: 'success', message: 'Paramètres mail enregistrés.' };
    router.reload({ preserveState: true });
  } catch (error) {
    const errs = error.response?.data?.errors;
    const message = errs
      ? Object.values(errs).flat().join(' ')
      : (error.response?.data?.message ?? 'Erreur lors de la sauvegarde.');
    banner.value = { type: 'error', message };
  } finally {
    saving.value = false;
  }
}

function smtpPayload(provider) {
  if (provider === 'smtp2go') {
    return {
      provider,
      sender_email: form.value.senderEmail || undefined,
      smtp_host: form.value.smtp2go.smtpHost || undefined,
      smtp_port: form.value.smtp2go.smtpPort || undefined,
      smtp_secure: form.value.smtp2go.smtpSecure,
      smtp_username: form.value.smtp2go.smtpUsername || undefined,
      smtp_password: form.value.smtp2go.smtpPassword || undefined,
    };
  }

  return {
    provider,
    sender_email: form.value.senderEmail || undefined,
    mailbox_username: form.value.mailboxUsername || undefined,
    mailbox_password: form.value.mailboxPassword || undefined,
    smtp_host: form.value.ovh.smtpHost || undefined,
    smtp_port: form.value.ovh.smtpPort || undefined,
    smtp_secure: form.value.ovh.smtpSecure,
  };
}

async function testSmtp(provider) {
  testingSmtp.value = { ...testingSmtp.value, [provider]: true };
  smtpTestResults.value = { ...smtpTestResults.value, [provider]: null };

  try {
    const response = await axios.post('/api/settings/mail/test-smtp', smtpPayload(provider));
    smtpTestResults.value = {
      ...smtpTestResults.value,
      [provider]: {
        ok: true,
        message: response.data.message ?? 'Connexion réussie.',
        details: extractDiagnosticDetails(response.data),
      },
    };
  } catch (error) {
    const data = error.response?.data ?? {};
    smtpTestResults.value = {
      ...smtpTestResults.value,
      [provider]: {
        ok: false,
        message: data.message ?? 'Connexion échouée.',
        details: extractDiagnosticDetails(data),
      },
    };
  } finally {
    testingSmtp.value = { ...testingSmtp.value, [provider]: false };
  }
}

async function testImap() {
  testingImap.value = true;
  imapTestResult.value = null;

  try {
    const response = await axios.post('/api/settings/mail/test-imap', {
      provider: 'ovh_mx_plan',
      sender_email: form.value.senderEmail || undefined,
      mailbox_username: form.value.mailboxUsername || undefined,
      mailbox_password: form.value.mailboxPassword || undefined,
      imap_host: form.value.imapHost || undefined,
      imap_port: form.value.imapPort || undefined,
      imap_secure: form.value.imapSecure,
    });

    imapTestResult.value = {
      ok: true,
      message: response.data.message ?? 'Connexion réussie.',
      details: extractDiagnosticDetails(response.data),
    };
  } catch (error) {
    const data = error.response?.data ?? {};
    imapTestResult.value = {
      ok: false,
      message: data.message ?? 'Connexion échouée.',
      details: extractDiagnosticDetails(data),
    };
  } finally {
    testingImap.value = false;
  }
}

function extractDiagnosticDetails(data) {
  if (!data || typeof data !== 'object') return null;
  const d = {};
  if (data.tested_host) d.tested_host = data.tested_host;
  if (data.tested_port) d.tested_port = data.tested_port;
  if (data.tested_secure !== undefined) d.tested_secure = data.tested_secure;
  if (data.tested_at) d.tested_at = data.tested_at;
  if (data.provider_label) d.provider_label = data.provider_label;
  if (data.failure_stage) d.failure_stage = data.failure_stage;
  if (data.technical_detail) d.technical_detail = data.technical_detail;
  return Object.keys(d).length > 0 ? d : null;
}
</script>

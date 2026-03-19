<template>
  <CrmLayout :title="thread.subject || 'Fil de discussion'" subtitle="Historique détaillé du fil" current-page="mails">
    <template #header-actions>
      <Link href="/mails" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
        ← Retour aux mails
      </Link>
    </template>

    <div class="space-y-6">
      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Contact</p>
            <p class="mt-1 text-sm font-bold text-slate-900">{{ thread.contactName || 'Non résolu' }}</p>
          </div>
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Organisation</p>
            <p class="mt-1 text-sm font-bold text-slate-900">{{ thread.organization || '—' }}</p>
          </div>
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Dernière activité</p>
            <p class="mt-1 text-sm font-bold text-slate-900">{{ formatDateFR(thread.lastActivityAt) }}</p>
          </div>
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Statut</p>
            <p class="mt-1 text-sm font-bold text-slate-900">
              <span v-if="thread.replyReceived">Réponse reçue</span>
              <span v-else-if="thread.autoReplyReceived">Auto-réponse reçue</span>
              <span v-else>Suivi en cours</span>
            </p>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Messages</p>
        <div class="mt-4 space-y-4">
          <div v-if="thread.messages.length === 0" class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-400">
            Aucun message historisé dans ce fil.
          </div>
          <article
            v-for="message in thread.messages"
            :key="message.id"
            class="rounded-2xl border p-4"
            :class="message.direction === 'out' ? 'border-blue-200 bg-blue-50/30' : 'border-slate-200'"
          >
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-bold text-slate-900">{{ message.subject || '(Sans objet)' }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ message.direction === 'out' ? '→ Sortant' : '← Entrant' }} · {{ message.fromEmail }}
                  <span v-if="message.toEmails?.length"> → {{ message.toEmails.join(', ') }}</span>
                </p>
              </div>
              <span class="text-xs font-medium text-slate-400">{{ formatDateFR(message.receivedAt || message.sentAt) }}</span>
            </div>

            <div class="mt-3 flex items-center gap-3 text-xs text-slate-500">
              <span>Classification: {{ message.classification }}</span>
              <span v-if="message.hasAttachments">· {{ message.attachmentCount }} pièce(s) jointe(s)</span>
            </div>

            <!-- Message body -->
            <div v-if="message.htmlBody || message.textBody" class="mt-4">
              <button
                class="text-xs font-bold text-blue-600 hover:text-blue-800"
                @click="toggleBody(message.id)"
              >
                {{ expandedMessages[message.id] ? 'Masquer le contenu' : 'Afficher le contenu' }}
              </button>
              <div v-if="expandedMessages[message.id]" class="mt-3 rounded-xl border border-slate-200 bg-white p-4">
                <div v-if="message.htmlBody" class="prose prose-sm max-w-none text-slate-700" v-html="sanitizeHtml(message.htmlBody)" />
                <pre v-else-if="message.textBody" class="whitespace-pre-wrap text-sm text-slate-700 font-sans">{{ message.textBody }}</pre>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </CrmLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { formatDateFR } from '@/Utils/formatDate.js';
import { sanitizeHtml } from '@/Utils/sanitizeHtml.js';

const props = defineProps({
  thread: { type: Object, required: true },
});

const expandedMessages = reactive({});

function toggleBody(messageId) {
  expandedMessages[messageId] = !expandedMessages[messageId];
}
</script>

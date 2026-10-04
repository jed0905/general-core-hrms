<template>
  <CareersLayout>
    <Head :title="`Application ${application.number}`" />
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="px-0 mb-2" @click="go(route('careers.applications.index'))">My Applications</v-btn>
    <div class="d-flex flex-wrap align-center ga-3 mb-1">
      <h1 class="text-h5 font-weight-bold">{{ application.job_title }}</h1>
      <v-chip :color="color" variant="tonal">{{ application.status.label }}</v-chip>
    </div>
    <p class="text-body-2 text-medium-emphasis mb-4">
      Application {{ application.number }} · submitted {{ formatDate(application.submitted_on) }}<span v-if="application.department"> · {{ application.department }}</span>
    </p>
    <v-alert v-if="pageError" type="error" variant="tonal" class="mb-4" role="alert">{{ pageError }}</v-alert>

    <v-card variant="outlined" class="rounded-lg pa-4 mb-4">
      <p class="text-body-1 mb-4">{{ application.status.description }}</p>
      <ol v-if="!application.status.closed" class="d-flex flex-wrap ga-2 pl-0" style="list-style: none" aria-label="Progress">
        <li v-for="(step, i) in application.steps" :key="step">
          <v-chip :variant="i + 1 <= application.status.step ? 'flat' : 'outlined'" :color="i + 1 <= application.status.step ? 'primary' : undefined" size="small">
            <v-icon v-if="i + 1 < application.status.step" icon="mdi-check" start />{{ step }}
            <span v-if="i + 1 === application.status.step" class="sr-only"> (current)</span>
          </v-chip>
        </li>
      </ol>
    </v-card>

    <v-card variant="outlined" class="rounded-lg mb-4">
      <v-card-title class="text-subtitle-1 font-weight-bold">Submitted documents</v-card-title>
      <v-list v-if="application.documents.length" density="compact">
        <v-list-item v-for="d in application.documents" :key="d.id" :title="d.name" :subtitle="`${d.type} · ${fileSize(d.size)}`">
          <template #append>
            <v-btn :href="route('careers.documents.download', d.id)" icon="mdi-download" variant="text" :aria-label="`Download ${d.name}`" />
          </template>
        </v-list-item>
      </v-list>
      <v-card-text v-else class="text-medium-emphasis">No documents were submitted.</v-card-text>
    </v-card>

    <div v-if="application.can_withdraw" class="d-flex justify-end">
      <v-btn color="error" variant="text" @click="dialog = true">Withdraw application</v-btn>
    </div>

    <v-dialog v-model="dialog" max-width="480">
      <v-card>
        <v-card-title class="pa-4">Withdraw this application?</v-card-title>
        <v-card-text>
          <p class="text-body-2 mb-3">You won't be considered for this position any more, and the application can't be restored.</p>
          <v-textarea v-model="form.reason" label="Reason *" rows="3" variant="outlined" :error-messages="form.errors.reason" />
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Keep my application</v-btn>
          <v-btn color="error" :loading="form.processing" :disabled="form.processing" @click="withdraw">Withdraw</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </CareersLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";
import { formatDate, fileSize, STATUS_COLORS } from "@/utils/careers";

export default {
  name: "CareersMyApplication",
  components: { CareersLayout, Head },
  props: { application: { type: Object, required: true } },
  data() {
    return { dialog: false, form: useForm({ reason: "" }) };
  },
  computed: {
    color() {
      return STATUS_COLORS[this.application.status.key] || "grey";
    },
    pageError() {
      return this.dialog ? null : this.$page.props.errors?.status || null;
    },
  },
  methods: {
    formatDate,
    fileSize,
    withdraw() {
      this.form.post(route("careers.applications.withdraw", this.application.number), { preserveScroll: true, onSuccess: () => (this.dialog = false), onError: (e) => { if (e.status) this.dialog = false; } });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>

<template>
  <CareersLayout>
    <Head :title="`Apply · ${job.title}`" />
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="px-0 mb-2" @click="go(route('careers.jobs.show', job.slug))">Back to the job</v-btn>
    <h1 class="text-h5 font-weight-bold mb-1">Apply for {{ job.title }}</h1>
    <p class="text-body-2 text-medium-emphasis mb-4">
      {{ [job.department, job.location].filter(Boolean).join(" · ") }}<span v-if="job.closing_date"> · applications close {{ formatDate(job.closing_date) }}</span>
    </p>
    <v-alert v-if="instructions" type="info" variant="tonal" class="mb-4" style="white-space: pre-line">{{ instructions }}</v-alert>
    <v-alert v-if="topError" type="error" variant="tonal" class="mb-4" role="alert">{{ topError }}</v-alert>

    <v-card variant="outlined" class="rounded-lg pa-4 mb-4">
      <h2 class="text-subtitle-1 font-weight-bold mb-1">Documents</h2>
      <p class="text-body-2 text-medium-emphasis mb-3">
        Allowed: {{ limits.extensions.join(", ").toUpperCase() }}, up to {{ Math.round(limits.max_kb / 1024) }} MB each.
        <span v-if="required.length"> Required: <strong>{{ required.map((t) => t.name).join(", ") }}</strong>.</span>
      </p>

      <div v-for="(row, i) in form.uploads" :key="row.key" class="d-flex flex-wrap ga-2 align-start mb-2">
        <v-select v-model="row.applicant_document_type_id" :items="typeItems" :label="`Document type ${i + 1}`" variant="outlined" density="compact" style="min-width: 200px; max-width: 260px" :error-messages="form.errors[`uploads.${i}.applicant_document_type_id`]" />
        <v-file-input v-model="row.file" :label="`File ${i + 1}`" :accept="accept" variant="outlined" density="compact" show-size class="flex-grow-1" style="min-width: 220px" :error-messages="form.errors[`uploads.${i}.file`]" />
        <v-btn icon="mdi-close" variant="text" :aria-label="`Remove file ${i + 1}`" @click="form.uploads.splice(i, 1)" />
      </div>
      <v-btn variant="tonal" prepend-icon="mdi-paperclip" size="small" :disabled="form.uploads.length >= 10" @click="addUpload">Add a file</v-btn>

      <template v-if="myDocuments.length">
        <h3 class="text-subtitle-2 mt-4 mb-1">Or include documents from your profile</h3>
        <v-checkbox v-for="d in myDocuments" :key="d.id" v-model="form.document_ids" :value="d.id" :label="`${d.name} (${d.type})`" density="compact" hide-details />
      </template>
      <div v-if="form.errors.uploads || form.errors.document_ids" class="text-error text-body-2 mt-2" role="alert">{{ form.errors.uploads || form.errors.document_ids }}</div>
    </v-card>

    <v-card variant="outlined" class="rounded-lg pa-4 mb-4">
      <v-textarea v-model="form.cover_note" label="Message to the hiring team (optional)" rows="4" counter="2000" variant="outlined" :error-messages="form.errors.cover_note" />
    </v-card>

    <div class="d-flex justify-end ga-2">
      <v-btn variant="text" @click="go(route('careers.jobs.show', job.slug))">Cancel</v-btn>
      <v-btn color="primary" size="large" :loading="form.processing" :disabled="form.processing" @click="submit">Submit application</v-btn>
    </div>
  </CareersLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";
import { formatDate } from "@/utils/careers";

let key = 0;

export default {
  name: "CareersApply",
  components: { CareersLayout, Head },
  props: {
    job: { type: Object, required: true },
    documentTypes: { type: Array, default: () => [] },
    myDocuments: { type: Array, default: () => [] },
    limits: { type: Object, default: () => ({ max_kb: 5120, extensions: [] }) },
  },
  data() {
    const required = this.documentTypes.filter((t) => t.required_online);
    const first = required.length ? required : this.documentTypes.slice(0, 1);
    return {
      form: useForm({
        uploads: first.map((t) => ({ key: ++key, applicant_document_type_id: t.id, file: null })),
        document_ids: [],
        cover_note: "",
      }),
    };
  },
  computed: {
    required() {
      return this.documentTypes.filter((t) => t.required_online);
    },
    typeItems() {
      return this.documentTypes.map((t) => ({ value: t.id, title: t.name + (t.required_online ? " (required)" : "") }));
    },
    accept() {
      return this.limits.extensions.map((e) => `.${e}`).join(",");
    },
    instructions() {
      return this.$page.props.portal?.settings?.application_instructions;
    },
    topError() {
      const e = this.form.errors;
      return e.vacancy_id || e.applicant_id || e.application || null;
    },
  },
  methods: {
    formatDate,
    addUpload() {
      this.form.uploads.push({ key: ++key, applicant_document_type_id: null, file: null });
    },
    submit() {
      this.form
        .transform((d) => ({
          uploads: d.uploads
            .filter((u) => u.file)
            .map((u) => ({ applicant_document_type_id: u.applicant_document_type_id, file: Array.isArray(u.file) ? u.file[0] : u.file })),
          document_ids: d.document_ids,
          cover_note: d.cover_note || null,
        }))
        .post(route("careers.jobs.apply.store", this.job.slug), { forceFormData: true, preserveScroll: true });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>

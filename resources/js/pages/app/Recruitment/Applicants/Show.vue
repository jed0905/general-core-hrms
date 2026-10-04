<template>
  <SidebarLayout>
    <Head :title="applicant.full_name" />
    <v-container fluid class="pa-6">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <div>
          <v-btn variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('recruitment.applicants.index'))">Applicants</v-btn>
          <h1 class="text-h5 font-weight-bold d-flex align-center ga-2">
            {{ applicant.full_name }}
            <v-chip v-if="applicant.is_internal" size="small" color="primary" variant="tonal">Internal</v-chip>
            <v-chip v-if="applicant.status === 'archived'" size="small" variant="tonal">Archived</v-chip>
          </h1>
          <p class="text-body-2 text-medium-emphasis">{{ applicant.applicant_number }}<span v-if="applicant.preferred_name"> · prefers "{{ applicant.preferred_name }}"</span></p>
        </div>
        <div class="d-flex ga-2">
          <v-btn v-if="can.update" variant="outlined" prepend-icon="mdi-pencil-outline" @click="go(route('recruitment.applicants.edit', applicant.id))">Edit</v-btn>
          <v-btn v-if="can.apply" color="primary" prepend-icon="mdi-briefcase-plus-outline" :disabled="!openVacancies.length" @click="applyDialog = true">Apply to vacancy</v-btn>
        </div>
      </div>

      <v-row>
        <v-col cols="12" lg="4">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Contact</v-card-title>
            <v-list density="compact">
              <v-list-item prepend-icon="mdi-email-outline" :title="applicant.email || '—'" subtitle="Email" />
              <v-list-item prepend-icon="mdi-phone-outline" :title="applicant.phone || '—'" subtitle="Phone" />
              <v-list-item v-if="applicant.alternate_phone" prepend-icon="mdi-phone-outline" :title="applicant.alternate_phone" subtitle="Alternate phone" />
              <v-list-item v-if="applicant.address" prepend-icon="mdi-map-marker-outline" :title="applicant.address" subtitle="Address" />
            </v-list>
          </v-card>
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Source & consent</v-card-title>
            <v-list density="compact">
              <v-list-item :title="applicant.source?.name || '—'" :subtitle="applicant.source_details || 'Source'" />
              <v-list-item v-if="applicant.employee" :title="`${applicant.employee.emp_first_name} ${applicant.employee.emp_last_name} (${applicant.employee.employee_number})`" subtitle="Linked employee" />
              <v-list-item :title="applicant.privacy_consent ? `Given ${formatDateTime(applicant.privacy_consented_at)}` : 'Not recorded'" subtitle="Privacy consent" />
            </v-list>
          </v-card>
        </v-col>

        <v-col cols="12" lg="8">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Applications</v-card-title>
            <v-table v-if="applicant.applications.length" density="compact">
              <thead>
                <tr><th>Vacancy</th><th>Application</th><th>Stage</th><th>Status</th><th>Applied</th><th>Last activity</th><th /></tr>
              </thead>
              <tbody>
                <tr v-for="app in applicant.applications" :key="app.id">
                  <td>{{ app.vacancy?.title }} <div class="text-caption text-medium-emphasis">{{ app.vacancy?.vacancy_number }}</div></td>
                  <td class="text-no-wrap">{{ app.application_number }}</td>
                  <td>{{ app.current_stage?.name }}</td>
                  <td><v-chip size="x-small" variant="tonal" :color="appStatus(app.status).color">{{ appStatus(app.status).label }}</v-chip></td>
                  <td class="text-no-wrap">{{ formatDateTime(app.applied_at) }}</td>
                  <td class="text-no-wrap">{{ formatDateTime(app.last_activity_at) }}</td>
                  <td class="text-end"><v-btn v-if="can.viewApplications" icon="mdi-eye-outline" size="small" variant="text" @click="go(route('recruitment.applications.show', app.id))" /></td>
                </tr>
              </tbody>
            </v-table>
            <v-card-text v-else class="text-medium-emphasis">No applications yet.</v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
              Documents
              <v-btn v-if="can.manageDocuments" size="small" color="primary" prepend-icon="mdi-upload" @click="openUpload">Upload</v-btn>
            </v-card-title>
            <v-alert v-if="docErrors" type="error" variant="tonal" density="compact" class="ma-3">{{ docErrors }}</v-alert>
            <v-table v-if="applicant.documents.length" density="compact">
              <thead><tr><th>Type</th><th>File</th><th>Size</th><th>Uploaded</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
                <tr v-for="d in applicant.documents" :key="d.id">
                  <td>{{ d.type?.name }}</td>
                  <td>{{ d.original_name }}<div v-if="d.description" class="text-caption text-medium-emphasis">{{ d.description }}</div></td>
                  <td class="text-no-wrap">{{ fileSize(d.file_size) }}</td>
                  <td class="text-no-wrap">{{ formatDateTime(d.uploaded_at) }}<div class="text-caption text-medium-emphasis">{{ d.uploader?.username }}</div></td>
                  <td class="text-end text-no-wrap">
                    <v-btn v-if="previewable(d)" :href="route('recruitment.applicants.documents.view', [applicant.id, d.id])" target="_blank" rel="noopener" icon="mdi-eye-outline" size="small" variant="text" />
                    <v-btn :href="route('recruitment.applicants.documents.download', [applicant.id, d.id])" icon="mdi-download" size="small" variant="text" />
                    <v-btn v-if="can.manageDocuments" icon="mdi-delete-outline" size="small" variant="text" color="error" @click="removeDocument(d)" />
                  </td>
                </tr>
              </tbody>
            </v-table>
            <v-card-text v-else class="text-medium-emphasis">No documents uploaded.</v-card-text>
          </v-card>

          <v-row>
            <v-col cols="12" md="6">
              <v-card variant="outlined" class="rounded-lg h-100">
                <v-card-title class="text-subtitle-1 font-weight-bold border-b">Education</v-card-title>
                <v-list v-if="applicant.education.length" density="compact">
                  <v-list-item v-for="e in applicant.education" :key="e.id" :title="[e.degree, e.institute].filter(Boolean).join(' · ')"
                    :subtitle="[e.level, e.major_specialization, period(e.start_date, e.end_date, e.is_completed ? null : 'in progress')].filter(Boolean).join(' · ')" />
                </v-list>
                <v-card-text v-else class="text-medium-emphasis">None recorded.</v-card-text>
              </v-card>
            </v-col>
            <v-col cols="12" md="6">
              <v-card variant="outlined" class="rounded-lg h-100">
                <v-card-title class="text-subtitle-1 font-weight-bold border-b">Work experience</v-card-title>
                <v-list v-if="applicant.work_experience.length" density="compact">
                  <v-list-item v-for="w in applicant.work_experience" :key="w.id" :title="`${w.job_title} · ${w.company}`" :subtitle="[period(w.from, w.to, 'present'), w.notes].filter(Boolean).join(' · ')" />
                </v-list>
                <v-card-text v-else class="text-medium-emphasis">None recorded.</v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <v-dialog v-model="uploadDialog" max-width="520">
        <v-card>
          <v-card-title class="pa-4">Upload document</v-card-title>
          <v-card-text>
            <v-file-input v-model="upload.file" label="File *" accept=".pdf,.doc,.docx,.odt,.rtf,.txt,.jpg,.jpeg,.png" hint="Up to 10 MB" persistent-hint prepend-icon="" prepend-inner-icon="mdi-paperclip" variant="outlined" density="compact" class="mb-3" :error-messages="upload.errors.file" />
            <v-select v-model="upload.applicant_document_type_id" :items="documentTypes" item-title="name" item-value="id" label="Type *" variant="outlined" density="compact" class="mb-3" :error-messages="upload.errors.applicant_document_type_id" />
            <v-textarea v-model="upload.description" label="Description" rows="2" variant="outlined" density="compact" :error-messages="upload.errors.description" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="uploadDialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="upload.processing" @click="submitUpload">Upload</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="applyDialog" max-width="560">
        <v-card>
          <v-card-title class="pa-4">Apply to vacancy</v-card-title>
          <v-card-text>
            <v-autocomplete v-model="apply.vacancy_id" :items="vacancyItems" label="Open vacancy *" variant="outlined" density="compact" class="mb-3" :error-messages="apply.errors.vacancy_id || apply.errors.applicant_id" />
            <v-select v-model="apply.recruitment_source_id" :items="sources" item-title="name" item-value="id" label="Source for this application" clearable variant="outlined" density="compact" class="mb-3" />
            <v-select v-model="apply.document_ids" :items="applicant.documents" item-title="original_name" item-value="id" label="Documents to submit" multiple chips variant="outlined" density="compact" class="mb-3" :error-messages="apply.errors.document_ids" />
            <v-textarea v-model="apply.remarks" label="Remarks" rows="2" variant="outlined" density="compact" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="applyDialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="apply.processing" :disabled="!apply.vacancy_id" @click="submitApply">Create application</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { APPLICATION_STATUS, statusInfo, formatDate, formatDateTime, fileSize } from "@/utils/recruitment";

const INLINE_TYPES = ["application/pdf", "image/jpeg", "image/png"];

export default {
  name: "ApplicantShow",
  components: { SidebarLayout, Head },
  props: {
    applicant: { type: Object, required: true },
    documentTypes: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    openVacancies: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      uploadDialog: false,
      applyDialog: false,
      upload: useForm({ file: null, applicant_document_type_id: null, description: "" }),
      apply: useForm({ applicant_id: this.applicant.id, vacancy_id: null, recruitment_source_id: null, document_ids: [], remarks: "" }),
    };
  },
  computed: {
    vacancyItems() {
      return this.openVacancies.map((v) => ({ value: v.id, title: `${v.title} (${v.vacancy_number})` }));
    },
    docErrors() {
      return this.$page.props.errors?.document || null;
    },
  },
  methods: {
    formatDateTime,
    fileSize,
    appStatus(value) {
      return statusInfo(APPLICATION_STATUS, value);
    },
    previewable(doc) {
      return INLINE_TYPES.includes(doc.mime_type);
    },
    period(from, to, openLabel) {
      if (!from && !to) return null;
      return `${formatDate(from)} – ${to ? formatDate(to) : openLabel || "—"}`;
    },
    openUpload() {
      this.upload.reset();
      this.upload.clearErrors();
      this.uploadDialog = true;
    },
    submitUpload() {
      this.upload
        .transform((data) => ({ ...data, file: Array.isArray(data.file) ? data.file[0] : data.file }))
        .post(route("recruitment.applicants.documents.store", this.applicant.id), {
          forceFormData: true,
          preserveScroll: true,
          onSuccess: () => (this.uploadDialog = false),
        });
    },
    removeDocument(doc) {
      if (!confirm(`Delete ${doc.original_name}?`)) return;
      router.delete(route("recruitment.applicants.documents.destroy", [this.applicant.id, doc.id]), { preserveScroll: true });
    },
    submitApply() {
      this.apply.post(route("recruitment.applications.store"), { preserveScroll: true });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>

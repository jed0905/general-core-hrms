<template>
  <CareersLayout>
    <Head title="My Profile" />
    <h1 class="text-h5 font-weight-bold mb-1">My Profile</h1>
    <p class="text-body-2 text-medium-emphasis mb-4">{{ profile.name }} · {{ profile.email }}. To change your name or email address, please contact our HR team.</p>

    <v-card variant="outlined" class="rounded-lg pa-4 mb-4">
      <h2 class="text-subtitle-1 font-weight-bold mb-3">Contact details</h2>
      <v-row density="compact">
        <v-col cols="12" md="4"><v-text-field v-model="form.preferred_name" label="Preferred name" variant="outlined" density="compact" :error-messages="form.errors.preferred_name" /></v-col>
        <v-col cols="12" md="4"><v-text-field v-model="form.phone" label="Phone" type="tel" autocomplete="tel" variant="outlined" density="compact" :error-messages="form.errors.phone" /></v-col>
        <v-col cols="12" md="4"><v-text-field v-model="form.alternate_phone" label="Alternate phone" type="tel" variant="outlined" density="compact" :error-messages="form.errors.alternate_phone" /></v-col>
        <v-col cols="12"><v-textarea v-model="form.address" label="Address" rows="2" autocomplete="street-address" variant="outlined" density="compact" :error-messages="form.errors.address" /></v-col>
      </v-row>
    </v-card>

    <v-card variant="outlined" class="rounded-lg pa-4 mb-4">
      <div class="d-flex align-center mb-2">
        <h2 class="text-subtitle-1 font-weight-bold">Education</h2>
        <v-spacer />
        <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="form.education.push({ level: '', institute: '', degree: '', major_specialization: '', start_date: null, end_date: null, is_completed: false })">Add</v-btn>
      </div>
      <v-row v-for="(e, i) in form.education" :key="`e${i}`" density="compact" class="mb-1">
        <v-col cols="12" md="3"><v-text-field v-model="e.institute" label="School *" variant="outlined" density="compact" hide-details="auto" :error-messages="form.errors[`education.${i}.institute`]" /></v-col>
        <v-col cols="6" md="2"><v-text-field v-model="e.level" label="Level" variant="outlined" density="compact" hide-details /></v-col>
        <v-col cols="6" md="2"><v-text-field v-model="e.degree" label="Degree" variant="outlined" density="compact" hide-details /></v-col>
        <v-col cols="6" md="2"><v-text-field v-model="e.start_date" type="date" label="From" variant="outlined" density="compact" hide-details /></v-col>
        <v-col cols="6" md="2"><v-text-field v-model="e.end_date" type="date" label="To" variant="outlined" density="compact" hide-details="auto" :error-messages="form.errors[`education.${i}.end_date`]" /></v-col>
        <v-col cols="12" md="1" class="d-flex align-center"><v-btn icon="mdi-delete-outline" size="small" variant="text" aria-label="Remove education" @click="form.education.splice(i, 1)" /></v-col>
      </v-row>
    </v-card>

    <v-card variant="outlined" class="rounded-lg pa-4 mb-4">
      <div class="d-flex align-center mb-2">
        <h2 class="text-subtitle-1 font-weight-bold">Work experience</h2>
        <v-spacer />
        <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="form.work_experience.push({ company: '', job_title: '', from: null, to: null, notes: '' })">Add</v-btn>
      </div>
      <v-row v-for="(w, i) in form.work_experience" :key="`w${i}`" density="compact" class="mb-1">
        <v-col cols="12" md="3"><v-text-field v-model="w.company" label="Company *" variant="outlined" density="compact" hide-details="auto" :error-messages="form.errors[`work_experience.${i}.company`]" /></v-col>
        <v-col cols="12" md="3"><v-text-field v-model="w.job_title" label="Job title *" variant="outlined" density="compact" hide-details="auto" :error-messages="form.errors[`work_experience.${i}.job_title`]" /></v-col>
        <v-col cols="6" md="2"><v-text-field v-model="w.from" type="date" label="From *" variant="outlined" density="compact" hide-details="auto" :error-messages="form.errors[`work_experience.${i}.from`]" /></v-col>
        <v-col cols="6" md="2"><v-text-field v-model="w.to" type="date" label="To (blank = current)" variant="outlined" density="compact" hide-details="auto" :error-messages="form.errors[`work_experience.${i}.to`]" /></v-col>
        <v-col cols="12" md="2" class="d-flex align-center"><v-btn icon="mdi-delete-outline" size="small" variant="text" aria-label="Remove work experience" @click="form.work_experience.splice(i, 1)" /></v-col>
      </v-row>
    </v-card>

    <div class="d-flex justify-end mb-8">
      <v-btn color="primary" :loading="form.processing" :disabled="form.processing" @click="save">Save profile</v-btn>
    </div>

    <v-card variant="outlined" class="rounded-lg">
      <v-card-title class="text-subtitle-1 font-weight-bold">My documents</v-card-title>
      <v-card-text>
        <div class="d-flex flex-wrap ga-2 align-start mb-3">
          <v-select v-model="upload.applicant_document_type_id" :items="documentTypes" item-title="name" item-value="id" label="Type" variant="outlined" density="compact" style="max-width: 240px" :error-messages="upload.errors.applicant_document_type_id" />
          <v-file-input v-model="upload.file" label="File" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" variant="outlined" density="compact" class="flex-grow-1" :error-messages="upload.errors.file" />
          <v-btn color="primary" variant="tonal" :loading="upload.processing" @click="uploadDocument">Upload</v-btn>
        </div>
        <v-list v-if="documents.length" density="compact">
          <v-list-item v-for="d in documents" :key="d.id" :title="d.name" :subtitle="`${d.type} · ${fileSize(d.size)}${d.submitted ? ' · submitted with an application' : ''}`">
            <template #append>
              <v-btn :href="route('careers.documents.download', d.id)" icon="mdi-download" variant="text" :aria-label="`Download ${d.name}`" />
              <v-btn v-if="!d.submitted" icon="mdi-delete-outline" variant="text" :aria-label="`Delete ${d.name}`" @click="remove(d)" />
            </template>
          </v-list-item>
        </v-list>
        <p v-else class="text-medium-emphasis mb-0">No documents yet.</p>
        <div v-if="$page.props.errors?.document" class="text-error text-body-2" role="alert">{{ $page.props.errors.document }}</div>
      </v-card-text>
    </v-card>
  </CareersLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";
import { fileSize } from "@/utils/careers";

export default {
  name: "CareersProfile",
  components: { CareersLayout, Head },
  props: {
    profile: { type: Object, required: true },
    documents: { type: Array, default: () => [] },
    documentTypes: { type: Array, default: () => [] },
  },
  data() {
    const p = this.profile;
    const date = (v) => (v ? String(v).substring(0, 10) : null);
    return {
      form: useForm({
        preferred_name: p.preferred_name || "",
        phone: p.phone || "",
        alternate_phone: p.alternate_phone || "",
        address: p.address || "",
        education: p.education.map((e) => ({ ...e, start_date: date(e.start_date), end_date: date(e.end_date) })),
        work_experience: p.work_experience.map((w) => ({ ...w, from: date(w.from), to: date(w.to) })),
      }),
      upload: useForm({ applicant_document_type_id: null, file: null }),
    };
  },
  methods: {
    fileSize,
    save() {
      this.form.put(route("careers.profile.update"), { preserveScroll: true });
    },
    uploadDocument() {
      this.upload
        .transform((d) => ({ applicant_document_type_id: d.applicant_document_type_id, file: Array.isArray(d.file) ? d.file[0] : d.file }))
        .post(route("careers.documents.store"), { forceFormData: true, preserveScroll: true, onSuccess: () => this.upload.reset() });
    },
    remove(doc) {
      if (window.confirm(`Delete ${doc.name}?`)) {
        router.delete(route("careers.documents.destroy", doc.id), { preserveScroll: true });
      }
    },
  },
};
</script>

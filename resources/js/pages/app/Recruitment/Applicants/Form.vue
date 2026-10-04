<template>
  <SidebarLayout>
    <Head :title="editing ? `Edit ${applicant.applicant_number}` : 'New Applicant'" />
    <v-container fluid class="pa-6" style="max-width: 1100px">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">{{ editing ? `Edit ${applicant.applicant_number}` : "New Applicant" }}</h1>
        <p class="text-body-2 text-medium-emphasis">Only the information recruitment needs. Documents are added from the applicant's profile.</p>
      </div>

      <v-alert v-if="form.errors.duplicate" type="warning" variant="tonal" class="mb-4">
        <div class="mb-2">{{ form.errors.duplicate }}</div>
        <v-checkbox v-model="form.confirm_not_duplicate" label="I've checked: this is a different person" density="compact" hide-details />
      </v-alert>

      <v-card variant="outlined" class="rounded-lg pa-5 mb-4">
        <div class="text-subtitle-1 font-weight-bold mb-3">Personal information</div>
        <v-row density="compact">
          <v-col cols="12" md="4"><v-text-field v-model="form.first_name" label="First name *" variant="outlined" density="compact" :error-messages="form.errors.first_name" /></v-col>
          <v-col cols="12" md="3"><v-text-field v-model="form.middle_name" label="Middle name" variant="outlined" density="compact" :error-messages="form.errors.middle_name" /></v-col>
          <v-col cols="12" md="3"><v-text-field v-model="form.last_name" label="Last name *" variant="outlined" density="compact" :error-messages="form.errors.last_name" /></v-col>
          <v-col cols="12" md="2"><v-text-field v-model="form.suffix" label="Suffix" variant="outlined" density="compact" :error-messages="form.errors.suffix" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.preferred_name" label="Preferred name" variant="outlined" density="compact" :error-messages="form.errors.preferred_name" /></v-col>
          <v-col v-if="editing" cols="12" md="3">
            <v-select v-model="form.status" :items="[{ value: 'active', title: 'Active' }, { value: 'archived', title: 'Archived' }]" label="Status" variant="outlined" density="compact" :error-messages="form.errors.status" />
          </v-col>
        </v-row>

        <div class="text-subtitle-1 font-weight-bold mb-3 mt-2">Contact</div>
        <v-row density="compact">
          <v-col cols="12" md="4"><v-text-field v-model="form.email" type="email" label="Email" variant="outlined" density="compact" :error-messages="form.errors.email" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.phone" label="Phone" variant="outlined" density="compact" :error-messages="form.errors.phone" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.alternate_phone" label="Alternate phone" variant="outlined" density="compact" :error-messages="form.errors.alternate_phone" /></v-col>
          <v-col cols="12"><v-textarea v-model="form.address" label="Address" rows="2" variant="outlined" density="compact" :error-messages="form.errors.address" /></v-col>
        </v-row>

        <div class="text-subtitle-1 font-weight-bold mb-3 mt-2">Source</div>
        <v-row density="compact">
          <v-col cols="12" md="4"><v-select v-model="form.recruitment_source_id" :items="sources" item-title="name" item-value="id" label="Source" clearable variant="outlined" density="compact" :error-messages="form.errors.recruitment_source_id" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.source_details" label="Source details (e.g. referrer)" variant="outlined" density="compact" :error-messages="form.errors.source_details" /></v-col>
          <v-col cols="12" md="4"><v-switch v-model="form.is_internal" label="Internal candidate (current employee)" color="primary" density="compact" hide-details /></v-col>
          <v-col v-if="form.is_internal" cols="12" md="6">
            <v-autocomplete v-model="form.employee_id" :items="employees" item-title="title" item-value="value" label="Employee *" variant="outlined" density="compact" :error-messages="form.errors.employee_id" />
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg pa-5 mb-4">
        <div class="d-flex align-center justify-space-between mb-3">
          <div class="text-subtitle-1 font-weight-bold">Education</div>
          <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="form.education.push(blankEducation())">Add</v-btn>
        </div>
        <v-row v-for="(edu, i) in form.education" :key="`edu-${i}`" density="compact" class="border-b mb-2">
          <v-col cols="12" md="3"><v-text-field v-model="edu.level" label="Level" placeholder="e.g. Bachelor's" variant="outlined" density="compact" /></v-col>
          <v-col cols="12" md="5"><v-text-field v-model="edu.institute" label="School / institution" variant="outlined" density="compact" :error-messages="form.errors[`education.${i}.institute`]" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="edu.degree" label="Degree / course" variant="outlined" density="compact" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="edu.major_specialization" label="Field of study" variant="outlined" density="compact" /></v-col>
          <v-col cols="6" md="3"><v-text-field v-model="edu.start_date" type="date" label="Start" variant="outlined" density="compact" /></v-col>
          <v-col cols="6" md="3"><v-text-field v-model="edu.end_date" type="date" label="End" variant="outlined" density="compact" :error-messages="form.errors[`education.${i}.end_date`]" /></v-col>
          <v-col cols="10" md="1"><v-checkbox v-model="edu.is_completed" label="Done" density="compact" hide-details /></v-col>
          <v-col cols="2" md="1" class="text-end"><v-btn icon="mdi-delete-outline" size="small" variant="text" @click="form.education.splice(i, 1)" /></v-col>
        </v-row>
        <div v-if="!form.education.length" class="text-medium-emphasis">No education added.</div>
      </v-card>

      <v-card variant="outlined" class="rounded-lg pa-5 mb-4">
        <div class="d-flex align-center justify-space-between mb-3">
          <div class="text-subtitle-1 font-weight-bold">Work experience</div>
          <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="form.work_experience.push(blankWork())">Add</v-btn>
        </div>
        <v-row v-for="(w, i) in form.work_experience" :key="`work-${i}`" density="compact" class="border-b mb-2">
          <v-col cols="12" md="4"><v-text-field v-model="w.company" label="Company" variant="outlined" density="compact" :error-messages="form.errors[`work_experience.${i}.company`]" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="w.job_title" label="Job title" variant="outlined" density="compact" :error-messages="form.errors[`work_experience.${i}.job_title`]" /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="w.from" type="date" label="From" variant="outlined" density="compact" :error-messages="form.errors[`work_experience.${i}.from`]" /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="w.to" type="date" label="To" hint="Empty = current" persistent-hint variant="outlined" density="compact" :error-messages="form.errors[`work_experience.${i}.to`]" /></v-col>
          <v-col cols="10" md="11"><v-textarea v-model="w.notes" label="Description" rows="1" auto-grow counter="1000" variant="outlined" density="compact" :error-messages="form.errors[`work_experience.${i}.notes`]" /></v-col>
          <v-col cols="2" md="1" class="text-end"><v-btn icon="mdi-delete-outline" size="small" variant="text" @click="form.work_experience.splice(i, 1)" /></v-col>
        </v-row>
        <div v-if="!form.work_experience.length" class="text-medium-emphasis">No work experience added.</div>
      </v-card>

      <v-card variant="outlined" class="rounded-lg pa-5">
        <v-checkbox
          v-if="!editing"
          v-model="form.privacy_consent"
          label="The applicant consented to the processing of their personal data for recruitment *"
          density="compact"
          :error-messages="form.errors.privacy_consent"
        />
        <div class="d-flex justify-end ga-2">
          <v-btn variant="text" @click="cancel">Cancel</v-btn>
          <v-btn color="primary" :loading="form.processing" @click="submit">{{ editing ? "Save changes" : "Create applicant" }}</v-btn>
        </div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";

export default {
  name: "ApplicantForm",
  components: { SidebarLayout, Head },
  props: {
    applicant: { type: Object, default: null },
    sources: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
  },
  data() {
    const a = this.applicant || {};
    return {
      form: useForm({
        first_name: a.first_name ?? "",
        middle_name: a.middle_name ?? "",
        last_name: a.last_name ?? "",
        suffix: a.suffix ?? "",
        preferred_name: a.preferred_name ?? "",
        email: a.email ?? "",
        phone: a.phone ?? "",
        alternate_phone: a.alternate_phone ?? "",
        address: a.address ?? "",
        recruitment_source_id: a.recruitment_source_id ?? null,
        source_details: a.source_details ?? "",
        is_internal: Boolean(a.is_internal),
        employee_id: a.employee_id ?? null,
        status: a.status ?? "active",
        education: (a.education || []).map((e) => ({ ...e })),
        work_experience: (a.work_experience || []).map((w) => ({ ...w })),
        privacy_consent: false,
        confirm_not_duplicate: false,
      }),
    };
  },
  computed: {
    editing() {
      return Boolean(this.applicant);
    },
  },
  methods: {
    blankEducation() {
      return { level: "", institute: "", degree: "", major_specialization: "", start_date: null, end_date: null, is_completed: false };
    },
    blankWork() {
      return { company: "", job_title: "", from: null, to: null, notes: "" };
    },
    submit() {
      const options = { preserveScroll: true };
      if (this.editing) {
        this.form.put(route("recruitment.applicants.update", this.applicant.id), options);
      } else {
        this.form.post(route("recruitment.applicants.store"), options);
      }
    },
    cancel() {
      router.visit(this.editing ? route("recruitment.applicants.show", this.applicant.id) : route("recruitment.applicants.index"));
    },
  },
};
</script>

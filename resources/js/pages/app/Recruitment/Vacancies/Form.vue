<template>
  <SidebarLayout>
    <Head :title="editing ? `Edit ${vacancy.vacancy_number}` : 'New Vacancy'" />
    <v-container fluid class="pa-6" style="max-width: 1100px">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">{{ editing ? `Edit ${vacancy.vacancy_number}` : "New Vacancy" }}</h1>
        <p class="text-body-2 text-medium-emphasis">{{ editing ? "Status changes are made from the vacancy page." : "Saved as a draft; open it from the vacancy page." }}</p>
      </div>

      <v-alert v-if="source" type="info" variant="tonal" class="mb-4">
        From approved requisition <strong>{{ source.requisition_number }}</strong>. Position details come from the requisition;
        <template v-if="requisition">{{ requisition.remaining }} of {{ requisition.positions }} positions are still unallocated.</template>
      </v-alert>
      <v-alert v-if="contentOnly" type="info" variant="tonal" class="mb-4">As hiring manager you can update the description, responsibilities and qualifications.</v-alert>

      <v-card variant="outlined" class="rounded-lg pa-5">
        <v-row v-if="!contentOnly">
          <v-col cols="12" md="6">
            <v-text-field v-model="form.title" label="Posting title" :placeholder="defaultTitle" persistent-placeholder hint="Leave blank to use the job title." persistent-hint variant="outlined" density="compact" :error-messages="form.errors.title" />
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete v-model="form.job_title_id" :items="options.job_titles" item-title="title" item-value="value" label="Job title *" :disabled="positionLocked" variant="outlined" density="compact" :error-messages="form.errors.job_title_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete v-model="form.department_id" :items="options.departments" item-title="title" item-value="value" label="Department *" :disabled="positionLocked" variant="outlined" density="compact" :error-messages="form.errors.department_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete v-model="form.location_id" :items="options.locations" item-title="title" item-value="value" label="Location" :disabled="positionLocked" clearable variant="outlined" density="compact" :error-messages="form.errors.location_id" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="form.employment_status_id" :items="options.employment_statuses" item-title="title" item-value="value" label="Employment status" :disabled="positionLocked" clearable variant="outlined" density="compact" :error-messages="form.errors.employment_status_id" />
          </v-col>
          <v-col cols="12" md="2">
            <v-text-field v-model.number="form.openings" type="number" min="1" label="Openings *" variant="outlined" density="compact" :error-messages="form.errors.openings" />
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete v-model="form.hiring_manager_id" :items="options.employees" item-title="title" item-value="value" label="Hiring manager" clearable variant="outlined" density="compact" :error-messages="form.errors.hiring_manager_id" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="form.visibility" :items="visibilityItems" label="Visibility *" variant="outlined" density="compact" :error-messages="form.errors.visibility" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.opening_date" type="date" label="Opening date" variant="outlined" density="compact" :error-messages="form.errors.opening_date" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.closing_date" type="date" label="Closing date" variant="outlined" density="compact" :error-messages="form.errors.closing_date" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.salary_min" type="number" min="0" label="Salary from" variant="outlined" density="compact" :error-messages="form.errors.salary_min" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.salary_max" type="number" min="0" label="Salary to" variant="outlined" density="compact" :error-messages="form.errors.salary_max" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.salary_currency" label="Currency" placeholder="e.g. PHP" maxlength="3" variant="outlined" density="compact" :error-messages="form.errors.salary_currency" />
          </v-col>
        </v-row>

        <v-textarea v-model="form.description" label="Job description" rows="4" variant="outlined" density="compact" class="mt-2" :error-messages="form.errors.description" />
        <v-textarea v-model="form.responsibilities" label="Responsibilities" rows="4" variant="outlined" density="compact" :error-messages="form.errors.responsibilities" />
        <v-textarea v-model="form.qualifications" label="Qualifications" rows="4" variant="outlined" density="compact" :error-messages="form.errors.qualifications" />

        <v-alert v-if="form.errors.status || form.errors.job_requisition_id" type="error" variant="tonal" class="mb-3">{{ form.errors.status || form.errors.job_requisition_id }}</v-alert>
        <div class="d-flex justify-end ga-2">
          <v-btn variant="text" @click="cancel">Cancel</v-btn>
          <v-btn color="primary" :loading="form.processing" @click="submit">{{ editing ? "Save changes" : "Save draft" }}</v-btn>
        </div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { VISIBILITY_LABELS } from "@/utils/recruitment";

export default {
  name: "VacancyForm",
  components: { SidebarLayout, Head },
  props: {
    vacancy: { type: Object, default: null },
    requisition: { type: Object, default: null },
    options: { type: Object, required: true },
    visibilities: { type: Array, default: () => [] },
    contentOnly: { type: Boolean, default: false },
  },
  data() {
    const v = this.vacancy || {};
    const r = this.requisition || {};
    return {
      form: useForm({
        job_requisition_id: this.requisition ? r.id : undefined,
        title: v.title ?? "",
        job_title_id: v.job_title_id ?? r.job_title_id ?? null,
        department_id: v.department_id ?? r.department_id ?? null,
        location_id: v.location_id ?? r.location_id ?? null,
        employment_status_id: v.employment_status_id ?? r.employment_status_id ?? null,
        openings: v.openings ?? (this.requisition ? r.remaining : 1),
        description: v.description ?? "",
        responsibilities: v.responsibilities ?? "",
        qualifications: v.qualifications ?? "",
        salary_min: v.salary_min ?? null,
        salary_max: v.salary_max ?? null,
        salary_currency: v.salary_currency ?? "",
        opening_date: v.opening_date ?? null,
        closing_date: v.closing_date ?? null,
        visibility: v.visibility ?? "internal",
        hiring_manager_id: v.hiring_manager_id ?? null,
      }),
    };
  },
  computed: {
    editing() {
      return Boolean(this.vacancy);
    },
    source() {
      return this.requisition || this.vacancy?.requisition || null;
    },
    positionLocked() {
      return Boolean(this.source);
    },
    defaultTitle() {
      const match = this.options.job_titles.find((t) => t.value === this.form.job_title_id);
      return match ? match.title : "";
    },
    visibilityItems() {
      return this.visibilities.map((v) => ({ value: v, title: VISIBILITY_LABELS[v] || v }));
    },
  },
  methods: {
    submit() {
      const payload = this.contentOnly
        ? (data) => ({ description: data.description, responsibilities: data.responsibilities, qualifications: data.qualifications })
        : (data) => data;
      if (this.editing) {
        this.form.transform(payload).put(route("recruitment.vacancies.update", this.vacancy.id));
      } else {
        this.form.post(route("recruitment.vacancies.store"));
      }
    },
    cancel() {
      router.visit(this.editing ? route("recruitment.vacancies.show", this.vacancy.id) : route("recruitment.vacancies.index"));
    },
  },
};
</script>

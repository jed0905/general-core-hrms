<template>
  <v-dialog :model-value="modelValue" max-width="720" @update:model-value="$emit('update:modelValue', $event)">
    <v-card>
      <v-card-title class="pa-4">{{ offer ? "Edit offer draft" : "Prepare offer" }}</v-card-title>
      <v-card-text>
        <p class="text-body-2 text-medium-emphasis mb-3">Proposed terms only: nothing here creates an employee or payroll record. Terms can be changed until the offer is submitted for approval.</p>
        <v-alert v-if="form.errors.offer || form.errors.status" type="error" variant="tonal" density="compact" class="mb-3">{{ form.errors.offer || form.errors.status }}</v-alert>
        <v-row density="compact">
          <v-col cols="12" md="6">
            <v-autocomplete v-model="form.job_title_id" :items="options.job_titles" label="Position *" variant="outlined" density="compact" :error-messages="form.errors.job_title_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-select v-model="form.employment_status_id" :items="options.employment_statuses" label="Employment type" variant="outlined" density="compact" clearable :error-messages="form.errors.employment_status_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-select v-model="form.location_id" :items="options.locations" label="Work location" variant="outlined" density="compact" clearable :error-messages="form.errors.location_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.work_location" label="Work location (free text, overrides)" placeholder="e.g. Remote, hybrid" variant="outlined" density="compact" :error-messages="form.errors.work_location" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.base_salary" type="number" min="0" step="0.01" label="Base salary *" variant="outlined" density="compact" :error-messages="form.errors.base_salary" />
          </v-col>
          <v-col cols="6" md="4">
            <v-select v-model="form.salary_frequency" :items="frequencyItems" label="Frequency *" variant="outlined" density="compact" :error-messages="form.errors.salary_frequency" />
          </v-col>
          <v-col cols="6" md="4">
            <v-text-field v-model="form.currency" label="Currency *" maxlength="3" variant="outlined" density="compact" :error-messages="form.errors.currency" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.expiry_date" type="date" label="Offer expires on *" variant="outlined" density="compact" :error-messages="form.errors.expiry_date" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.proposed_start_date" type="date" label="Proposed start date *" hint="On or after the expiry date" persistent-hint variant="outlined" density="compact" :error-messages="form.errors.proposed_start_date" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.benefits" label="Benefits and allowances" rows="2" variant="outlined" density="compact" :error-messages="form.errors.benefits" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.remarks" label="Remarks" rows="2" variant="outlined" density="compact" :error-messages="form.errors.remarks" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="$emit('update:modelValue', false)">Back</v-btn>
        <v-btn color="primary" :loading="form.processing" @click="submit">{{ offer ? "Save draft" : "Create draft" }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import { useForm } from "@inertiajs/vue3";
import { SALARY_FREQUENCIES, localDateParts } from "@/utils/recruitment";

export default {
  name: "OfferFormDialog",
  props: {
    modelValue: { type: Boolean, default: false },
    // Create: the application id. Edit: the draft offer.
    applicationId: { type: Number, default: null },
    offer: { type: Object, default: null },
    options: { type: Object, required: true },
  },
  emits: ["update:modelValue"],
  data() {
    const o = this.offer;
    const d = this.options.defaults || {};
    const inDays = (n) => localDateParts(new Date(Date.now() + n * 86400000)).date;
    return {
      form: useForm({
        job_title_id: o?.job_title_id ?? d.job_title_id ?? null,
        employment_status_id: o?.employment_status_id ?? d.employment_status_id ?? null,
        location_id: o?.location_id ?? d.location_id ?? null,
        work_location: o && !o.location_id ? o.work_location || "" : "",
        base_salary: o?.base_salary ?? "",
        salary_frequency: o?.salary_frequency ?? "monthly",
        currency: o?.currency ?? d.currency ?? "PHP",
        expiry_date: o?.expiry_date ?? inDays(7),
        proposed_start_date: o?.proposed_start_date ?? inDays(30),
        benefits: o?.benefits ?? "",
        remarks: o?.remarks ?? "",
      }),
    };
  },
  computed: {
    frequencyItems() {
      return (this.options.frequencies || Object.keys(SALARY_FREQUENCIES)).map((f) => ({ value: f, title: SALARY_FREQUENCIES[f] || f }));
    },
  },
  methods: {
    submit() {
      const form = this.form.transform((d) => ({ ...d, work_location: d.work_location || null }));
      const options = { preserveScroll: true, onSuccess: () => this.$emit("update:modelValue", false) };
      if (this.offer) {
        form.put(route("recruitment.offers.update", this.offer.id), options);
      } else {
        form.post(route("recruitment.applications.offers.store", this.applicationId), options);
      }
    },
  },
};
</script>

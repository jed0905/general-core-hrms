<template>
  <SidebarLayout>
    <Head :title="editing ? `Edit ${requisition.requisition_number}` : 'New Job Requisition'" />
    <v-container fluid class="pa-6" style="max-width: 1100px">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">{{ editing ? `Edit ${requisition.requisition_number}` : "New Job Requisition" }}</h1>
        <p class="text-body-2 text-medium-emphasis">Saved as a draft. Submit it for approval from the requisition page.</p>
      </div>

      <v-card variant="outlined" class="rounded-lg pa-5">
        <v-row>
          <v-col v-if="canChooseRequester" cols="12" md="6">
            <v-autocomplete v-model="form.requested_by_employee_id" :items="options.employees" item-title="title" item-value="value" label="Requested by *" variant="outlined" density="compact" :error-messages="form.errors.requested_by_employee_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete v-model="form.department_id" :items="options.departments" item-title="title" item-value="value" label="Department *" variant="outlined" density="compact" :error-messages="form.errors.department_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete v-model="form.job_title_id" :items="options.job_titles" item-title="title" item-value="value" label="Job title *" variant="outlined" density="compact" :error-messages="form.errors.job_title_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete v-model="form.location_id" :items="options.locations" item-title="title" item-value="value" label="Location" variant="outlined" density="compact" clearable :error-messages="form.errors.location_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-select v-model="form.employment_status_id" :items="options.employment_statuses" item-title="title" item-value="value" label="Employment status" variant="outlined" density="compact" clearable :error-messages="form.errors.employment_status_id" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model.number="form.positions" type="number" min="1" label="Positions *" variant="outlined" density="compact" :error-messages="form.errors.positions" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="form.target_start_date" type="date" label="Target start date" variant="outlined" density="compact" :error-messages="form.errors.target_start_date" />
          </v-col>
          <v-col cols="12" md="6">
            <v-radio-group v-model="form.reason" inline label="Reason *" :error-messages="form.errors.reason">
              <v-radio v-for="r in reasons" :key="r" :label="reasonLabel(r)" :value="r" />
            </v-radio-group>
          </v-col>
          <v-col v-if="form.reason === 'replacement'" cols="12" md="6">
            <v-autocomplete v-model="form.replaced_employee_id" :items="options.employees" item-title="title" item-value="value" label="Employee being replaced *" variant="outlined" density="compact" :error-messages="form.errors.replaced_employee_id" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.justification" label="Justification *" rows="4" counter="5000" variant="outlined" density="compact" :error-messages="form.errors.justification" />
          </v-col>
        </v-row>
        <v-alert v-if="form.errors.status" type="error" variant="tonal" class="mb-3">{{ form.errors.status }}</v-alert>
        <div class="d-flex justify-end ga-2">
          <v-btn variant="text" @click="cancel">Cancel</v-btn>
          <v-btn color="primary" :loading="form.processing" @click="submit">Save draft</v-btn>
        </div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { REASON_LABELS } from "@/utils/recruitment";

export default {
  name: "RequisitionForm",
  components: { SidebarLayout, Head },
  props: {
    requisition: { type: Object, default: null },
    options: { type: Object, required: true },
    reasons: { type: Array, default: () => [] },
    canChooseRequester: { type: Boolean, default: false },
    currentEmployeeId: { type: Number, default: null },
  },
  data() {
    const r = this.requisition || {};
    return {
      form: useForm({
        requested_by_employee_id: r.requested_by_employee_id ?? this.currentEmployeeId,
        department_id: r.department_id ?? null,
        job_title_id: r.job_title_id ?? null,
        location_id: r.location_id ?? null,
        employment_status_id: r.employment_status_id ?? null,
        positions: r.positions ?? 1,
        reason: r.reason ?? "new_position",
        replaced_employee_id: r.replaced_employee_id ?? null,
        justification: r.justification ?? "",
        target_start_date: r.target_start_date ?? null,
      }),
    };
  },
  computed: {
    editing() {
      return Boolean(this.requisition);
    },
  },
  methods: {
    reasonLabel(value) {
      return REASON_LABELS[value] || value;
    },
    submit() {
      if (this.editing) {
        this.form.put(route("recruitment.requisitions.update", this.requisition.id));
      } else {
        this.form.post(route("recruitment.requisitions.store"));
      }
    },
    cancel() {
      router.visit(this.editing ? route("recruitment.requisitions.show", this.requisition.id) : route("recruitment.requisitions.index"));
    },
  },
};
</script>

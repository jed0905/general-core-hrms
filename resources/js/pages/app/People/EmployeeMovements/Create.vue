<template>
  <SidebarLayout>
    <Head title="Record Employee Movement" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="mb-6">
        <v-btn
          variant="text"
          size="small"
          prepend-icon="mdi-arrow-left"
          class="mb-2 px-0"
          @click="back"
        >
          Employee Movements
        </v-btn>
        <h1 class="text-h5 font-weight-bold">Record Employee Movement</h1>
        <p class="text-body-2 text-medium-emphasis">
          Movements dated today or earlier apply immediately. Future-dated
          movements are scheduled and apply on their effective date.
        </p>
      </div>

      <v-row>
        <v-col cols="12" md="7">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-text>
              <v-row density="compact">
                <v-col cols="12">
                  <v-autocomplete
                    v-model="form.employee_id"
                    :items="employeeOptions"
                    label="Employee *"
                    variant="outlined"
                    density="compact"
                    :error-messages="form.errors.employee_id"
                    @update:model-value="loadCurrentState"
                  />
                </v-col>
                <v-col cols="12" sm="7">
                  <v-select
                    v-model="form.movement_type_id"
                    :items="typeOptions"
                    label="Movement Type *"
                    variant="outlined"
                    density="compact"
                    :error-messages="form.errors.movement_type_id"
                    @update:model-value="onTypeChange"
                  />
                </v-col>
                <v-col cols="12" sm="5">
                  <v-text-field
                    v-model="form.effective_date"
                    type="date"
                    label="Effective Date *"
                    variant="outlined"
                    density="compact"
                    :error-messages="form.errors.effective_date"
                  />
                </v-col>
                <v-col v-if="selectedType?.description" cols="12" class="pt-0">
                  <p class="text-caption text-medium-emphasis">{{ selectedType.description }}</p>
                </v-col>
                <v-col v-if="selectedType?.employee_status" cols="12" class="pt-0">
                  <v-alert type="warning" variant="tonal" density="compact">
                    This movement ends the employment: the employee's record
                    status becomes <strong>{{ selectedType.employee_status }}</strong>
                    on the effective date.
                  </v-alert>
                </v-col>
              </v-row>

              <template v-if="selectedType">
                <v-divider class="my-4" />
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-subtitle-2 font-weight-bold">New Information</span>
                  <v-switch
                    v-model="showAllFields"
                    label="Show all fields"
                    color="primary"
                    density="compact"
                    hide-details
                  />
                </div>
                <p v-if="!visibleFields.length" class="text-body-2 text-medium-emphasis">
                  This movement records the employee's current assignment; nothing
                  is changed.
                </p>
                <div
                  v-if="form.errors.changed_fields"
                  class="text-caption text-error mb-2"
                >
                  {{ form.errors.changed_fields }}
                </div>
                <v-row v-for="field in visibleFields" :key="field" density="compact" align="center">
                  <v-col cols="12" sm="4">
                    <v-checkbox
                      v-model="form.changed_fields"
                      :value="field"
                      :label="fieldLabels[field]"
                      density="compact"
                      hide-details
                    />
                  </v-col>
                  <v-col cols="12" sm="8">
                    <v-autocomplete
                      v-model="form.to[field]"
                      :items="options[field] || []"
                      :label="`New ${fieldLabels[field].toLowerCase()}`"
                      :disabled="!form.changed_fields.includes(field)"
                      variant="outlined"
                      density="compact"
                      clearable
                      :hint="currentLabel(field) ? `Currently: ${currentLabel(field)}` : 'Currently: none'"
                      persistent-hint
                      :error-messages="form.errors[`to_${field}`]"
                    />
                  </v-col>
                </v-row>
              </template>

              <v-divider class="my-4" />
              <v-row density="compact">
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.reference_number"
                    label="Reference No."
                    variant="outlined"
                    density="compact"
                    :error-messages="form.errors.reference_number"
                  />
                </v-col>
                <v-col cols="12">
                  <v-textarea
                    v-model="form.reason"
                    label="Reason"
                    rows="2"
                    variant="outlined"
                    density="compact"
                    :error-messages="form.errors.reason"
                  />
                </v-col>
                <v-col cols="12">
                  <v-textarea
                    v-model="form.remarks"
                    label="Remarks"
                    rows="2"
                    variant="outlined"
                    density="compact"
                    :error-messages="form.errors.remarks"
                  />
                </v-col>
              </v-row>
            </v-card-text>
            <v-card-actions class="justify-end ga-2 pa-4 pt-0">
              <v-btn variant="outlined" @click="back">Cancel</v-btn>
              <v-btn
                color="primary"
                variant="flat"
                :loading="form.processing"
                :disabled="!form.employee_id || !form.movement_type_id"
                @click="submit"
              >
                Save Movement
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>

        <v-col cols="12" md="5">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">Current Information</v-card-title>
            <v-card-text>
              <p v-if="!currentState" class="text-body-2 text-medium-emphasis">
                Select an employee to see their current assignment.
              </p>
              <template v-else>
                <div v-for="(label, key) in snapshotLabels" :key="key" class="mb-2">
                  <div class="text-caption text-medium-emphasis">{{ label }}</div>
                  <div class="text-body-2 font-weight-medium">{{ currentState.labels[key] || "—" }}</div>
                </div>
                <p class="text-caption text-medium-emphasis mt-3">
                  For reference. The saved "before" values are read from the
                  employee record at the moment the movement applies.
                </p>
              </template>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { FIELD_LABELS, SNAPSHOT_KEYS, SNAPSHOT_LABELS } from "@/utils/employeeMovement";

export default {
  name: "EmployeeMovementCreate",
  components: { SidebarLayout, Head },
  props: {
    employees: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    fields: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
    selectedEmployeeId: { type: Number, default: null },
    currentState: { type: Object, default: null },
  },
  data() {
    return {
      fieldLabels: FIELD_LABELS,
      snapshotLabels: SNAPSHOT_LABELS,
      showAllFields: false,
      form: useForm({
        employee_id: this.selectedEmployeeId,
        movement_type_id: null,
        effective_date: new Date().toISOString().substring(0, 10),
        reference_number: "",
        reason: "",
        remarks: "",
        changed_fields: [],
        to: Object.fromEntries(this.fields.map((f) => [f, null])),
      }),
    };
  },
  computed: {
    employeeOptions() {
      return this.employees.map((e) => ({
        value: e.id,
        title: `${e.emp_last_name}, ${e.emp_first_name} (${e.employee_number})${e.status !== "active" ? ` · ${e.status}` : ""}`,
      }));
    },
    typeOptions() {
      return this.types.map((t) => ({ value: t.id, title: t.name }));
    },
    selectedType() {
      return this.types.find((t) => t.id === this.form.movement_type_id) || null;
    },
    visibleFields() {
      if (!this.selectedType) return [];
      if (this.showAllFields) return this.fields;
      return this.fields.filter((f) => (this.selectedType.affected_fields || []).includes(f));
    },
  },
  methods: {
    currentLabel(field) {
      return this.currentState?.labels?.[SNAPSHOT_KEYS[field]] ?? null;
    },
    loadCurrentState(employeeId) {
      router.get(
        route("people.employee-movements.create"),
        employeeId ? { employee_id: employeeId } : {},
        { only: ["currentState", "selectedEmployeeId"], preserveState: true, preserveScroll: true, replace: true }
      );
    },
    onTypeChange() {
      this.showAllFields = false;
      this.form.changed_fields = [];
    },
    submit() {
      this.form
        .transform((data) => {
          // Only the requested changes are sent; the server works out the rest.
          const payload = {
            employee_id: data.employee_id,
            movement_type_id: data.movement_type_id,
            effective_date: data.effective_date,
            reference_number: data.reference_number,
            reason: data.reason,
            remarks: data.remarks,
            changed_fields: data.changed_fields,
          };
          data.changed_fields.forEach((field) => {
            payload[`to_${field}`] = data.to[field];
          });
          return payload;
        })
        .post(route("people.employee-movements.store"));
    },
    back() {
      router.visit(route("people.employee-movements.index"));
    },
  },
};
</script>

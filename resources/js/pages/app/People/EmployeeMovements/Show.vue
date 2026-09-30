<template>
  <SidebarLayout>
    <Head title="Employee Movement" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-4 mb-6">
        <div>
          <v-btn
            v-if="canList"
            variant="text"
            size="small"
            prepend-icon="mdi-arrow-left"
            class="mb-2 px-0"
            @click="go('people.employee-movements.index')"
          >
            Employee Movements
          </v-btn>
          <h1 class="text-h5 font-weight-bold">{{ movement.type?.name }}</h1>
          <p class="text-body-2 text-medium-emphasis">
            {{ employeeName(movement.employee) }} · {{ movement.employee?.employee_number }}
          </p>
        </div>
        <div class="d-flex flex-wrap align-center ga-2">
          <v-chip :color="status.color" variant="tonal">{{ status.label }}</v-chip>
          <v-btn v-if="can.update" variant="outlined" prepend-icon="mdi-pencil-outline" @click="openEdit">
            Edit Details
          </v-btn>
          <v-btn v-if="can.cancel" color="error" variant="outlined" prepend-icon="mdi-undo" @click="cancelDialog = true">
            {{ movement.status === "implemented" ? "Reverse" : "Cancel" }}
          </v-btn>
        </div>
      </div>

      <v-row>
        <v-col cols="12" md="7">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">Before and After</v-card-title>
            <v-table density="comfortable">
              <thead>
                <tr>
                  <th />
                  <th>{{ movement.status === "approved" ? "Current" : "Before" }}</th>
                  <th>After</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(label, key) in snapshotLabels" :key="key" :class="{ 'font-weight-bold': changed(key) }">
                  <td class="text-medium-emphasis">{{ label }}</td>
                  <td>{{ displayFrom(key) }}</td>
                  <td>
                    {{ displayTo(key) }}
                    <v-icon v-if="changed(key)" icon="mdi-arrow-left-bold" size="x-small" color="primary" class="ml-1" />
                  </td>
                </tr>
              </tbody>
            </v-table>
            <v-card-text v-if="movement.status === 'approved'" class="text-caption text-medium-emphasis">
              Scheduled: the "before" values are captured from the employee
              record when the movement applies.
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="5">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">Details</v-card-title>
            <v-card-text>
              <div v-for="row in detailRows" :key="row.label" class="mb-3">
                <div class="text-caption text-medium-emphasis">{{ row.label }}</div>
                <div class="text-body-2" style="white-space: pre-line">{{ row.value || "—" }}</div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Edit descriptive fields -->
      <v-dialog v-model="editDialog" max-width="560">
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">Edit Movement Details</v-card-title>
          <v-card-text>
            <p class="text-caption text-medium-emphasis mb-3">
              Employment data can't be edited. To correct it, reverse this
              movement and record a new one.
            </p>
            <v-text-field v-model="editForm.reference_number" label="Reference No." variant="outlined" density="compact" :error-messages="editForm.errors.reference_number" />
            <v-textarea v-model="editForm.reason" label="Reason" rows="2" variant="outlined" density="compact" :error-messages="editForm.errors.reason" />
            <v-textarea v-model="editForm.remarks" label="Remarks" rows="2" variant="outlined" density="compact" :error-messages="editForm.errors.remarks" />
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="editDialog = false">Cancel</v-btn>
            <v-btn color="primary" variant="flat" :loading="editForm.processing" @click="saveEdit">Save</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Cancel / reverse -->
      <v-dialog v-model="cancelDialog" max-width="520">
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">
            {{ movement.status === "implemented" ? "Reverse movement?" : "Cancel scheduled movement?" }}
          </v-card-title>
          <v-card-text>
            <p v-if="movement.status === 'implemented'" class="text-body-2 mb-3">
              The employee's assignment is restored to the "Before" values. This
              only works for the employee's latest movement, and only if those
              fields haven't been changed since. The movement stays on record as
              cancelled.
            </p>
            <p v-else class="text-body-2 mb-3">
              The movement will not be applied. It stays on record as cancelled.
            </p>
            <v-textarea
              v-model="cancelForm.cancellation_reason"
              label="Reason *"
              rows="2"
              variant="outlined"
              density="compact"
              :error-messages="cancelForm.errors.cancellation_reason || cancelForm.errors.movement"
            />
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="cancelDialog = false">Keep</v-btn>
            <v-btn color="error" variant="flat" :loading="cancelForm.processing" @click="submitCancel">
              {{ movement.status === "implemented" ? "Reverse" : "Cancel Movement" }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { SNAPSHOT_LABELS, employeeName, formatDate, movementStatus } from "@/utils/employeeMovement";

export default {
  name: "EmployeeMovementShow",
  components: { SidebarLayout, Head },
  props: {
    movement: { type: Object, required: true },
    // Record-level abilities from EmployeeMovementPolicy.
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      snapshotLabels: SNAPSHOT_LABELS,
      editDialog: false,
      cancelDialog: false,
      editForm: useForm({ reference_number: "", reason: "", remarks: "" }),
      cancelForm: useForm({ cancellation_reason: "" }),
    };
  },
  computed: {
    canList() {
      // `can` is taken by the record-level prop, so read the shared permissions directly.
      return (this.$page.props.auth?.permissions || []).includes("employee_movement.view");
    },
    status() {
      return movementStatus(this.movement.status);
    },
    from() {
      return this.movement.snapshot?.from ?? {};
    },
    to() {
      return this.movement.snapshot?.to ?? {};
    },
    detailRows() {
      const m = this.movement;
      const rows = [
        { label: "Effective Date", value: formatDate(m.effective_date) },
        { label: "Recorded", value: `${new Date(m.created_at).toLocaleString()}${m.created_by ? ` by ${m.created_by.username}` : ""}` },
        { label: "Reference No.", value: m.reference_number },
        { label: "Reason", value: m.reason },
        { label: "Remarks", value: m.remarks },
      ];
      if (m.implemented_at) rows.splice(1, 0, { label: "Applied", value: new Date(m.implemented_at).toLocaleString() });
      if (m.status === "cancelled") {
        rows.push({ label: "Cancelled", value: `${new Date(m.cancelled_at).toLocaleString()}${m.cancelled_by ? ` by ${m.cancelled_by.username}` : ""}` });
        rows.push({ label: "Cancellation Reason", value: m.cancellation_reason });
      }
      return rows;
    },
  },
  methods: {
    employeeName,
    go(name, params = {}) {
      router.visit(route(name, params));
    },
    changed(key) {
      return key in this.to && (this.from[key] ?? null) !== (this.to[key] ?? null);
    },
    displayFrom(key) {
      return this.from[key] ?? "—";
    },
    displayTo(key) {
      // Scheduled movements only store the requested changes.
      return key in this.to ? this.to[key] ?? "—" : this.displayFrom(key);
    },
    openEdit() {
      this.editForm.reference_number = this.movement.reference_number ?? "";
      this.editForm.reason = this.movement.reason ?? "";
      this.editForm.remarks = this.movement.remarks ?? "";
      this.editForm.clearErrors();
      this.editDialog = true;
    },
    saveEdit() {
      this.editForm.put(route("people.employee-movements.update", { employeeMovement: this.movement.id }), {
        preserveScroll: true,
        onSuccess: () => (this.editDialog = false),
      });
    },
    submitCancel() {
      this.cancelForm.post(route("people.employee-movements.cancel", { employeeMovement: this.movement.id }), {
        preserveScroll: true,
        onSuccess: () => {
          this.cancelDialog = false;
          this.cancelForm.reset();
        },
      });
    },
  },
};
</script>

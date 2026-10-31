<template>
  <SidebarLayout>
    <Head title="Biometric Enrollments" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">Biometric Enrollments</h1>
          <p class="text-body-2 text-medium-emphasis">Which device user ID belongs to which employee, per device. Fingerprints and faces stay on the devices; the HRMS stores only these references.</p>
        </div>
        <v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="openCreate">Map employee</v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="5"><v-text-field v-model="form.search" label="Employee or device user ID" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="() => { form.search = ''; apply(); }" /></v-col>
          <v-col cols="12" md="4"><v-select v-model="form.device_id" :items="devices" item-title="name" item-value="id" label="Device" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="3"><v-select v-model="form.status" :items="['pending', 'active', 'inactive']" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead><tr><th>Employee</th><th>Device</th><th>Device user ID</th><th>Status</th><th>Enrolled</th><th>Remarks</th><th></th></tr></thead>
          <tbody>
            <tr v-if="!enrollments.data.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No mappings.</td></tr>
            <tr v-for="e in enrollments.data" :key="e.id">
              <td>{{ employeeName(e.employee) }}<div class="text-caption text-medium-emphasis">{{ e.employee?.employee_number }}</div></td>
              <td>{{ e.device?.name }}<div class="text-caption text-medium-emphasis">{{ e.device?.vendor }}</div></td>
              <td class="font-weight-medium">{{ e.device_user_id }}</td>
              <td><v-chip size="x-small" variant="tonal" :color="e.status === 'active' ? 'success' : e.status === 'pending' ? 'warning' : 'grey'">{{ e.status }}</v-chip></td>
              <td>{{ date(e.enrolled_on) }}<div class="text-caption text-medium-emphasis">{{ e.enrolled_by?.username }}</div></td>
              <td class="text-caption">{{ e.remarks }}</td>
              <td class="text-end text-no-wrap">
                <template v-if="can.manage">
                  <v-btn v-if="e.status !== 'inactive'" size="small" variant="text" @click="openReassign(e)">Change ID</v-btn>
                  <v-btn v-if="e.status !== 'inactive'" size="small" variant="text" color="error" @click="act('deactivate', e)">Deactivate</v-btn>
                  <v-btn v-else size="small" variant="text" @click="act('activate', e)">Reactivate</v-btn>
                </template>
              </td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="enrollments" /></div>
      </v-card>

      <v-dialog v-model="dialogs.create" max-width="560">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Map employee to device</v-card-title>
          <v-card-text>
            <v-autocomplete v-model="createForm.employee_id" :items="employees" :item-title="(e) => `${employeeName(e)} · ${e.employee_number}`" item-value="id" label="Employee" variant="outlined" density="compact" class="mb-2" :error-messages="createForm.errors.employee_id" />
            <v-select v-model="createForm.attendance_device_id" :items="devices" :item-title="(d) => `${d.name} (${d.vendor})`" item-value="id" label="Device" variant="outlined" density="compact" class="mb-2" :error-messages="createForm.errors.attendance_device_id" />
            <v-text-field v-model="createForm.device_user_id" label="User ID on the device (employee no. on Hikvision, PIN on ZKTeco)" variant="outlined" density="compact" class="mb-2" :error-messages="createForm.errors.device_user_id" />
            <v-text-field v-model="createForm.card_number" label="Card number (optional, stored encrypted)" variant="outlined" density="compact" class="mb-2" />
            <div class="d-flex ga-2">
              <v-select v-model="createForm.status" :items="[{ title: 'Active', value: 'active' }, { title: 'Pending (not yet enrolled on device)', value: 'pending' }]" label="Status" variant="outlined" density="compact" />
              <v-text-field v-model="createForm.enrolled_on" type="date" label="Enrolled on" variant="outlined" density="compact" />
            </div>
            <v-text-field v-model="createForm.remarks" label="Remarks" variant="outlined" density="compact" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.create = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="createForm.processing" @click="save">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.reassign" max-width="460">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Change device user ID</v-card-title>
          <v-card-text>
            <p class="text-body-2 mb-3">The current mapping is closed and kept in the history; punches before today stay with the old ID.</p>
            <v-text-field v-model="reassignForm.device_user_id" label="New device user ID" variant="outlined" density="compact" :error-messages="reassignForm.errors.device_user_id" />
            <v-text-field v-model="reassignForm.remarks" label="Reason" variant="outlined" density="compact" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.reassign = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="reassignForm.processing" @click="reassign">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import attendancePage from "@/mixins/attendancePage";

export default {
  name: "BiometricEnrollmentIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [attendancePage],
  props: {
    enrollments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    devices: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      form: { search: this.filters.search || "", device_id: this.filters.device_id ? Number(this.filters.device_id) : null, status: this.filters.status || null },
      dialogs: { create: false, reassign: false },
      createForm: useForm({}),
      reassignForm: useForm({ id: null, device_user_id: "", remarks: "" }),
    };
  },
  methods: {
    apply() {
      router.get(route("attendance.enrollments.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    openCreate() {
      this.createForm = useForm({ employee_id: null, attendance_device_id: this.form.device_id, device_user_id: "", card_number: "", status: "active", enrolled_on: new Date().toISOString().substring(0, 10), remarks: "" });
      this.dialogs.create = true;
    },
    save() {
      this.createForm.transform((d) => ({ ...d, card_number: d.card_number || null })).post(route("attendance.enrollments.store"), { preserveScroll: true, onSuccess: () => (this.dialogs.create = false) });
    },
    openReassign(e) {
      this.reassignForm = useForm({ id: e.id, device_user_id: "", remarks: "" });
      this.dialogs.reassign = true;
    },
    reassign() {
      this.reassignForm.transform(({ id, ...d }) => d).put(route("attendance.enrollments.reassign", this.reassignForm.id), { preserveScroll: true, onSuccess: () => (this.dialogs.reassign = false) });
    },
    act(action, e) {
      router.put(route(`attendance.enrollments.${action}`, e.id), {}, { preserveScroll: true, onError: (errors) => this.showToast(this.firstError(errors), "error") });
    },
  },
};
</script>

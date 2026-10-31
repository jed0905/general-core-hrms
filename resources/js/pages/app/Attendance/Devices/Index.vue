<template>
  <SidebarLayout>
    <Head title="Biometric Devices" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">Biometric Devices</h1>
          <p class="text-body-2 text-medium-emphasis">Devices the HRMS reads attendance from. No vendor desktop software is needed.</p>
        </div>
        <v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="openCreate">Register device</v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="4"><v-text-field v-model="form.search" label="Name or serial" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="() => { form.search = ''; apply(); }" /></v-col>
          <v-col cols="12" md="3"><v-select v-model="form.vendor" :items="options.vendors.map((v) => ({ title: v.label, value: v.vendor }))" label="Vendor" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="2"><v-select v-model="form.status" :items="Object.entries(statusMap).map(([value, s]) => ({ value, title: s.label }))" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="3"><v-select v-model="form.location_id" :items="options.locations" :item-title="(l) => l.city || l.address" item-value="id" label="Location" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead><tr><th>Device</th><th>Vendor / model</th><th>Location</th><th>Connection</th><th>Status</th><th>Last sync</th><th class="text-end">Mapped users</th></tr></thead>
          <tbody>
            <tr v-if="!devices.data.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No devices registered.</td></tr>
            <tr v-for="d in devices.data" :key="d.id">
              <td>
                <a :href="route('attendance.devices.show', d.id)" class="text-primary font-weight-medium text-decoration-none" @click.prevent="go(route('attendance.devices.show', d.id))">{{ d.name }}</a>
                <v-chip v-if="!d.is_active" size="x-small" class="ml-1">Inactive</v-chip>
                <div class="text-caption text-medium-emphasis">{{ d.serial_number || 'no serial' }}</div>
              </td>
              <td>{{ vendorLabel(d.vendor) }}<div class="text-caption text-medium-emphasis">{{ d.model || '—' }}</div></td>
              <td>{{ d.location?.city || '—' }}<div class="text-caption text-medium-emphasis">{{ d.legal_entity?.name }}</div></td>
              <td class="text-caption">{{ methodLabel(d.vendor, d.connection_method) }}</td>
              <td>
                <v-chip size="small" variant="tonal" :color="statusInfo(statusMap, d.status).color">{{ statusInfo(statusMap, d.status).label }}</v-chip>
                <div v-if="d.last_error" class="text-caption text-error" style="max-width: 260px">{{ d.last_error }}</div>
              </td>
              <td class="text-caption">{{ dateTime(d.last_sync_at) }}</td>
              <td class="text-end">{{ d.active_enrollments_count }}</td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="devices" /></div>
      </v-card>

      <v-dialog v-model="dialog" max-width="760">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Register device</v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" md="6"><v-text-field v-model="createForm.name" label="Name" variant="outlined" density="compact" :error-messages="createForm.errors.name" /></v-col>
              <v-col cols="12" md="3"><v-select v-model="createForm.vendor" :items="options.vendors.map((v) => ({ title: v.label, value: v.vendor }))" label="Vendor" variant="outlined" density="compact" :error-messages="createForm.errors.vendor" @update:model-value="createForm.connection_method = null" /></v-col>
              <v-col cols="12" md="3"><v-text-field v-model="createForm.model" label="Model (e.g. DS-K1T321)" variant="outlined" density="compact" :error-messages="createForm.errors.model" /></v-col>
              <v-col cols="12" md="4"><v-text-field v-model="createForm.serial_number" label="Serial number" variant="outlined" density="compact" :error-messages="createForm.errors.serial_number" /></v-col>
              <v-col cols="12" md="4"><v-select v-model="createForm.location_id" :items="options.locations" :item-title="(l) => l.city || l.address" item-value="id" label="Location / branch" variant="outlined" density="compact" clearable /></v-col>
              <v-col cols="12" md="4"><v-select v-model="createForm.legal_entity_id" :items="options.legalEntities" item-title="name" item-value="id" label="Legal entity" variant="outlined" density="compact" clearable /></v-col>
              <v-col cols="12" md="6"><v-autocomplete v-model="createForm.timezone" :items="options.timezones" label="Device clock timezone" variant="outlined" density="compact" :error-messages="createForm.errors.timezone" /></v-col>
              <v-col cols="12" md="6"><v-select v-model="createForm.connection_method" :items="methodItems(createForm.vendor)" label="Connection" variant="outlined" density="compact" :error-messages="createForm.errors.connection_method" /></v-col>
              <template v-if="createForm.connection_method === 'isapi_pull'">
                <v-col cols="12" md="5"><v-text-field v-model="createForm.host" label="IP address or hostname" variant="outlined" density="compact" :error-messages="createForm.errors.host" /></v-col>
                <v-col cols="6" md="2"><v-text-field v-model="createForm.port" label="Port" type="number" variant="outlined" density="compact" /></v-col>
                <v-col cols="6" md="2"><v-text-field v-model="createForm.page_size" label="Page size" type="number" variant="outlined" density="compact" hint="Events per request" persistent-hint /></v-col>
                <v-col cols="12" md="3"><v-switch v-model="createForm.use_tls" label="HTTPS" color="primary" density="compact" hide-details /></v-col>
                <template v-if="can.configure">
                  <v-col cols="12" md="6"><v-text-field v-model="createForm.username" label="Device username" autocomplete="off" variant="outlined" density="compact" /></v-col>
                  <v-col cols="12" md="6"><v-text-field v-model="createForm.password" label="Device password" type="password" autocomplete="new-password" variant="outlined" density="compact" hint="Stored encrypted; never shown again" persistent-hint /></v-col>
                </template>
              </template>
              <v-col v-if="createForm.connection_method === 'connector'" cols="12" md="6"><v-select v-model="createForm.attendance_connector_id" :items="options.connectors" item-title="name" item-value="id" label="Connector" variant="outlined" density="compact" /></v-col>
              <v-col v-if="createForm.vendor === 'simulator'" cols="12"><v-alert type="info" variant="tonal" density="compact">Simulated devices produce clearly labelled SIMULATED events for testing; configure the scenario on the device page.</v-alert></v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialog = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="createForm.processing" @click="create">Register</v-btn></v-card-actions>
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
import { DEVICE_STATUS } from "@/utils/attendance";

export default {
  name: "AttendanceDeviceIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [attendancePage],
  props: {
    devices: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      statusMap: DEVICE_STATUS,
      dialog: false,
      form: { search: this.filters.search || "", vendor: this.filters.vendor || null, status: this.filters.status || null, location_id: this.filters.location_id ? Number(this.filters.location_id) : null },
      createForm: useForm({}),
    };
  },
  methods: {
    apply() {
      router.get(route("attendance.devices.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    vendorLabel(vendor) {
      return this.options.vendors.find((v) => v.vendor === vendor)?.label || vendor;
    },
    methodLabel(vendor, method) {
      return this.options.vendors.find((v) => v.vendor === vendor)?.connection_methods?.[method] || method;
    },
    methodItems(vendor) {
      const methods = this.options.vendors.find((v) => v.vendor === vendor)?.connection_methods || {};
      return Object.entries(methods).map(([value, title]) => ({ value, title }));
    },
    openCreate() {
      this.createForm = useForm({ name: "", vendor: "hikvision", model: "", serial_number: "", location_id: null, legal_entity_id: null, timezone: "Asia/Manila", connection_method: null, host: "", port: 80, page_size: 30, use_tls: false, verify_tls: true, username: "", password: "", attendance_connector_id: null });
      this.dialog = true;
    },
    create() {
      this.createForm
        .transform((d) => ({ ...d, port: d.port || null, page_size: d.page_size || null, host: d.host || null, username: d.username || null, password: d.password || null }))
        .post(route("attendance.devices.store"), { onError: (errors) => this.showToast(this.firstError(errors), "error") });
    },
  },
};
</script>

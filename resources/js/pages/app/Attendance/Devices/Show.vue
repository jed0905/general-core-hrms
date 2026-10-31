<template>
  <SidebarLayout>
    <Head :title="device.name" />
    <v-container fluid class="pa-6">
      <a :href="route('attendance.devices.index')" class="text-caption text-primary text-decoration-none" @click.prevent="go(route('attendance.devices.index'))">← Devices</a>
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">
            {{ device.name }}
            <v-chip size="small" variant="tonal" class="ml-2" :color="statusInfo(statusMap, device.status).color">{{ statusInfo(statusMap, device.status).label }}</v-chip>
            <v-chip v-if="!device.is_active" size="small" class="ml-1">Inactive</v-chip>
            <v-chip v-if="device.vendor === 'simulator'" size="small" color="deep-purple" variant="tonal" class="ml-1">SIMULATOR</v-chip>
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            {{ vendorLabel }} · {{ device.model || 'model not reported' }} · serial {{ device.serial_number || '—' }} · firmware {{ device.firmware_version || 'not reported' }} · clock {{ device.timezone }}
          </p>
        </div>
        <div class="d-flex flex-wrap ga-2">
          <v-btn v-if="can.sync && has('test_connection')" variant="outlined" prepend-icon="mdi-lan-connect" :loading="busy === 'test'" @click="post('test')">Test connection</v-btn>
          <v-btn v-if="can.sync && has('fetch_events')" color="primary" prepend-icon="mdi-sync" :loading="busy === 'sync'" :disabled="!device.is_active" @click="post('sync')">Synchronize now</v-btn>
          <v-btn v-if="can.manage" variant="text" @click="toggle">{{ device.is_active ? 'Deactivate' : 'Activate' }}</v-btn>
        </div>
      </div>

      <v-alert v-if="newPushToken" type="warning" variant="tonal" class="mb-4">
        <div class="font-weight-medium mb-1">Push URL with the new token (shown once):</div>
        <code class="text-body-2" style="word-break: break-all">{{ pushUrl.replace('{token}', newPushToken) }}</code>
      </v-alert>
      <v-alert v-if="device.last_error" type="error" variant="tonal" density="compact" class="mb-4">Last error: {{ device.last_error }}</v-alert>

      <v-row class="mb-2">
        <v-col v-for="s in statCards" :key="s.key" cols="6" md="2">
          <v-card variant="outlined" class="rounded-lg pa-3">
            <div class="text-caption text-medium-emphasis">{{ s.label }}</div>
            <div class="text-h6 font-weight-bold">{{ stats[s.key] ?? 0 }}</div>
          </v-card>
        </v-col>
        <v-col cols="12" md="2">
          <v-card variant="outlined" class="rounded-lg pa-3">
            <div class="text-caption text-medium-emphasis">Last contact</div>
            <div class="text-body-2">{{ dateTime(device.last_seen_at) }}</div>
            <div v-if="device.clock_drift_seconds !== null" class="text-caption" :class="Math.abs(device.clock_drift_seconds) > 120 ? 'text-error' : 'text-medium-emphasis'">Clock drift {{ device.clock_drift_seconds }}s</div>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" md="5">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold">Details</v-card-title>
            <v-card-text>
              <v-text-field v-model="details.name" label="Name" variant="outlined" density="compact" :disabled="!can.manage" :error-messages="details.errors.name" />
              <v-text-field v-model="details.model" label="Model" variant="outlined" density="compact" :disabled="!can.manage" />
              <v-text-field v-model="details.serial_number" label="Serial number" variant="outlined" density="compact" :disabled="!can.manage" :error-messages="details.errors.serial_number" />
              <v-select v-model="details.location_id" :items="options.locations" :item-title="(l) => l.city || l.address" item-value="id" label="Location" variant="outlined" density="compact" clearable :disabled="!can.manage" />
              <v-select v-model="details.legal_entity_id" :items="options.legalEntities" item-title="name" item-value="id" label="Legal entity" variant="outlined" density="compact" clearable :disabled="!can.manage" />
              <v-autocomplete v-model="details.timezone" :items="options.timezones" label="Device clock timezone" variant="outlined" density="compact" :disabled="!can.manage" />
              <v-btn v-if="can.manage" color="primary" variant="tonal" :loading="details.processing" @click="details.put(route('attendance.devices.update', device.id), { preserveScroll: true })">Save details</v-btn>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="7">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold">Connection</v-card-title>
            <v-card-text>
              <p class="text-caption text-medium-emphasis mb-3">Capabilities: {{ (device.capabilities || []).map(label).join(', ') || 'none (import / manual entry only)' }}</p>
              <template v-if="can.configure">
                <v-select v-model="connection.connection_method" :items="methodItems" label="Connection method" variant="outlined" density="compact" :error-messages="connection.errors.connection_method" />
                <template v-if="connection.connection_method === 'isapi_pull'">
                  <div class="d-flex ga-2">
                    <v-text-field v-model="connection.host" label="IP / hostname" variant="outlined" density="compact" :error-messages="connection.errors.host" />
                    <v-text-field v-model="connection.port" label="Port" type="number" variant="outlined" density="compact" style="max-width: 110px" />
                    <v-text-field v-model="connection.page_size" label="Page size" type="number" variant="outlined" density="compact" style="max-width: 110px" />
                  </div>
                  <div class="d-flex ga-4">
                    <v-switch v-model="connection.use_tls" label="HTTPS" color="primary" density="compact" hide-details />
                    <v-switch v-model="connection.verify_tls" label="Verify certificate" color="primary" density="compact" hide-details :disabled="!connection.use_tls" />
                  </div>
                  <div class="d-flex ga-2 mt-2">
                    <v-text-field v-model="connection.username" label="Username (blank = keep)" autocomplete="off" variant="outlined" density="compact" />
                    <v-text-field v-model="connection.password" label="Password (blank = keep)" type="password" autocomplete="new-password" variant="outlined" density="compact" />
                  </div>
                  <p class="text-caption text-medium-emphasis">{{ device.has_credentials ? 'Credentials are stored (encrypted).' : 'No credentials stored.' }}</p>
                  <v-combobox v-model="connection.minors" label="Event minor codes (Hikvision AcsEvent filter)" hint="Default 38 (as tested on the DS-K1T321). Add codes your firmware uses for face/card events." persistent-hint multiple chips variant="outlined" density="compact" class="mt-2" />
                </template>
                <v-select v-if="connection.connection_method === 'connector'" v-model="connection.attendance_connector_id" :items="options.connectors" item-title="name" item-value="id" label="Connector" variant="outlined" density="compact" />
                <v-textarea v-if="device.vendor === 'simulator'" v-model="connection.simulationText" label="Simulation scenario (JSON)" rows="6" variant="outlined" density="compact" :error-messages="simulationError" />
                <v-btn color="primary" variant="tonal" class="mt-2" :loading="connection.processing" @click="saveConnection">Save connection</v-btn>
              </template>

              <div v-if="pushUrl" class="mt-4">
                <div class="text-subtitle-2">Device push</div>
                <p v-if="isHikvisionIsapi" class="text-body-2">Configure the device's HTTP listening (event upload) address as <code>{{ pushUrl }}</code><span v-if="device.connection_method === 'isapi_pull'"> (optional, alongside polling; events are stored once)</span>. Use the server's LAN address, never localhost. {{ device.has_push_token ? 'A token is set.' : 'No token yet.' }}</p>
                <p v-else class="text-body-2">Set the device's cloud/ADMS server address to <code>{{ pushUrl }}</code> (it uses the fixed paths /iclock/…). The device is identified by its serial number.</p>
                <v-btn v-if="can.configure && isHikvisionIsapi" size="small" variant="outlined" @click="rotateToken">{{ device.has_push_token ? 'Replace push token' : 'Create push token' }}</v-btn>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-card variant="outlined" class="rounded-lg">
        <v-card-title class="text-subtitle-1 font-weight-bold">Synchronization history</v-card-title>
        <v-table density="compact">
          <thead><tr><th>Started</th><th>Trigger</th><th>Status</th><th class="text-end">Fetched</th><th class="text-end">New</th><th class="text-end">Duplicates</th><th class="text-end">Unmapped</th><th class="text-end">Invalid</th><th>Attempts</th><th>Message</th></tr></thead>
          <tbody>
            <tr v-if="!syncRuns.data.length"><td colspan="10" class="text-center text-medium-emphasis pa-6">No synchronizations yet.</td></tr>
            <tr v-for="r in syncRuns.data" :key="r.id">
              <td class="text-no-wrap">{{ dateTime(r.started_at) }}</td>
              <td>{{ label(r.trigger) }}<div v-if="r.triggered_by" class="text-caption text-medium-emphasis">{{ r.triggered_by.username }}</div></td>
              <td><v-chip size="x-small" variant="tonal" :color="statusInfo(syncMap, r.status).color">{{ statusInfo(syncMap, r.status).label }}</v-chip></td>
              <td class="text-end">{{ r.fetched }}</td>
              <td class="text-end">{{ r.inserted }}</td>
              <td class="text-end">{{ r.duplicates }}</td>
              <td class="text-end">{{ r.unmatched }}</td>
              <td class="text-end">{{ r.invalid }}</td>
              <td>{{ r.attempts }}</td>
              <td class="text-caption">{{ r.error_code ? r.error_code + ': ' : '' }}{{ r.error_message }}</td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="syncRuns" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import attendancePage from "@/mixins/attendancePage";
import { DEVICE_STATUS, SYNC_STATUS } from "@/utils/attendance";

export default {
  name: "AttendanceDeviceShow",
  components: { SidebarLayout, Head, Pagination },
  mixins: [attendancePage],
  props: {
    device: { type: Object, required: true },
    syncRuns: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    pushUrl: { type: String, default: null },
    newPushToken: { type: String, default: null },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      statusMap: DEVICE_STATUS,
      syncMap: SYNC_STATUS,
      busy: null,
      simulationError: null,
      statCards: [
        { key: "events_total", label: "Events stored" },
        { key: "events_today", label: "Received today" },
        { key: "unmatched", label: "Unmapped" },
        { key: "invalid", label: "Invalid time" },
        { key: "enrollments_active", label: "Mapped users" },
      ],
      details: useForm({ name: this.device.name, model: this.device.model, serial_number: this.device.serial_number, location_id: this.device.location_id, legal_entity_id: this.device.legal_entity_id, timezone: this.device.timezone }),
      connection: useForm({
        connection_method: this.device.connection_method, host: this.device.host, port: this.device.port, page_size: this.device.page_size,
        use_tls: !!this.device.use_tls, verify_tls: !!this.device.verify_tls, username: "", password: "", attendance_connector_id: this.device.attendance_connector_id,
        minors: (this.device.settings?.acs_event_minors || []).map(String),
        simulationText: JSON.stringify(this.device.simulation || {}, null, 2),
      }),
    };
  },
  computed: {
    isHikvisionIsapi() {
      return this.device.vendor === "hikvision" && ["isapi_push", "isapi_pull"].includes(this.device.connection_method);
    },
    vendor() {
      return this.options.vendors.find((v) => v.vendor === this.device.vendor);
    },
    vendorLabel() {
      return this.vendor?.label || this.device.vendor;
    },
    methodItems() {
      return Object.entries(this.vendor?.connection_methods || {}).map(([value, title]) => ({ value, title }));
    },
  },
  methods: {
    has(capability) {
      return (this.device.capabilities || []).includes(capability);
    },
    post(action) {
      this.busy = action;
      router.post(route(`attendance.devices.${action}`, this.device.id), {}, { preserveScroll: true, onFinish: () => (this.busy = null), onError: (e) => this.showToast(this.firstError(e), "error") });
    },
    toggle() {
      router.put(route("attendance.devices.toggle", this.device.id), {}, { preserveScroll: true });
    },
    rotateToken() {
      router.post(route("attendance.devices.push-token", this.device.id), {}, { preserveScroll: true });
    },
    saveConnection() {
      let simulation = null;
      if (this.device.vendor === "simulator") {
        try {
          simulation = JSON.parse(this.connection.simulationText || "{}");
          this.simulationError = null;
        } catch (e) {
          this.simulationError = "Invalid JSON";
          return;
        }
      }
      this.connection
        .transform(({ minors, simulationText, ...d }) => ({
          ...d,
          host: d.host || null, port: d.port || null, page_size: d.page_size || null,
          username: d.username || null, password: d.password || null,
          settings: minors && minors.length ? { acs_event_minors: minors.map(Number) } : null,
          simulation,
        }))
        .put(route("attendance.devices.connection", this.device.id), { preserveScroll: true, onSuccess: () => this.connection.reset("username", "password"), onError: (e) => this.showToast(this.firstError(e), "error") });
    },
  },
};
</script>

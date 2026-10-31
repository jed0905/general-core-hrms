<template>
  <SidebarLayout>
    <Head title="Attendance Settings" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Attendance Settings</h1>
        <p class="text-body-2 text-medium-emphasis">Company attendance rules and on-premises connectors.</p>
      </div>

      <v-alert v-if="newConnectorSecret" type="warning" variant="tonal" class="mb-4">
        <div class="font-weight-medium">Connector credentials (the secret is shown only now):</div>
        <div>Key: <code>{{ newConnectorSecret.key }}</code></div>
        <div style="word-break: break-all">Secret: <code>{{ newConnectorSecret.secret }}</code></div>
        <div>HRMS endpoint: <code>{{ connectorEndpoint }}</code></div>
      </v-alert>

      <v-card variant="outlined" class="rounded-lg mb-4">
        <div class="d-flex pa-3 align-center"><span class="text-subtitle-1 font-weight-bold">Attendance policies</span><v-spacer /><v-btn v-if="can.config" color="primary" prepend-icon="mdi-plus" @click="openPolicy()">Policy</v-btn></div>
        <v-table density="compact">
          <thead><tr><th>Name</th><th>Applies to</th><th>Grace (late / undertime)</th><th>Pairing</th><th>Overtime</th><th>Night window</th><th></th></tr></thead>
          <tbody>
            <tr v-for="p in policies" :key="p.id">
              <td>{{ p.name }} <v-chip v-if="p.is_default" size="x-small" class="ml-1">default</v-chip></td>
              <td>{{ p.legal_entity?.name || 'All entities without their own policy' }}</td>
              <td>{{ p.late_grace_minutes }} / {{ p.undertime_grace_minutes }} min</td>
              <td>{{ p.pairing_mode === 'in_out' ? 'In/out pairs' : 'First in, last out' }}</td>
              <td>≥ {{ p.overtime_threshold_minutes }} min{{ p.overtime_requires_approval ? ', needs approval' : '' }}</td>
              <td>{{ p.night_start ? `${p.night_start.substring(0, 5)}–${p.night_end.substring(0, 5)}` : 'none' }}</td>
              <td class="text-end"><v-btn v-if="can.config" size="small" variant="text" @click="openPolicy(p)">Edit</v-btn></td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <v-card v-if="can.connectors" variant="outlined" class="rounded-lg">
        <div class="d-flex pa-3 align-center"><span class="text-subtitle-1 font-weight-bold">On-premises connectors</span><v-spacer /><v-btn color="primary" prepend-icon="mdi-plus" @click="connectorDialog = true">Connector</v-btn></div>
        <p class="text-body-2 px-3">For a hosted HRMS that can't reach devices inside a branch LAN: run <code>php artisan attendance:connector config.json</code> on a machine in that LAN.</p>
        <v-table density="compact">
          <thead><tr><th>Name</th><th>Key</th><th>Devices</th><th>Last contact</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <tr v-if="!connectors.length"><td colspan="6" class="text-center text-medium-emphasis pa-6">No connectors.</td></tr>
            <tr v-for="c in connectors" :key="c.id">
              <td>{{ c.name }}</td>
              <td class="text-caption"><code>{{ c.key }}</code></td>
              <td>{{ c.devices_count }}</td>
              <td>{{ dateTime(c.last_seen_at) }} <span class="text-caption text-medium-emphasis">{{ c.last_seen_ip }}</span></td>
              <td><v-chip size="x-small" variant="tonal" :color="c.is_active ? 'success' : 'grey'">{{ c.is_active ? 'Active' : 'Inactive' }}</v-chip></td>
              <td class="text-end text-no-wrap">
                <v-btn size="small" variant="text" @click="post(route('attendance.connectors.rotate', c.id))">Rotate secret</v-btn>
                <v-btn size="small" variant="text" @click="put(route('attendance.connectors.toggle', c.id))">{{ c.is_active ? 'Deactivate' : 'Activate' }}</v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <v-dialog v-model="policyDialog" max-width="720">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ policyForm.id ? 'Edit' : 'New' }} attendance policy</v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" md="6"><v-text-field v-model="policyForm.name" label="Name" variant="outlined" density="compact" :error-messages="policyForm.errors.name" /></v-col>
              <v-col cols="12" md="6"><v-select v-model="policyForm.legal_entity_id" :items="legalEntities" item-title="name" item-value="id" label="Legal entity (blank = default policy)" variant="outlined" density="compact" clearable :disabled="!!policyForm.is_default" :error-messages="policyForm.errors.legal_entity_id" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="policyForm.late_grace_minutes" type="number" label="Late grace (min)" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="policyForm.undertime_grace_minutes" type="number" label="Undertime grace (min)" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" md="3"><v-select v-model="policyForm.pairing_mode" :items="[{ value: 'in_out', title: 'In/out pairs' }, { value: 'first_last', title: 'First in, last out' }]" label="Punch pairing" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="policyForm.debounce_minutes" type="number" label="Ignore repeats within (min)" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="policyForm.overtime_threshold_minutes" type="number" label="Min. overtime (min)" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="policyForm.window_before_minutes" type="number" label="Punch window before shift (min)" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="policyForm.night_start" label="Night from (HH:MM)" variant="outlined" density="compact" :error-messages="policyForm.errors.night_start" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="policyForm.night_end" label="Night to (HH:MM)" variant="outlined" density="compact" :error-messages="policyForm.errors.night_end" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="policyForm.day_start_time" label="Day starts (no shift)" variant="outlined" density="compact" :error-messages="policyForm.errors.day_start_time" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="policyForm.timezone" label="Fallback timezone" variant="outlined" density="compact" :error-messages="policyForm.errors.timezone" /></v-col>
              <v-col cols="12" md="6"><v-switch v-model="policyForm.overtime_requires_approval" label="Overtime is paid only when approved" color="primary" density="compact" hide-details /></v-col>
              <v-col cols="12" md="6"><v-switch v-model="policyForm.deduct_scheduled_break" label="Deduct the shift's break when no break punches" color="primary" density="compact" hide-details /></v-col>
              <v-col cols="12" md="6"><v-switch v-model="policyForm.count_early_arrival_as_overtime" label="Count early arrival as overtime" color="primary" density="compact" hide-details /></v-col>
            </v-row>
            <p class="text-caption text-medium-emphasis mt-2">These are company rules, not legal values. Changes apply to days recalculated afterwards.</p>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="policyDialog = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="policyForm.processing" @click="savePolicy">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="connectorDialog" max-width="480">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">New connector</v-card-title>
          <v-card-text>
            <v-text-field v-model="connectorForm.name" label="Name (e.g. Cebu branch PC)" variant="outlined" density="compact" :error-messages="connectorForm.errors.name" />
            <v-select v-model="connectorForm.legal_entity_id" :items="legalEntities" item-title="name" item-value="id" label="Legal entity" variant="outlined" density="compact" clearable />
            <v-select v-model="connectorForm.location_id" :items="locations" item-title="city" item-value="id" label="Location" variant="outlined" density="compact" clearable />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="connectorDialog = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="connectorForm.processing" @click="saveConnector">Create</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import attendancePage from "@/mixins/attendancePage";

export default {
  name: "AttendanceSettings",
  components: { SidebarLayout, Head },
  mixins: [attendancePage],
  props: {
    policies: { type: Array, default: () => [] },
    connectors: { type: Array, default: () => [] },
    legalEntities: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    newConnectorSecret: { type: Object, default: null },
    connectorEndpoint: { type: String, default: "" },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return { policyDialog: false, connectorDialog: false, policyForm: useForm({}), connectorForm: useForm({ name: "", legal_entity_id: null, location_id: null }) };
  },
  methods: {
    openPolicy(p = null) {
      const t = (v) => (v ? v.substring(0, 5) : "");
      this.policyForm = useForm({
        id: p?.id || null, is_default: !!p?.is_default, name: p?.name || "", legal_entity_id: p?.legal_entity_id || null,
        late_grace_minutes: p?.late_grace_minutes ?? 0, undertime_grace_minutes: p?.undertime_grace_minutes ?? 0, pairing_mode: p?.pairing_mode || "in_out",
        debounce_minutes: p?.debounce_minutes ?? 1, deduct_scheduled_break: p ? !!p.deduct_scheduled_break : true, overtime_threshold_minutes: p?.overtime_threshold_minutes ?? 0,
        overtime_requires_approval: p ? !!p.overtime_requires_approval : true, count_early_arrival_as_overtime: !!p?.count_early_arrival_as_overtime,
        night_start: t(p?.night_start), night_end: t(p?.night_end), window_before_minutes: p?.window_before_minutes ?? 240,
        day_start_time: t(p?.day_start_time) || "00:00", timezone: p?.timezone || "", is_active: p ? !!p.is_active : true,
      });
      this.policyDialog = true;
    },
    savePolicy() {
      const f = this.policyForm;
      f.transform(({ id, is_default, ...d }) => ({ ...d, night_start: d.night_start || null, night_end: d.night_end || null, timezone: d.timezone || null }));
      const opts = { preserveScroll: true, onSuccess: () => (this.policyDialog = false) };
      f.id ? f.put(route("attendance.settings.policies.update", f.id), opts) : f.post(route("attendance.settings.policies.store"), opts);
    },
    saveConnector() {
      this.connectorForm.post(route("attendance.connectors.store"), { preserveScroll: true, onSuccess: () => { this.connectorDialog = false; this.connectorForm.reset(); } });
    },
    post(url) {
      router.post(url, {}, { preserveScroll: true });
    },
    put(url) {
      router.put(url, {}, { preserveScroll: true });
    },
  },
};
</script>

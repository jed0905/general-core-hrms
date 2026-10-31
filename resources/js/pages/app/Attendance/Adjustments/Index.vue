<template>
  <SidebarLayout>
    <Head title="Attendance Adjustments" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Attendance Adjustments</h1>
        <p class="text-body-2 text-medium-emphasis">Corrections with the original values, reason and decision. Request them from an employee's attendance history.</p>
      </div>
      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="3"><v-select v-model="form.status" :items="['pending', 'approved', 'rejected']" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="5"><v-autocomplete v-model="form.employee_id" :items="employees" :item-title="(e) => `${employeeName(e)} · ${e.employee_number}`" item-value="id" label="Employee" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        </v-row>
      </v-card>
      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead><tr><th>Employee / date</th><th>Correction</th><th>Original</th><th>Reason</th><th>Requested</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <tr v-if="!adjustments.data.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No adjustments.</td></tr>
            <tr v-for="a in adjustments.data" :key="a.id">
              <td>{{ employeeName(a.employee) }}<div class="text-caption text-medium-emphasis">{{ date(a.work_date) }}</div></td>
              <td>{{ label(a.type) }}<div class="text-caption text-medium-emphasis">{{ describe(a) }}</div></td>
              <td class="text-caption">{{ a.original ? `${label(a.original.status)}, worked ${minutes(a.original.worked_minutes)}` : '—' }}</td>
              <td>{{ a.reason }}<div v-if="a.remarks" class="text-caption text-medium-emphasis">{{ a.remarks }}</div></td>
              <td class="text-caption">{{ a.requester?.username }}<br />{{ dateTime(a.created_at) }}</td>
              <td><v-chip size="x-small" variant="tonal" :color="statusInfo(statusMap, a.status).color">{{ statusInfo(statusMap, a.status).label }}</v-chip>
                <div v-if="a.reviewer" class="text-caption text-medium-emphasis">{{ a.reviewer.username }}{{ a.review_remarks ? ': ' + a.review_remarks : '' }}</div></td>
              <td class="text-end text-no-wrap">
                <template v-if="a.can_decide">
                  <v-btn size="small" variant="text" color="success" @click="decide(a, 'approve')">Approve</v-btn>
                  <v-btn size="small" variant="text" color="error" @click="decide(a, 'reject')">Reject</v-btn>
                </template>
              </td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="adjustments" /></div>
      </v-card>
      <v-dialog v-model="decision.show" max-width="460">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ decision.action === 'approve' ? 'Approve' : 'Reject' }} adjustment</v-card-title>
          <v-card-text><v-text-field v-model="decision.remarks" :label="decision.action === 'reject' ? 'Reason (required)' : 'Remarks'" variant="outlined" density="compact" /></v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="decision.show = false">Cancel</v-btn><v-btn :color="decision.action === 'approve' ? 'success' : 'error'" variant="flat" :disabled="decision.action === 'reject' && !decision.remarks" @click="confirm">Confirm</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import attendancePage from "@/mixins/attendancePage";
import { ADJUSTMENT_STATUS } from "@/utils/attendance";

export default {
  name: "AttendanceAdjustmentIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [attendancePage],
  props: {
    adjustments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    employees: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      statusMap: ADJUSTMENT_STATUS,
      form: { status: this.filters.status || null, employee_id: this.filters.employee_id ? Number(this.filters.employee_id) : null },
      decision: { show: false, action: null, adjustment: null, remarks: "" },
    };
  },
  methods: {
    apply() {
      router.get(route("attendance.adjustments.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    describe(a) {
      if (a.type === "add_punch") return `${a.corrected?.occurred_at} (${this.label(a.corrected?.event_type)})`;
      if (a.type === "approve_overtime") return `${a.corrected?.minutes} minutes`;
      if (a.type === "void_punch") return a.original?.event ? `${new Date(a.original.event.occurred_at).toLocaleString()} (${a.original.event.type})` : "";
      return "";
    },
    decide(adjustment, action) {
      this.decision = { show: true, action, adjustment, remarks: "" };
    },
    confirm() {
      router.put(route(`attendance.adjustments.${this.decision.action}`, this.decision.adjustment.id), { remarks: this.decision.remarks || null }, {
        preserveScroll: true, onSuccess: () => (this.decision.show = false), onError: (e) => this.showToast(this.firstError(e), "error"),
      });
    },
  },
};
</script>

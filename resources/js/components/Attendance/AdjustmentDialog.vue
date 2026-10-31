<template>
  <v-dialog :model-value="modelValue" max-width="560" @update:model-value="$emit('update:modelValue', $event)">
    <v-card class="rounded-lg">
      <v-card-title class="text-h6 pa-4">Request adjustment{{ day ? ' · ' + day.work_date.substring(0, 10) : '' }}</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-3">Raw punches are never changed. Another authorized user must approve this request.</p>
        <v-select v-model="form.type" :items="types" label="Correction" variant="outlined" density="compact" class="mb-2" :error-messages="form.errors.type" />
        <template v-if="form.type === 'add_punch'">
          <div class="d-flex ga-2">
            <v-text-field v-model="form.occurred_at" label="Missing punch (YYYY-MM-DD HH:MM)" variant="outlined" density="compact" :error-messages="form.errors.occurred_at" />
            <v-select v-model="form.event_type" :items="['check_in', 'check_out', 'break_out', 'break_in']" label="Type" variant="outlined" density="compact" />
          </div>
        </template>
        <v-select v-if="form.type === 'void_punch'" v-model="form.attendance_event_id" :items="events.map((e) => ({ value: e.id, title: `${new Date(e.occurred_at).toLocaleString()} · ${e.event_type}` }))" label="Punch to exclude" variant="outlined" density="compact" class="mb-2" :error-messages="form.errors.attendance_event_id" />
        <v-text-field v-if="form.type === 'approve_overtime'" v-model="form.minutes" type="number" label="Overtime minutes to approve" variant="outlined" density="compact" class="mb-2" :error-messages="form.errors.minutes" />
        <v-text-field v-model="form.reason" label="Reason" variant="outlined" density="compact" :error-messages="form.errors.reason" />
        <v-textarea v-model="form.remarks" label="Remarks / supporting details" rows="2" variant="outlined" density="compact" />
      </v-card-text>
      <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="$emit('update:modelValue', false)">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="form.processing" @click="submit">Submit</v-btn></v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import { useForm } from "@inertiajs/vue3";

export default {
  name: "AdjustmentDialog",
  props: {
    modelValue: { type: Boolean, default: false },
    employeeId: { type: Number, required: true },
    day: { type: Object, default: null },
    events: { type: Array, default: () => [] },
  },
  emits: ["update:modelValue"],
  data() {
    return {
      types: [
        { value: "add_punch", title: "Add a missing punch" },
        { value: "void_punch", title: "Exclude a wrong punch" },
        { value: "approve_overtime", title: "Approve overtime" },
        { value: "mark_excused", title: "Excuse the day (official business, etc.)" },
      ],
      form: useForm({ type: "add_punch", occurred_at: "", event_type: "check_out", attendance_event_id: null, minutes: null, reason: "", remarks: "" }),
    };
  },
  watch: {
    day(day) {
      this.form.reset();
      if (day) this.form.occurred_at = `${day.work_date.substring(0, 10)} `;
    },
  },
  methods: {
    submit() {
      this.form
        .transform((d) => ({ ...d, employee_id: this.employeeId, work_date: this.day.work_date.substring(0, 10), minutes: d.minutes ? Number(d.minutes) : null }))
        .post(route("attendance.adjustments.store"), { preserveScroll: true, onSuccess: () => this.$emit("update:modelValue", false) });
    },
  },
};
</script>

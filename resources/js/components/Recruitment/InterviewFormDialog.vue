<template>
  <v-dialog :model-value="modelValue" max-width="680" @update:model-value="$emit('update:modelValue', $event)">
    <v-card>
      <v-card-title class="pa-4">{{ interview ? "Edit interview" : "Schedule interview" }}</v-card-title>
      <v-card-text>
        <v-alert v-if="form.errors.status || form.errors.stage" type="error" variant="tonal" density="compact" class="mb-3">{{ form.errors.status || form.errors.stage }}</v-alert>
        <v-row density="compact">
          <v-col cols="12" md="6">
            <v-select v-model="form.interview_type_id" :items="interviewTypes" item-title="name" item-value="id" label="Interview type *" variant="outlined" density="compact" :error-messages="form.errors.interview_type_id" />
          </v-col>
          <v-col cols="12" md="6">
            <v-select v-model="form.mode" :items="modeItems" label="Mode *" variant="outlined" density="compact" :error-messages="form.errors.mode" />
          </v-col>
          <template v-if="!interview">
            <v-col cols="12" md="4">
              <v-text-field v-model="form.scheduled_date" type="date" label="Date *" variant="outlined" density="compact" :error-messages="form.errors.scheduled_date" />
            </v-col>
            <v-col cols="6" md="4">
              <v-text-field v-model="form.start_time" type="time" label="Start time *" variant="outlined" density="compact" :error-messages="form.errors.start_time" />
            </v-col>
            <v-col cols="6" md="4">
              <v-select v-model="form.duration_minutes" :items="durations" label="Duration *" variant="outlined" density="compact" :error-messages="form.errors.duration_minutes" />
            </v-col>
          </template>
          <v-col v-if="form.mode === 'video'" cols="12">
            <v-text-field v-model="form.meeting_url" label="Meeting link *" placeholder="https://" variant="outlined" density="compact" :error-messages="form.errors.meeting_url" />
          </v-col>
          <v-col v-else cols="12">
            <v-text-field v-model="form.location" :label="form.mode === 'in_person' ? 'Location *' : 'Location or number (optional)'" variant="outlined" density="compact" :error-messages="form.errors.location" />
          </v-col>
          <v-col cols="12" md="8">
            <v-autocomplete v-model="form.panelist_ids" :items="employees" label="Panelists *" multiple chips closable-chips variant="outlined" density="compact" :error-messages="panelistError" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="form.primary_panelist_id" :items="primaryItems" label="Lead panelist" variant="outlined" density="compact" clearable :error-messages="form.errors.primary_panelist_id" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.instructions" label="Instructions for the panel" rows="2" variant="outlined" density="compact" :error-messages="form.errors.instructions" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="$emit('update:modelValue', false)">Back</v-btn>
        <v-btn color="primary" :loading="form.processing" @click="submit">{{ interview ? "Save changes" : "Schedule" }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import { useForm } from "@inertiajs/vue3";
import { INTERVIEW_MODES, localDateParts } from "@/utils/recruitment";

export default {
  name: "InterviewFormDialog",
  props: {
    modelValue: { type: Boolean, default: false },
    // Schedule: the application id. Edit: the interview being edited.
    applicationId: { type: Number, default: null },
    interview: { type: Object, default: null },
    interviewTypes: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
  },
  emits: ["update:modelValue"],
  data() {
    const i = this.interview;
    const tomorrow = localDateParts(new Date(Date.now() + 86400000));
    return {
      durations: [15, 30, 45, 60, 90, 120, 180, 240].map((m) => ({ value: m, title: m < 60 ? `${m} minutes` : `${m / 60} hour${m > 60 ? "s" : ""}` })),
      form: useForm({
        interview_type_id: i?.interview_type_id ?? null,
        mode: i?.mode ?? "in_person",
        scheduled_date: tomorrow.date,
        start_time: "09:00",
        duration_minutes: 60,
        location: i?.location ?? "",
        meeting_url: i?.meeting_url ?? "",
        instructions: i?.instructions ?? "",
        panelist_ids: i ? i.panelists.map((p) => p.employee_id) : [],
        primary_panelist_id: i?.panelists.find((p) => p.is_primary)?.employee_id ?? null,
      }),
    };
  },
  computed: {
    modeItems() {
      return Object.entries(INTERVIEW_MODES).map(([value, m]) => ({ value, title: m.label }));
    },
    primaryItems() {
      return this.employees.filter((e) => this.form.panelist_ids.includes(e.value));
    },
    panelistError() {
      const key = Object.keys(this.form.errors).find((k) => k.startsWith("panelist_ids"));
      return key ? this.form.errors[key] : [];
    },
  },
  methods: {
    submit() {
      const options = { preserveScroll: true, onSuccess: () => this.$emit("update:modelValue", false) };
      if (this.interview) {
        this.form.put(route("recruitment.interviews.update", this.interview.id), options);
      } else {
        this.form.post(route("recruitment.applications.interviews.store", this.applicationId), options);
      }
    },
  },
};
</script>

<template>
  <v-card variant="outlined" class="rounded-lg mb-4">
    <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
      Candidate conversion
      <v-chip v-if="conversion.record" size="small" variant="tonal" color="success">Converted</v-chip>
      <v-chip v-else-if="conversion.problem" size="small" variant="tonal" color="grey">Not eligible</v-chip>
      <v-chip v-else size="small" variant="tonal" color="primary">Ready</v-chip>
    </v-card-title>

    <!-- After conversion: the recorded hand-off -->
    <v-card-text v-if="conversion.record">
      <v-alert type="success" variant="tonal" density="compact" class="mb-3">
        {{ record.conversion_type === "new_employee" ? "Conversion completed: the employee was created in Core HR." : "Conversion completed: linked to the existing employee record." }}
      </v-alert>
      <v-row density="compact">
        <v-col cols="12" sm="6">
          <div class="text-caption text-medium-emphasis">Employee</div>
          <a v-if="conversion.canViewEmployee" href="#" class="text-primary" @click.prevent="go(route('people.employee.show', record.employee_id))">{{ record.employee_number }} · {{ personName(record.employee) }}</a>
          <span v-else>{{ record.employee_number }} · {{ personName(record.employee) }}</span>
        </v-col>
        <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Accepted offer</div>{{ record.offer?.offer_number }}</v-col>
        <v-col cols="12" sm="6">
          <div class="text-caption text-medium-emphasis">Hiring movement</div>
          <template v-if="record.movement">{{ record.movement.type?.name }} · {{ record.movement.status === "implemented" ? "in effect" : "scheduled" }}</template>
          <template v-else>None (existing employee)</template>
        </v-col>
        <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Effective / start date</div>{{ formatDate(record.start_date) }}</v-col>
        <v-col cols="12" sm="6">
          <div class="text-caption text-medium-emphasis">User account</div>
          {{ { created: `Created (${record.user?.username})`, existing: `Existing (${record.user?.username})`, none: "Not created" }[record.user_account] }}
        </v-col>
        <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Converted</div>{{ formatDateTime(record.converted_at) }} · {{ record.converter?.username }}</v-col>
        <v-col v-if="record.conversion_type === 'new_employee'" cols="12">
          <div class="text-caption text-medium-emphasis">Copied to Core HR</div>
          {{ record.education_copied }} education · {{ record.work_experience_copied }} work experience · {{ record.documents_copied }} document(s)
        </v-col>
      </v-row>
      <v-alert v-for="(n, i) in record.notices || []" :key="i" type="info" variant="tonal" density="compact" class="mt-2">{{ n }}</v-alert>
      <div v-if="conversion.onboarding || conversion.canStartOnboarding" class="d-flex flex-wrap align-center justify-end ga-2 mt-3">
        <span v-if="conversion.onboarding" class="text-body-2 text-medium-emphasis">Onboarding: {{ conversion.onboarding.status.replace("_", " ") }}</span>
        <v-btn v-if="conversion.onboarding && conversion.canViewOnboarding" size="small" variant="text" append-icon="mdi-open-in-new" @click="go(route('people.onboarding.show', conversion.onboarding.id))">View onboarding</v-btn>
        <v-btn v-if="conversion.canStartOnboarding" size="small" color="primary" variant="tonal" prepend-icon="mdi-clipboard-check-outline"
          @click="go(route('people.onboarding.create', { employee_id: record.employee_id, application_conversion_id: record.id }))">Start onboarding</v-btn>
      </div>
    </v-card-text>

    <!-- Before conversion: eligibility -->
    <v-card-text v-else>
      <v-row density="compact" class="mb-2">
        <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Candidate</div>{{ candidateName }}</v-col>
        <v-col cols="12" sm="6">
          <div class="text-caption text-medium-emphasis">Offer</div>
          <template v-if="conversion.offer">{{ conversion.offer.offer_number }} · Accepted {{ formatDateTime(conversion.offer.responded_at) }}</template>
          <template v-else>No accepted offer</template>
        </v-col>
        <v-col v-if="conversion.offer" cols="12" sm="6"><div class="text-caption text-medium-emphasis">Position</div>{{ conversion.offer.position_title }}<span v-if="conversion.offer.department_name"> · {{ conversion.offer.department_name }}</span></v-col>
        <v-col v-if="conversion.offer" cols="12" sm="6"><div class="text-caption text-medium-emphasis">Employment start</div>{{ formatDate(conversion.offer.proposed_start_date) }}</v-col>
        <v-col cols="12">
          <div class="text-caption text-medium-emphasis">Employee</div>
          <template v-if="existing">
            Existing employee: {{ existing.employee_number }} · {{ existing.name }}
            <v-btn v-if="existing.canView" size="x-small" variant="text" append-icon="mdi-open-in-new" @click="go(route('people.employee.show', existing.id))">View</v-btn>
          </template>
          <template v-else>Not yet created</template>
        </v-col>
      </v-row>

      <v-alert v-if="conversion.problem" type="info" variant="tonal" density="compact">{{ conversion.problem }}</v-alert>
      <v-alert v-else-if="existing" type="info" variant="tonal" density="compact" class="mb-3">
        This candidate is already an employee. Converting links this application and offer to their employee record; nothing is created or changed in Core HR.
        Record any change of position, department or status as an Employee Movement.
      </v-alert>
      <v-alert v-if="pageError" type="error" variant="tonal" density="compact" class="mb-3">{{ pageError }}</v-alert>

      <div v-if="conversion.canConvert" class="d-flex justify-end">
        <v-btn color="success" prepend-icon="mdi-account-arrow-right-outline" @click="dialog = true">{{ existing ? "Link to employee" : "Convert to employee" }}</v-btn>
      </div>
    </v-card-text>

    <v-dialog v-model="dialog" max-width="640" persistent>
      <v-card>
        <v-card-title class="pa-4">{{ existing ? "Link to existing employee" : "Convert to employee" }}</v-card-title>
        <v-card-text>
          <p class="text-body-2 mb-3">
            <strong>{{ candidateName }}</strong> accepted offer <strong>{{ conversion.offer?.offer_number }}</strong>.
            <template v-if="existing"> The application will be linked to employee {{ existing.employee_number }}. No employee, number, movement or account is created.</template>
            <template v-else>
              This will create the employee in Core HR, issue the next <strong>employee number</strong>, and record a <strong>Hiring movement</strong> effective {{ formatDate(conversion.offer?.proposed_start_date) }}.
              Personal details come from the applicant; position, department, employment type and location from the offer. The offer itself is not changed.
            </template>
          </p>
          <v-alert v-if="formError" type="error" variant="tonal" density="compact" class="mb-3">{{ formError }}</v-alert>

          <template v-if="!existing">
            <v-row density="compact">
              <v-col cols="12" md="6">
                <v-select v-model="form.emp_sex" :items="sexItems" label="Sex *" hint="Required by Core HR; not collected from applicants" persistent-hint variant="outlined" density="compact" :error-messages="form.errors.emp_sex" />
              </v-col>
              <v-col cols="12" md="6">
                <v-autocomplete v-model="form.supervisor_id" :items="conversion.supervisors" label="Supervisor (optional)" variant="outlined" density="compact" clearable :error-messages="form.errors.supervisor_id" />
              </v-col>
            </v-row>

            <div class="text-subtitle-2 mt-2">Documents to copy to the employee record</div>
            <p class="text-caption text-medium-emphasis mb-1">Documents submitted with this application. Copies are private; the applicant's originals stay in recruitment.</p>
            <div v-if="!conversion.documents.length" class="text-medium-emphasis text-body-2 mb-2">No transferable documents.</div>
            <v-checkbox v-for="d in conversion.documents" :key="d.id" v-model="form.document_ids" :value="d.id" :label="`${d.name} (${d.type})`" density="compact" hide-details />
            <div v-if="docError" class="text-error text-caption">{{ docError }}</div>

            <template v-if="conversion.canCreateAccount">
              <v-checkbox v-model="form.create_user_account" label="Also create a login for this employee (employee role only)" density="compact" hide-details class="mt-3" />
              <v-row v-if="form.create_user_account" density="compact">
                <v-col cols="12" md="6"><v-text-field v-model="form.username" label="Username *" autocomplete="off" variant="outlined" density="compact" :error-messages="form.errors.username" /></v-col>
                <v-col cols="12" md="6"><v-text-field v-model="form.password" label="Initial password *" type="password" autocomplete="new-password" variant="outlined" density="compact" :error-messages="form.errors.password" /></v-col>
              </v-row>
            </template>
            <p v-else class="text-caption text-medium-emphasis mt-3 mb-0">A login can be created later in User Management.</p>
          </template>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" :disabled="form.processing" @click="dialog = false">Back</v-btn>
          <v-btn color="success" :loading="form.processing" :disabled="form.processing" @click="submit">{{ existing ? "Link employee" : "Create employee" }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script>
import { router, useForm } from "@inertiajs/vue3";
import { applicantName, formatDate, formatDateTime, personName } from "@/utils/recruitment";

export default {
  name: "ConversionPanel",
  props: {
    conversion: { type: Object, required: true },
    application: { type: Object, required: true },
  },
  data() {
    return {
      dialog: false,
      sexItems: [
        { value: "female", title: "Female" },
        { value: "male", title: "Male" },
        { value: "other", title: "Other" },
      ],
      form: useForm({
        emp_sex: null,
        supervisor_id: this.conversion.defaultSupervisorId || null,
        document_ids: (this.conversion.documents || []).map((d) => d.id),
        create_user_account: false,
        username: "",
        password: "",
      }),
    };
  },
  computed: {
    record() {
      return this.conversion.record;
    },
    existing() {
      return this.conversion.existingEmployee;
    },
    candidateName() {
      return applicantName(this.application.applicant);
    },
    formError() {
      return this.form.errors.conversion || this.form.errors.create_user_account || null;
    },
    docError() {
      const key = Object.keys(this.form.errors).find((k) => k.startsWith("document_ids"));
      return key ? this.form.errors[key] : null;
    },
    pageError() {
      return this.dialog ? null : this.$page.props.errors?.conversion || null;
    },
  },
  methods: {
    formatDate,
    formatDateTime,
    personName,
    submit() {
      this.form
        .transform((d) => (this.existing ? {} : { ...d, ...(d.create_user_account ? {} : { username: null, password: null }) }))
        .post(route("recruitment.applications.convert", this.application.id), {
          preserveScroll: true,
          onSuccess: () => (this.dialog = false),
        });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>

<template>
  <SidebarLayout>
    <Head title="My Profile" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="mb-6">
        <h1 class="text-h5 font-weight-bold">My Profile</h1>
        <p class="text-body-2 text-medium-emphasis">
          Your employee record. Contact HR to change anything other than your
          contact details.
        </p>
      </div>

      <v-alert v-if="!profile" type="info" variant="tonal">
        Your account is not linked to an employee record. Contact HR if this is
        unexpected.
      </v-alert>

      <v-row v-else>
        <v-col cols="12" md="6">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold">
              Personal
            </v-card-title>
            <v-card-text>
              <v-row density="compact">
                <v-col v-for="field in personalFields" :key="field.label" cols="6">
                  <div class="text-caption text-medium-emphasis">
                    {{ field.label }}
                  </div>
                  <div class="font-weight-medium">{{ field.value || "—" }}</div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">
              Employment
            </v-card-title>
            <v-card-text>
              <v-row density="compact">
                <v-col
                  v-for="field in employmentFields"
                  :key="field.label"
                  cols="6"
                >
                  <div class="text-caption text-medium-emphasis">
                    {{ field.label }}
                  </div>
                  <div class="font-weight-medium">{{ field.value || "—" }}</div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">
              Contact Details
            </v-card-title>
            <v-card-text>
              <v-row density="compact">
                <v-col v-for="field in contactFields" :key="field.key" :cols="field.cols">
                  <v-text-field
                    v-model="form[field.key]"
                    :label="field.label"
                    :type="field.type || 'text'"
                    :readonly="!can.update"
                    variant="outlined"
                    density="compact"
                    :error-messages="form.errors[field.key]"
                  />
                </v-col>
              </v-row>
            </v-card-text>
            <v-card-actions v-if="can.update" class="justify-end">
              <v-btn
                color="primary"
                variant="flat"
                :loading="form.processing"
                :disabled="!form.isDirty"
                @click="submit"
              >
                Save Contact Details
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";

const CONTACT_FIELDS = [
  { key: "mobile_no", label: "Mobile No.", cols: 6 },
  { key: "home_telephone_no", label: "Home Telephone", cols: 6 },
  { key: "other_email", label: "Personal Email", type: "email", cols: 12 },
  { key: "street1", label: "Street Address", cols: 12 },
  { key: "street2", label: "Street Address 2", cols: 12 },
  { key: "city", label: "City", cols: 6 },
  { key: "province", label: "Province", cols: 6 },
  { key: "zip_code", label: "ZIP Code", cols: 6 },
  { key: "country_id", label: "Country", cols: 6 },
];

export default {
  name: "MyProfileIndex",
  components: { SidebarLayout, Head },
  props: {
    profile: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    const contact = this.profile?.contact || {};

    return {
      contactFields: CONTACT_FIELDS,
      form: useForm(
        Object.fromEntries(CONTACT_FIELDS.map((f) => [f.key, contact[f.key] ?? ""]))
      ),
    };
  },
  computed: {
    fullName() {
      const p = this.profile || {};
      return [p.emp_first_name, p.emp_middle_name, p.emp_last_name, p.emp_suffix]
        .filter(Boolean)
        .join(" ");
    },
    personalFields() {
      const p = this.profile || {};
      return [
        { label: "Name", value: this.fullName },
        { label: "Employee No.", value: p.employee_number },
        { label: "Birthday", value: this.formatDate(p.emp_birthday) },
        { label: "Sex", value: p.emp_sex },
        { label: "Marital Status", value: p.emp_marital_status },
      ];
    },
    employmentFields() {
      const p = this.profile || {};
      return [
        { label: "Job Title", value: p.job_title },
        { label: "Department", value: p.department },
        { label: "Location", value: p.location },
        { label: "Employment Status", value: p.employment_status },
        { label: "Supervisor", value: p.supervisor },
        { label: "Date Joined", value: this.formatDate(p.joined_date) },
        { label: "Work Email", value: p.work_email },
        { label: "Work Phone", value: p.work_no },
      ];
    },
  },
  methods: {
    formatDate(value) {
      return value ? new Date(value).toLocaleDateString() : "";
    },
    submit() {
      this.form.put(route("people.my-profile.update"), {
        preserveScroll: true,
        onSuccess: () => this.form.defaults(),
      });
    },
  },
};
</script>

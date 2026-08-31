<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

const props = defineProps({
  options: {
    type: Object,
    default: () => ({}),
  },
});

// Photo & Signature Preview States
const photoPreview = ref(null);
const signaturePreview = ref(null);

// Inertia Form Setup
const form = useForm({
  employee_number: "",
  emp_first_name: "",
  emp_last_name: "",
  emp_middle_name: "",
  emp_suffix: "",
  emp_birthday: null,
  emp_sex: "male",
  emp_marital_status: null,
  emp_nationality_id: null,
  photo: null,
  e_signature: null,

  // Contact & Address
  street1: "",
  street2: "",
  city: "",
  province: "",
  zip_code: "",
  country_id: "",
  home_telephone_no: "",
  mobile_no: "",
  work_no: "",
  work_email: "",
  other_email: "",

  // Employment Details
  joined_date: null,
  job_title_id: null,
  department_id: null,
  location_id: null,
  employment_status_id: null,
  supervisor_id: null,
  status: "active",
});

// Static Select Options
const sexOptions = [
  { title: "Male", value: "male" },
  { title: "Female", value: "female" },
  { title: "Other", value: "other" },
];

const maritalStatusOptions = [
  { title: "Single", value: "single" },
  { title: "Married", value: "married" },
  { title: "Widowed", value: "widowed" },
  { title: "Divorced", value: "divorced" },
  { title: "Separated", value: "separated" },
];

const statusOptions = [
  { title: "Active", value: "active" },
  { title: "On Leave", value: "on_leave" },
  { title: "Terminated", value: "terminated" },
  { title: "Archived", value: "archived" },
];

// File Handlers
const handlePhotoChange = (file) => {
  if (file) {
    const rawFile = Array.isArray(file) ? file[0] : file;
    form.photo = rawFile;
    photoPreview.value = URL.createObjectURL(rawFile);
  } else {
    form.photo = null;
    photoPreview.value = null;
  }
};

const handleSignatureChange = (file) => {
  if (file) {
    const rawFile = Array.isArray(file) ? file[0] : file;
    form.e_signature = rawFile;
    signaturePreview.value = URL.createObjectURL(rawFile);
  } else {
    form.e_signature = null;
    signaturePreview.value = null;
  }
};

const submit = () => {
  form.post(route("people.employee.store"), {
    preserveScroll: true,
  });
};
</script>

<template>
  <SidebarLayout>
    <Head title="Create Employee" />

    <v-container fluid class="pa-6">
      <!-- Header Bar -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6"
      >
        <div class="d-flex align-center ga-3">
          <!-- Back Button -->
          <Link :href="route('people.employee.index')">
            <v-btn
              icon="mdi-arrow-left"
              variant="text"
              density="comfortable"
              color="medium-emphasis"
            />
          </Link>
          <div>
            <h1 class="text-h5 font-weight-bold text-high-emphasis">
              Create New Employee
            </h1>
            <p class="text-body-2 text-medium-emphasis">
              Fill in the required information to onboard a new organization
              member.
            </p>
          </div>
        </div>

        <div class="d-flex align-center ga-2">
          <v-btn
            variant="outlined"
            color="medium-emphasis"
            elevation="0"
            size="small"
            :to="route('people.employee.index')"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-content-save-outline"
            elevation="0"
            size="small"
            :loading="form.processing"
            @click="submit"
          >
            Save Employee
          </v-btn>
        </div>
      </div>

      <form @submit.prevent="submit">
        <v-row>
          <!-- Left Column: Personal Information & Media -->
          <v-col cols="12" lg="8">
            <!-- Basic Information Card -->
            <v-card variant="outlined" class="rounded-lg mb-6">
              <v-card-title
                class="text-subtitle-1 font-weight-bold py-3 px-5 border-b d-flex align-center ga-2"
              >
                <v-icon
                  icon="mdi-account-outline"
                  color="primary"
                  size="small"
                />
                Personal Information
              </v-card-title>

              <v-card-text class="pa-5">
                <!-- Avatar Upload Section -->
                <div class="d-flex align-center ga-4 mb-6">
                  <v-avatar
                    size="72"
                    color="primary"
                    variant="tonal"
                    class="border"
                  >
                    <v-img
                      v-if="photoPreview"
                      :src="photoPreview"
                      alt="Preview"
                      cover
                    />
                    <v-icon
                      v-else
                      icon="mdi-camera-outline"
                      size="32"
                      color="medium-emphasis"
                    />
                  </v-avatar>

                  <div class="flex-grow-1">
                    <v-file-input
                      accept="image/*"
                      label="Employee Photo"
                      variant="outlined"
                      density="compact"
                      prepend-icon=""
                      prepend-inner-icon="mdi-paperclip"
                      hide-details="auto"
                      :error-messages="form.errors.photo"
                      @update:model-value="handlePhotoChange"
                    />
                    <div class="text-caption text-medium-emphasis mt-1">
                      Allowed formats: JPG, PNG, WEBP (Max 2MB)
                    </div>
                  </div>
                </div>

                <v-row density="compact">
                  <!-- Employee Number -->
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.employee_number"
                      label="Employee ID / Number *"
                      placeholder="e.g. EMP-2026-001"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.employee_number"
                    />
                  </v-col>

                  <!-- Status -->
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="form.status"
                      :items="statusOptions"
                      item-title="title"
                      item-value="value"
                      label="Account Status *"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.status"
                    />
                  </v-col>

                  <!-- First Name -->
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field
                      v-model="form.emp_first_name"
                      label="First Name *"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.emp_first_name"
                    />
                  </v-col>

                  <!-- Middle Name -->
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field
                      v-model="form.emp_middle_name"
                      label="Middle Name"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.emp_middle_name"
                    />
                  </v-col>

                  <!-- Last Name -->
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field
                      v-model="form.emp_last_name"
                      label="Last Name *"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.emp_last_name"
                    />
                  </v-col>

                  <!-- Suffix -->
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field
                      v-model="form.emp_suffix"
                      label="Suffix"
                      placeholder="Jr., III"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.emp_suffix"
                    />
                  </v-col>

                  <!-- Birthday -->
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.emp_birthday"
                      type="date"
                      label="Birth Date"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.emp_birthday"
                    />
                  </v-col>

                  <!-- Sex -->
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="form.emp_sex"
                      :items="sexOptions"
                      item-title="title"
                      item-value="value"
                      label="Sex *"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.emp_sex"
                    />
                  </v-col>

                  <!-- Marital Status -->
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="form.emp_marital_status"
                      :items="maritalStatusOptions"
                      item-title="title"
                      item-value="value"
                      label="Marital Status"
                      variant="outlined"
                      density="compact"
                      clearable
                      :error-messages="form.errors.emp_marital_status"
                    />
                  </v-col>

                  <!-- Nationality -->
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="form.emp_nationality_id"
                      :items="options.nationalities || []"
                      item-title="name"
                      item-value="id"
                      label="Nationality"
                      variant="outlined"
                      density="compact"
                      clearable
                      :error-messages="form.errors.emp_nationality_id"
                    />
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>

            <!-- Contact & Address Details Card -->
            <v-card variant="outlined" class="rounded-lg mb-6">
              <v-card-title
                class="text-subtitle-1 font-weight-bold py-3 px-5 border-b d-flex align-center ga-2"
              >
                <v-icon icon="mdi-email-outline" color="primary" size="small" />
                Contact & Address Details
              </v-card-title>

              <v-card-text class="pa-5">
                <v-row density="compact">
                  <!-- Emails -->
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.work_email"
                      type="email"
                      label="Work Email"
                      placeholder="name@company.com"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.work_email"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.other_email"
                      type="email"
                      label="Personal / Other Email"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.other_email"
                    />
                  </v-col>

                  <!-- Phone Numbers -->
                  <v-col cols="12" sm="4">
                    <v-text-field
                      v-model="form.mobile_no"
                      label="Mobile Number"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.mobile_no"
                    />
                  </v-col>

                  <v-col cols="12" sm="4">
                    <v-text-field
                      v-model="form.work_no"
                      label="Work Telephone"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.work_no"
                    />
                  </v-col>

                  <v-col cols="12" sm="4">
                    <v-text-field
                      v-model="form.home_telephone_no"
                      label="Home Telephone"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.home_telephone_no"
                    />
                  </v-col>

                  <!-- Address Line 1 -->
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.street1"
                      label="Address Line 1"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.street1"
                    />
                  </v-col>

                  <!-- Address Line 2 -->
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.street2"
                      label="Address Line 2"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.street2"
                    />
                  </v-col>

                  <!-- City -->
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field
                      v-model="form.city"
                      label="City"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.city"
                    />
                  </v-col>

                  <!-- Province -->
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field
                      v-model="form.province"
                      label="Province / State"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.province"
                    />
                  </v-col>

                  <!-- Zip Code -->
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field
                      v-model="form.zip_code"
                      label="Zip / Postal Code"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.zip_code"
                    />
                  </v-col>

                  <!-- Country -->
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field
                      v-model="form.country_id"
                      label="Country"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.country_id"
                    />
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>

          <!-- Right Column: Employment Assignment & E-Signature -->
          <v-col cols="12" lg="4">
            <!-- Job Assignment Card -->
            <v-card variant="outlined" class="rounded-lg mb-6">
              <v-card-title
                class="text-subtitle-1 font-weight-bold py-3 px-5 border-b d-flex align-center ga-2"
              >
                <v-icon
                  icon="mdi-briefcase-outline"
                  color="primary"
                  size="small"
                />
                Employment & Assignment
              </v-card-title>

              <v-card-text class="pa-5">
                <v-row density="compact">
                  <!-- Joined Date -->
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.joined_date"
                      type="date"
                      label="Date Joined"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.joined_date"
                    />
                  </v-col>

                  <!-- Department -->
                  <v-col cols="12">
                    <v-select
                      v-model="form.department_id"
                      :items="options.departments || []"
                      item-title="name"
                      item-value="id"
                      label="Department"
                      variant="outlined"
                      density="compact"
                      clearable
                      :error-messages="form.errors.department_id"
                    />
                  </v-col>

                  <!-- Job Title -->
                  <v-col cols="12">
                    <v-select
                      v-model="form.job_title_id"
                      :items="options.job_titles || []"
                      item-title="title"
                      item-value="id"
                      label="Job Title"
                      variant="outlined"
                      density="compact"
                      clearable
                      :error-messages="form.errors.job_title_id"
                    />
                  </v-col>

                  <!-- Location -->
                  <v-col cols="12">
                    <v-select
                      v-model="form.location_id"
                      :items="options.locations || []"
                      item-title="name"
                      item-value="id"
                      label="Work Location"
                      variant="outlined"
                      density="compact"
                      clearable
                      :error-messages="form.errors.location_id"
                    />
                  </v-col>

                  <!-- Employment Status -->
                  <v-col cols="12">
                    <v-select
                      v-model="form.employment_status_id"
                      :items="options.employment_statuses || []"
                      item-title="name"
                      item-value="id"
                      label="Employment Classification"
                      variant="outlined"
                      density="compact"
                      clearable
                      :error-messages="form.errors.employment_status_id"
                    />
                  </v-col>

                  <!-- Supervisor -->
                  <v-col cols="12">
                    <v-select
                      v-model="form.supervisor_id"
                      :items="options.supervisors || []"
                      item-title="name"
                      item-value="id"
                      label="Reporting Supervisor"
                      variant="outlined"
                      density="compact"
                      clearable
                      :error-messages="form.errors.supervisor_id"
                    />
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>

            <!-- E-Signature Card -->
            <v-card variant="outlined" class="rounded-lg mb-6">
              <v-card-title
                class="text-subtitle-1 font-weight-bold py-3 px-5 border-b d-flex align-center ga-2"
              >
                <v-icon icon="mdi-draw" color="primary" size="small" />
                Digital E-Signature
              </v-card-title>

              <v-card-text class="pa-5">
                <div class="mb-3">
                  <div
                    v-if="signaturePreview"
                    class="border rounded-lg pa-2 text-center bg-surface-variant mb-2"
                  >
                    <v-img
                      :src="signaturePreview"
                      max-height="100"
                      contain
                      alt="Signature Preview"
                    />
                  </div>

                  <v-file-input
                    accept="image/*"
                    label="Upload Signature"
                    variant="outlined"
                    density="compact"
                    prepend-icon=""
                    prepend-inner-icon="mdi-paperclip"
                    hide-details="auto"
                    :error-messages="form.errors.e_signature"
                    @update:model-value="handleSignatureChange"
                  />
                  <div class="text-caption text-medium-emphasis mt-1">
                    Upload a transparent PNG signature file.
                  </div>
                </div>
              </v-card-text>
            </v-card>

            <!-- Bottom Form Actions -->
            <div class="d-flex align-center ga-2 justify-end">
              <v-btn
                variant="outlined"
                color="medium-emphasis"
                elevation="0"
                size="small"
                :to="route('people.employee.index')"
              >
                Cancel
              </v-btn>
              <v-btn
                color="primary"
                prepend-icon="mdi-content-save-outline"
                elevation="0"
                size="small"
                :loading="form.processing"
                @click="submit"
              >
                Save Employee
              </v-btn>
            </div>
          </v-col>
        </v-row>
      </form>
    </v-container>
  </SidebarLayout>
</template>
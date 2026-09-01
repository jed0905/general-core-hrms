<template>
  <SidebarLayout>
    <Head :title="`${employee.emp_first_name} ${employee.emp_last_name} - Profile`" />

    <v-container fluid class="pa-6">
      <!-- Top Action Bar -->
      <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6">
        <div class="d-flex align-center ga-3">
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
              Employee Profile
            </h1>
            <p class="text-body-2 text-medium-emphasis">
              Detailed view of employee profile and historical records.
            </p>
          </div>
        </div>

        <div class="d-flex align-center ga-2">
          <v-btn
            variant="outlined"
            color="medium-emphasis"
            size="small"
            :to="route('people.employee.index')"
          >
            Back to Directory
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-pencil-outline"
            elevation="0"
            size="small"
            :to="route('people.employee.edit', employee.id)"
          >
            Edit Profile
          </v-btn>
        </div>
      </div>

      <!-- Hero Header Card -->
      <v-card variant="outlined" class="rounded-lg mb-6 bg-surface">
        <v-card-text class="pa-6">
          <div class="d-flex flex-column flex-sm-row align-center align-sm-start ga-6">
            <v-avatar size="96" color="primary" variant="tonal" class="border">
              <v-img
                v-if="photoUrl"
                :src="photoUrl"
                alt="Employee Photo"
                cover
              />
              <span v-else class="text-h4 font-weight-bold">
                {{ initials }}
              </span>
            </v-avatar>

            <div class="flex-grow-1 text-center text-sm-start">
              <div class="d-flex flex-column flex-sm-row align-center ga-2 mb-1">
                <h2 class="text-h5 font-weight-bold">
                  {{ fullName }}
                </h2>
                <v-chip
                  :color="getStatusColor(employee.status)"
                  size="small"
                  class="text-capitalize font-weight-medium"
                >
                  {{ employee.status }}
                </v-chip>
              </div>

              <p class="text-subtitle-1 text-medium-emphasis mb-3">
                {{ employee.job_title?.job_title || 'No Job Title Assigned' }}
                <span v-if="employee.department?.name"> • {{ employee.department.name }}</span>
              </p>

              <div class="d-flex flex-wrap justify-center justify-sm-start ga-4 text-caption text-medium-emphasis">
                <div class="d-flex align-center ga-1">
                  <v-icon icon="mdi-badge-account-outline" size="small" />
                  <span>ID: {{ employee.employee_number }}</span>
                </div>
                <div class="d-flex align-center ga-1">
                  <v-icon icon="mdi-email-outline" size="small" />
                  <span>{{ employee.work_email || 'No email' }}</span>
                </div>
                <div class="d-flex align-center ga-1">
                  <v-icon icon="mdi-phone-outline" size="small" />
                  <span>{{ employee.mobile_no || 'No mobile' }}</span>
                </div>
                <div class="d-flex align-center ga-1">
                  <v-icon icon="mdi-map-marker-outline" size="small" />
                  <span>{{ formattedLocation }}</span>
                </div>
              </div>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Profile Tabs -->
      <v-tabs v-model="activeTab" color="primary" class="mb-6 border-b">
        <v-tab value="overview" prepend-icon="mdi-account-outline">Overview</v-tab>
        <v-tab value="job" prepend-icon="mdi-briefcase-outline">Job & Placement</v-tab>
        <v-tab value="education" prepend-icon="mdi-school-outline">
          Education ({{ employee.education?.length || 0 }})
        </v-tab>
        <v-tab value="experience" prepend-icon="mdi-history">
          Experience ({{ employee.work_experience?.length || 0 }})
        </v-tab>
      </v-tabs>

      <!-- Tab Content Windows -->
      <v-window v-model="activeTab">
        <!-- TAB 1: Overview -->
        <v-window-item value="overview">
          <v-row>
            <v-col cols="12" md="8">
              <!-- Personal Details -->
              <v-card variant="outlined" class="rounded-lg mb-6">
                <v-card-title class="text-subtitle-1 font-weight-bold py-3 px-5 border-b">
                  Personal Demographics
                </v-card-title>
                <v-card-text class="pa-5">
                  <v-row density="comfortable">
                    <v-col cols="12" sm="6">
                      <div class="text-caption text-medium-emphasis">Full Legal Name</div>
                      <div class="text-body-2 font-weight-medium">{{ fullName }}</div>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <div class="text-caption text-medium-emphasis">Date of Birth</div>
                      <div class="text-body-2 font-weight-medium">{{ formatDate(employee.emp_birthday) }}</div>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <div class="text-caption text-medium-emphasis">Sex</div>
                      <div class="text-body-2 font-weight-medium text-capitalize">{{ employee.emp_sex || 'N/A' }}</div>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <div class="text-caption text-medium-emphasis">Marital Status</div>
                      <div class="text-body-2 font-weight-medium text-capitalize">{{ employee.emp_marital_status || 'N/A' }}</div>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <div class="text-caption text-medium-emphasis">Nationality</div>
                      <div class="text-body-2 font-weight-medium">{{ employee.nationality?.name || 'N/A' }}</div>
                    </v-col>
                  </v-row>
                </v-card-text>
              </v-card>

              <!-- Address & Contact Details -->
              <v-card variant="outlined" class="rounded-lg mb-6">
                <v-card-title class="text-subtitle-1 font-weight-bold py-3 px-5 border-b">
                  Contact & Address Details
                </v-card-title>
                <v-card-text class="pa-5">
                  <v-row density="comfortable">
                    <v-col cols="12" sm="6">
                      <div class="text-caption text-medium-emphasis">Work Email</div>
                      <div class="text-body-2 font-weight-medium">{{ employee.work_email || 'N/A' }}</div>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <div class="text-caption text-medium-emphasis">Personal Email</div>
                      <div class="text-body-2 font-weight-medium">{{ employee.other_email || 'N/A' }}</div>
                    </v-col>
                    <v-col cols="12" sm="4">
                      <div class="text-caption text-medium-emphasis">Mobile Phone</div>
                      <div class="text-body-2 font-weight-medium">{{ employee.mobile_no || 'N/A' }}</div>
                    </v-col>
                    <v-col cols="12" sm="4">
                      <div class="text-caption text-medium-emphasis">Work Phone</div>
                      <div class="text-body-2 font-weight-medium">{{ employee.work_no || 'N/A' }}</div>
                    </v-col>
                    <v-col cols="12" sm="4">
                      <div class="text-caption text-medium-emphasis">Home Phone</div>
                      <div class="text-body-2 font-weight-medium">{{ employee.home_telephone_no || 'N/A' }}</div>
                    </v-col>
                    <v-col cols="12">
                      <div class="text-caption text-medium-emphasis">Residential Address</div>
                      <div class="text-body-2 font-weight-medium">{{ fullAddress }}</div>
                    </v-col>
                  </v-row>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" md="4">
              <!-- Digital Signature -->
              <v-card variant="outlined" class="rounded-lg mb-6">
                <v-card-title class="text-subtitle-1 font-weight-bold py-3 px-5 border-b">
                  E-Signature
                </v-card-title>
                <v-card-text class="pa-5 text-center">
                  <div
                    v-if="signatureUrl"
                    class="border rounded-lg pa-4 bg-surface-light d-flex align-center justify-center"
                  >
                    <v-img :src="signatureUrl" max-height="120" contain alt="Signature" />
                  </div>
                  <div v-else class="text-caption text-medium-emphasis py-6">
                    No e-signature uploaded
                  </div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-window-item>

        <!-- TAB 2: Job Placement -->
        <v-window-item value="job">
          <v-card variant="outlined" class="rounded-lg mb-6">
            <v-card-title class="text-subtitle-1 font-weight-bold py-3 px-5 border-b">
              Employment Parameters
            </v-card-title>
            <v-card-text class="pa-5">
              <v-row density="comfortable">
                <v-col cols="12" sm="6" md="4">
                  <div class="text-caption text-medium-emphasis">Joined Date</div>
                  <div class="text-body-2 font-weight-medium">{{ formatDate(employee.joined_date) }}</div>
                </v-col>
                <v-col cols="12" sm="6" md="4">
                  <div class="text-caption text-medium-emphasis">Department</div>
                  <div class="text-body-2 font-weight-medium">{{ employee.department?.name || 'N/A' }}</div>
                </v-col>
                <v-col cols="12" sm="6" md="4">
                  <div class="text-caption text-medium-emphasis">Job Title</div>
                  <div class="text-body-2 font-weight-medium">{{ employee.job_title?.job_title || 'N/A' }}</div>
                </v-col>
                <v-col cols="12" sm="6" md="4">
                  <div class="text-caption text-medium-emphasis">Work Location</div>
                  <div class="text-body-2 font-weight-medium">{{ formattedLocation }}</div>
                </v-col>
                <v-col cols="12" sm="6" md="4">
                  <div class="text-caption text-medium-emphasis">Employment Status</div>
                  <div class="text-body-2 font-weight-medium">{{ employee.employment_status?.name || 'N/A' }}</div>
                </v-col>
                <v-col cols="12" sm="6" md="4">
                  <div class="text-caption text-medium-emphasis">Reporting Supervisor</div>
                  <div class="text-body-2 font-weight-medium">
                    {{ supervisorName }}
                  </div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-window-item>

        <!-- TAB 3: Education -->
        <v-window-item value="education">
          <v-card variant="outlined" class="rounded-lg mb-6">
            <v-card-title class="text-subtitle-1 font-weight-bold py-3 px-5 border-b">
              Academic Background
            </v-card-title>
            <v-card-text class="pa-0">
              <v-table v-if="employee.education && employee.education.length" hover class="bg-transparent">
                <thead>
                  <tr>
                    <th class="text-left">Institute</th>
                    <th class="text-left">Level</th>
                    <th class="text-left">Major / Specialization</th>
                    <th class="text-left">Year</th>
                    <th class="text-left">GPA Score</th>
                    <th class="text-left">Dates</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="edu in employee.education" :key="edu.id">
                    <td class="font-weight-medium">{{ edu.institute || 'N/A' }}</td>
                    <td>{{ edu.level || 'N/A' }}</td>
                    <td>{{ edu.major_specialization || 'N/A' }}</td>
                    <td>{{ edu.year || 'N/A' }}</td>
                    <td>{{ edu.gpa_score || 'N/A' }}</td>
                    <td class="text-caption text-medium-emphasis">
                      {{ formatDate(edu.start_date) }} - {{ formatDate(edu.end_date) }}
                    </td>
                  </tr>
                </tbody>
              </v-table>
              <div v-else class="pa-6 text-center text-medium-emphasis">
                No educational background records found.
              </div>
            </v-card-text>
          </v-card>
        </v-window-item>

        <!-- TAB 4: Work Experience -->
        <v-window-item value="experience">
          <v-card variant="outlined" class="rounded-lg mb-6">
            <v-card-title class="text-subtitle-1 font-weight-bold py-3 px-5 border-b">
              Prior Experience
            </v-card-title>
            <v-card-text class="pa-5">
              <div v-if="employee.work_experience && employee.work_experience.length">
                <div
                  v-for="(exp, idx) in employee.work_experience"
                  :key="exp.id || idx"
                  class="mb-4 pb-4"
                  :class="{ 'border-b': idx !== employee.work_experience.length - 1 }"
                >
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                      {{ exp.job_title }} @ {{ exp.company }}
                    </span>
                    <span class="text-caption text-medium-emphasis">
                      {{ formatDate(exp.from) }} – {{ formatDate(exp.to) || 'Present' }}
                    </span>
                  </div>
                  <p class="text-body-2 text-medium-emphasis mb-0">
                    {{ exp.description || 'No description provided.' }}
                  </p>
                </div>
              </div>
              <div v-else class="pa-6 text-center text-medium-emphasis">
                No work experience history recorded.
              </div>
            </v-card-text>
          </v-card>
        </v-window-item>
      </v-window>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, Link } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

export default {
  name: "EmployeeShow",

  components: {
    SidebarLayout,
    Head,
    Link,
  },

  props: {
    employee: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      activeTab: "overview",
    };
  },

  computed: {
    fullName() {
      const parts = [
        this.employee.emp_first_name,
        this.employee.emp_middle_name,
        this.employee.emp_last_name,
        this.employee.emp_suffix,
      ].filter(Boolean);
      return parts.join(" ");
    },

    initials() {
      const first = this.employee.emp_first_name?.[0] || "";
      const last = this.employee.emp_last_name?.[0] || "";
      return `${first}${last}`.toUpperCase();
    },

    photoUrl() {
        console.log('Employee Photo URL:', this.employee);
      return this.employee.photo_url || (this.employee.photo ? `/storage/${this.employee.photo}` : null);
    },

    signatureUrl() {
      return this.employee.signature_url || (this.employee.e_signature_path ? `/storage/${this.employee.e_signature_path}` : null);
    },

    supervisorName() {
      if (!this.employee.supervisor) return "None Assigned";
      return `${this.employee.supervisor.emp_first_name} ${this.employee.supervisor.emp_last_name}`;
    },

    formattedLocation() {
      if (!this.employee.location) return "N/A";
      const parts = [this.employee.location.city, this.employee.location.province].filter(Boolean);
      return parts.length ? parts.join(", ") : this.employee.location.address || "Location Recorded";
    },

    fullAddress() {
      const parts = [
        this.employee.street1,
        this.employee.street2,
        this.employee.city,
        this.employee.province,
        this.employee.zip_code,
        this.employee.country_id,
      ].filter(Boolean);
      return parts.length ? parts.join(", ") : "N/A";
    },
  },

  methods: {
    getStatusColor(status) {
      switch (status) {
        case "active":
          return "success";
        case "on_leave":
          return "warning";
        case "terminated":
          return "error";
        case "archived":
          return "grey";
        default:
          return "primary";
      }
    },

    formatDate(dateStr) {
      if (!dateStr) return "N/A";
      try {
        const date = new Date(dateStr);
        return date.toLocaleDateString("en-US", {
          year: "numeric",
          month: "short",
          day: "numeric",
        });
      } catch {
        return dateStr;
      }
    },
  },
};
</script>

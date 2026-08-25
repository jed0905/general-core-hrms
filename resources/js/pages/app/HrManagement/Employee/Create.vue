<template>
  <EmployeeTabs v-model:activeTab="activeTab" />

  <v-card class="pa-6 rounded-lg shadow-sm">
    <!-- Registration Mode Selector -->
    <div class="d-flex justify-space-between align-center mb-4">
      <v-card-title class="pa-0">Add Employee</v-card-title>
      <v-btn-toggle
        v-model="registrationMode"
        mandatory
        color="primary"
        variant="outlined"
        divided
      >
        <v-btn value="hrms" prepend-icon="mdi-account"> HRMS DEFAULT </v-btn>
        <v-btn
          value="psa"
          prepend-icon="mdi-shield-check"
          :disabled="$page.props.auth.operating_unit?.id !== 2"
        >
          PSA VERIFICATION
        </v-btn>
      </v-btn-toggle>
    </div>

    <v-divider class="mb-6"></v-divider>

    <!-- PSA Registration Form -->
    <template v-if="registrationMode === 'psa'">
      <div class="d-flex justify-center mb-4">
        <v-btn-toggle
          v-model="registrationOption"
          mandatory
          color="primary"
          variant="outlined"
          divided
          style="width: 100%"
          class="w-100"
        >
          <v-btn
            value="personalInformation"
            prepend-icon="mdi-account"
            style="flex: 1 1 0"
            class="w-50"
          >
            Personal Information
          </v-btn>
          <v-btn
            value="pcnOrDigitalId"
            prepend-icon="mdi-shield-check"
            style="flex: 1 1 0"
            class="w-50"
          >
            Nat'l ID Card No/Digital Nat'l ID No
          </v-btn>
        </v-btn-toggle>
      </div>

      <!-- Verification via Personal Information -->
      <template v-if="registrationOption === 'personalInformation'">
        <v-form ref="psaForm" @submit.prevent="validateAndStartPsaLiveness()">
          <v-alert type="info" variant="tonal" class="mb-6" prominent>
            <v-alert-title>PSA Verification Requirements</v-alert-title>
            Please provide the required information for PSA registration. After
            clicking proceed, liveness validation data will be submitted to the
            PSA system.
          </v-alert>

          <!-- PSA Required Fields -->
          <v-row dense>
            <v-col cols="12" md="6">
              <v-text-field
                label="First Name *"
                v-model="v$.psaFormData.firstName.$model"
                density="compact"
                variant="outlined"
                :error-messages="
                  v$.psaFormData.firstName.$errors.map((e) => e.$message)
                "
                required
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="Middle Name"
                v-model="v$.psaFormData.middleName.$model"
                density="compact"
                variant="outlined"
                :error-messages="
                  v$.psaFormData.middleName.$errors.map((e) => e.$message)
                "
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="Last Name *"
                v-model="v$.psaFormData.lastName.$model"
                density="compact"
                variant="outlined"
                :error-messages="
                  v$.psaFormData.lastName.$errors.map((e) => e.$message)
                "
                required
              />
            </v-col>

            <v-col cols="12" md="3">
              <v-text-field
                label="Suffix"
                v-model="v$.psaFormData.suffix.$model"
                density="compact"
                variant="outlined"
                placeholder="e.g., Jr., Sr., III (leave empty if none)"
                :error-messages="
                  v$.psaFormData.suffix.$errors.map((e) => e.$message)
                "
                hint="Optional - leave empty if no suffix"
                persistent-hint
              />
            </v-col>

            <v-col cols="12" md="3">
              <v-text-field
                label="Birthdate *"
                variant="outlined"
                density="compact"
                type="date"
                v-model="v$.psaFormData.birthdate.$model"
                :error-messages="
                  v$.psaFormData.birthdate.$errors.map((e) => e.$message)
                "
                required
              />
            </v-col>
          </v-row>
          <!-- Additional Fields -->
          <v-row dense>
            <v-col
              cols="12"
              md="6"
              v-if="
                $page.props.auth.roles[0] == 'superadmin' ||
                $page.props.auth.roles[0] == 'hr_director'
              "
            >
              <v-select
                label="Operating Unit *"
                density="compact"
                variant="outlined"
                :items="operating_units"
                item-title="name"
                item-value="id"
                v-model="v$.psaFormData.operating_unit_id.$model"
                :error-messages="
                  v$.psaFormData.operating_unit_id.$errors.map(
                    (e) => e.$message
                  )
                "
                required
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="Date Hired"
                variant="outlined"
                density="compact"
                type="date"
                v-model="psaFormData.date_hired"
              />
            </v-col>
          </v-row>

          <v-divider class="my-6"></v-divider>

          <!-- Actions -->
          <div class="d-flex justify-end">
            <ButtonMuted min-width="120" name="Cancel" @click="goToIndex" />
            <ButtonSuccess
              color="primary"
              min-width="120"
              type="submit"
              name="Proceed"
              prepend-icon="mdi-face-recognition"
              class="ml-2"
            >
            </ButtonSuccess>
          </div>
        </v-form>
      </template>

      <!-- Verification via PCN/DNIDN -->
      <template v-else-if="registrationOption === 'pcnOrDigitalId'">
        <v-form ref="pcnForm" @submit.prevent="validateAndStartLiveness()">
          <v-row>
            <v-col
              cols="12"
              md="6"
              v-if="
                $page.props.auth.roles[0] == 'superadmin' ||
                $page.props.auth.roles[0] == 'hr_director'
              "
            >
              <v-select
                label="Operating Unit *"
                density="compact"
                variant="outlined"
                :items="operating_units"
                item-title="name"
                item-value="id"
                v-model="v$.pcnFormData.operating_unit_id.$model"
                :error-messages="
                  v$.pcnFormData.operating_unit_id.$errors.map(
                    (e) => e.$message
                  )
                "
                required
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                label="Date Hired *"
                variant="outlined"
                density="compact"
                type="date"
                v-model="v$.pcnFormData.date_hired.$model"
                :error-messages="
                  v$.pcnFormData.date_hired.$errors.map((e) => e.$message)
                "
                required
              />
            </v-col>

            <v-col cols="12">
              <v-text-field
                label="Nat'l ID Card No or Digital Nat'l ID No *"
                density="compact"
                variant="outlined"
                v-model="v$.pcnFormData.id_number.$model"
                prepend-inner-icon="mdi-account-badge-outline"
                placeholder="Enter Nat'l ID Card No or Digital Nat'l ID No number"
                :error-messages="
                  v$.pcnFormData.id_number.$errors.map((e) => e.$message)
                "
                @input="
                  v$.pcnFormData.id_number.$model =
                    v$.pcnFormData.id_number.$model
                      .toUpperCase()
                      .replace(/[^0-9A-Z]/g, '')
                "
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <div class="d-flex align-center justify-end">
                <ButtonMuted min-width="120" name="Cancel" @click="goToIndex" />
                <ButtonSuccess
                  color="primary"
                  min-width="120"
                  class="ml-2"
                  type="submit"
                  name="Proceed"
                  prepend-icon="mdi-face-recognition"
                />
              </div>
            </v-col>
          </v-row>
        </v-form>
      </template>
    </template>

    <!-- HRMS Default Registration Form -->
    <template v-else>
      <v-form @submit.prevent="submitHrmsForm">
        <v-row align="center" class="mb-4" dense>
          <v-col cols="12">
            <v-row dense>
              <v-col
                cols="12"
                v-if="
                  $page.props.auth.roles[0] == 'superadmin' ||
                  $page.props.auth.roles[0] == 'hr_director'
                "
              >
                <v-select
                  rounded="lg"
                  label="Operating Unit"
                  density="compact"
                  variant="outlined"
                  :items="operating_units"
                  item-title="name"
                  item-value="id"
                  v-model="v$.hrmsFormData.operating_unit_id.$model"
                  :error-messages="
                    v$.hrmsFormData.operating_unit_id.$errors.map(
                      (e) => e.$message
                    )
                  "
                />
              </v-col>

              <!-- First Name -->
              <v-col cols="12" md="3">
                <v-text-field
                  rounded="lg"
                  label="First Name"
                  v-model="v$.hrmsFormData.firstName.$model"
                  density="compact"
                  variant="outlined"
                  :error-messages="
                    v$.hrmsFormData.firstName.$errors.map((e) => e.$message)
                  "
                />
              </v-col>

              <!-- Middle Name -->
              <v-col cols="12" md="3">
                <v-text-field
                  rounded="lg"
                  label="Middle Name"
                  v-model="hrmsFormData.middleName"
                  density="compact"
                  variant="outlined"
                />
              </v-col>

              <!-- Last Name -->
              <v-col cols="12" md="3">
                <v-text-field
                  rounded="lg"
                  label="Last Name"
                  v-model="v$.hrmsFormData.lastName.$model"
                  density="compact"
                  variant="outlined"
                  :error-messages="
                    v$.hrmsFormData.lastName.$errors.map((e) => e.$message)
                  "
                />
              </v-col>

              <!-- Suffix / Name Extension -->
              <v-col cols="12" md="3">
                <v-text-field
                  rounded="lg"
                  label="Suffix"
                  v-model="hrmsFormData.suffix"
                  density="compact"
                  variant="outlined"
                />
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  rounded="lg"
                  label="Birthdate"
                  variant="outlined"
                  density="compact"
                  type="date"
                  v-model="hrmsFormData.birthdate"
                  :error-messages="
                    v$.hrmsFormData.birthdate.$errors.map((e) => e.$message)
                  "
                ></v-text-field>
              </v-col>

              <!-- Email -->
              <v-col cols="12" md="6">
                <v-text-field
                  rounded="lg"
                  label="Email"
                  v-model="v$.hrmsFormData.email.$model"
                  density="compact"
                  variant="outlined"
                  :error-messages="
                    v$.hrmsFormData.email.$errors.map((e) => e.$message)
                  "
                />
              </v-col>

              <v-col>
                <v-text-field
                  rounded="lg"
                  label="Date Hired"
                  variant="outlined"
                  density="compact"
                  type="date"
                  v-model="hrmsFormData.date_hired"
                  :error-messages="
                    v$.hrmsFormData.date_hired.$errors.map((e) => e.$message)
                  "
                ></v-text-field>
              </v-col>
            </v-row>
          </v-col>
        </v-row>

        <v-divider class="my-6" />

        <!-- Actions -->
        <div class="d-flex justify-end">
          <ButtonMuted min-width="120" name="Cancel" @click="goToIndex" />
          <ButtonSuccess
            min-width="120"
            class="ml-2"
            type="submit"
            name="Save"
          />
        </div>
      </v-form>
    </template>
  </v-card>

  <!-- Save Authorization Dialog -->
  <v-dialog
    v-model="isSaveAuthorizationDialogVisible"
    max-width="700"
    persistent
    scrollable
  >
    <v-card>
      <v-card-title class="d-flex align-center pa-4 starbucks-green text-white">
        <v-icon class="mr-2" color="white">mdi-shield-check</v-icon>
        <span class="text-h6 font-weight-bold"
          >Authorize Saving of PSA Data</span
        >
      </v-card-title>

      <v-card-text class="pa-4">
        <!-- Information Alert -->
        <v-alert
          type="info"
          variant="tonal"
          class="mb-4"
          prominent
          density="compact"
          color="starbucks-green"
        >
          <v-alert-title class="text-subtitle-1 font-weight-bold mb-2">
            PSA Verification Successful
          </v-alert-title>
          <div class="text-body-2">
            PSA verification has successfully collected key personal details.
            Please review the information below and authorize saving this data
            to your employee profile.
          </div>
        </v-alert>

        <!-- Data Preview Section -->
        <div class="mb-4">
          <div
            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center"
          >
            <v-icon class="mr-2" color="starbucks-green"
              >mdi-account-details</v-icon
            >
            Retrieved Information
          </div>

          <v-card variant="outlined" class="pa-3" style="border-color: #006241">
            <v-row dense>
              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    First Name
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.firstName || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Middle Name
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.middleName || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Last Name
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.lastName || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Suffix
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.suffix ?? "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Place of Birth
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.place_of_birth || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Birthdate
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{
                      psaLoadedData.birthdate
                        ? new Date(psaLoadedData.birthdate).toLocaleDateString()
                        : "N/A"
                    }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Mobile Number
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.mobile_no || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Email
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.email || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12">
                <v-divider class="my-2"></v-divider>
                <div
                  class="text-subtitle-2 font-weight-bold mb-2 d-flex align-center"
                >
                  <v-icon class="mr-2" color="starbucks-green" size="small"
                    >mdi-map-marker</v-icon
                  >
                  Address
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Province
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.province || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Municipality/City
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{
                      psaLoadedData.municipality || psaLoadedData.city || "N/A"
                    }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Barangay
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.barangay || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Zip Code
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.zip_code || "N/A" }}
                  </div>
                </div>
              </v-col>
            </v-row>
          </v-card>
        </div>

        <!-- Authorization Message -->
        <v-alert type="warning" variant="tonal" density="compact" class="mb-2">
          <div class="text-body-2">
            <strong>Authorization Required:</strong> Do you authorize this
            system to save the information retrieved from the PSA verification
            (such as name, birthdate, and other identifiers) into your employee
            profile? Your consent ensures data accuracy and secure record
            keeping.
          </div>
        </v-alert>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="pa-4 justify-end">
        <v-btn
          min-width="120"
          class="text-black"
          @click="isSaveAuthorizationDialogVisible = false"
          color="grey-darken-1"
          variant="outlined"
          rounded="xl"
          prepend-icon="mdi-close-circle"
          >No</v-btn
        >
        <v-btn
          min-width="120"
          class="text-white"
          @click="continueSaving()"
          color="starbucks-green"
          variant="elevated"
          rounded="xl"
          prepend-icon="mdi-check-circle"
          >Yes</v-btn
        >
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- <pre>{{ $page.props.psa_public_api_key }}</pre> -->
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import EmployeeTabs from "@/components/EmployeeTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";

import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, email, minLength } from "@vuelidate/validators";

export default {
  layout: SidebarLayout,

  components: {
    EmployeeTabs,
    ButtonSuccess,
    ButtonMuted,
  },

  props: {
    roles: Object,
    operating_units: Object,
  },

  data() {
    return {
      v$: useVuelidate(),
      isSaveAuthorizationDialogVisible: false,
      activeTab: "employeeAdd",
      registrationMode: "hrms", // 'hrms' or 'psa'
      registrationOption: "personalInformation", // 'personalInformation' or 'pcnOrDigitalId' or 'qrCode'

      // PSA Form Data
      psaFormData: {
        verificationType: "psa",
        firstName: null,
        middleName: null,
        lastName: null,
        suffix: "",
        birthdate: null,
        date_hired: new Date().toISOString().split("T")[0],
        operating_unit_id: null,
      },

      pcnFormData: {
        operating_unit_id: null,
        date_hired: new Date().toISOString().split("T")[0],
        verificationType: "pcn",
        id_number: null,
        face_liveness_session_id: null,
      },

      psaLivenessSessionId: null,

      // HRMS Form Data
      hrmsFormData: useForm({
        operating_unit_id: null,
        firstName: null,
        middleName: null,
        lastName: null,
        suffix: null,
        email: null,
        birthdate: null,
        date_hired: new Date().toISOString().split("T")[0],
        photo: null,
      }),

      // Profile Picture
      profilePicture: null,
      formErrors: {},

      psaLoadedData: {
        firstName: null,
        middleName: null,
        lastName: null,
        suffix: null,
        idNumber: null,
        birthdate: null,
        sex: null,
        civil_status: null,
        blood_type: null,
        place_of_birth: null,
        mobile_number: null,
        province: null,
        municipality: null,
        city: null,
        barangay: null,
        zip_code: null,
        email: null,
      },

      fetchedData: {
        firstName: null,
        middleName: null,
        lastName: null,
        suffix: null,
        birthdate: null,
        province: null,
        municipality: null,
        barangay: null,
        street: null,
        zip_code: null,
        phone_number: null,
        email: null,
        sex: null,
      },
    };
  },

  created() {
    // If user is NOT superadmin/hr_director, set operating unit
    if (
      this.$page.props.auth.roles[0] !== "superadmin" &&
      this.$page.props.auth.roles[0] !== "hr_director"
    ) {
      this.hrmsFormData.operating_unit_id = this.operating_units[0].id;
      this.psaFormData.operating_unit_id = this.operating_units[0].id;
      this.pcnFormData.operating_unit_id = this.operating_units[0].id;
    }
  },

  mounted() {
    const scriptUrl =
      "https://liveness.everify.gov.ph/js/everify-liveness-sdk.min.js";

    if (!document.querySelector(`script[src="${scriptUrl}"]`)) {
      const script = document.createElement("script");
      script.src = scriptUrl;
      script.onload = () => {
        console.log("eKYC script loaded");
      };
      script.onerror = () => {
        console.error("Failed to load eKYC script");
      };
      document.body.appendChild(script);
    }
  },

  // beforeUnmount() {
  //   // Clean up camera stream
  //   this.stopCamera();
  // },

  validations() {
    const isAdminOrDirector =
      this.$page?.props?.auth?.roles?.[0] === "superadmin" ||
      this.$page?.props?.auth?.roles?.[0] === "hr_director";

    return {
      psaFormData: {
        firstName: { required },
        middleName: { minLength: minLength(0) },
        lastName: { required },
        suffix: { minLength: minLength(0) },
        birthdate: { required },
        operating_unit_id: { required },
      },

      hrmsFormData: {
        operating_unit_id: { required },
        firstName: { required },
        lastName: { required },
        email: { required, email },
        birthdate: { required },
        date_hired: { required },
      },

      pcnFormData: {
        operating_unit_id: {
          required: (value) => {
            // Only required if user is admin/director (field is visible)
            if (isAdminOrDirector) {
              return !!value || "Operating Unit is required";
            }
            return true;
          },
        },
        date_hired: { required },
        id_number: {
          required,
          minLength: minLength(5),
          validFormat: (value) => {
            if (!value) return true;
            // Allow only letters and numbers, no hyphens
            return (
              /^[0-9a-zA-Z]+$/.test(value) ||
              "Invalid format: no dashes allowed"
            );
          },
        },
      },
    };
  },

  methods: {
    goToIndex() {
      this.$inertia.visit(route("hrmanagement.employee.index"));
    },

    handleSaveAuthorization() {
      // Close the dialog
      this.isSaveAuthorizationDialogVisible = false;

      // TODO: Implement the logic to save the psaLoadedData
      // This could involve mapping psaLoadedData to psaFormData and submitting
      // console.log("Authorizing save of PSA data:", this.psaLoadedData);

      // Example: You might want to populate psaFormData with psaLoadedData
      // this.psaFormData.firstName = this.psaLoadedData.firstName;
      // this.psaFormData.middleName = this.psaLoadedData.middleName;
      // this.psaFormData.lastName = this.psaLoadedData.lastName;
      // this.psaFormData.suffix = this.psaLoadedData.suffix;
      // this.psaFormData.birthdate = this.psaLoadedData.birthdate;
    },

    validateAndStartPsaLiveness() {
      // Validate the PSA form before starting liveness
      this.v$.psaFormData.$touch();

      if (this.v$.psaFormData.$invalid) {
        this.showToast(
          "Please fill in all required fields correctly.",
          "error"
        );
        return;
      }

      this.startLiveness();
    },

    validateAndStartLiveness() {
      // Validate the PCN form before starting liveness
      this.v$.pcnFormData.$touch();

      if (this.v$.pcnFormData.$invalid) {
        this.showToast(
          "Please fill in all required fields correctly.",
          "error"
        );
        return;
      }

      this.startLiveness();
    },

    startLiveness() {
      if (typeof window.eKYC !== "function") {
        console.error("eKYC is not available yet, script not loaded");
        this.showToast(
          "Liveness verification service is not available. Please try again later.",
          "error"
        );
        return;
      }
      window
        .eKYC()
        .start({
          pubKey: this.$page.props.psa_public_api_key,
        })
        .then((data) => {
          this.response = data;
          // console.log("Liveness data", data.result.session_id);
          this.psaLivenessSessionId = data.result.session_id;
          this.pcnFormData.face_liveness_session_id = data.result.session_id;
          if (this.registrationOption === "personalInformation") {
            this.submitPsaForm();
          } else if (this.registrationOption === "pcnOrDigitalId") {
            this.submitPcnForm();
          }
        })
        .catch((err) => {
          console.error("error", err);
          this.showToast(
            "Liveness verification failed. Please try again.",
            "error"
          );
        });
    },

    // PSA Form Submission
    submitPsaForm() {
      this.v$.psaFormData.$touch();
      this.$inertia.post(
        route("hrmanagement.employee.psa.verify"),
        {
          ...this.psaFormData,
          face_liveness_session_id: this.psaLivenessSessionId,
        },
        {
          onSuccess: () => {
            // Load data from backend if submit is success
            const data = this.$page.props.flash.psaLoadedData;

            if (data) {
              // Map backend keys to frontend keys
              this.psaLoadedData.firstName = data.first_name;
              this.psaLoadedData.middleName = data.middle_name;
              this.psaLoadedData.lastName = data.last_name;
              this.psaLoadedData.suffix = data.suffix;
              this.psaLoadedData.idNumber = data.id_number || null;
              this.psaLoadedData.birthdate = data.birth_date;
              this.psaLoadedData.sex = data.sex;
              this.psaLoadedData.civil_status = data.marital_status;
              this.psaLoadedData.blood_type = data.blood_type;
              this.psaLoadedData.place_of_birth = data.place_of_birth;
              this.psaLoadedData.mobile_number = data.mobile_number;
              this.psaLoadedData.province = data.province;
              this.psaLoadedData.municipality = data.municipality;
              this.psaLoadedData.barangay = data.barangay;
              this.psaLoadedData.zip_code = data.postal_code;
              this.psaLoadedData.email = data.email;
            }
            this.isSaveAuthorizationDialogVisible = true;
            // this.showToast("PSA Verification Successful", "success");
            // this.showToast(
            //   "Employee successfully registered via PSA.",
            //   "success"
            // );
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    submitPcnForm() {
      this.v$.pcnFormData.$touch();
      this.$inertia.post(
        route("hrmanagement.employee.psa.verify"),
        {
          ...this.pcnFormData,
        },
        {
          onSuccess: () => {
            const data = this.$page.props.flash.psaLoadedData;

            if (data) {
              // Map backend keys to frontend keys

              this.psaLoadedData.firstName = data.first_name;
              this.psaLoadedData.middleName = data.middle_name;
              this.psaLoadedData.lastName = data.last_name;
              this.psaLoadedData.suffix = data.suffix;
              this.psaLoadedData.idNumber = data.id_number || null;
              this.psaLoadedData.birthdate = data.birth_date;
              this.psaLoadedData.sex = data.sex;
              this.psaLoadedData.civil_status = data.marital_status;
              this.psaLoadedData.blood_type = data.blood_type;
              this.psaLoadedData.place_of_birth = data.place_of_birth;
              this.psaLoadedData.mobile_number = data.mobile_number;
              this.psaLoadedData.province = data.province;
              this.psaLoadedData.municipality = data.municipality;
              this.psaLoadedData.barangay = data.barangay;
              this.psaLoadedData.zip_code = data.postal_code;
              this.psaLoadedData.email = data.email;
            }
            this.isSaveAuthorizationDialogVisible = true;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    continueSaving() {
      /* Add Route Here for storing fetch data */
      this.$inertia.post(
        route("hrmanagement.employee.storePsaData"),
        {
          ...this.psaLoadedData,
          date_hired:
            this.registrationOption === "personalInformation"
              ? this.psaFormData.date_hired
              : this.pcnFormData.date_hired,
          operating_unit_id:
            this.registrationOption === "personalInformation"
              ? this.psaFormData.operating_unit_id
              : this.pcnFormData.operating_unit_id,
        },
        {
          onSuccess: () => {
            this.isSaveAuthorizationDialogVisible = false;
            this.showToast(
              "Employee successfully created with PSA data.",
              "success"
            );
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    // HRMS Form Submission
    submitHrmsForm() {
      this.v$.$touch();

      this.hrmsFormData.post(route("hrmanagement.employee.store"), {
        onSuccess: () => {
          this.showToast("Employee successfully created.", "success");
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ");

          this.showToast(`${errorMessages}`, "error");
        },
      });
    },

    // Convert Data URL to File (for backend submission)
    dataURLtoFile(dataurl, filename) {
      const arr = dataurl.split(",");
      const mime = arr[0].match(/:(.*?);/)[1];
      const bstr = atob(arr[1]);
      let n = bstr.length;
      const u8arr = new Uint8Array(n);
      while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
      }
      return new File([u8arr], filename, { type: mime });
    },

    // HRMS Profile Picture Upload
    triggerFileInput() {
      this.$refs.fileInput.click();
    },

    handleFileChange(event) {
      const file = event.target.files[0];
      if (file) {
        this.processFile(file);
      }
    },

    processFile(file) {
      if (file.type.startsWith("image/")) {
        const reader = new FileReader();
        reader.onload = (e) => {
          this.profilePicture = e.target.result;
          this.hrmsFormData.photo = file;
        };
        reader.readAsDataURL(file);
      }
    },
  },
};
</script>

<style scoped>
.profile-upload {
  text-align: center;
}

.avatar-wrapper {
  position: relative;
  display: inline-block;
}

.plus-icon {
  position: relative;
  bottom: -25px;
  right: 40px;
  transform: translate(50%, 50%);
  z-index: 1;
}

.camera-preview {
  position: relative;
  overflow: hidden;
}

.camera-preview video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.gap-2 {
  gap: 8px;
}
</style>

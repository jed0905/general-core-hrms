<template>
  <UserManagementTabs v-model:activeTab="activeTab" />

  <v-card class="pa-6 rounded-lg shadow-sm">
    <v-card-title class="mb-2">Add User</v-card-title>
    <v-divider class="mb-6"></v-divider>

    <v-form ref="form" @submit.prevent="submitForm" validate-on="submit lazy">
      <v-row align="center" class="mb-4" dense>
        <v-col cols="12">
          <v-row dense>
            <!-- User Role -->
            <v-col cols="12" md="6">
              <v-autocomplete
                label="User Role"
                density="compact"
                variant="outlined"
                v-model="v$.formData.userRole.$model"
                :items="roles"
                item-title="display_name"
                item-value="id"
                :error-messages="
                  v$.formData.userRole.$errors.map((e) => e.$message)
                "
              />
            </v-col>

            <!-- Employee Name -->
            <v-col cols="12" md="6">
              <v-autocomplete
                v-model="v$.formData.employeeId.$model"
                label="Employee Name (Type to search)"
                density="compact"
                variant="outlined"
                :items="filteredEmployees"
                item-title="personal_information.full_name_asc"
                item-value="id"
                :search="search"
                :no-filter="true"
                @update:search="onSearch"
                :hide-no-data="search.length < 2"
                :error-messages="
                  v$.formData.employeeId?.$errors.map((e) => e.$message)
                "
                return-object
              />
            </v-col>

            <!-- Status -->
            <v-col cols="12" md="6">
              <v-select
                label="Status"
                :items="account_types"
                item-title="text"
                item-value="value"
                density="compact"
                variant="outlined"
                v-model="v$.formData.status.$model"
                :error-messages="
                  v$.formData.status.$errors.map((e) => e.$message)
                "
              />
            </v-col>

            <!-- Username -->
            <v-col cols="12" md="6">
              <v-text-field
                label="Username"
                density="compact"
                variant="outlined"
                v-model="v$.formData.username.$model"
                @input="onUsernameInput"
                @blur="onUsernameBlur"
                :error-messages="
                  v$.formData.username.$errors.map((e) => e.$message)
                "
                hint="Only letters, numbers, dots, underscores, and hyphens allowed"
                persistent-hint
              />
            </v-col>

            <!-- Password -->
            <v-col cols="12" md="6">
              <v-text-field
                label="Password"
                density="compact"
                variant="outlined"
                v-model="v$.formData.password.$model"
                @input="onPasswordInput"
                :error-messages="
                  v$.formData.password.$errors.map((e) => e.$message)
                "
                :append-inner-icon="
                  !isPasswordVisible ? 'mdi-eye-off-outline' : 'mdi-eye-outline'
                "
                :type="!isPasswordVisible ? 'password' : 'text'"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
                hint="8-20 chars: uppercase, lowercase, number, special character"
                persistent-hint
              />
            </v-col>

            <!-- Confirm Password -->
            <v-col cols="12" md="6">
              <v-text-field
                label="Confirm Password"
                density="compact"
                variant="outlined"
                v-model="v$.formData.password_confirmation.$model"
                @input="onPasswordConfirmationInput"
                :error-messages="
                  v$.formData.password_confirmation.$errors.map(
                    (e) => e.$message
                  )
                "
                :append-inner-icon="
                  !isPasswordVisible ? 'mdi-eye-off-outline' : 'mdi-eye-outline'
                "
                :type="!isPasswordVisible ? 'password' : 'text'"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />
            </v-col>

            <!-- Note -->
            <v-col cols="12">
              <small class="text-caption text-grey-darken-1">
                Note: For a strong password, please use a hard-to-guess
                combination of text with upper and lower case characters,
                symbols, and numbers.
              </small>
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <v-divider class="my-6" />

      <!-- Actions -->
      <div class="d-flex justify-end">
        <ButtonMuted name="Cancel" @click="goToIndex"/>
        <ButtonSuccess class="ml-2" type="submit" name="Save" />
      </div>
    </v-form>
  </v-card>

  <!-- Create Another User Dialog -->
  <v-dialog v-model="createAnotherDialog" max-width="500">
    <v-card class="pa-4 rounded-lg">
      <v-card-title class="d-flex align-center justify-center text-h5 font-weight-bold mb-4">
        <v-icon color="success" size="large" class="mr-2">mdi-account-plus</v-icon>
        Create Another User?
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">Would you like to create another user account?</p>
        <p class="text-caption text-medium-emphasis">
          Click 'Yes' to create a new user or 'No' to return to user list.
        </p>
      </v-card-text>
      <v-card-actions class="d-flex justify-end gap-2 pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          min-width="120"
          @click="goToIndex()"
        >
          No
        </v-btn>

        <v-btn
          color="success"
          variant="elevated"
          min-width="120"
          @click="createAnother()"
        >
          Yes
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- <pre>{{ roles }}</pre> -->
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import UserManagementTabs from "@/components/UserManagementTabs.vue";
import { useForm } from "@inertiajs/vue3";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useVuelidate } from "@vuelidate/core";
import {
  required,
  minLength,
  maxLength,
  sameAs,
  helpers,
} from "@vuelidate/validators";
import { passwordRegex } from "@/helpers/DataHelper.js";
import { 
  sanitizeFormData, 
  getUserFormCleaningRules, 
  cleanUsername, 
  cleanPassword,
  validateCleanedInput 
} from "@/helpers/InputCleansing.js";

export default {
  layout: SidebarLayout,

  components: {
    Breadcrumbs,
    PrimaryButton,
    TableWrapper,
    FilterWrapper,
    UserManagementTabs,
    ButtonSuccess,
    ButtonMuted,
  },

  props: {
    errors: Object,
    roles: Array,
    employees: Object,
  },

  data() {
    return {
      // Initialize validation
      v$: useVuelidate(),

      activeTab: "userAdd",

      account_types: [
        { text: "Active", value: "active" },
        { text: "Inactive", value: "inactive" },
      ],

      search: "",
      formData: useForm({
        userRole: null,
        employeeId: null,
        status: "active",
        username: null,
        password: null,
        password_confirmation: null,
      }),

      isPasswordVisible: false,

      createAnotherDialog: false,
    };
  },

  computed: {
    filteredEmployees() {
      if (this.search.length < 2) return [];
      return this.employees.data.filter((employee) =>
        employee.personal_information.full_name_asc
          .toLowerCase()
          .includes(this.search.toLowerCase())
      );
    },
  },

  validations() {
    return {
      formData: {
        userRole: { required },
        employeeId: { required },
        status: { required },
        username: {
          required,
          minLength: minLength(5),
          maxLength: maxLength(20),
        },
        password: { required, passwordRules: helpers.regex(passwordRegex) },
        password_confirmation: {
          required,
          sameAs: helpers.withMessage(
            "Passwords do not match",
            sameAs(this.formData.password)
          ),
        },
      },
    };
  },

  methods: {
    goToIndex() {
      this.$inertia.visit(route("administration.user.index"));
    },

    onSearch(val) {
      this.search = val;
    },

    // Input cleansing methods
    onUsernameInput(event) {
      const cleaned = cleanUsername(event.target.value);
      this.formData.username = cleaned;
    },

    onUsernameBlur() {
      // Additional validation on blur
      if (this.formData.username && !validateCleanedInput(this.formData.username, 'username')) {
        this.showToast('Username contains invalid characters', 'warning');
      }
    },

    onPasswordInput(event) {
      // Minimal cleaning for passwords (only trim)
      const cleaned = cleanPassword(event.target.value);
      this.formData.password = cleaned;
    },

    onPasswordConfirmationInput(event) {
      // Minimal cleaning for passwords (only trim)
      const cleaned = cleanPassword(event.target.value);
      this.formData.password_confirmation = cleaned;
    },

    // Sanitize all form data before submission
    sanitizeFormData() {
      const cleaningRules = getUserFormCleaningRules();
      const formDataObj = {
        userRole: this.formData.userRole,
        employeeId: this.formData.employeeId,
        status: this.formData.status,
        username: this.formData.username,
        password: this.formData.password,
        password_confirmation: this.formData.password_confirmation,
      };

      const sanitized = sanitizeFormData(formDataObj, cleaningRules);

      // Update form data with sanitized values
      Object.keys(sanitized).forEach(key => {
        if (this.formData.hasOwnProperty(key)) {
          this.formData[key] = sanitized[key];
        }
      });
    },

    submitForm() {
      // Sanitize form data before validation and submission
      this.sanitizeFormData();

      this.formData.employeeId = this.formData.employeeId.id;
      this.v$.$touch();

      if (this.v$.$invalid) return;

      // Additional client-side validation for cleaned data
      if (!validateCleanedInput(this.formData.username, 'username')) {
        this.showToast('Username contains invalid characters', 'error');
        return;
      }

      //   console.log("Form Data:", this.formData);

      this.formData.post(route("administration.user.store"), {
        onSuccess: () => {
          this.showToast("User created successfully!", "success");
          this.createAnotherDialog = true;
        },
        onError: (errors) => {
          // Combine all error messages into one string
          const errorMessages = Object.values(errors).flat().join(" ");

          this.showToast(`${errorMessages}`, "error");
        },
      });
    },

    createAnother(){
      this.formData.reset();
      this.createAnotherDialog = false;
    },

    // dontCreateAnother(){
    //   this.goToIndex();
    // }


  },
};
</script>

<style>
</style>

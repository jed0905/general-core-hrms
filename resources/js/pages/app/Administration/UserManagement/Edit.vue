<template>
  <UserManagementTabs v-model:activeTab="activeTab" />

  <v-card class="pa-6 rounded-lg shadow-sm">
    <v-card-title class="mb-2">Edit User</v-card-title>
    <v-divider class="mb-6"></v-divider>

    <v-form ref="form" @submit.prevent="submitForm" validate-on="submit lazy">
      <v-row align="center" class="mb-4" dense>
        <v-col cols="12">
          <v-row dense>
            <!-- User Role -->
            <v-col cols="12" md="6">
              <v-select
                label="User Role (Required)"
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
              <v-text-field
                label="Employee Name"
                density="compact"
                variant="outlined"
                v-model="formData.employeeName"
                item-title="personal_information.full_name_asc"
                item-value="id"
                readonly
              />
            </v-col>

            <!-- Status -->
            <v-col cols="12" md="6">
              <v-select
                label="Status (Required)"
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
                label="Username (Required)"
                density="compact"
                variant="outlined"
                v-model="v$.formData.username.$model"
                :error-messages="
                  v$.formData.username.$errors.map((e) => e.$message)
                "
              />
            </v-col>

            <!-- Checkbox if will change password -->
            <v-col cols="12">
              <v-checkbox
                label="Change Password ?"
                v-model="isPasswordFieldsVisible"
                density="compact"
                variant="outlined"
              />
            </v-col>

            <!-- Password Fields -->
            <v-col v-if="isPasswordFieldsVisible" cols="12" class="mt-4">
              <v-row dense>
                <!-- Password -->
                <v-col cols="12" md="6">
                  <v-text-field
                    label="Password (Required)"
                    density="compact"
                    variant="outlined"
                    v-model="v$.formData.password.$model"
                    :error-messages="
                      v$.formData.password.$errors.map((e) => e.$message)
                    "
                    :append-inner-icon="
                      !isPasswordVisible
                        ? 'mdi-eye-off-outline'
                        : 'mdi-eye-outline'
                    "
                    :type="!isPasswordVisible ? 'password' : 'text'"
                    @click:append-inner="isPasswordVisible = !isPasswordVisible"
                  />
                </v-col>

                <!-- Confirm Password -->
                <v-col cols="12" md="6">
                  <v-text-field
                    label="Confirm Password (Required)"
                    density="compact"
                    variant="outlined"
                    v-model="v$.formData.password_confirmation.$model"
                    :error-messages="
                      v$.formData.password_confirmation.$errors.map(
                        (e) => e.$message
                      )
                    "
                    :append-inner-icon="
                      !isPasswordVisible
                        ? 'mdi-eye-off-outline'
                        : 'mdi-eye-outline'
                    "
                    :type="!isPasswordVisible ? 'password' : 'text'"
                    @click:append-inner="isPasswordVisible = !isPasswordVisible"
                  />
                </v-col>
              </v-row>

              <!-- Note -->
              <v-col cols="12">
                <small class="text-caption text-grey-darken-1">
                  Note: For a strong password, please use a hard-to-guess
                  combination of text with upper and lower case characters,
                  symbols, and numbers.
                </small>
              </v-col>
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <v-divider class="my-6" />

      <!-- Actions -->
      <div class="d-flex justify-end">
        <ButtonMuted name="Cancel" @click="goToIndex" />
        <ButtonSuccess class="ml-2" type="submit" name="Save" />
      </div>
    </v-form>
  </v-card>

  <!-- <pre>{{ user }}</pre> -->
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
import useVuelidate from "@vuelidate/core";
import {
  required,
  minLength,
  maxLength,
  alpha,
  sameAs,
  helpers,
} from "@vuelidate/validators";
import { passwordRegex } from "@/helpers/DataHelper.js";

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
    user: Array,
  },

  data() {
    return {
      // Initialize validation
      v$: useVuelidate(),

      account_types: [
        { text: "Active", value: "active" },
        { text: "Inactive", value: "inactive" },
      ],

      formData: useForm({
        userRole: this.user.data.role,
        employeeName:
          this.user.data.employee?.personal_information?.full_name_asc,
        status: this.user.data.status,
        username: this.user.data.username,
        password: null,
        password_confirmation: null,
      }),

      isPasswordVisible: false,
      isPasswordFieldsVisible: false,
    };
  },

  validations() {
    return {
      formData: {
        userRole: { required },
        status: { required },
        username: {
          required,
          minLength: minLength(3),
          maxLength: maxLength(20),
        },
        password: this.isPasswordFieldsVisible
          ? {
              required,
              passwordRules: helpers.regex(passwordRegex),
            }
          : {},
        password_confirmation: this.isPasswordFieldsVisible
          ? {
              required,
              sameAs: helpers.withMessage(
                "Your passwords do not match.",
                sameAs(this.formData.password)
              ),
            }
          : {},
      },
    };
  },

  watch: {
    isPasswordFieldsVisible(newValue) {
      if (!newValue) {
        this.formData.password = null;
        this.formData.password_confirmation = null;
        this.v$.formData.password.$reset();
        this.v$.formData.password_confirmation.$reset();
      }
    },
  },

  methods: {
    goToIndex() {
      this.$inertia.visit(route("administration.user.index"));
    },

    submitForm() {
      this.v$.$touch();

      if (this.v$.$invalid) return;

      this.formData.put(
        route("administration.user.update", { id: this.user.data.id }),
        {
          onSuccess: () => {
            this.showToast("User updated successfully!", "success");
            this.formData.reset();
          },
          onError: (errors) => {
            // Combine all error messages into one string
            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },
  },
};
</script>

<style>
</style>

<template>
  <div class="account-credentials-container">
    <!-- Header Section -->
    <div class="page-header mb-6">
      <div class="d-flex align-center mb-2">
        <v-icon size="32" color="starbucks-green" class="mr-3"
          >mdi-account-cog</v-icon
        >
        <h2 class="text-h4 font-weight-bold text-starbucks-green">
          Account Settings
        </h2>
      </div>
      <p class="text-body-1 text-medium-emphasis">
        Manage your account credentials and security settings
      </p>
    </div>

    <!-- <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <span class="text-h6 font-weight-semibold">
                <v-icon color="starbucks-green" size="24" class="mr-2">
                  mdi-shield-check
                </v-icon>
                Two-Factor Authentication
              </span>
              <div>
                <v-switch
                  v-model="isTwoFactorEnabled"
                  color="starbucks-green"
                  hide-details
                  @change="sendOtpForTwoFactor(isTwoFactorEnabled)"
                ></v-switch>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row> -->

    <v-row>
      <!-- Username Update Section -->
      <v-col cols="12" lg="6">
        <v-card
          class="credential-card"
          elevation="2"
          rounded="xl"
          :class="{ 'card-hover': true }"
        >
          <div class="card-header">
            <div class="d-flex align-center">
              <div class="icon-container primary-light">
                <v-icon size="24" color="starbucks-green">mdi-account</v-icon>
              </div>
              <div class="ml-4">
                <h3 class="text-h6 font-weight-semibold">Username</h3>
                <p class="text-caption text-medium-emphasis mb-0">
                  Update your login username
                </p>
              </div>
            </div>
          </div>

          <v-divider class="my-4"></v-divider>

          <v-card-text class="pt-0 flex-grow-1 d-flex flex-column">
            <v-form
              @submit.prevent="showUpdateUsernameDialog()"
              class="d-flex flex-column"
            >
              <v-text-field
                label="Username"
                variant="outlined"
                density="compact"
                rounded="lg"
                prepend-inner-icon="mdi-account-outline"
                v-model="usernameForm.username"
                :error-messages="
                  v$.usernameForm.username.$errors.map((e) => e.$message)
                "
                class="mb-10"
                hide-details="auto"
              ></v-text-field>

              <div class="d-flex justify-end mt-20">
                <v-btn
                  color="starbucks-green"
                  variant="elevated"
                  rounded="lg"
                  prepend-icon="mdi-content-save"
                  @click="showUpdateUsernameDialog()"
                  :loading="usernameForm.processing"
                  :disabled="
                    !usernameForm.username ||
                    usernameForm.username === $page.props.auth.user.username
                  "
                >
                  Update Username
                </v-btn>
              </div>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Password Update Section -->
      <v-col cols="12" lg="6">
        <v-card
          class="credential-card"
          elevation="2"
          rounded="xl"
          :class="{ 'card-hover': true }"
        >
          <div class="card-header">
            <div class="d-flex align-center">
              <div class="icon-container warning-light">
                <v-icon size="24" color="warning">mdi-lock</v-icon>
              </div>
              <div class="ml-4">
                <h3 class="text-h6 font-weight-semibold">Password</h3>
                <p class="text-caption text-medium-emphasis mb-0">
                  Change your account password
                </p>
              </div>
            </div>
          </div>

          <v-divider class="my-4"></v-divider>

          <v-card-text class="pt-0 flex-grow-1 d-flex flex-column">
            <v-form
              @submit.prevent="showProcceedDialog()"
              class="d-flex flex-column flex-grow-1"
            >
              <v-text-field
                label="Current Password"
                variant="outlined"
                density="compact"
                rounded="lg"
                :type="current_password_visible ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-outline"
                v-model="passwordForm.currentPassword"
                :append-inner-icon="
                  current_password_visible ? 'mdi-eye' : 'mdi-eye-off'
                "
                @click:append-inner="
                  current_password_visible = !current_password_visible
                "
                :error-messages="
                  v$.passwordForm.currentPassword.$errors.map((e) => e.$message)
                "
                class="mb-3"
                hide-details="auto"
              ></v-text-field>

              <v-text-field
                label="New Password"
                variant="outlined"
                density="compact"
                rounded="lg"
                :type="new_password_visible ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-plus"
                v-model="passwordForm.newPassword"
                :append-inner-icon="
                  new_password_visible ? 'mdi-eye' : 'mdi-eye-off'
                "
                @click:append-inner="
                  new_password_visible = !new_password_visible
                "
                :error-messages="
                  v$.passwordForm.newPassword.$errors.map((e) => e.$message)
                "
                class="mb-3"
                hide-details="auto"
              ></v-text-field>

              <v-text-field
                label="Confirm New Password"
                variant="outlined"
                density="compact"
                rounded="lg"
                :type="confirm_new_password_visible ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-check"
                :append-inner-icon="
                  confirm_new_password_visible ? 'mdi-eye' : 'mdi-eye-off'
                "
                @click:append-inner="
                  confirm_new_password_visible = !confirm_new_password_visible
                "
                v-model="passwordForm.confirmNewPassword"
                :error-messages="
                  v$.passwordForm.confirmNewPassword.$errors.map(
                    (e) => e.$message
                  )
                "
                class="mb-4"
                hide-details="auto"
              ></v-text-field>

              <div class="d-flex justify-end mt-auto">
                <v-btn
                  color="warning"
                  variant="elevated"
                  rounded="lg"
                  prepend-icon="mdi-lock-reset"
                  @click="showProcceedDialog()"
                  :loading="passwordForm.processing"
                  :disabled="!passwordForm.confirmNewPassword"
                >
                  Change Password
                </v-btn>
              </div>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- <v-row>
      <v-col cols="12" md="6">
        <v-card elevation="2" rounded="xl" :class="{ 'card-hover': true }">
          <div class="card-header">
            <div class="d-flex align-center">
              <div class="icon-container info-light">
                <v-icon size="24" color="info">mdi-email</v-icon>
              </div>
              <div class="ml-4">
                <h3 class="text-h6 font-weight-semibold">Email Address</h3>
                <p class="text-caption text-medium-emphasis mb-0">
                  Update your account email
                </p>
              </div>
            </div>
          </div>

          <v-divider class="my-4"></v-divider>

          <v-card-text class="pt-0 flex-grow-1 d-flex flex-column">
            <v-form
              @submit.prevent="showUpdateEmailDialog()"
              class="d-flex flex-column"
            >
              <v-text-field
                label="Current Email"
                variant="outlined"
                density="compact"
                rounded="lg"
                v-model="emailForm.currentEmail"
                prepend-inner-icon="mdi-email-outline"
                :readonly="true"
                class="mb-3"
                hide-details="auto"
              ></v-text-field>

              <v-text-field
                label="New Email"
                variant="outlined"
                density="compact"
                rounded="lg"
                prepend-inner-icon="mdi-email-plus"
                v-model="emailForm.newEmail"
                :error-messages="
                  v$.emailForm.newEmail.$errors.map((e) => e.$message)
                "
                class="mb-3"
                hide-details="auto"
              ></v-text-field>

              <v-text-field
                label="Confirm Password"
                variant="outlined"
                density="compact"
                rounded="lg"
                :type="confirm_password_visible ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-check"
                v-model="emailForm.confirmPassword"
                :append-inner-icon="
                  confirm_password_visible ? 'mdi-eye' : 'mdi-eye-off'
                "
                @click:append-inner="
                  confirm_password_visible = !confirm_password_visible
                "
                :error-messages="
                  v$.emailForm.confirmPassword.$errors.map((e) => e.$message)
                "
                class="mb-4"
                hide-details="auto"
              ></v-text-field>

              <div class="d-flex justify-end mt-auto">
                <v-btn
                  color="info"
                  variant="elevated"
                  rounded="lg"
                  prepend-icon="mdi-email-check"
                  @click="showUpdateEmailDialog()"
                  :loading="emailForm.processing"
                  :disabled="
                    !emailForm.newEmail ||
                    emailForm.newEmail === $page.props.auth.user.email ||
                    !emailForm.confirmPassword
                  "
                >
                  Update Email
                </v-btn>
              </div>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row> -->

    <!-- Security Tips Section -->
    <v-row class="mt-6">
      <v-col cols="12">
        <v-card class="security-tips-card" elevation="1" rounded="lg">
          <v-card-text>
            <div class="d-flex align-center mb-3">
              <v-icon size="24" color="starbucks-green" class="mr-3"
                >mdi-shield-check</v-icon
              >
              <h4 class="text-h6 font-weight-semibold mb-0">Security Tips</h4>
            </div>
            <v-row>
              <v-col cols="12" md="3">
                <div class="d-flex align-start">
                  <v-icon size="20" color="success" class="mr-2"
                    >mdi-check-circle</v-icon
                  >
                  <span class="text-body-2">Minimum 8 characters long</span>
                </div>
              </v-col>
              <v-col cols="12" md="3">
                <div class="d-flex align-start">
                  <v-icon size="20" color="success" class="mr-2"
                    >mdi-check-circle</v-icon
                  >
                  <span class="text-body-2">At least one uppercase letter</span>
                </div>
              </v-col>
              <v-col cols="12" md="3">
                <div class="d-flex align-start">
                  <v-icon size="20" color="success" class="mr-2"
                    >mdi-check-circle</v-icon
                  >
                  <span class="text-body-2">At least one number</span>
                </div>
              </v-col>
              <v-col cols="12" md="3">
                <div class="d-flex align-start">
                  <v-icon size="20" color="success" class="mr-2"
                    >mdi-check-circle</v-icon
                  >
                  <span class="text-body-2"
                    >At least one special character (!@#$%^&*_-)</span
                  >
                </div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Username Update Confirmation Dialog -->
    <v-dialog v-model="updateUsernameDialog" width="500" persistent>
      <v-card class="confirmation-dialog" rounded="lg">
        <v-card-title class="d-flex align-center pb-2">
          <div class="icon-container warning-light mr-4">
            <v-icon size="28" color="warning">mdi-alert-circle</v-icon>
          </div>
          <span class="text-h5 font-weight-semibold"
            >Confirm Username Change</span
          >
        </v-card-title>
        <v-card-text class="pt-4">
          <p class="text-body-1 mb-3">
            Are you sure you want to update your username to
            <strong>"{{ usernameForm.username }}"</strong>?
          </p>
          <v-alert
            type="warning"
            variant="tonal"
            class="mb-3"
            density="compact"
            rounded="lg"
          >
            <div class="d-flex align-center">
              <span class="text-caption"
                >You will need to use your new username the next time you log
                in.</span
              >
            </div>
          </v-alert>
        </v-card-text>

        <v-card-actions class="pb-4 px-4">
          <v-spacer></v-spacer>
          <v-btn
            rounded="lg"
            @click="closeUpdateUsernameDialog()"
            class="mr-2"
            min-width="120"
            color="grey-darken-4"
            variant="outlined"
          >
            Cancel
          </v-btn>
          <v-btn
            color="starbucks-green"
            variant="elevated"
            rounded="lg"
            min-width="120"
            @click="updateUsername()"
            :loading="usernameForm.processing"
          >
            Confirm Update
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Password Update Confirmation Dialog -->
    <v-dialog v-model="changePasswordDialog" width="500" persistent>
      <v-card class="confirmation-dialog" rounded="lg">
        <v-card-title class="d-flex align-center pb-2">
          <div class="icon-container warning-light mr-4">
            <v-icon size="28" color="warning">mdi-alert-circle</v-icon>
          </div>
          <span class="text-h5 font-weight-semibold"
            >Confirm Password Change</span
          >
        </v-card-title>
        <v-card-text class="pt-4">
          <p class="text-body-1 mb-3">
            Are you sure you want to update your password?
          </p>
          <v-alert
            type="warning"
            variant="tonal"
            class="mb-3"
            density="compact"
          >
            <div class="d-flex align-center">
              <span class="text-caption"
                >Make sure you remember your new password as you will need it to
                log in next time.</span
              >
            </div>
          </v-alert>
        </v-card-text>
        <v-card-actions class="pb-4 px-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="outlined"
            rounded="lg"
            @click="closeDialog()"
            class="mr-2"
            min-width="120"
          >
            Cancel
          </v-btn>
          <v-btn
            min-width="120"
            color="warning"
            variant="elevated"
            rounded="lg"
            @click="updatePassword()"
            :loading="passwordForm.processing"
          >
            Confirm Change
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Email Update Confirmation Dialog -->
    <v-dialog v-model="updateEmailDialog" width="500" persistent>
      <v-card class="confirmation-dialog" rounded="lg">
        <v-card-title class="d-flex align-center pb-2">
          <div class="icon-container info-light mr-4">
            <v-icon size="28" color="info">mdi-alert-circle</v-icon>
          </div>
          <span class="text-h5 font-weight-semibold">Confirm Email Change</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <p class="text-body-1 mb-3">
            Are you sure you want to update your email to
            <strong>"{{ emailForm.newEmail }}"</strong>?
          </p>
          <v-alert
            type="info"
            variant="tonal"
            class="mb-3"
            density="compact"
            rounded="lg"
          >
            <div class="d-flex align-center">
              <span class="text-caption"
                >A verification email will be sent to your new email address.
                You'll need to verify it before the change takes effect.</span
              >
            </div>
          </v-alert>
        </v-card-text>

        <v-card-actions class="pb-4 px-4">
          <v-spacer></v-spacer>
          <v-btn
            rounded="lg"
            @click="closeUpdateEmailDialog()"
            class="mr-2"
            min-width="120"
            color="grey-darken-4"
            variant="outlined"
          >
            Cancel
          </v-btn>
          <v-btn
            color="info"
            variant="elevated"
            rounded="lg"
            min-width="120"
            @click="updateEmail()"
            :loading="emailForm.processing"
          >
            Confirm Update
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>

  <!-- OTP Dialog -->
  <v-dialog v-model="isOtpDialogVisible" max-width="500px" persistent>
    <v-form @submit.prevent="verifyOtp()">
      <v-card class="pa-4">
        <v-card-title class="text-h5 text-center">
          OTP Activation
        </v-card-title>

        <v-card-text class="text-center pt-4">
          <p class="mb-4">
            Please enter the 6-digit verification code sent to your phone
          </p>

          <OtpInput
            v-model:value="otpCode"
            class="mb-2"
            :error="!!otpError"
            :disabled="verifyingOtp"
          />

          <div class="d-flex align-center justify-center mb-4">
            <v-text-text class="text-caption">Didn't Receive OTP?</v-text-text>
            <v-btn
              v-if="countdown === 0"
              variant="text"
              color="primary"
              size="small"
              @click="sendOtp"
              :disabled="verifyingOtp"
            >
              Resend OTP
            </v-btn>
            <span v-else class="text-caption text-emphasis">
              Resend OTP in {{ countdown }}s
            </span>
          </div>

          <v-alert v-if="otpError" type="error" variant="tonal" class="mb-4">
            {{ otpError }}
          </v-alert>
        </v-card-text>

        <v-card-actions class="justify-end pb-4">
          <v-btn
            color="grey"
            variant="tonal"
            min-width="120"
            rounded="xl"
            :disabled="verifyingOtp"
            @click="isOtpDialogVisible = false"
          >
            Cancel
          </v-btn>

          <v-btn
            variant="tonal"
            min-width="120"
            color="starbucks-green"
            rounded="xl"
            type="submit"
            :loading="verifyingOtp"
          >
            Verify
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-form>
  </v-dialog>

  <!-- <pre>{{ $page.props.auth.user }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import ButtonUpdate from "@/components/ButtonUpdate.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import OtpInput from "@/components/OtpInput.vue";
import { router, useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, sameAs, helpers } from "@vuelidate/validators";
import {
  sanitizeFormData,
  cleanUsername,
  cleanEmail,
  cleanPassword,
} from "@/helpers/InputCleansing";

// Custom password validator
const passwordValidator = helpers.withMessage(
  "Password must contain at least one uppercase letter, one number, and one special character",
  (value) => {
    const hasUpperCase = /[A-Z]/.test(value);
    const hasNumber = /[0-9]/.test(value);
    const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>_-]/.test(value);
    return hasUpperCase && hasNumber && hasSpecialChar;
  }
);

export default {
  layout: SidebarLayout,
  components: {
    ButtonUpdate,
    ButtonSuccess,
    ButtonMuted,
    OtpInput,
  },
  props: {
    employee: Object,
  },
  data() {
    return {
      v$: useVuelidate(),
      changePasswordDialog: false,
      updateUsernameDialog: false,
      current_password_visible: false,
      new_password_visible: false,
      confirm_new_password_visible: false,
      confirm_password_visible: false,
      updateEmailDialog: false,
      usernameForm: useForm({
        username: this.$page.props.auth.user.username,
      }),

      passwordForm: useForm({
        currentPassword: "",
        newPassword: "",
        confirmNewPassword: "",
      }),
      emailForm: useForm({
        currentEmail: this.employee.personal_information.email,
        newEmail: "",
        confirmPassword: "",
      }),

      isOtpDialogVisible: false,

      isTwoFactorEnabled:
        this.$page.props.auth.user.is_two_factor_enabled === 1, // Track OTP switch state
      otpCode: "", // For OTP input
      otpError: "", // For OTP error messages
      verifyingOtp: false, // For loading state
      countdown: 0, // Countdown timer for OTP resend
      countdownTimer: null, // Timer reference

      changingTwoFactor: false,
      changingPassword: false,
    };
  },

  validations() {
    return {
      usernameForm: {
        username: { required },
      },
      passwordForm: {
        currentPassword: { required },
        newPassword: {
          required,
          complexity: passwordValidator,
          minLength: helpers.withMessage(
            "Password must be at least 8 characters long",
            (value) => value.length >= 8
          ),
        },
        confirmNewPassword: {
          required,
          sameAs: helpers.withMessage(
            "Your passwords do not match.",
            (value) => value === this.passwordForm.newPassword
          ),
        },
      },
      emailForm: {
        newEmail: {
          required,
          email: (value) =>
            /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i.test(value),
        },
        confirmPassword: { required },
      },
    };
  },

  methods: {
    sendOtpForTwoFactor() {
      this.$inertia.post(
        route("my-account.send-two-factor-otp"),
        {}, // no data to send
        {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.isOtpDialogVisible = true;
            this.changingTwoFactor = true;
            this.showToast("OTP sent to your email", "success");
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(errorMessages, "error");
            this.isTwoFactorEnabled = false;
          },
        }
      );
    },

    startCountdown() {
      this.countdown = 60; // Set initial countdown time (60 seconds)
      clearInterval(this.countdownTimer);
      this.countdownTimer = setInterval(() => {
        if (this.countdown > 0) {
          this.countdown--;
        } else {
          clearInterval(this.countdownTimer);
        }
      }, 1000);
    },

    cancelOtpActivation() {
      this.otpDialogForOtpActivation = false;
      this.isTwoFactorEnabled = false;
      this.otpCode = "";
      this.otpError = "";
      clearInterval(this.countdownTimer);
      this.countdown = 0;
    },

    showProcceedDialog() {
      this.v$.passwordForm.$validate();
      if (!this.v$.passwordForm.$error) {
        this.changePasswordDialog = true;
      }
    },

    closeDialog() {
      this.changePasswordDialog = false;
    },

    updateUsername() {
      this.v$.usernameForm.$validate();
      if (!this.v$.usernameForm.$error) {
        // Sanitize the username before submission
        const sanitizedData = sanitizeFormData(
          { username: this.usernameForm.username },
          { username: { type: "username" } }
        );
        this.usernameForm.username = sanitizedData.username;

        if (!this.v$.usernameForm.$invalid) {
          this.usernameForm.post(route("my-account.change-username"), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
              this.showToast("Username changed successfully", "success");
              this.updateUsernameDialog = false;
              this.usernameForm.reset();
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
              this.updateUsernameDialog = false;
            },
          });
        }
      }
    },

    updatePassword() {
      this.v$.passwordForm.$validate();
      if (!this.v$.passwordForm.$error) {
        // Sanitize password data before submission
        const sanitizedData = sanitizeFormData(
          {
            currentPassword: this.passwordForm.currentPassword,
            newPassword: this.passwordForm.newPassword,
            confirmNewPassword: this.passwordForm.confirmNewPassword,
          },
          {
            currentPassword: { type: "password" },
            newPassword: { type: "password" },
            confirmNewPassword: { type: "password" },
          }
        );

        Object.assign(this.passwordForm, sanitizedData);

        if (!this.v$.passwordForm.$invalid) {
          this.passwordForm.post(route("my-account.change-password"), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
              this.changePasswordDialog = false;
              this.isOtpDialogVisible = true;
              this.changingPassword = true;
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
              //   this.changePasswordDialog = false;
            },
          });
        }
      }
    },

    verifyOtp() {
      if (!this.otpCode || this.otpCode.length !== 6) {
        this.otpError = "Please enter a valid 6-digit code";
        return;
      }

      this.verifyingOtp = true;
      this.otpError = null;

      if (this.changingPassword) {
        const otpForm = useForm({
          currentPassword: this.passwordForm.currentPassword,
          newPassword: this.passwordForm.newPassword,
          confirmNewPassword: this.passwordForm.confirmNewPassword,
          otp: this.otpCode,
        });

        otpForm.post(route("my-account.updatePassword"), {
          onSuccess: () => {
            this.otpDialog = false;
            this.showToast(
              "You have successfully updated your password. Please login again using you new password",
              "success"
            );
          },

          onError: (errors) => {
            // Combine all error messages into one string
            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(`${errorMessages}`, "error");
            this.isOtpDialogVisible = false;
            this.passwordForm.currentPassword = null;
            this.passwordForm.newPassword = null;
            this.passwordForm.confirmNewPassword = null;
            this.passwordForm.reset();
          },
        });
      } else if (this.changingTwoFactor) {
        this.$inertia.post(
          route("my-account-enable-two-factor"),
          {
            otp: this.otpCode,
          },
          {
            onSuccess: () => {
              this.isOtpDialogVisible = false;
              this.showToast(
                "You have successfully enabled two factor authentication.",
                "success"
              );
            },

            onError: (errors) => {
              // Combine all error messages into one string
              const errorMessages = Object.values(errors).flat().join(" ");

              this.showToast(`${errorMessages}`, "error");
              this.isOtpDialogVisible = false;
            },
          }
        );
      }
    },

    showUpdateUsernameDialog() {
      this.v$.usernameForm.$validate();
      if (!this.v$.usernameForm.$error) {
        this.updateUsernameDialog = true;
      }
    },

    closeUpdateUsernameDialog() {
      this.updateUsernameDialog = false;
    },

    // onChangeTwoFactorAuthentication
    onChangeTwoFactorAuthentication() {},

    showUpdateEmailDialog() {
      this.v$.emailForm.$validate();
      if (!this.v$.emailForm.$error) {
        this.updateEmailDialog = true;
      }
    },

    closeUpdateEmailDialog() {
      this.updateEmailDialog = false;
    },

    updateEmail() {
      this.v$.emailForm.$validate();
      if (!this.v$.emailForm.$error) {
        // Sanitize email data before submission
        const sanitizedData = sanitizeFormData(
          {
            newEmail: this.emailForm.newEmail,
            confirmPassword: this.emailForm.confirmPassword,
          },
          {
            newEmail: { type: "email" },
            confirmPassword: { type: "password" },
          }
        );

        this.emailForm.newEmail = sanitizedData.newEmail;
        this.emailForm.confirmPassword = sanitizedData.confirmPassword;

        // Here you would typically make an API call to update the email
        console.log("Updating email to:", this.emailForm.newEmail);
        this.updateEmailDialog = false;
        this.emailForm.reset();
        this.emailForm.currentEmail = this.$page.props.auth.user.email;
      }
    },
  },
};
</script>

<style scoped>
.account-credentials-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px;
}

.page-header {
  border-bottom: 2px solid #e0e0e0;
  padding-bottom: 24px;
}

.credential-card {
  transition: all 0.3s ease;
  border: 1px solid #e0e0e0;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.credential-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
}

.card-header {
  padding: 24px 24px 0 24px;
  flex-shrink: 0;
}

.icon-container {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.primary-light {
  background-color: rgba(var(--v-theme-primary), 0.1);
}

.warning-light {
  background-color: rgba(var(--v-theme-warning), 0.1);
}

.info-light {
  background-color: rgba(var(--v-theme-info), 0.1);
}

.security-tips-card {
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  border: 1px solid #dee2e6;
}

.confirmation-dialog {
  border-radius: 16px !important;
}

.confirmation-dialog .v-card-title {
  border-bottom: 1px solid #e0e0e0;
  padding-bottom: 16px;
}

/* Responsive adjustments */
@media (max-width: 960px) {
  .account-credentials-container {
    padding: 16px;
  }

  .card-header {
    padding: 16px 16px 0 16px;
  }
}

@media (max-width: 600px) {
  .page-header h2 {
    font-size: 1.5rem !important;
  }

  .icon-container {
    width: 40px;
    height: 40px;
  }
}
</style>

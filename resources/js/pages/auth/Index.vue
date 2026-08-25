<template>
  <div
    class="bg min-h-screen bg-gray-100 text-gray-900 flex justify-center items-center px-4 py-8"
  >
    <div class="w-full max-w-screen-xl mx-auto">
      <div class="bg-white shadow-lg rounded-lg lg:rounded-xl overflow-hidden">
        <div class="flex flex-col lg:flex-row min-h-[600px]">
          <!-- Left Side - Login Form -->
          <div
            class="w-full lg:w-1/2 xl:w-5/12 p-6 sm:p-8 lg:p-12 flex flex-col justify-center"
          >
            <!-- Logo Section -->
            <div class="flex justify-center mb-6 lg:mb-8">
              <v-img
                :src="dmmmsulogo"
                alt="dmmmsu-logo"
                class="w-24 sm:w-32 lg:w-40"
                max-width="160"
              ></v-img>
            </div>

            <!-- Title Section -->
            <div class="text-center mb-6 lg:mb-8">
              <div
                class="v-card-title text-center d-md-none d-lg-none text-wrap"
              >
                Human Resource Management System
              </div>

              <div class="v-card-title text-center d-none d-md-block">
                Welcome to the PRIME-HRM PORTAL
              </div>
              <div class="v-card-subtitle text-center text-gray-1000 px-2">
                Sign in to your account
              </div>
            </div>

            <!-- Form Section -->
            <div class="w-full max-w-sm mx-auto">
              <v-form @submit.prevent="handleSubmit()">
                <div>
                  <v-text-field
                    variant="outlined"
                    density="comfortable"
                    label="Username"
                    rounded="xl"
                    v-model="login.username"
                    :error-messages="
                      v$.login.username.$errors.map((e) => e.$message)
                    "
                    :disabled="loggingIn"
                    class="w-full"
                  ></v-text-field>
                  <v-text-field
                    variant="outlined"
                    density="comfortable"
                    label="Password"
                    rounded="xl"
                    :type="!password_visible ? 'password' : 'text'"
                    :append-inner-icon="
                      !password_visible
                        ? 'mdi-eye-off-outline'
                        : 'mdi-eye-outline'
                    "
                    @click:append-inner="password_visible = !password_visible"
                    v-model="login.password"
                    :error-messages="
                      v$.login.password.$errors.map((e) => e.$message)
                    "
                    :disabled="loggingIn"
                    class="w-full"
                  ></v-text-field>

                  <v-checkbox
                    v-model="login.remember"
                    label="Remember me"
                    rounded="xl"
                    :disabled="loggingIn"
                    class="w-full"
                  ></v-checkbox>

                  <v-btn
                    class="w-full text-center tracking-wide font-semibold text-black-100 transition-all duration-300 ease-in-out flex items-center justify-center focus:shadow-outline focus:outline-none mb-4"
                    color="starbucks-green"
                    rounded="xl"
                    type="submit"
                    prepend-icon="mdi-login"
                    size="large"
                    :loading="loggingIn"
                    :disabled="loggingIn"
                  >
                    Sign In
                  </v-btn>

                  <v-btn
                    class="w-full text-center tracking-wide font-semibold text-black-100 transition-all duration-300 ease-in-out flex items-center justify-center focus:shadow-outline focus:outline-none"
                    color="#f5f5f5"
                    rounded="xl"
                    elevation="2"
                    size="large"
                    :loading="googleLoggingIn"
                    :disabled="googleLoggingIn"
                    @click="loginWithGoogle"
                  >
                    <!-- Google G Icon -->
                    <span class="mr-3 flex items-center">
                      <svg
                        version="1.1"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 48 48"
                        width="20"
                        height="20"
                      >
                        <path
                          fill="#EA4335"
                          d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
                        ></path>
                        <path
                          fill="#4285F4"
                          d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
                        ></path>
                        <path
                          fill="#FBBC05"
                          d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"
                        ></path>
                        <path
                          fill="#34A853"
                          d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
                        ></path>
                      </svg>
                    </span>

                    <!-- Button Text -->
                    <span class="text-gray-700 font-semibold"
                      >Sign in with Google</span
                    >
                  </v-btn>
                </div>
              </v-form>

              <!-- Forgot Password & Don't Have an Account -->
              <div class="mt-6 flex justify-between text-sm">
                <Link
                  :href="route('auth.registration')"
                  class="text-starbucks-green hover:text-blue-900 transition-colors duration-200"
                >
                  Create your account
                </Link>
                <Link
                  :href="route('forgot-password')"
                  class="text-starbucks-green hover:text-blue-900 transition-colors duration-200"
                >
                  Reset your password
                </Link>
              </div>
            </div>

            <!-- Copyright Footer -->
            <div class="text-center mt-8 text-sm text-starbucks-green">
              <p>&copy; 2022 DMMMSU USDO . All Rights Reserved.</p>
              <p class="text-xs">{{ $page.props.version }}</p>
            </div>
          </div>

          <!-- Right Side - Decorative Image with Bubble Background -->
          <div
            class="hidden lg:flex lg:w-1/2 xl:w-7/12 bg-gradient-to-br from-green-50 to-yellow-50 items-center justify-center p-8 relative overflow-hidden"
          >
            <!-- Logo Image -->
            <div class="d-flex flex-sm-column align-center justify-center">
              <v-img
                class="-mt-20"
                :src="hrms"
                width="600px"
                height="600px"
                alt="logo"
                contain
              ></v-img>
              <p class="-mt-20 text-h5 font-weight-bold bebas-neue">
                Human Resource Management System
              </p>
              <p class="mt-1 font-italic">
                <!-- TAG LINE HERE -->
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Full Screen Loading Overlay -->
  <div
    v-if="loggingIn"
    class="fixed inset-0 flex items-center justify-center bg-white bg-opacity-90 z-50 transition-opacity duration-300"
  >
    <div class="flex flex-col items-center justify-center">
      <DotLottieVue
        :src="loadingLottieSrc"
        :autoplay="true"
        :loop="true"
        class="w-64 h-64"
      />
      <p class="mt-6 text-lg font-semibold text-gray-700">Signing in...</p>
    </div>
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

  <!-- Privacy Notice Dialog -->
  <v-dialog v-model="isPrivacyNoticeDialogVisible" max-width="640">
    <v-card class="rounded-l">
      <v-card-title class="d-flex align-center gap-2">
        <v-icon color="starbucks-green" size="28">mdi-shield-lock</v-icon>
        <span class="text-h6 font-weight-bold">Privacy Notice</span>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text
        class="text-body-2 pt-4"
        style="max-height: 360px; overflow-y: auto"
      >
        <p class="mb-3">
          This <strong>Human Resource Management System (HRMS)</strong> is
          maintained by the
          <strong>University Systems Development Office</strong>
          for official human resource management purposes.
        </p>

        <p class="mb-3">
          By accessing this system, you acknowledge that your personal and
          sensitive personal information will be collected and processed in
          accordance with the
          <strong>Data Privacy Act of 2012 (RA 10173)</strong>
          and applicable institutional policies.
        </p>

        <p class="mb-3">
          Data collected are used solely for legitimate HR administration,
          compliance, and system security purposes. All information is handled
          with strict confidentiality and accessed only by authorized personnel.
        </p>

        <v-alert type="info" color="starbucks-green" dense text class="mt-4">
          By clicking <strong>Continue</strong>, you acknowledge that you have
          read and understood this Privacy Notice.
        </v-alert>
      </v-card-text>

      <v-card-actions class="px-6 pb-4 justify-end">
        <v-btn
          color="starbucks-green"
          class="px-6"
          elevation="2"
          @click="isPrivacyNoticeDialogVisible = false"
        >
          Continue
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import useVuelidate from "@vuelidate/core";
import { useForm } from "@inertiajs/vue3";
import { required } from "@vuelidate/validators";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import {
  cleanUsername,
  cleanPassword,
  sanitizeFormData,
} from "@/helpers/InputCleansing.js";
import logo from "../../../images/abstract-doodle.png";
import hrms from "../../../images/HRMS.svg";
import dmmmsulogo from "../../../images/dmmmsu-logo.png";
import cschrlogo from "../../../images/usmprimehrmlogo.png";

export default {
  components: {
    DotLottieVue,
  },

  props: {
    errors: Object,
  },

  data() {
    return {
      logo,
      hrms,
      dmmmsulogo,
      cschrlogo,

      password_visible: false,
      v$: useVuelidate(),
      login: useForm({
        username: null,
        password: null,
        remember: false,
        latitude: null,
        longitude: null,
        public_ip: null,
      }),
      submitted: false,
      loggingIn: false, // For login loading state

      // Lottie file path - can be a .lottie or .json file
      // For files in public folder, use: "/animations/your-file.json"
      // For remote URLs, use: "https://lottie.host/..."
      loadingLottieSrc: "/animations/Double_circular_Loader.json", // Using animation from public folder

      isOtpDialogVisible: false,
      otpCode: "", // For OTP input
      otpError: "", // For OTP error messages
      verifyingOtp: false, // For loading state

      flash_error: null,

      isPrivacyNoticeDialogVisible: true,
    };
  },

  validations() {
    return {
      login: {
        username: { required },
        password: { required },
      },
    };
  },

  mounted() {
    const error = this.$page.props.flash?.error || null;
    if (error) {
      this.flash_error = error;
    }

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition((position) => {
        this.login.latitude = position.coords.latitude;
        this.login.longitude = position.coords.longitude;
      });
    }

    fetch("https://api.ipify.org?format=json")
      .then((res) => res.json())
      .then((data) => {
        this.login.public_ip = data.ip;
      })
      .catch(() => {
        console.warn("Failed to fetch public IP");
      });
  },

  watch: {
    flash_error(newError) {
      if (newError) {
        this.showToast(newError, "error");
      }
    },

    // Real-time sanitization for username
    "login.username"(newValue) {
      if (newValue) {
        const sanitized = cleanUsername(newValue);
        if (sanitized !== newValue) {
          this.login.username = sanitized;
        }
      }
    },

    // Minimal real-time sanitization for password (only trim)
    "login.password"(newValue) {
      if (newValue && newValue !== newValue.trim()) {
        this.login.password = newValue.trim();
      }
    },
  },

  methods: {
    loginWithGoogle() {
      this.googleLoggingIn = true;
      window.location.href = route("auth.google.redirect");
    },

    handleSubmit() {
      this.v$.$validate();

      if (!this.v$.$error) {
        // Set loading state
        this.loggingIn = true;

        // Sanitize input data before submission
        const sanitizedData = this.sanitizeLoginData();

        // Create a new form with sanitized data
        const sanitizedForm = useForm(sanitizedData);

        sanitizedForm.post(route("auth.login"), {
          onError: (errors) => {
            // Reset loading state on error
            this.loggingIn = false;
            for (const error in errors) {
              this.showToast(`${errors[error]}`, "error");
            }
          },
          onSuccess: () => {
            // Keep loading state during redirect for smooth transition
            // Don't reset loggingIn here - let it stay true until navigation completes
            // The component will be replaced by the new page, so loggingIn will be reset automatically
            this.showToast("Welcome!");
          },
          onFinish: () => {
            // Only reset loading state on error cases
            // For successful logins, keep loading state true until the page actually changes
            // This prevents the login page from briefly flashing before redirect
            // The component will be replaced during navigation, so loggingIn doesn't need manual reset
          },
        });
      }
    },

    handleForgotPassword() {
      this.$inertia.visit(route("forgot-password"));
    },

    sanitizeLoginData() {
      return sanitizeFormData(this.login.data(), {
        username: { type: "username" },
        password: { type: "password" },
        remember: { type: "boolean" },
      });
    },
  },
};
</script>
<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap");

body {
  font-family: "Inter", sans-serif;
}

.bg {
  position: relative;
}

.bg::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(
    207deg,
    rgba(0, 98, 65, 1) 18%,
    rgba(70, 194, 165, 1) 62%,
    rgba(242, 199, 27, 1) 98%
  );
  opacity: 75%; /* control transparency */
  z-index: 0;
}

.bg > * {
  position: relative;
  z-index: 1; /* keep text/images above overlay */
}

.bebas-neue {
  font-family: "Bebas Neue", cursive;
  font-weight: 400;
}
</style>

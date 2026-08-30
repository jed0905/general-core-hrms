```vue
<template>
  <div
    class="auth-page"
    :style="{
      '--brand': primaryColor,
      '--brand-light': secondaryColor,
    }"
  >
    <!-- ========================================================= -->
    <!-- BACKGROUND -->
    <!-- ========================================================= -->

    <div class="background-decoration background-decoration-1"></div>
    <div class="background-decoration background-decoration-2"></div>

    <!-- ========================================================= -->
    <!-- MAIN -->
    <!-- ========================================================= -->

    <main class="auth-container">
      <!-- ===================================================== -->
      <!-- BRAND PANEL -->
      <!-- ===================================================== -->

      <section class="brand-section">
        <div class="brand-content">
          <!-- Logo -->
          <div class="company-logo-container">
            <v-img
              v-if="companyLogo"
              :src="companyLogo"
              :alt="`${companyName} logo`"
              contain
              class="company-logo"
            />

            <div v-else class="logo-placeholder">
              <v-icon size="42" :color="primaryColor">
                mdi-office-building-outline
              </v-icon>
            </div>
          </div>

          <!-- Company -->
          <div class="company-name">
            {{ companyName }}
          </div>

          <!-- System -->
          <h1>
            {{ systemName }}
          </h1>

          <!-- Tagline -->
          <p v-if="tagline" class="tagline">
            {{ tagline }}
          </p>

          <!-- Feature Highlights -->
          <div class="feature-list">
            <div class="feature-item">
              <div class="feature-icon">
                <v-icon size="20"> mdi-account-group-outline </v-icon>
              </div>

              <div>
                <strong>People Management</strong>
                <span> Manage your workforce from one place. </span>
              </div>
            </div>

            <div class="feature-item">
              <div class="feature-icon">
                <v-icon size="20"> mdi-shield-check-outline </v-icon>
              </div>

              <div>
                <strong>Secure & Private</strong>
                <span> Your employee information stays protected. </span>
              </div>
            </div>

            <div class="feature-item">
              <div class="feature-icon">
                <v-icon size="20"> mdi-chart-line </v-icon>
              </div>

              <div>
                <strong>Workforce Insights</strong>
                <span> Make better decisions with HR data. </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Brand Footer -->
        <div class="brand-footer">
          <span>
            {{ companyName }}
          </span>

          <span class="brand-footer-dot"> • </span>

          <span>
            {{ currentYear }}
          </span>
        </div>
      </section>

      <!-- ===================================================== -->
      <!-- LOGIN SECTION -->
      <!-- ===================================================== -->

      <section class="login-section">
        <div class="login-content">
          <!-- Mobile Logo -->
          <div class="mobile-brand">
            <v-img
              v-if="companyLogo"
              :src="companyLogo"
              :alt="`${companyName} logo`"
              contain
              class="mobile-logo"
            />

            <div v-else class="mobile-logo-placeholder">
              <v-icon size="32" :color="primaryColor">
                mdi-office-building-outline
              </v-icon>
            </div>

            <div class="mobile-company-name">
              {{ companyName }}
            </div>
          </div>

          <!-- Header -->
          <div class="login-header">
            <div class="eyebrow">ACCOUNT ACCESS</div>

            <h2>Welcome back</h2>

            <p>Sign in to continue to your account.</p>
          </div>

          <!-- Login Form -->
          <v-form @submit.prevent="handleSubmit()" class="login-form">
            <!-- Username -->
            <div class="form-group">
              <label> Username </label>

              <v-text-field
                v-model="login.username"
                variant="outlined"
                density="comfortable"
                placeholder="Enter your username"
                prepend-inner-icon="mdi-account-outline"
                rounded="lg"
                :error-messages="
                  v$.login.username.$errors.map((e) => e.$message)
                "
                :disabled="loggingIn"
                hide-details="auto"
              />
            </div>

            <!-- Password -->
            <div class="form-group">
              <div class="password-label">
                <label> Password </label>

                <Link :href="route('forgot-password')" class="forgot-link">
                  Forgot password?
                </Link>
              </div>

              <v-text-field
                v-model="login.password"
                variant="outlined"
                density="comfortable"
                placeholder="Enter your password"
                prepend-inner-icon="mdi-lock-outline"
                rounded="lg"
                :type="password_visible ? 'text' : 'password'"
                :append-inner-icon="
                  password_visible ? 'mdi-eye-outline' : 'mdi-eye-off-outline'
                "
                @click:append-inner="password_visible = !password_visible"
                :error-messages="
                  v$.login.password.$errors.map((e) => e.$message)
                "
                :disabled="loggingIn"
                hide-details="auto"
              />
            </div>

            <!-- Remember -->
            <div class="remember-row">
              <v-checkbox
                v-model="login.remember"
                label="Keep me signed in"
                :disabled="loggingIn"
                hide-details
                density="compact"
              />
            </div>

            <!-- Sign In -->
            <button
              type="submit"
              class="login-button"
              :disabled="loggingIn"
              :style="{
                backgroundColor: primaryColor,
              }"
            >
              <span v-if="!loggingIn"> Sign in </span>

              <span v-else class="button-loading">
                <v-progress-circular
                  indeterminate
                  size="20"
                  width="2"
                  color="white"
                />

                Signing in...
              </span>

              <v-icon v-if="!loggingIn" size="20"> mdi-arrow-right </v-icon>
            </button>

            <!-- Divider -->
            <div v-if="showGoogleLogin" class="divider">
              <span></span>
              <small>OR</small>
              <span></span>
            </div>

            <!-- Google -->
            <button
              v-if="showGoogleLogin"
              type="button"
              class="google-button"
              :disabled="loggingIn || googleLoggingIn"
              @click="loginWithGoogle"
            >
              <svg width="20" height="20" viewBox="0 0 48 48">
                <path
                  fill="#EA4335"
                  d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
                />

                <path
                  fill="#4285F4"
                  d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
                />

                <path
                  fill="#FBBC05"
                  d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"
                />

                <path
                  fill="#34A853"
                  d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
                />
              </svg>

              <span> Continue with Google </span>
            </button>

            <!-- Registration -->
            <div v-if="showRegistration" class="register-section">
              <span> Don't have an account? </span>

              <Link :href="route('auth.registration')" class="register-link">
                Create an account
              </Link>
            </div>
          </v-form>

          <!-- Privacy / Footer -->
          <div class="login-footer">
            <button
              type="button"
              class="privacy-link"
              @click="isPrivacyNoticeDialogVisible = true"
            >
              <v-icon size="15"> mdi-shield-lock-outline </v-icon>

              Privacy & Security
            </button>

            <span>
              © {{ currentYear }}
              {{ companyName }}
            </span>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- ============================================================= -->
  <!-- LOADING OVERLAY -->
  <!-- ============================================================= -->

  <transition name="fade">
    <div v-if="loggingIn" class="loading-overlay">
      <div class="loading-box">
        <DotLottieVue
          :src="loadingLottieSrc"
          :autoplay="true"
          :loop="true"
          class="loading-animation"
        />

        <strong> Signing you in </strong>

        <span> Please wait... </span>
      </div>
    </div>
  </transition>

  <!-- ============================================================= -->
  <!-- OTP DIALOG -->
  <!-- ============================================================= -->

  <v-dialog v-model="isOtpDialogVisible" max-width="460" persistent>
    <v-card class="otp-card">
      <div class="otp-icon">
        <v-icon size="30" :color="primaryColor">
          mdi-shield-key-outline
        </v-icon>
      </div>

      <v-card-title class="text-center"> Verify your identity </v-card-title>

      <v-card-text>
        <p class="otp-description">
          Enter the 6-digit verification code sent to your registered phone
          number.
        </p>

        <OtpInput
          v-model:value="otpCode"
          class="otp-input"
          :error="!!otpError"
          :disabled="verifyingOtp"
        />

        <v-alert v-if="otpError" type="error" variant="tonal" class="mt-4">
          {{ otpError }}
        </v-alert>

        <div class="otp-resend">
          <span> Didn't receive the code? </span>

          <v-btn
            v-if="countdown === 0"
            variant="text"
            size="small"
            :color="primaryColor"
            :disabled="verifyingOtp"
            @click="sendOtp"
          >
            Resend
          </v-btn>

          <span v-else> Resend in {{ countdown }}s </span>
        </div>
      </v-card-text>

      <v-card-actions>
        <v-btn
          variant="text"
          @click="isOtpDialogVisible = false"
          :disabled="verifyingOtp"
        >
          Cancel
        </v-btn>

        <v-spacer />

        <v-btn
          :color="primaryColor"
          rounded="lg"
          variant="flat"
          :loading="verifyingOtp"
          @click="verifyOtp"
        >
          Verify
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- ============================================================= -->
  <!-- PRIVACY NOTICE -->
  <!-- ============================================================= -->

  <v-dialog v-model="isPrivacyNoticeDialogVisible" max-width="600">
    <v-card class="privacy-card">
      <div class="privacy-header">
        <div
          class="privacy-icon"
          :style="{
            backgroundColor: `${primaryColor}12`,
          }"
        >
          <v-icon :color="primaryColor" size="28">
            mdi-shield-lock-outline
          </v-icon>
        </div>

        <div>
          <div class="text-h6 font-weight-bold">Privacy & Security</div>

          <div class="text-caption text-grey">
            {{ companyName }}
          </div>
        </div>
      </div>

      <v-divider />

      <v-card-text class="privacy-content">
        <p>
          This
          <strong>{{ systemName }}</strong>
          is used by
          <strong>{{ companyName }}</strong>
          for legitimate human resource and administrative purposes.
        </p>

        <p>
          Personal and sensitive personal information may be collected and
          processed in accordance with applicable data protection laws and the
          organization's privacy policies.
        </p>

        <p>
          Information is accessed only by authorized personnel and is used for
          legitimate employment, HR administration, compliance, and security
          purposes.
        </p>

        <div class="privacy-notice">
          <v-icon :color="primaryColor" size="20">
            mdi-information-outline
          </v-icon>

          <span>
            By continuing, you acknowledge that you have read and understood
            this Privacy Notice.
          </span>
        </div>
      </v-card-text>

      <v-card-actions class="privacy-actions">
        <v-btn
          :color="primaryColor"
          variant="flat"
          rounded="lg"
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
import { useForm, Link } from "@inertiajs/vue3";
import { required } from "@vuelidate/validators";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

import {
  cleanUsername,
  cleanPassword,
  sanitizeFormData,
} from "@/helpers/InputCleansing.js";

export default {
  components: {
    DotLottieVue,
    Link,
  },

  props: {
    errors: {
      type: Object,
      default: () => ({}),
    },

    branding: {
      type: Object,

      default: () => ({
        company_name: "Your Company",

        system_name: "Human Resource Management System",

        welcome_message: "Welcome",

        tagline: "Empowering your people. Growing your organization.",

        logo: null,

        primary_color: "#2563EB",

        secondary_color: "#EFF6FF",

        show_google_login: true,

        show_registration: true,
      }),
    },
  },

  data() {
    return {
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

      loggingIn: false,

      googleLoggingIn: false,

      loadingLottieSrc: "/animations/Double_circular_Loader.json",

      /*
       * OTP
       */
      isOtpDialogVisible: false,

      otpCode: "",

      otpError: "",

      verifyingOtp: false,

      countdown: 0,

      flash_error: null,

      /*
       * Privacy
       */
      isPrivacyNoticeDialogVisible: true,
    };
  },

  computed: {
    companyName() {
      return this.branding.company_name || "Your Company";
    },

    systemName() {
      return this.branding.system_name || "Human Resource Management System";
    },

    tagline() {
      return this.branding.tagline || "";
    },

    companyLogo() {
      return this.branding.logo || null;
    },

    primaryColor() {
      return this.branding.primary_color || "#2563EB";
    },

    secondaryColor() {
      return this.branding.secondary_color || "#EFF6FF";
    },

    showGoogleLogin() {
      return this.branding.show_google_login !== false;
    },

    showRegistration() {
      return this.branding.show_registration !== false;
    },

    currentYear() {
      return new Date().getFullYear();
    },
  },

  validations() {
    return {
      login: {
        username: {
          required,
        },

        password: {
          required,
        },
      },
    };
  },

  mounted() {
    const error = this.$page.props.flash?.error || null;

    if (error) {
      this.flash_error = error;
    }

    /*
     * Capture location
     */
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition((position) => {
        this.login.latitude = position.coords.latitude;

        this.login.longitude = position.coords.longitude;
      });
    }

    /*
     * Capture public IP
     */
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

    "login.username"(newValue) {
      if (newValue) {
        const sanitized = cleanUsername(newValue);

        if (sanitized !== newValue) {
          this.login.username = sanitized;
        }
      }
    },

    "login.password"(newValue) {
      if (newValue && newValue !== newValue.trim()) {
        this.login.password = newValue.trim();
      }
    },
  },

  methods: {
    /*
     * =========================================================
     * GOOGLE LOGIN
     * =========================================================
     */

    loginWithGoogle() {
      this.googleLoggingIn = true;

      window.location.href = route("auth.google.redirect");
    },

    /*
     * =========================================================
     * LOGIN
     * =========================================================
     */

    handleSubmit() {
      this.v$.$validate();

      if (this.v$.$error) {
        return;
      }

      this.loggingIn = true;

      const sanitizedData = this.sanitizeLoginData();

      const sanitizedForm = useForm(sanitizedData);

      sanitizedForm.post(route("auth.login"), {
        onError: (errors) => {
          this.loggingIn = false;

          for (const error in errors) {
            this.showToast(`${errors[error]}`, "error");
          }
        },

        onSuccess: () => {
          this.showToast("Welcome!");
        },

        onFinish: () => {
          /*
           * Keep loading state during
           * successful Inertia navigation.
           */
        },
      });
    },

    /*
     * =========================================================
     * SANITIZE
     * =========================================================
     */

    sanitizeLoginData() {
      return sanitizeFormData(this.login.data(), {
        username: {
          type: "username",
        },

        password: {
          type: "password",
        },

        remember: {
          type: "boolean",
        },
      });
    },

    /*
     * =========================================================
     * TOAST
     * =========================================================
     */

    showToast(message, type = "success") {
      if (this.$toast) {
        this.$toast[type](message);
      } else {
        console[type === "error" ? "error" : "log"](message);
      }
    },

    /*
     * =========================================================
     * OTP
     * =========================================================
     *
     * Keep your existing OTP implementation here.
     */

    verifyOtp() {
      // Keep your existing implementation.
    },

    sendOtp() {
      // Keep your existing implementation.
    },
  },
};
</script>


<style scoped>
* {
  box-sizing: border-box;
}

/* ================================================================ */
/* PAGE */
/* ================================================================ */

.auth-page {
  min-height: 100vh;

  width: 100%;

  display: flex;

  align-items: center;

  justify-content: center;

  position: relative;

  overflow: hidden;

  background: #f7f8fa;

  font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

/* ================================================================ */
/* BACKGROUND */
/* ================================================================ */

.background-decoration {
  position: absolute;

  border-radius: 50%;

  pointer-events: none;
}

.background-decoration-1 {
  width: 650px;

  height: 650px;

  top: -400px;

  right: -200px;

  background: var(--brand);

  opacity: 0.055;
}

.background-decoration-2 {
  width: 500px;

  height: 500px;

  bottom: -350px;

  left: -200px;

  background: var(--brand);

  opacity: 0.04;
}

/* ================================================================ */
/* MAIN CONTAINER */
/* ================================================================ */

.auth-container {
  width: min(1180px, calc(100% - 48px));

  min-height: 720px;

  display: grid;

  grid-template-columns: 1fr 0.9fr;

  background: white;

  border-radius: 28px;

  overflow: hidden;

  box-shadow: 0 30px 80px rgba(15, 23, 42, 0.1);

  position: relative;

  z-index: 1;
}

/* ================================================================ */
/* BRAND SECTION */
/* ================================================================ */

.brand-section {
  position: relative;

  display: flex;

  flex-direction: column;

  justify-content: space-between;

  padding: 64px;

  overflow: hidden;

  background: linear-gradient(145deg, var(--brand-light), #ffffff);
}

.brand-content {
  position: relative;

  z-index: 2;

  max-width: 520px;
}

/* ================================================================ */
/* LOGO */
/* ================================================================ */

.company-logo-container {
  width: 92px;

  height: 92px;

  background: white;

  border-radius: 22px;

  padding: 14px;

  display: flex;

  align-items: center;

  justify-content: center;

  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);

  margin-bottom: 34px;
}

.company-logo {
  width: 100%;

  height: 100%;
}

.logo-placeholder {
  width: 64px;

  height: 64px;

  display: flex;

  align-items: center;

  justify-content: center;
}

/* ================================================================ */
/* BRAND TEXT */
/* ================================================================ */

.company-name {
  color: var(--brand);

  font-size: 14px;

  font-weight: 700;

  text-transform: uppercase;

  letter-spacing: 1.5px;

  margin-bottom: 14px;
}

.brand-content h1 {
  margin: 0;

  font-size: clamp(38px, 4vw, 58px);

  line-height: 1.05;

  letter-spacing: -2px;

  font-weight: 750;

  color: #111827;
}

.tagline {
  margin-top: 22px;

  max-width: 440px;

  font-size: 17px;

  line-height: 1.7;

  color: #64748b;
}

/* ================================================================ */
/* FEATURES */
/* ================================================================ */

.feature-list {
  margin-top: 52px;

  display: flex;

  flex-direction: column;

  gap: 22px;
}

.feature-item {
  display: flex;

  align-items: flex-start;

  gap: 14px;
}

.feature-icon {
  flex: 0 0 42px;

  width: 42px;

  height: 42px;

  border-radius: 12px;

  display: flex;

  align-items: center;

  justify-content: center;

  background: white;

  color: var(--brand);

  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.feature-item strong {
  display: block;

  font-size: 14px;

  color: #1e293b;

  margin-bottom: 3px;
}

.feature-item span {
  display: block;

  font-size: 13px;

  color: #64748b;
}

/* ================================================================ */
/* BRAND FOOTER */
/* ================================================================ */

.brand-footer {
  position: relative;

  z-index: 2;

  font-size: 12px;

  color: #94a3b8;
}

.brand-footer-dot {
  margin: 0 7px;
}

/* ================================================================ */
/* LOGIN SECTION */
/* ================================================================ */

.login-section {
  display: flex;

  align-items: center;

  justify-content: center;

  background: white;

  padding: 64px;
}

.login-content {
  width: 100%;

  max-width: 400px;
}

/* ================================================================ */
/* MOBILE BRAND */
/* ================================================================ */

.mobile-brand {
  display: none;
}

/* ================================================================ */
/* LOGIN HEADER */
/* ================================================================ */

.login-header {
  margin-bottom: 34px;
}

.eyebrow {
  font-size: 11px;

  font-weight: 700;

  letter-spacing: 1.5px;

  color: var(--brand);

  margin-bottom: 10px;
}

.login-header h2 {
  margin: 0;

  font-size: 34px;

  line-height: 1.2;

  letter-spacing: -1px;

  color: #111827;

  font-weight: 750;
}

.login-header p {
  margin: 9px 0 0;

  color: #64748b;

  font-size: 14px;
}

/* ================================================================ */
/* FORM */
/* ================================================================ */

.login-form {
  width: 100%;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;

  font-size: 13px;

  font-weight: 600;

  color: #334155;

  margin-bottom: 8px;
}

.password-label {
  display: flex;

  align-items: center;

  justify-content: space-between;

  margin-bottom: 8px;
}

.password-label label {
  margin-bottom: 0;
}

.forgot-link {
  font-size: 12px;

  color: var(--brand);

  text-decoration: none;

  font-weight: 600;
}

.remember-row {
  margin-top: -2px;

  margin-bottom: 18px;
}

/* ================================================================ */
/* LOGIN BUTTON */
/* ================================================================ */

.login-button {
  width: 100%;

  height: 52px;

  border: 0;

  border-radius: 12px;

  color: white;

  font-size: 14px;

  font-weight: 700;

  display: flex;

  align-items: center;

  justify-content: center;

  gap: 10px;

  cursor: pointer;

  transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

.login-button:hover:not(:disabled) {
  transform: translateY(-1px);

  box-shadow: 0 10px 24px color-mix(in srgb, var(--brand) 25%, transparent);
}

.login-button:disabled {
  cursor: not-allowed;

  opacity: 0.7;
}

.button-loading {
  display: flex;

  align-items: center;

  gap: 9px;
}

/* ================================================================ */
/* DIVIDER */
/* ================================================================ */

.divider {
  display: flex;

  align-items: center;

  gap: 12px;

  margin: 25px 0;
}

.divider span {
  height: 1px;

  background: #e2e8f0;

  flex: 1;
}

.divider small {
  color: #94a3b8;

  font-size: 10px;

  font-weight: 600;
}

/* ================================================================ */
/* GOOGLE */
/* ================================================================ */

.google-button {
  width: 100%;

  height: 50px;

  border: 1px solid #e2e8f0;

  border-radius: 12px;

  background: white;

  display: flex;

  align-items: center;

  justify-content: center;

  gap: 10px;

  color: #334155;

  font-size: 14px;

  font-weight: 600;

  cursor: pointer;

  transition: background 0.2s ease, border-color 0.2s ease;
}

.google-button:hover:not(:disabled) {
  background: #f8fafc;

  border-color: #cbd5e1;
}

.google-button:disabled {
  opacity: 0.6;

  cursor: not-allowed;
}

/* ================================================================ */
/* REGISTER */
/* ================================================================ */

.register-section {
  display: flex;

  justify-content: center;

  gap: 5px;

  margin-top: 28px;

  font-size: 13px;

  color: #64748b;
}

.register-link {
  color: var(--brand);

  font-weight: 700;

  text-decoration: none;
}

/* ================================================================ */
/* FOOTER */
/* ================================================================ */

.login-footer {
  display: flex;

  justify-content: space-between;

  align-items: center;

  margin-top: 46px;

  padding-top: 20px;

  border-top: 1px solid #f1f5f9;

  font-size: 11px;

  color: #94a3b8;
}

.privacy-link {
  border: 0;

  padding: 0;

  background: transparent;

  color: #64748b;

  display: flex;

  align-items: center;

  gap: 5px;

  font-size: 11px;

  cursor: pointer;
}

.privacy-link:hover {
  color: var(--brand);
}

/* ================================================================ */
/* LOADING */
/* ================================================================ */

.loading-overlay {
  position: fixed;

  inset: 0;

  z-index: 9999;

  display: flex;

  align-items: center;

  justify-content: center;

  background: rgba(255, 255, 255, 0.92);

  backdrop-filter: blur(8px);
}

.loading-box {
  display: flex;

  flex-direction: column;

  align-items: center;

  justify-content: center;
}

.loading-animation {
  width: 150px;

  height: 150px;
}

.loading-box strong {
  color: #1e293b;

  font-size: 16px;
}

.loading-box span {
  color: #94a3b8;

  font-size: 13px;

  margin-top: 5px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ================================================================ */
/* OTP */
/* ================================================================ */

.otp-card {
  border-radius: 20px !important;

  padding: 28px;
}

.otp-icon {
  width: 60px;

  height: 60px;

  margin: 0 auto 15px;

  border-radius: 18px;

  display: flex;

  align-items: center;

  justify-content: center;

  background: var(--brand-light);
}

.otp-description {
  text-align: center;

  color: #64748b;

  font-size: 14px;

  line-height: 1.6;
}

.otp-input {
  margin-top: 22px;
}

.otp-resend {
  display: flex;

  justify-content: center;

  align-items: center;

  gap: 5px;

  margin-top: 20px;

  color: #64748b;

  font-size: 12px;
}

/* ================================================================ */
/* PRIVACY */
/* ================================================================ */

.privacy-card {
  border-radius: 20px !important;

  overflow: hidden;
}

.privacy-header {
  padding: 24px;

  display: flex;

  align-items: center;

  gap: 15px;
}

.privacy-icon {
  width: 52px;

  height: 52px;

  border-radius: 15px;

  display: flex;

  align-items: center;

  justify-content: center;
}

.privacy-content {
  padding: 24px !important;

  color: #475569;

  font-size: 13px;

  line-height: 1.7;
}

.privacy-content p {
  margin-bottom: 16px;
}

.privacy-notice {
  display: flex;

  gap: 10px;

  padding: 14px;

  border-radius: 12px;

  background: #f8fafc;

  align-items: flex-start;

  font-size: 12px;
}

.privacy-actions {
  padding: 0 24px 24px;
}

/* ================================================================ */
/* RESPONSIVE */
/* ================================================================ */

@media (max-width: 1000px) {
  .auth-container {
    grid-template-columns: 0.85fr 1fr;
  }

  .brand-section,
  .login-section {
    padding: 48px;
  }
}

@media (max-width: 800px) {
  .auth-page {
    padding: 20px;

    align-items: flex-start;

    overflow-y: auto;
  }

  .auth-container {
    width: 100%;

    min-height: auto;

    display: block;

    border-radius: 20px;
  }

  .brand-section {
    display: none;
  }

  .login-section {
    padding: 42px 28px;
  }

  .mobile-brand {
    display: flex;

    flex-direction: column;

    align-items: center;

    margin-bottom: 42px;
  }

  .mobile-logo {
    width: 80px;

    height: 80px;
  }

  .mobile-logo-placeholder {
    width: 70px;

    height: 70px;

    border-radius: 18px;

    background: var(--brand-light);

    display: flex;

    align-items: center;

    justify-content: center;
  }

  .mobile-company-name {
    margin-top: 12px;

    color: var(--brand);

    font-size: 12px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 1px;

    text-align: center;
  }

  .login-header {
    text-align: center;
  }

  .login-footer {
    margin-top: 35px;
  }
}

@media (max-width: 480px) {
  .auth-page {
    padding: 0;

    background: white;
  }

  .auth-container {
    border-radius: 0;

    box-shadow: none;

    min-height: 100vh;
  }

  .login-section {
    padding: 35px 22px;
  }

  .login-header h2 {
    font-size: 30px;
  }

  .register-section {
    flex-direction: column;

    align-items: center;
  }

  .login-footer {
    flex-direction: column;

    gap: 12px;
  }
}
</style>
```

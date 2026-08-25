<template>
  <div
    class="bg min-h-screen bg-gray-100 text-gray-900 flex justify-center items-center px-4 py-8"
  >
    <div class="w-full max-w-screen-xl mx-auto">
      <div class="bg-white shadow-lg rounded-lg lg:rounded-xl overflow-hidden">
        <div class="flex flex-col lg:flex-row min-h-[600px]">
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
                class="v-card-title text-center text-lg sm:text-xl md:text-2xl lg:text-3xl xl:text-4xl font-bold text-gray-800 mb-2 leading-tight text-wrap px-2"
              >
                Human Resource Management System
              </div>
              <div
                class="v-card-subtitle text-center text-sm sm:text-base text-gray-600 px-2"
              >
                Sign in to your account
              </div>
            </div>

            <!-- Form Section -->
            <div class="w-full mx-auto">
              <v-form @submit.prevent="submitForm()">
                <v-text-field
                  variant="outlined"
                  density="compact"
                  label="Employee Number"
                  type="text"
                  @input="onEmployeeNumberInput"
                  @keypress="onEmployeeNumberKeypress"
                  rounded="lg"
                  v-model="v$.form.employee_number.$model"
                  :error-messages="
                    v$.form.employee_number.$errors.map((e) => e.$message)
                  "
                  class="w-full"
                ></v-text-field>

                <div>
                  <v-text-field
                    variant="outlined"
                    density="compact"
                    label="Email Address"
                    type="email"
                    rounded="lg"
                    v-model="v$.form.email.$model"
                    :error-messages="
                      v$.form.email.$errors.map((e) => e.$message)
                    "
                    class="w-full"
                  ></v-text-field>
                  <!-- <div class="d-flex align-center justify-end">
                    <v-btn
                      v-if="showVerifyButton"
                      size="small"
                      color="primary"
                      variant="text"
                      @click="verifyEmail"
                      :loading="verifyingEmail"
                    >
                      {{
                        isEmailVerified ? "Email Verified ✓" : "Verify Email"
                      }}
                    </v-btn>
                  </div> -->
                </div>

                <v-text-field
                  variant="outlined"
                  density="compact"
                  label="Username"
                  rounded="lg"
                  v-model="v$.form.username.$model"
                  :error-messages="
                    v$.form.username.$errors.map((e) => e.$message)
                  "
                  class="w-full"
                ></v-text-field>

                <v-text-field
                  variant="outlined"
                  density="compact"
                  label="Password"
                  rounded="lg"
                  :type="!password_visible ? 'password' : 'text'"
                  :append-inner-icon="
                    !password_visible
                      ? 'mdi-eye-off-outline'
                      : 'mdi-eye-outline'
                  "
                  @click:append-inner="password_visible = !password_visible"
                  v-model="v$.form.password.$model"
                  :error-messages="
                    v$.form.password.$errors.map((e) => e.$message)
                  "
                  class="w-full mt-3"
                ></v-text-field>

                <v-text-field
                  variant="outlined"
                  density="compact"
                  label="Confirm Password"
                  rounded="lg"
                  :type="!password_visible ? 'password' : 'text'"
                  :append-inner-icon="
                    !password_visible
                      ? 'mdi-eye-off-outline'
                      : 'mdi-eye-outline'
                  "
                  @click:append-inner="password_visible = !password_visible"
                  v-model="v$.form.password_confirmation.$model"
                  :error-messages="
                    v$.form.password_confirmation.$errors.map((e) => e.$message)
                  "
                  class="w-full"
                ></v-text-field>

                <!-- Terms & Conditions and Privacy Policy Checkbox -->
                <v-checkbox
                  v-model="v$.terms.$model"
                  density="compact"
                  color="primary"
                  hide-details
                  class="mt-2"
                >
                  <template v-slot:label>
                    <span :class="{ 'text-error': v$.terms.$error }">
                      By checking the box, I agree to HRMS's
                      <a
                        :href="route('terms-and-services')"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary text-decoration-underline"
                      >
                        Terms of Service
                      </a>
                      and
                      <a
                        :href="route('privacy-policies')"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary text-decoration-underline"
                      >
                        Privacy Policy </a
                      >.
                    </span>
                  </template>
                </v-checkbox>

                <v-btn
                  class="w-full mt-3 py-3 text-center tracking-wide font-semibold text-gray-100 transition-all duration-300 ease-in-out flex items-center justify-center focus:shadow-outline focus:outline-none"
                  color="starbucks-green"
                  rounded="xl"
                  prepend-icon="mdi-account-plus"
                  size="large"
                  type="submit"
                >
                  Register
                </v-btn>
              </v-form>

              <!-- Forgot Password & Don't Have an Account -->
              <div class="mt-6 flex justify-between text-sm">
                <Link
                  :href="route('forgot-password')"
                  class="text-sm text-starbucks-green hover:text-blue-900 transition-colors duration-200"
                >
                  Reset your password
                </Link>
                <Link
                  :href="route('/')"
                  class="text-sm text-starbucks-green hover:text-blue-900 transition-colors duration-200"
                >
                  Click here to Log in
                </Link>
              </div>
              <!-- Copyright Footer -->
              <div class="text-center mt-8 text-sm text-starbucks-green">
                <p>&copy; 2022 DMMMSU USDO . All Rights Reserved.</p>
                <p class="text-xs">v{{ $page.props.version }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- OTP Dialog -->
  <v-dialog v-model="otpDialog" max-width="500px" persistent>
    <v-card class="pa-4">
      <v-form @submit.prevent="verifyOtp()">
        <v-card-title class="text-h5 text-center">
          OTP Verification
        </v-card-title>

        <v-card-text class="text-center pt-4">
          <p class="mb-4">
            Please enter the 6-digit verification code sent to your email
          </p>

          <!-- OTP Input -->
          <OtpInput v-model:value="otpCode" class="mb-2" :error="!!otpError" />

          <!-- Error Alert -->
          <v-alert v-if="otpError" type="error" variant="tonal" class="mb-4">
            {{ otpError }}
          </v-alert>

          <!-- Timer / Resend OTP -->
          <div class="mt-2">
            <v-btn
              v-if="!isTimerRunning"
              variant="text"
              color="primary"
              @click="resendOtp"
            >
              Resend OTP
            </v-btn>

            <span v-else class="text-grey text-caption">
              Resend available in {{ timer }}s
            </span>
          </div>
        </v-card-text>

        <v-card-actions class="justify-end pb-4">
          <v-btn
            color="grey"
            variant="tonal"
            min-width="120"
            rounded="xl"
            @click="otpDialog = false"
          >
            Cancel
          </v-btn>

          <v-btn
            variant="tonal"
            min-width="120"
            color="starbucks-green"
            rounded="xl"
            type="submit"
          >
            Verify
          </v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script>
import useVuelidate from "@vuelidate/core";
import { useForm } from "@inertiajs/vue3";
import {
  required,
  numeric,
  minLength,
  maxLength,
  sameAs,
  helpers,
} from "@vuelidate/validators";
import {
  sanitizeFormData,
  getUserFormCleaningRules,
  cleanUsername,
  cleanPassword,
  validateCleanedInput,
} from "@/helpers/InputCleansing.js";
import OtpInput from "@/components/OtpInput.vue";
import logo from "../../../images/abstract-doodle.png";
import dmmmsulogo from "../../../images/dmmmsu-logo.png";
import cschrlogo from "../../../images/usmprimehrmlogo.png";
import { passwordRegex } from "@/helpers/DataHelper.js";
import hrms from "../../../images/HRMS.svg";

export default {
  components: {
    OtpInput,
  },
  props: {
    errors: Object,
  },

  data() {
    return {
      logo,
      dmmmsulogo,
      cschrlogo,
      hrms,
      password_visible: false,
      otpDialog: false,
      v$: useVuelidate(),
      form: useForm({
        employee_number: null,
        username: null,
        email: null,
        password: null,
        password_confirmation: null,
      }),

      terms: null,
      submitted: false,
      // Email verification related data
      isEmailVerified: false,
      verifyingEmail: false,
      otpDialog: false,
      otpCode: "",
      otpError: null,

      timer: 60,
      timerInterval: null,
      isTimerRunning: false,

    };
  },
  validations() {
    return {
      form: {
        employee_number: {
          required,
          numeric,
          exactLength: {
            $validator: (value) => !value || value.length === 6,
            $message: "Employee ID must be exactly 6 digits",
          },
        },
        email: {
          required,
          email: {
            $validator: (value) =>
              !value || /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i.test(value),
            $message: "Please enter a valid email address",
          },
        },
        username: {
          required,
          minLength: minLength(5),
          maxLength: maxLength(15),
        },
        password: {
          required: helpers.withMessage("Password is required", required),
          passwordRules: helpers.withMessage(
            "Password must be at least 8 characters, include one uppercase, one number, and one special character.",
            helpers.regex(passwordRegex)
          ),
        },
        password_confirmation: {
          required,
          sameAs: helpers.withMessage(
            "Passwords do not match",
            sameAs(this.form.password)
          ),
        },
      },

      terms: {
        accepted: helpers.withMessage(
          "You must accept the Terms of Service and Privacy Policy.",
          sameAs(true) // ✅ checkbox must be checked
        ),
      },
    };
  },

  computed: {
    showVerifyButton() {
      return (
        this.form.email &&
        /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i.test(this.form.email) &&
        !this.isEmailVerified
      );
    },
  },

  watch: {
    // Real-time sanitization for employee number (numbers only)
    "form.employee_number": {
      immediate: true,
      handler(newValue) {
        if (newValue) {
          // Remove any non-numeric characters
          const sanitized = String(newValue).replace(/\D/g, "");
          if (sanitized !== String(newValue)) {
            this.form.employee_number = sanitized;
          }
        }
      },
    },

    // Real-time sanitization for username
    "form.username"(newValue) {
      if (newValue) {
        const sanitized = cleanUsername(newValue);
        if (sanitized !== newValue) {
          this.form.username = sanitized;
        }
      }
    },

    // Minimal real-time sanitization for password (only trim)
    "form.password"(newValue) {
      if (newValue && newValue !== newValue.trim()) {
        this.form.password = newValue.trim();
      }
    },

    "form.password_confirmation"(newValue) {
      if (newValue && newValue !== newValue.trim()) {
        this.form.password_confirmation = newValue.trim();
      }
    },

    otpDialog(value){
      if(value){
        this.startTimer();
      } else{
        this.stopTimer();
      }
    },
  },

  methods: {
    onEmployeeNumberKeypress(event) {
      // Only allow number keys (0-9)
      const charCode = event.which ? event.which : event.keyCode;
      if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        event.preventDefault();
      }
    },

    onEmployeeNumberInput(event) {
      // Remove any non-numeric characters
      let value = event.target.value.replace(/\D/g, "");

      // Limit to exactly 6 digits
      value = value.slice(0, 6);

      // Update the model
      this.form.employee_number = value;
    },

    verifyOtp() {
      console.log(this.otpCode);
      if (!this.otpCode || this.otpCode.length !== 6) {
        this.otpError = "Please enter a valid 6-digit code";
        return;
      }

      this.verifyingOtp = true;
      this.otpError = null;

      const otpForm = useForm({
        employee_number: this.form.employee_number,
        email: this.form.email,
        username: this.form.username,
        password: this.form.password,
        password_confirmation: this.form.password_confirmation,
        otp: this.otpCode,
      });

      otpForm.post(route("auth.verifyOtp"), {
        onSuccess: () => {
          this.isEmailVerified = true;
          this.otpDialog = false;
          this.showToast("You have successfully registered.", "success");
        },
        onError: (errors) => {
          // Combine all error messages into one string
          const errorMessages = Object.values(errors).flat().join(" ");

          this.showToast(`${errorMessages}`, "error");
        },
      });
    },

    resendOtp() {
      // 📨 Call your resend API here
      console.log('Resending OTP...');
      this.form.post(route("auth.resendOtp"), {
        onSuccess: () => {
          this.showToast("A new OTP has been sent to your email.", "success");
          this.timer = 60;
          this.isTimerRunning = true;
          this.startTimer();
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ");
          this.showToast(`${errorMessages}`, "error");
        },
      });

      // Reset timer
      this.timer = 60;
      this.isTimerRunning = true;

    },

    startTimer() {
      this.timerInterval = setInterval(() => {
        if (this.timer > 0) {
          this.timer--;
        } else {
          this.stopTimer();
        }
      }, 1000);
    },

    stopTimer() {
      this.isTimerRunning = false;
      clearInterval(this.timerInterval);
      this.timerInterval = null;
    },

    sanitizeFormData() {
      const cleaningRules = getUserFormCleaningRules();
      const formDataObj = {
        employee_number: this.form.employee_number,
        email: this.form.email,
        username: this.form.username,
        password: this.form.password,
      };

      const sanitized = sanitizeFormData(formDataObj, cleaningRules);

      // Update form data with sanitized values
      Object.keys(sanitized).forEach((key) => {
        if (this.form.hasOwnProperty(key)) {
          this.form[key] = sanitized[key];
        }
      });
    },

    submitForm() {
      // Sanitize form data before validation and submission
      this.sanitizeFormData();

      this.v$.$touch();

      if (this.v$.$invalid) return;

      this.form.post(route("auth.sendOtp"), {
        onSuccess: () => {
          this.otpDialog = true;
          this.showToast(
            "OTP has been successfully sent to your email.",
            "success"
          );
        },
        onError: (errors) => {
          // Combine all error messages into one string
          const errorMessages = Object.values(errors).flat().join(" ");

          this.showToast(`${errorMessages}`, "error");
        },
      });
    },



  },
};
</script>
<style scoped>
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

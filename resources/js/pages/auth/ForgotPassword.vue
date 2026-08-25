<template>
  <div
    class="bg min-h-screen bg-gray-100 text-gray-900 flex justify-center items-center"
  >
    <div
      class="max-w-screen-xl m-0 sm:m-20 bg-white shadow sm:rounded-lg flex justify-center items-center flex-1"
    >
      <!-- Left Side -->
      <div
        class="w-full lg:w-1/2 xl:w-5/12 p-6 sm:p-8 lg:p-12 flex flex-col justify-center"
      >
        <div class="d-flex align-center justify-center">
          <v-img
            :src="dmmmsulogo"
            alt="dmmmsu-logo"
            class="w-1/2"
            max-width="200"
          ></v-img>
        </div>
        <div class="v-card-title text-center text-h4 text-wrap">
          Human Resource Management System
        </div>
        <div class="v-card-subtitle text-center">Forgot Your Password?</div>
        <div class="flex flex-col">
          <div class="w-full flex-1 mt-5">
            <div class="mx-auto max-w-xs">
              <v-form @submit.prevent="handleSubmit()">
                <v-text-field
                  variant="outlined"
                  density="compact"
                  label="Email Address"
                  rounded="lg"
                  type="email"
                  v-model="forgotPasswordForm.email"
                  :error-messages="
                    v$.forgotPasswordForm.email.$errors.map((e) => e.$message)
                  "
                  @input="
                    forgotPasswordForm.email = sanitizeEmail(
                      $event.target.value
                    )
                  "
                ></v-text-field>
                <v-btn
                  class="text-center tracking-wide font-semibold text-gray-100 w-full transition-all duration-300 ease-in-out flex items-center justify-center focus:shadow-outline focus:outline-none"
                  color="starbucks-green"
                  rounded="xl"
                  type="submit"
                >
                  Send Password Reset Link
                </v-btn>
              </v-form>

              <div class="mt-6 flex justify-between text-sm">
                <Link
                  :href="route('/')"
                  class="text-sm text-starbucks-green hover:text-blue-900 transition-colors duration-200"
                >
                  Click here to Log in
                </Link>
                <Link
                  :href="route('auth.registration')"
                  class="text-decoration-none"
                >
                  <button
                    class="text-sm text-starbucks-green hover:text-blue-900 transition-colors duration-200"
                  >
                    Create your account
                  </button>
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
      <!-- Rigth Side -->
      <div
        class="hidden lg:flex lg:w-1/2 xl:w-7/12 bg-gradient-to-br from-green-50 to-yellow-50 items-center justify-center p-8 relative overflow-hidden"
      >
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
</template>

<script>
import useVuelidate from "@vuelidate/core";
import { useForm } from "@inertiajs/vue3";
import { required } from "@vuelidate/validators";
import logo from "../../../images/abstract-doodle.png";
import dmmmsulogo from "../../../images/dmmmsu-logo.png";
import cschrlogo from "../../../images/usmprimehrmlogo.png";
import hrms from "../../../images/HRMS.svg";

export default {
  props: {
    errors: Object,
    status: String,
  },

  data() {
    return {
      logo,
      hrms,
      dmmmsulogo,

      cschrlogo,

      v$: useVuelidate(),

      forgotPasswordForm: useForm({
        email: null,
      }),

      submitted: false,
    };
  },

  validations() {
    return {
      forgotPasswordForm: {
        email: {
          required,
          email: (value) => {
            if (!value) return true;
            // Basic email format validation
            const emailRegex =
              /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return emailRegex.test(value);
          },
        },
      },
    };
  },

  methods: {
    sanitizeEmail(email) {
      if (!email) return email;

      // Convert to lowercase
      email = email.toLowerCase();

      // Remove leading/trailing whitespace
      email = email.trim();

      // Remove multiple spaces
      email = email.replace(/\s+/g, "");

      // Remove any HTML tags that might have been pasted
      email = email.replace(/<[^>]*>/g, "");

      // Remove line breaks
      email = email.replace(/[\r\n\t]/g, "");

      return email;
    },

    handleSubmit() {
      // Sanitize email before validation
      this.forgotPasswordForm.email = this.sanitizeEmail(
        this.forgotPasswordForm.email
      );

      this.v$.$validate();

      if (!this.v$.$error) {
        this.forgotPasswordForm.post(route("sendResetLink"), {
          onSuccess: (page) => {
            // this.showToast(
            //   "Password reset instructions have been sent to your email!"
            // );

            const message = page.props.flash.status;

            if (message) {
              this.showToast(message);
            }

            this.$inertia.visit(route("/"));
          },
          onError: (errors) => {
            // Combine all error messages into one string
            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(`${errorMessages}`, "error");
          },
        });
      }
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

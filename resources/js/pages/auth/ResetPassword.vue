<template>
  <div
    class="bg min-h-screen bg-gray-100 text-gray-900 flex justify-center items-center"
  >
    <div
      class="max-w-screen-xl m-0 sm:m-20 bg-white shadow sm:rounded-lg flex justify-center items-center flex-1"
    >
      <!-- Left Side -->
      <div class="lg:w-1/2 xl:w-5/12 p-6 sm:p-12">
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
        <div class="v-card-subtitle text-center">Reset Your Password</div>
        <div class="flex flex-col">
          <div class="w-full flex-1 mt-5">
            <div class="mx-auto max-w-xs">
              <v-form @submit.prevent="handleSubmit()">
                <!-- Hidden Token -->
                <input type="hidden" v-model="reset.token" />
                <input type="hidden" v-model="reset.email" />

                <!-- New Password -->
                <v-text-field
                  variant="outlined"
                  density="compact"
                  label="New Password"
                  rounded="lg"
                  type="password"
                  v-model="reset.password"
                  :error-messages="
                    v$.reset.password.$errors.map((e) => e.$message)
                  "
                ></v-text-field>

                <!-- Confirm Password -->
                <v-text-field
                  variant="outlined"
                  density="compact"
                  label="Confirm Password"
                  rounded="lg"
                  type="password"
                  v-model="reset.password_confirmation"
                  :error-messages="
                    v$.reset.password_confirmation.$errors.map(
                      (e) => e.$message
                    )
                  "
                ></v-text-field>

                <!-- Submit Button -->
                <v-btn
                  class="text-center tracking-wide font-semibold text-gray-100 w-full transition-all duration-300 ease-in-out flex items-center justify-center focus:shadow-outline focus:outline-none"
                  color="starbucks-green"
                  rounded="xl"
                  type="submit"
                >
                  Update Password
                </v-btn>
              </v-form>

              <div class="mt-4 text-right">
                <Link
                  :href="route('/')"
                  class="text-sm text-gray-600 hover:text-gray-900"
                >
                  Go Back to Login
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Rigth Side -->
      <div class="flex-1 text-center hidden lg:flex">
        <v-img
          class="m-12 xl:m-16 bg-contain bg-center bg-no-repeat"
          :src="logo"
          alt="logo"
        ></v-img>
      </div>
    </div>
  </div>
</template>

<script>
import useVuelidate from "@vuelidate/core";
import { useForm } from "@inertiajs/vue3";
import { required, sameAs, helpers } from "@vuelidate/validators";
import logo from "../../../images/abstract-doodle.png";
import dmmmsulogo from "../../../images/dmmmsu-logo.png";
import cschrlogo from "../../../images/usmprimehrmlogo.png";
import { passwordRegex } from "@/helpers/DataHelper.js";

export default {
  props: {
    errors: Object,
    token: String,
    email: String,
  },

  data() {
    return {
      logo,
      dmmmsulogo,
      cschrlogo,
      v$: useVuelidate(),

      reset: useForm({
        token: this.token,
        email: this.email,
        password: null,
        password_confirmation: null,
      }),
    };
  },

  validations() {
    return {
      reset: {
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
            sameAs(this.reset.password)
          ),
        },
      },
    };
  },

  methods: {
    handleSubmit() {
      this.v$.$validate();

      if (!this.v$.$error) {
        this.reset.post(route("updatePassword"), {
          onSuccess: () => {
            this.showToast("Your Password is successfully reset.", "success");
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
  background: #006241;
  background: linear-gradient(
    207deg,
    rgba(0, 98, 65, 1) 18%,
    rgba(70, 194, 165, 1) 62%,
    rgba(242, 199, 27, 1) 98%
  );
}
</style>
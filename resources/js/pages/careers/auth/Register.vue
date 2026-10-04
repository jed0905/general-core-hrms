<template>
  <CareersLayout>
    <Head title="Create your careers account" />
    <v-card variant="outlined" class="rounded-lg pa-6 mx-auto" max-width="720">
      <h1 class="text-h5 font-weight-bold mb-1">Create your careers account</h1>
      <p class="text-body-2 text-medium-emphasis mb-4">We'll email you a link to confirm your address and choose a password.</p>
      <v-row density="compact">
        <v-col cols="12" md="6"><v-text-field v-model="form.first_name" label="First name *" autocomplete="given-name" variant="outlined" density="compact" :error-messages="form.errors.first_name" /></v-col>
        <v-col cols="12" md="6"><v-text-field v-model="form.middle_name" label="Middle name" autocomplete="additional-name" variant="outlined" density="compact" :error-messages="form.errors.middle_name" /></v-col>
        <v-col cols="12" md="8"><v-text-field v-model="form.last_name" label="Last name *" autocomplete="family-name" variant="outlined" density="compact" :error-messages="form.errors.last_name" /></v-col>
        <v-col cols="12" md="4"><v-text-field v-model="form.suffix" label="Suffix" autocomplete="honorific-suffix" variant="outlined" density="compact" :error-messages="form.errors.suffix" /></v-col>
        <v-col cols="12" md="6"><v-text-field v-model="form.email" label="Email *" type="email" autocomplete="email" variant="outlined" density="compact" :error-messages="form.errors.email" /></v-col>
        <v-col cols="12" md="6"><v-text-field v-model="form.phone" label="Phone" type="tel" autocomplete="tel" variant="outlined" density="compact" :error-messages="form.errors.phone" /></v-col>
        <v-col cols="12"><v-textarea v-model="form.address" label="Address" rows="2" autocomplete="street-address" variant="outlined" density="compact" :error-messages="form.errors.address" /></v-col>
      </v-row>

      <v-card variant="tonal" class="pa-4 mb-3">
        <h2 class="text-subtitle-2 font-weight-bold mb-1">Privacy notice</h2>
        <p class="text-body-2 mb-0" style="white-space: pre-line">{{ privacyNotice }}</p>
      </v-card>
      <v-checkbox v-model="form.privacy_consent" label="I have read the privacy notice and agree to the processing of my personal data for recruitment." :error-messages="form.errors.privacy_consent" />

      <div class="d-flex flex-wrap align-center ga-2">
        <v-btn variant="text" @click="go(route('careers.login'))">Already registered? Sign in</v-btn>
        <v-spacer />
        <v-btn color="primary" :loading="form.processing" :disabled="form.processing" @click="submit">Create account</v-btn>
      </div>
    </v-card>
  </CareersLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";

export default {
  name: "CareersRegister",
  components: { CareersLayout, Head },
  data() {
    return { form: useForm({ first_name: "", middle_name: "", last_name: "", suffix: "", email: "", phone: "", address: "", privacy_consent: false }) };
  },
  computed: {
    privacyNotice() {
      return this.$page.props.portal?.settings?.privacy_notice || "";
    },
  },
  methods: {
    submit() {
      this.form.post(route("careers.register.store"));
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>

<template>
  <CareersLayout>
    <Head :title="job.title" />
    <v-alert v-if="preview" type="warning" variant="tonal" class="mb-4">Preview: this is how the job appears on the careers site. {{ job.is_open ? "" : "It is not visible to the public right now." }}</v-alert>
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="px-0 mb-2" @click="go(route('careers.index'))">All jobs</v-btn>

    <v-row>
      <v-col cols="12" md="8">
        <h1 class="text-h4 font-weight-bold mb-2">{{ job.title }}</h1>
        <div class="d-flex flex-wrap ga-4 text-body-1 text-medium-emphasis mb-6">
          <span v-if="job.department"><v-icon icon="mdi-domain" size="small" class="mr-1" />{{ job.department }}</span>
          <span v-if="job.employment_type"><v-icon icon="mdi-briefcase-outline" size="small" class="mr-1" />{{ job.employment_type }}</span>
          <span v-if="job.location"><v-icon icon="mdi-map-marker-outline" size="small" class="mr-1" />{{ job.location }}</span>
        </div>

        <section v-if="job.description" class="mb-6">
          <h2 class="text-h6 font-weight-bold mb-2">About the role</h2>
          <p class="text-body-1" style="white-space: pre-line">{{ job.description }}</p>
        </section>
        <section v-if="job.responsibilities" class="mb-6">
          <h2 class="text-h6 font-weight-bold mb-2">Responsibilities</h2>
          <p class="text-body-1" style="white-space: pre-line">{{ job.responsibilities }}</p>
        </section>
        <section v-if="job.qualifications" class="mb-6">
          <h2 class="text-h6 font-weight-bold mb-2">Qualifications</h2>
          <p class="text-body-1" style="white-space: pre-line">{{ job.qualifications }}</p>
        </section>
      </v-col>

      <v-col cols="12" md="4">
        <v-card variant="outlined" class="rounded-lg pa-4" style="position: sticky; top: 80px">
          <dl class="text-body-2 mb-4">
            <div v-if="job.openings" class="mb-2"><dt class="text-medium-emphasis">Openings</dt><dd>{{ job.openings }}</dd></div>
            <div v-if="job.salary" class="mb-2"><dt class="text-medium-emphasis">Salary</dt><dd>{{ salary }}</dd></div>
            <div v-if="job.posted_on" class="mb-2"><dt class="text-medium-emphasis">Posted</dt><dd>{{ formatDate(job.posted_on) }}</dd></div>
            <div v-if="job.closing_date" class="mb-2"><dt class="text-medium-emphasis">Applications close</dt><dd>{{ formatDate(job.closing_date) }}</dd></div>
          </dl>
          <div v-if="requiredDocuments.length" class="text-body-2 mb-4">
            <div class="text-medium-emphasis">Please have ready</div>
            <ul class="ml-4"><li v-for="d in requiredDocuments" :key="d">{{ d }}</li></ul>
          </div>

          <v-btn v-if="alreadyApplied" block variant="tonal" disabled>You have applied</v-btn>
          <v-btn v-else-if="!job.is_open" block variant="tonal" disabled>Applications closed</v-btn>
          <v-btn v-else-if="!preview" block color="primary" size="large" @click="apply">Apply now</v-btn>
          <p v-if="!candidate && job.is_open && !preview" class="text-caption text-medium-emphasis mt-2 mb-0">You'll be asked to sign in or create an account first.</p>
          <v-btn v-if="alreadyApplied" block variant="text" class="mt-2" @click="go(route('careers.applications.index'))">View my applications</v-btn>
        </v-card>
      </v-col>
    </v-row>
  </CareersLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";
import { formatDate, money } from "@/utils/careers";

export default {
  name: "CareersJob",
  components: { CareersLayout, Head },
  props: {
    job: { type: Object, required: true },
    alreadyApplied: { type: Boolean, default: false },
    requiredDocuments: { type: Array, default: () => [] },
    preview: { type: Boolean, default: false },
  },
  computed: {
    candidate() {
      return this.$page.props.portal?.candidate;
    },
    salary() {
      const s = this.job.salary;
      if (!s) return "";
      if (s.min && s.max) return `${money(s.min, s.currency)} – ${money(s.max, "")}`;
      return money(s.min || s.max, s.currency);
    },
  },
  methods: {
    formatDate,
    apply() {
      router.visit(route("careers.jobs.apply", this.job.slug));
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>

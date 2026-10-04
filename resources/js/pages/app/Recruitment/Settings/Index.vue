<template>
  <SidebarLayout>
    <Head title="Recruitment Settings" />
    <v-container fluid class="pa-6" style="max-width: 1200px">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Recruitment Settings</h1>
        <p class="text-body-2 text-medium-emphasis">Pipeline stage names, applicant sources, rejection reasons, interview and assessment types, and scorecard criteria.</p>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4">
        <v-card-title class="text-subtitle-1 font-weight-bold border-b">Pipeline stages</v-card-title>
        <v-card-text>
          <p class="text-body-2 text-medium-emphasis mb-3">Each vacancy keeps the stage names it had when it opened; renaming only affects vacancies opened afterwards.</p>
          <v-row v-for="s in stages" :key="s.id" density="compact" align="center">
            <v-col cols="1" class="text-medium-emphasis">{{ s.sort_order / 10 }}.</v-col>
            <v-col cols="7"><v-text-field v-model="stageNames[s.id]" density="compact" variant="outlined" hide-details /></v-col>
            <v-col cols="2" class="text-caption text-medium-emphasis">{{ s.stage_type }}</v-col>
            <v-col cols="2" class="text-end"><v-btn size="small" variant="tonal" :disabled="stageNames[s.id] === s.name || !stageNames[s.id]" @click="saveStage(s)">Save</v-btn></v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <v-row>
        <v-col v-for="list in lists" :key="list.key" cols="12" md="6">
          <v-card variant="outlined" class="rounded-lg h-100">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">{{ list.title }}</v-card-title>
            <v-card-text>
              <p v-if="list.hint" class="text-body-2 text-medium-emphasis mb-3">{{ list.hint }}</p>
              <div class="d-flex ga-2 mb-3">
                <v-text-field v-model="newNames[list.key]" :label="`New ${list.singular}`" density="compact" variant="outlined" hide-details @keyup.enter="add(list)" />
                <v-select v-if="list.resultType" v-model="newResultType" :items="resultTypeItems" density="compact" variant="outlined" hide-details style="max-width: 150px" />
                <v-btn color="primary" :disabled="!newNames[list.key]" @click="add(list)">Add</v-btn>
              </div>
              <v-table density="compact">
                <tbody>
                  <tr v-for="item in list.items" :key="item.id">
                    <td><v-text-field v-model="edits[list.key][item.id]" density="compact" variant="plain" hide-details /></td>
                    <td v-if="list.resultType" style="width: 150px">
                      <v-select :model-value="item.result_type" :items="resultTypeItems" density="compact" variant="plain" hide-details @update:model-value="(v) => update(list, item, { result_type: v })" />
                    </td>
                    <td class="text-no-wrap" style="width: 1%">
                      <v-switch :model-value="item.is_active" color="success" density="compact" hide-details :title="item.is_active ? 'Active' : 'Inactive'" @update:model-value="(v) => update(list, item, { is_active: v })" />
                    </td>
                    <td style="width: 1%">
                      <v-btn size="small" variant="text" :disabled="edits[list.key][item.id] === item.name || !edits[list.key][item.id]" @click="update(list, item, { name: edits[list.key][item.id] })">Save</v-btn>
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-card variant="outlined" class="rounded-lg mt-4">
        <v-card-title class="text-subtitle-1 font-weight-bold border-b">Careers site</v-card-title>
        <v-card-text>
          <p class="text-body-2 text-medium-emphasis mb-3">Texts on the public careers site. Company name, contact details and logo come from Organization settings.</p>
          <v-text-field v-model="careersForm.headline" label="Headline *" variant="outlined" density="compact" :error-messages="careersForm.errors.headline" />
          <v-textarea v-model="careersForm.introduction" label="Introduction" rows="2" variant="outlined" density="compact" :error-messages="careersForm.errors.introduction" />
          <v-textarea v-model="careersForm.application_instructions" label="Application instructions" rows="2" variant="outlined" density="compact" :error-messages="careersForm.errors.application_instructions" />
          <v-textarea v-model="careersForm.privacy_notice" label="Privacy notice (candidates must agree to it) *" rows="4" variant="outlined" density="compact" :error-messages="careersForm.errors.privacy_notice" />
          <div class="d-flex justify-end"><v-btn color="primary" :loading="careersForm.processing" @click="saveCareers">Save careers texts</v-btn></div>

          <h3 class="text-subtitle-2 font-weight-bold mt-4 mb-1">Documents required when applying online</h3>
          <v-table density="compact">
            <tbody>
              <tr v-for="t in documentTypes" :key="t.id">
                <td>{{ t.name }}<span v-if="!t.is_active" class="text-medium-emphasis"> (inactive)</span></td>
                <td style="width: 1%"><v-switch :model-value="t.required_online" color="primary" density="compact" hide-details :aria-label="`${t.name} required online`" @update:model-value="(v) => setRequired(t, v)" /></td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>

      <v-card variant="outlined" class="rounded-lg mt-4">
        <v-card-title class="text-subtitle-1 font-weight-bold border-b">Scorecard rating scale</v-card-title>
        <v-card-text>
          <p class="text-body-2 text-medium-emphasis mb-2">Panelists rate each active criterion on this 1–5 scale.</p>
          <div class="d-flex flex-wrap ga-2">
            <v-chip v-for="(label, value) in ratingScale" :key="value" variant="tonal">{{ value }} · {{ label }}</v-chip>
          </div>
        </v-card-text>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { RESULT_TYPE_LABELS } from "@/utils/recruitment";

const byId = (items) => Object.fromEntries(items.map((i) => [i.id, i.name]));

export default {
  name: "RecruitmentSettings",
  components: { SidebarLayout, Head },
  props: {
    stages: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    reasons: { type: Array, default: () => [] },
    interviewTypes: { type: Array, default: () => [] },
    assessmentTypes: { type: Array, default: () => [] },
    criteria: { type: Array, default: () => [] },
    ratingScale: { type: Object, default: () => ({}) },
    careers: { type: Object, default: () => ({}) },
    documentTypes: { type: Array, default: () => [] },
  },
  data() {
    return {
      stageNames: byId(this.stages),
      newNames: { sources: "", reasons: "", interviewTypes: "", assessmentTypes: "", criteria: "" },
      newResultType: "score",
      careersForm: useForm({
        headline: this.careers.headline || "",
        introduction: this.careers.introduction || "",
        application_instructions: this.careers.application_instructions || "",
        privacy_notice: this.careers.privacy_notice || "",
      }),
      resultTypeItems: Object.entries(RESULT_TYPE_LABELS).map(([value, title]) => ({ value, title })),
      edits: {
        sources: byId(this.sources),
        reasons: byId(this.reasons),
        interviewTypes: byId(this.interviewTypes),
        assessmentTypes: byId(this.assessmentTypes),
        criteria: byId(this.criteria),
      },
    };
  },
  computed: {
    lists() {
      return [
        { key: "sources", title: "Applicant sources", singular: "source", items: this.sources, store: "recruitment.settings.sources.store", update: "recruitment.settings.sources.update" },
        { key: "reasons", title: "Rejection reasons", singular: "reason", items: this.reasons, store: "recruitment.settings.reasons.store", update: "recruitment.settings.reasons.update" },
        { key: "interviewTypes", title: "Interview types", singular: "interview type", items: this.interviewTypes, store: "recruitment.settings.interview-types.store", update: "recruitment.settings.interview-types.update" },
        {
          key: "assessmentTypes",
          title: "Assessment types",
          singular: "assessment type",
          items: this.assessmentTypes,
          store: "recruitment.settings.assessment-types.store",
          update: "recruitment.settings.assessment-types.update",
          resultType: true,
          hint: "How a result is recorded. Existing assessments keep the result type they were created with.",
        },
        {
          key: "criteria",
          title: "Evaluation criteria",
          singular: "criterion",
          items: this.criteria,
          store: "recruitment.settings.criteria.store",
          update: "recruitment.settings.criteria.update",
          hint: "Every active criterion must be rated before a scorecard is submitted. Submitted scorecards keep the names they were rated under.",
        },
      ];
    },
  },
  watch: {
    sources(items) {
      this.edits.sources = byId(items);
    },
    reasons(items) {
      this.edits.reasons = byId(items);
    },
    interviewTypes(items) {
      this.edits.interviewTypes = byId(items);
    },
    assessmentTypes(items) {
      this.edits.assessmentTypes = byId(items);
    },
    criteria(items) {
      this.edits.criteria = byId(items);
    },
  },
  methods: {
    saveCareers() {
      this.careersForm.put(route("recruitment.settings.careers.update"), { preserveScroll: true });
    },
    setRequired(type, value) {
      router.put(route("recruitment.settings.document-types.update", type.id), { required_online: value }, { preserveScroll: true });
    },
    saveStage(stage) {
      router.put(route("recruitment.settings.stages.update", stage.id), { name: this.stageNames[stage.id] }, { preserveScroll: true });
    },
    add(list) {
      const data = { name: this.newNames[list.key], ...(list.resultType ? { result_type: this.newResultType } : {}) };
      router.post(route(list.store), data, { preserveScroll: true, onSuccess: () => (this.newNames[list.key] = "") });
    },
    update(list, item, changes) {
      router.put(route(list.update, item.id), { name: item.name, ...changes }, { preserveScroll: true });
    },
  },
};
</script>

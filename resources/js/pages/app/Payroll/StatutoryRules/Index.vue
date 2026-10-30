<template>
  <SidebarLayout>
    <Head title="Statutory Rules" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">Statutory Rules</h1>
          <p class="text-body-2 text-medium-emphasis">Effective-dated legal rules per country, each with its official source. A version must be verified by a second person before a run that uses it can be finalized.</p>
        </div>
        <v-btn v-if="can.create" color="primary" prepend-icon="mdi-plus" @click="openForm()">Rule version</v-btn>
      </div>

      <v-alert type="info" variant="tonal" density="compact" class="mb-4">
        Enter values only from the official issuance (law, regulation, circular or order) and cite it. Nothing here is pre-filled; the application never assumes a rate.
      </v-alert>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="3"><v-select v-model="filterForm.country_code" :items="catalog.map((c) => c.code)" label="Country" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="5"><v-select v-model="filterForm.rule_key" :items="ruleKeys" label="Rule" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="4"><v-select v-model="filterForm.verification_status" :items="statuses.map((s) => ({ title: statusInfo(statusMap, s).label, value: s }))" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead><tr><th>Rule</th><th>Effective</th><th>Method / applies by</th><th>Source</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <tr v-if="!rules.data.length"><td colspan="6" class="text-center text-medium-emphasis pa-6">No rule versions.</td></tr>
            <tr v-for="r in rules.data" :key="r.id">
              <td>
                <span class="font-weight-medium">{{ r.rule_key }}</span><span v-if="r.variant" class="text-caption"> · {{ r.variant }}</span><span v-if="r.jurisdiction_code" class="text-caption"> · {{ r.jurisdiction_code }}</span>
                <div class="text-caption text-medium-emphasis">{{ r.name }} · v{{ r.version }}.{{ r.revision }}</div>
              </td>
              <td class="text-no-wrap">{{ date(r.effective_from) }} – {{ r.effective_to ? date(r.effective_to) : 'open' }}</td>
              <td class="text-caption">{{ label(r.calculation_method) }} · {{ label(r.applies_by) }}</td>
              <td class="text-caption" style="max-width: 280px">
                {{ r.official_source }} · {{ r.issuance_reference }}<br />
                <a :href="r.source_url" target="_blank" rel="noopener" class="text-primary">source</a> (retrieved {{ date(r.retrieved_at) }})
              </td>
              <td>
                <v-chip size="small" variant="tonal" :color="statusInfo(statusMap, r.verification_status).color">{{ statusInfo(statusMap, r.verification_status).label }}</v-chip>
                <div class="text-caption text-medium-emphasis">by {{ r.creator?.username || '—' }}<span v-if="r.verifier">, verified by {{ r.verifier.username }}</span></div>
              </td>
              <td class="text-end text-no-wrap">
                <v-btn size="small" variant="text" @click="viewing = r">Values</v-btn>
                <v-btn v-if="r.can.update" size="small" variant="text" @click="openForm(r)">Revise</v-btn>
                <v-btn v-if="r.can.verify" size="small" variant="text" color="success" @click="openDecision(r, 'verify')">Verify</v-btn>
                <v-btn v-if="r.can.reject" size="small" variant="text" color="error" @click="openDecision(r, 'reject')">Reject</v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="rules" /></div>
      </v-card>

      <v-dialog :model-value="!!viewing" max-width="900" @update:model-value="viewing = null">
        <v-card v-if="viewing" class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ viewing.rule_key }} v{{ viewing.version }}.{{ viewing.revision }}</v-card-title>
          <v-card-text>
            <p v-if="viewing.verification_notes" class="text-body-2 mb-2">Verification notes: {{ viewing.verification_notes }}</p>
            <v-table v-if="viewing.parameters.length" density="compact" class="mb-3">
              <thead><tr><th>Parameter</th><th>Value</th></tr></thead>
              <tbody><tr v-for="p in viewing.parameters" :key="p.id"><td>{{ p.key }}</td><td>{{ p.value ?? p.text_value }}</td></tr></tbody>
            </v-table>
            <v-table v-if="viewing.brackets.length" density="compact">
              <thead><tr><th>#</th><th v-for="c in bracketColumns" :key="c">{{ label(c) }}</th></tr></thead>
              <tbody><tr v-for="b in viewing.brackets" :key="b.id"><td>{{ b.sequence }}</td><td v-for="c in bracketColumns" :key="c">{{ b[c] ?? '' }}</td></tr></tbody>
            </v-table>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="viewing = null">Close</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="formDialog" max-width="1100" scrollable>
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ form.id ? 'Revise draft' : 'New rule version' }}</v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" md="2"><v-select v-model="form.country_code" :items="catalog.map((c) => c.code)" label="Country" variant="outlined" density="compact" :error-messages="form.errors.country_code" /></v-col>
              <v-col cols="12" md="4"><v-select v-model="form.rule_key" :items="Object.keys(catalogFor(form.country_code))" label="Rule key" variant="outlined" density="compact" :error-messages="form.errors.rule_key" @update:model-value="onRuleKey" /></v-col>
              <v-col cols="12" md="3"><v-select v-model="form.calculation_method" :items="selectedCatalog?.methods || []" label="Method" variant="outlined" density="compact" :error-messages="form.errors.calculation_method" /></v-col>
              <v-col cols="12" md="3"><v-select v-model="form.variant" :items="selectedCatalog?.variants || []" label="Variant" variant="outlined" density="compact" clearable :disabled="!selectedCatalog?.variants" :error-messages="form.errors.variant" /></v-col>
              <v-col v-if="selectedCatalog" cols="12"><p class="text-caption text-medium-emphasis">{{ selectedCatalog.description }}</p></v-col>
              <v-col cols="12" md="6"><v-text-field v-model="form.name" label="Name" variant="outlined" density="compact" :error-messages="form.errors.name" /></v-col>
              <v-col cols="12" md="3"><v-select v-model="form.category" :items="categories" label="Category" variant="outlined" density="compact" :error-messages="form.errors.category" /></v-col>
              <v-col cols="12" md="3"><v-text-field v-model="form.jurisdiction_code" label="Jurisdiction (blank = national)" variant="outlined" density="compact" :error-messages="form.errors.jurisdiction_code" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="form.effective_from" label="Effective from (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="form.errors.effective_from" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="form.effective_to" label="Effective to (optional)" variant="outlined" density="compact" :error-messages="form.errors.effective_to" /></v-col>
              <v-col cols="6" md="3"><v-select v-model="form.applies_by" :items="appliesBy" label="Selected by date of" variant="outlined" density="compact" :error-messages="form.errors.applies_by" /></v-col>
              <v-col cols="6" md="3"><v-select v-model="form.rounding_mode" :items="['half_up', 'half_even', 'up', 'down']" label="Rounding (if the rule states one)" variant="outlined" density="compact" clearable /></v-col>
              <v-col cols="12" md="4"><v-text-field v-model="form.official_source" label="Issuing authority" variant="outlined" density="compact" :error-messages="form.errors.official_source" /></v-col>
              <v-col cols="12" md="4"><v-text-field v-model="form.issuance_reference" label="Issuance (number, section)" variant="outlined" density="compact" :error-messages="form.errors.issuance_reference" /></v-col>
              <v-col cols="12" md="4"><v-text-field v-model="form.retrieved_at" label="Retrieved on (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="form.errors.retrieved_at" /></v-col>
              <v-col cols="12"><v-text-field v-model="form.source_url" label="Official source URL" variant="outlined" density="compact" :error-messages="form.errors.source_url" /></v-col>
            </v-row>

            <div class="text-subtitle-2 mt-2">Parameters</div>
            <div v-for="(p, i) in form.parameters" :key="'p' + i" class="d-flex ga-2 mb-2">
              <v-text-field v-model="p.key" label="Key" variant="outlined" density="compact" hide-details style="max-width: 260px" />
              <v-text-field v-model="p.value" label="Decimal value" variant="outlined" density="compact" hide-details />
              <v-btn icon="mdi-close" variant="text" size="small" @click="form.parameters.splice(i, 1)" />
            </div>
            <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" class="mb-3" @click="form.parameters.push({ key: '', value: '' })">Parameter</v-btn>

            <div class="text-subtitle-2">Brackets</div>
            <div class="overflow-x-auto">
              <table class="w-100">
                <thead><tr><th v-for="c in bracketColumns" :key="c" class="text-caption text-left pr-1">{{ label(c) }}</th><th></th></tr></thead>
                <tbody>
                  <tr v-for="(b, i) in form.brackets" :key="'b' + i">
                    <td v-for="c in bracketColumns" :key="c" class="pr-1"><v-text-field v-model="b[c]" variant="outlined" density="compact" hide-details style="min-width: 100px" /></td>
                    <td><v-btn icon="mdi-close" variant="text" size="small" @click="form.brackets.splice(i, 1)" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" class="mt-2" @click="form.brackets.push(emptyBracket())">Bracket</v-btn>
            <v-textarea v-model="form.notes" label="Notes" variant="outlined" rows="2" class="mt-3" />
            <div v-if="firstError(form.errors)" class="text-error text-caption">{{ firstError(form.errors) }}</div>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="formDialog = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="form.processing" @click="save">Save draft</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="decision.show" max-width="520">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ decision.action === 'verify' ? 'Verify rule version' : 'Reject rule version' }}</v-card-title>
          <v-card-text>
            <p v-if="decision.action === 'verify'" class="text-body-2 mb-3">Confirm you compared every value with the cited official issuance. A verified version is read-only; an open-ended earlier verified version of the same rule is closed the day before this one starts.</p>
            <v-textarea v-model="decision.notes" :label="decision.action === 'reject' ? 'Reason (required)' : 'Verification notes'" variant="outlined" rows="3" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="decision.show = false">Cancel</v-btn><v-btn :color="decision.action === 'verify' ? 'success' : 'error'" variant="flat" :disabled="decision.action === 'reject' && !decision.notes" @click="decide">Confirm</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import payrollPage from "@/mixins/payrollPage";
import { RULE_STATUS } from "@/utils/payroll";

const BRACKET_COLUMNS = ["range_from", "range_to", "base_amount", "fixed_amount", "rate", "excess_over", "employee_amount", "employer_amount", "employee_rate", "employer_rate", "employer_extra_amount"];

export default {
  name: "StatutoryRuleIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [payrollPage],
  props: {
    rules: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    catalog: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    appliesBy: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      statusMap: RULE_STATUS,
      bracketColumns: BRACKET_COLUMNS,
      viewing: null,
      formDialog: false,
      form: useForm({}),
      decision: { show: false, action: null, rule: null, notes: "" },
      filterForm: { country_code: this.filters.country_code || null, rule_key: this.filters.rule_key || null, verification_status: this.filters.verification_status || null },
    };
  },
  computed: {
    ruleKeys() {
      return this.catalog.flatMap((c) => Object.keys(c.rules));
    },
    selectedCatalog() {
      return this.catalogFor(this.form.country_code)[this.form.rule_key] || null;
    },
  },
  methods: {
    catalogFor(country) {
      return this.catalog.find((c) => c.code === country)?.rules || {};
    },
    apply() {
      router.get(route("payroll.statutory-rules.index"), this.filterForm, { preserveState: true, preserveScroll: true, replace: true });
    },
    emptyBracket() {
      return Object.fromEntries(BRACKET_COLUMNS.map((c) => [c, ""]));
    },
    onRuleKey() {
      if (this.selectedCatalog) {
        this.form.category = this.selectedCatalog.category;
        if (this.selectedCatalog.methods.length === 1) this.form.calculation_method = this.selectedCatalog.methods[0];
      }
    },
    openForm(r = null) {
      const d = (v) => (v ? String(v).substring(0, 10) : "");
      this.form = useForm({
        id: r?.id || null,
        country_code: r?.country_code || this.catalog[0]?.code || null, rule_key: r?.rule_key || null, variant: r?.variant || null,
        jurisdiction_code: r?.jurisdiction_code || "", category: r?.category || null, name: r?.name || "",
        calculation_method: r?.calculation_method || null, applies_by: r?.applies_by || "pay_date", rounding_mode: r?.rounding_mode || null,
        effective_from: d(r?.effective_from), effective_to: d(r?.effective_to), official_source: r?.official_source || "",
        source_url: r?.source_url || "", issuance_reference: r?.issuance_reference || "", retrieved_at: d(r?.retrieved_at), notes: r?.notes || "",
        parameters: (r?.parameters || []).map((p) => ({ key: p.key, value: p.value ?? "" })),
        brackets: (r?.brackets || []).map((b) => Object.fromEntries(BRACKET_COLUMNS.map((c) => [c, b[c] ?? ""]))),
      });
      this.formDialog = true;
    },
    save() {
      const blank = (v) => (v === "" ? null : v);
      this.form.transform(({ id, ...data }) => ({
        ...data,
        jurisdiction_code: blank(data.jurisdiction_code),
        effective_to: blank(data.effective_to),
        parameters: data.parameters.filter((p) => p.key).map((p) => ({ key: p.key, value: blank(p.value) })),
        brackets: data.brackets.map((b) => Object.fromEntries(Object.entries(b).map(([k, v]) => [k, blank(v)]))),
      }));
      const options = { preserveScroll: true, onSuccess: () => (this.formDialog = false) };
      this.form.id ? this.form.put(route("payroll.statutory-rules.update", this.form.id), options) : this.form.post(route("payroll.statutory-rules.store"), options);
    },
    openDecision(rule, action) {
      this.decision = { show: true, action, rule, notes: "" };
    },
    decide() {
      const name = this.decision.action === "verify" ? "payroll.statutory-rules.verify" : "payroll.statutory-rules.reject";
      router.put(route(name, this.decision.rule.id), { notes: this.decision.notes || null }, {
        preserveScroll: true,
        onSuccess: () => (this.decision.show = false),
        onError: (errors) => this.showToast(this.firstError(errors), "error"),
      });
    },
  },
};
</script>

<template>
  <SidebarLayout>
    <Head :title="`Payroll · ${employeeName(employee)}`" />
    <v-container fluid class="pa-6">
      <a :href="route('payroll.employees.index')" class="text-caption text-primary text-decoration-none" @click.prevent="go(route('payroll.employees.index'))">← Payroll employees</a>
      <h1 class="text-h5 font-weight-bold mb-1">{{ employeeName(employee) }}</h1>
      <p class="text-body-2 text-medium-emphasis mb-4">{{ employee.employee_number }} · {{ employee.status }}</p>

      <v-tabs v-model="tab" color="primary" class="mb-3">
        <v-tab value="assignments">Assignments</v-tab>
        <v-tab v-if="can.viewPay" value="pay">Compensation</v-tab>
        <v-tab value="statutory">Residency &amp; statutory</v-tab>
        <v-tab value="bank">Bank accounts</v-tab>
      </v-tabs>

      <v-window v-model="tab">
        <v-window-item value="assignments">
          <v-card variant="outlined" class="rounded-lg">
            <div class="d-flex pa-3"><span class="text-caption text-medium-emphasis">The payroll jurisdiction is the pay group's payroll country. Nationality is never used.</span><v-spacer /><v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="dialogs.assignment = true">Assignment</v-btn></div>
            <v-table density="comfortable">
              <thead><tr><th>Legal entity</th><th>Pay group</th><th>Work location</th><th>Payroll jurisdiction</th><th>Effective</th><th></th></tr></thead>
              <tbody>
                <tr v-if="!assignments.length"><td colspan="6" class="text-center text-medium-emphasis pa-6">No payroll assignment.</td></tr>
                <tr v-for="a in assignments" :key="a.id">
                  <td>{{ a.legal_entity?.name }}</td>
                  <td>{{ a.pay_group?.name }} <span class="text-caption text-medium-emphasis">{{ a.pay_group?.pay_currency }}</span></td>
                  <td>{{ a.location?.city || '—' }} <span class="text-caption text-medium-emphasis">{{ a.work_country_code }}{{ a.work_subdivision_code ? ' / ' + a.work_subdivision_code : '' }}</span></td>
                  <td>{{ a.payroll_country_code }}<div v-if="a.jurisdiction_override_reason" class="text-caption text-medium-emphasis">{{ a.jurisdiction_override_reason }}</div></td>
                  <td class="text-no-wrap">{{ date(a.effective_from) }} – {{ a.effective_to ? date(a.effective_to) : 'open' }}</td>
                  <td class="text-end"><v-btn v-if="can.manage && !a.effective_to" size="small" variant="text" @click="openEnd('assignment', a.id)">End</v-btn></td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>

        <v-window-item v-if="can.viewPay" value="pay">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <div class="d-flex pa-3"><span class="text-subtitle-1 font-weight-bold">Salary history</span><v-spacer /><v-btn v-if="can.recordPay && assignments.length" color="primary" prepend-icon="mdi-plus" @click="dialogs.compensation = true">Salary change</v-btn></div>
            <v-table density="comfortable">
              <thead><tr><th>Effective</th><th>Basis</th><th class="text-end">Amount</th><th>Reason</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <tr v-if="!compensations.length"><td colspan="6" class="text-center text-medium-emphasis pa-6">No compensation recorded.</td></tr>
                <tr v-for="c in compensations" :key="c.id">
                  <td class="text-no-wrap">{{ date(c.effective_from) }} – {{ c.effective_to ? date(c.effective_to) : 'open' }}</td>
                  <td>{{ label(c.basis) }}</td>
                  <td class="text-end">{{ money(c.amount, c.currency) }}</td>
                  <td>{{ c.reason }}<div class="text-caption text-medium-emphasis">by {{ c.creator?.username || '—' }}<span v-if="c.approver">, approved by {{ c.approver.username }}</span></div></td>
                  <td><v-chip size="x-small" variant="tonal" :color="statusInfo(compensationMap, c.status).color">{{ statusInfo(compensationMap, c.status).label }}</v-chip></td>
                  <td class="text-end text-no-wrap">
                    <v-btn v-if="c.can.approve" size="small" variant="text" color="success" @click="put(route('payroll.compensations.approve', c.id))">Approve</v-btn>
                    <v-btn v-if="c.can.discard" size="small" variant="text" color="error" @click="destroy(route('payroll.compensations.destroy', c.id))">Discard</v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
          <v-card variant="outlined" class="rounded-lg">
            <div class="d-flex pa-3"><span class="text-subtitle-1 font-weight-bold">Recurring allowances and deductions</span><v-spacer /><v-btn v-if="can.recordPay && assignments.length" color="primary" prepend-icon="mdi-plus" @click="dialogs.component = true">Recurring item</v-btn></div>
            <v-table density="comfortable">
              <thead><tr><th>Component</th><th class="text-end">Amount</th><th>Basis</th><th>Limit</th><th>Effective</th><th></th></tr></thead>
              <tbody>
                <tr v-if="!payComponents.length"><td colspan="6" class="text-center text-medium-emphasis pa-6">None.</td></tr>
                <tr v-for="p in payComponents" :key="p.id">
                  <td>{{ p.pay_component?.name }} <span class="text-caption text-medium-emphasis">{{ label(p.pay_component?.line_class) }}</span></td>
                  <td class="text-end">{{ money(p.amount, p.currency) }}</td>
                  <td>{{ label(p.amount_basis) }}</td>
                  <td>{{ p.total_limit ? money(p.total_limit, p.currency) : '—' }}</td>
                  <td class="text-no-wrap">{{ date(p.effective_from) }} – {{ p.effective_to ? date(p.effective_to) : 'open' }}</td>
                  <td class="text-end"><v-btn v-if="can.endComponents && !p.effective_to" size="small" variant="text" @click="openEnd('component', p.id)">End</v-btn></td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>

        <v-window-item value="statutory">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <div class="d-flex pa-3"><span class="text-subtitle-1 font-weight-bold">Tax residency</span><v-spacer /><v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="dialogs.residency = true">Residency</v-btn></div>
            <v-table density="compact">
              <thead><tr><th>Country</th><th>Status</th><th>Effective</th><th>Evidence</th></tr></thead>
              <tbody>
                <tr v-if="!residencies.length"><td colspan="4" class="text-center text-medium-emphasis pa-6">Not recorded.</td></tr>
                <tr v-for="r in residencies" :key="r.id"><td>{{ r.country_code }}</td><td>{{ label(r.residency_status) }}</td><td>{{ date(r.effective_from) }} – {{ r.effective_to ? date(r.effective_to) : 'open' }}</td><td>{{ r.evidence_reference || '—' }}</td></tr>
              </tbody>
            </v-table>
          </v-card>
          <v-card variant="outlined" class="rounded-lg">
            <div class="d-flex pa-3"><span class="text-subtitle-1 font-weight-bold">Country statutory profile</span><v-spacer /><v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="openProfile">Profile</v-btn></div>
            <v-table density="compact">
              <thead><tr><th>Country</th><th>Effective</th><th>Details</th></tr></thead>
              <tbody>
                <tr v-if="!statutoryProfiles.length"><td colspan="3" class="text-center text-medium-emphasis pa-6">Not recorded.</td></tr>
                <tr v-for="p in statutoryProfiles" :key="p.id">
                  <td>{{ p.country_code }}</td>
                  <td>{{ date(p.effective_from) }} – {{ p.effective_to ? date(p.effective_to) : 'open' }}</td>
                  <td class="text-caption"><span v-for="(value, key) in p.fields" :key="key" class="mr-3">{{ profileLabel(p.country_code, key) }}: {{ displayValue(value) }}</span></td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>

        <v-window-item value="bank">
          <v-card variant="outlined" class="rounded-lg">
            <div class="d-flex pa-3"><span class="text-caption text-medium-emphasis">Account numbers are encrypted and shown masked.</span><v-spacer /><v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="dialogs.bank = true">Bank account</v-btn></div>
            <v-table density="compact">
              <thead><tr><th>Bank</th><th>Account</th><th>Currency</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <tr v-if="!bankAccounts.length"><td colspan="5" class="text-center text-medium-emphasis pa-6">No bank accounts.</td></tr>
                <tr v-for="b in bankAccounts" :key="b.id">
                  <td>{{ b.bank_name }} <span class="text-caption text-medium-emphasis">{{ b.bank_identifier }}</span></td>
                  <td>{{ b.account_name }} · ••••{{ b.account_last4 }}</td>
                  <td>{{ b.currency }}</td>
                  <td><v-chip size="x-small" variant="tonal" :color="b.is_active ? 'success' : 'grey'">{{ b.is_active ? (b.is_primary ? 'Primary' : 'Active') : 'Inactive' }}</v-chip></td>
                  <td class="text-end"><v-btn v-if="can.manage && b.is_active" size="small" variant="text" @click="put(route('payroll.bank-accounts.deactivate', b.id))">Deactivate</v-btn></td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>
      </v-window>

      <!-- Dialogs -->
      <v-dialog v-model="dialogs.assignment" max-width="640">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">New assignment</v-card-title>
          <v-card-text>
            <v-select v-model="assignmentForm.pay_group_id" :items="payGroupItems" label="Pay group (legal entity, payroll country)" variant="outlined" density="compact" class="mb-2" :error-messages="assignmentForm.errors.pay_group_id" />
            <v-select v-model="assignmentForm.location_id" :items="options.locations" :item-title="(l) => l.city || l.address" item-value="id" label="Work location (optional)" variant="outlined" density="compact" clearable class="mb-2" />
            <div class="d-flex ga-2">
              <v-text-field v-model="assignmentForm.work_country_code" label="Work country (ISO alpha-2)" maxlength="2" variant="outlined" density="compact" :error-messages="assignmentForm.errors.work_country_code" @update:model-value="(v) => (assignmentForm.work_country_code = (v || '').toUpperCase())" />
              <v-text-field v-model="assignmentForm.work_subdivision_code" label="Work subdivision / wage region" variant="outlined" density="compact" :error-messages="assignmentForm.errors.work_subdivision_code" />
            </div>
            <v-text-field v-if="jurisdictionDiffers" v-model="assignmentForm.jurisdiction_override_reason" label="Why this payroll country applies (e.g. secondment)" variant="outlined" density="compact" class="mb-2" :error-messages="assignmentForm.errors.jurisdiction_override_reason" />
            <div class="d-flex ga-2">
              <v-text-field v-model="assignmentForm.effective_from" label="Effective from (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="assignmentForm.errors.effective_from" />
              <v-text-field v-model="assignmentForm.effective_to" label="Effective to (optional)" variant="outlined" density="compact" :error-messages="assignmentForm.errors.effective_to" />
            </div>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.assignment = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="assignmentForm.processing" @click="save(assignmentForm, route('payroll.employees.assignments.store', employee.id), 'assignment', (d) => ({ ...d, effective_to: d.effective_to || null }))">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.compensation" max-width="560">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Salary change</v-card-title>
          <v-card-text>
            <p class="text-body-2 mb-3">The change closes the current record the day before it starts and must be approved by someone else before it is paid.</p>
            <v-select v-model="compensationForm.assignment_id" :items="openAssignmentItems" label="Assignment" variant="outlined" density="compact" class="mb-2" />
            <div class="d-flex ga-2">
              <v-select v-model="compensationForm.basis" :items="options.bases.map((b) => ({ title: label(b), value: b }))" label="Basis" variant="outlined" density="compact" :error-messages="compensationForm.errors.basis" />
              <v-text-field v-model="compensationForm.amount" label="Amount" variant="outlined" density="compact" :error-messages="compensationForm.errors.amount" />
              <v-select v-model="compensationForm.currency" :items="currencyItems" label="Currency" variant="outlined" density="compact" :error-messages="compensationForm.errors.currency" />
            </div>
            <div class="d-flex ga-2">
              <v-text-field v-model="compensationForm.effective_from" label="Effective from (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="compensationForm.errors.effective_from" />
              <v-select v-model="compensationForm.pay_grade_id" :items="options.payGrades" item-title="name" item-value="id" label="Pay grade (optional)" variant="outlined" density="compact" clearable />
            </div>
            <v-text-field v-model="compensationForm.reason" label="Reason" variant="outlined" density="compact" :error-messages="compensationForm.errors.reason" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.compensation = false">Cancel</v-btn><v-btn color="primary" variant="flat" :disabled="!compensationForm.assignment_id" :loading="compensationForm.processing" @click="save(compensationForm, route('payroll.compensations.store', compensationForm.assignment_id), 'compensation', ({ assignment_id, ...d }) => d)">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.component" max-width="560">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Recurring item</v-card-title>
          <v-card-text>
            <v-select v-model="componentForm.assignment_id" :items="openAssignmentItems" label="Assignment" variant="outlined" density="compact" class="mb-2" />
            <v-select v-model="componentForm.pay_component_id" :items="options.components" :item-title="(c) => `${c.name} (${label(c.line_class)})`" item-value="id" label="Component" variant="outlined" density="compact" class="mb-2" :error-messages="componentForm.errors.pay_component_id" />
            <div class="d-flex ga-2">
              <v-text-field v-model="componentForm.amount" label="Amount" variant="outlined" density="compact" :error-messages="componentForm.errors.amount" />
              <v-select v-model="componentForm.currency" :items="currencyItems" label="Currency" variant="outlined" density="compact" :error-messages="componentForm.errors.currency" />
              <v-select v-model="componentForm.amount_basis" :items="[{ title: 'Per pay period', value: 'per_period' }, { title: 'Per month', value: 'monthly' }]" label="Basis" variant="outlined" density="compact" />
            </div>
            <div class="d-flex ga-2">
              <v-text-field v-model="componentForm.total_limit" label="Total limit (e.g. loan)" variant="outlined" density="compact" :error-messages="componentForm.errors.total_limit" />
              <v-text-field v-model="componentForm.effective_from" label="From (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="componentForm.errors.effective_from" />
              <v-text-field v-model="componentForm.effective_to" label="To (optional)" variant="outlined" density="compact" :error-messages="componentForm.errors.effective_to" />
            </div>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.component = false">Cancel</v-btn><v-btn color="primary" variant="flat" :disabled="!componentForm.assignment_id" :loading="componentForm.processing" @click="save(componentForm, route('payroll.employee-pay-components.store', componentForm.assignment_id), 'component', ({ assignment_id, ...d }) => ({ ...d, total_limit: d.total_limit || null, effective_to: d.effective_to || null }))">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.residency" max-width="520">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Tax residency</v-card-title>
          <v-card-text>
            <div class="d-flex ga-2">
              <v-text-field v-model="residencyForm.country_code" label="Country (ISO alpha-2)" maxlength="2" variant="outlined" density="compact" :error-messages="residencyForm.errors.country_code" @update:model-value="(v) => (residencyForm.country_code = (v || '').toUpperCase())" />
              <v-select v-model="residencyForm.residency_status" :items="options.residencyStatuses.map((s) => ({ title: label(s), value: s }))" label="Status" variant="outlined" density="compact" :error-messages="residencyForm.errors.residency_status" />
            </div>
            <v-text-field v-model="residencyForm.effective_from" label="Effective from (YYYY-MM-DD)" variant="outlined" density="compact" class="mb-2" :error-messages="residencyForm.errors.effective_from" />
            <v-text-field v-model="residencyForm.evidence_reference" label="Evidence (document reference)" variant="outlined" density="compact" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.residency = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="residencyForm.processing" @click="save(residencyForm, route('payroll.employees.residencies.store', employee.id), 'residency')">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.profile" max-width="560">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Statutory profile</v-card-title>
          <v-card-text>
            <p class="text-body-2 mb-3">Recording a new profile replaces the current one from its effective date. Re-enter every field.</p>
            <div class="d-flex ga-2">
              <v-select v-model="profileForm.country_code" :items="Object.keys(options.profileFields)" label="Country" variant="outlined" density="compact" :error-messages="profileForm.errors.country_code" />
              <v-text-field v-model="profileForm.effective_from" label="Effective from (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="profileForm.errors.effective_from" />
            </div>
            <template v-for="(field, key) in options.profileFields[profileForm.country_code] || {}" :key="key">
              <v-select v-if="field.options" v-model="profileForm.fields[key]" :items="Object.entries(field.options).map(([value, title]) => ({ value: value === '1', title }))" :label="field.label" variant="outlined" density="compact" clearable class="mb-2" :error-messages="profileForm.errors[`fields.${key}`]" />
              <v-text-field v-else v-model="profileForm.fields[key]" :label="field.label" variant="outlined" density="compact" class="mb-2" :error-messages="profileForm.errors[`fields.${key}`]" />
            </template>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.profile = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="profileForm.processing" @click="save(profileForm, route('payroll.employees.statutory-profiles.store', employee.id), 'profile')">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.bank" max-width="560">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Bank account</v-card-title>
          <v-card-text>
            <div class="d-flex ga-2">
              <v-text-field v-model="bankForm.bank_name" label="Bank" variant="outlined" density="compact" :error-messages="bankForm.errors.bank_name" />
              <v-text-field v-model="bankForm.bank_identifier" label="BIC / bank code" variant="outlined" density="compact" :error-messages="bankForm.errors.bank_identifier" />
            </div>
            <v-text-field v-model="bankForm.account_name" label="Account name" variant="outlined" density="compact" class="mb-2" :error-messages="bankForm.errors.account_name" />
            <div class="d-flex ga-2">
              <v-text-field v-model="bankForm.account_number" label="Account number" variant="outlined" density="compact" :error-messages="bankForm.errors.account_number" />
              <v-text-field v-model="bankForm.iban" label="IBAN (optional)" variant="outlined" density="compact" :error-messages="bankForm.errors.iban" />
              <v-select v-model="bankForm.currency" :items="currencyItems" label="Currency" variant="outlined" density="compact" :error-messages="bankForm.errors.currency" />
            </div>
            <v-switch v-model="bankForm.is_primary" label="Primary account for this currency" color="primary" density="compact" hide-details />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.bank = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="bankForm.processing" @click="save(bankForm, route('payroll.employees.bank-accounts.store', employee.id), 'bank')">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.end" max-width="420">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">End on</v-card-title>
          <v-card-text><v-text-field v-model="endForm.effective_to" label="Last day (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="endForm.errors.effective_to" /></v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.end = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="endForm.processing" @click="saveEnd">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import payrollPage from "@/mixins/payrollPage";
import { COMPENSATION_STATUS } from "@/utils/payroll";

export default {
  name: "PayrollEmployeeShow",
  components: { SidebarLayout, Head },
  mixins: [payrollPage],
  props: {
    employee: { type: Object, required: true },
    assignments: { type: Array, default: () => [] },
    compensations: { type: Array, default: null },
    payComponents: { type: Array, default: null },
    residencies: { type: Array, default: () => [] },
    statutoryProfiles: { type: Array, default: () => [] },
    bankAccounts: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    const openAssignment = this.assignments.find((a) => !a.effective_to);
    return {
      tab: "assignments",
      compensationMap: COMPENSATION_STATUS,
      dialogs: { assignment: false, compensation: false, component: false, residency: false, profile: false, bank: false, end: false },
      endTarget: null,
      assignmentForm: useForm({ pay_group_id: null, location_id: null, work_country_code: "", work_subdivision_code: "", jurisdiction_override_reason: "", effective_from: "", effective_to: "" }),
      compensationForm: useForm({ assignment_id: openAssignment?.id || null, basis: "monthly", amount: "", currency: openAssignment?.pay_group?.pay_currency || null, effective_from: "", pay_grade_id: null, reason: "" }),
      componentForm: useForm({ assignment_id: openAssignment?.id || null, pay_component_id: null, amount: "", currency: openAssignment?.pay_group?.pay_currency || null, amount_basis: "per_period", total_limit: "", effective_from: "", effective_to: "" }),
      residencyForm: useForm({ country_code: "", residency_status: "resident", effective_from: "", evidence_reference: "" }),
      profileForm: useForm({ country_code: null, effective_from: "", fields: {} }),
      bankForm: useForm({ bank_name: "", bank_identifier: "", account_name: "", account_number: "", iban: "", currency: null, is_primary: true }),
      endForm: useForm({ effective_to: "" }),
    };
  },
  computed: {
    currencyItems() {
      return this.options.currencies.map((c) => c.code);
    },
    payGroupItems() {
      return this.options.payGroups.map((g) => ({ value: g.id, title: `${g.name} · ${g.legal_entity?.name} · ${g.payroll_country_code} · ${g.pay_currency}` }));
    },
    openAssignmentItems() {
      return this.assignments.filter((a) => !a.effective_to).map((a) => ({ value: a.id, title: `${a.legal_entity?.name} · ${a.pay_group?.name}` }));
    },
    jurisdictionDiffers() {
      const group = this.options.payGroups.find((g) => g.id === this.assignmentForm.pay_group_id);
      return group && this.assignmentForm.work_country_code && group.payroll_country_code !== this.assignmentForm.work_country_code;
    },
  },
  methods: {
    save(form, url, dialog, transform = null) {
      if (transform) form.transform(transform);
      form.post(url, {
        preserveScroll: true,
        onSuccess: () => {
          this.dialogs[dialog] = false;
          form.reset();
        },
        onError: (errors) => this.showToast(this.firstError(errors), "error"),
      });
    },
    put(url) {
      router.put(url, {}, { preserveScroll: true, onError: (errors) => this.showToast(this.firstError(errors), "error") });
    },
    destroy(url) {
      router.delete(url, { preserveScroll: true, onError: (errors) => this.showToast(this.firstError(errors), "error") });
    },
    openEnd(type, id) {
      this.endTarget = { type, id };
      this.endForm.reset();
      this.dialogs.end = true;
    },
    saveEnd() {
      const url = this.endTarget.type === "assignment" ? route("payroll.assignments.end", this.endTarget.id) : route("payroll.employee-pay-components.end", this.endTarget.id);
      this.endForm.put(url, { preserveScroll: true, onSuccess: () => (this.dialogs.end = false), onError: (errors) => this.showToast(this.firstError(errors), "error") });
    },
    openProfile() {
      this.profileForm.reset();
      this.profileForm.fields = {};
      this.dialogs.profile = true;
    },
    profileLabel(country, key) {
      return this.options.profileFields[country]?.[key]?.label || key;
    },
    displayValue(value) {
      if (value === true) return "Yes";
      if (value === false) return "No";
      return value ?? "—";
    },
  },
};
</script>

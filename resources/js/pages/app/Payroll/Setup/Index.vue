<template>
  <SidebarLayout>
    <Head title="Payroll Setup" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Payroll Setup</h1>
        <p class="text-body-2 text-medium-emphasis">Employers, pay groups and calendars, pay components, salary structures and exchange rates.</p>
      </div>

      <v-tabs v-model="activeTab" color="primary" class="mb-3">
        <v-tab value="entities">Legal entities</v-tab>
        <v-tab value="groups">Pay groups &amp; calendars</v-tab>
        <v-tab value="components">Pay components</v-tab>
        <v-tab value="structures">Salary structures</v-tab>
        <v-tab value="rates">Exchange rates</v-tab>
      </v-tabs>

      <v-window v-model="activeTab">
        <!-- Legal entities -->
        <v-window-item value="entities">
          <v-card variant="outlined" class="rounded-lg">
            <div class="d-flex pa-3"><v-spacer /><v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="openEntity()">Legal entity</v-btn></div>
            <v-table density="comfortable">
              <thead><tr><th>Code</th><th>Name</th><th>Country</th><th>Currency</th><th>Timezone / locale</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <tr v-if="!legalEntities.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No legal entities yet.</td></tr>
                <tr v-for="e in legalEntities" :key="e.id">
                  <td>{{ e.code }}</td>
                  <td>{{ e.name }}<div class="text-caption text-medium-emphasis">{{ e.legal_name }}</div></td>
                  <td>{{ e.country_code }}{{ e.subdivision_code ? ' / ' + e.subdivision_code : '' }}</td>
                  <td>{{ e.functional_currency }}</td>
                  <td class="text-caption">{{ e.timezone }} · {{ e.locale }}</td>
                  <td><v-chip size="x-small" variant="tonal" :color="e.is_active ? 'success' : 'grey'">{{ e.is_active ? 'Active' : 'Inactive' }}</v-chip></td>
                  <td class="text-end text-no-wrap">
                    <v-btn v-if="can.assignUsers" size="small" variant="text" @click="openUsers(e)">Payroll users</v-btn>
                    <v-btn v-if="can.manage" size="small" variant="text" @click="openEntity(e)">Edit</v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>

        <!-- Pay groups -->
        <v-window-item value="groups">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <div class="d-flex pa-3"><v-spacer /><v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" :disabled="!legalEntities.length" @click="openGroup()">Pay group</v-btn></div>
            <v-table density="comfortable">
              <thead><tr><th>Code</th><th>Name</th><th>Legal entity</th><th>Payroll country</th><th>Frequency</th><th>Currency</th><th>Working-time basis</th><th></th></tr></thead>
              <tbody>
                <tr v-if="!payGroups.length"><td colspan="8" class="text-center text-medium-emphasis pa-6">No pay groups yet.</td></tr>
                <tr v-for="g in payGroups" :key="g.id" :class="{ 'bg-surface-variant': selectedPayGroupId === g.id }">
                  <td>{{ g.code }}</td>
                  <td>{{ g.name }} <v-chip v-if="!g.is_active" size="x-small" class="ml-1">Inactive</v-chip></td>
                  <td>{{ g.legal_entity?.name }}</td>
                  <td>{{ g.payroll_country_code }}</td>
                  <td>{{ frequency[g.pay_frequency] }}</td>
                  <td>{{ g.pay_currency }}</td>
                  <td class="text-caption">{{ Number(g.hours_per_day) }} h/day · {{ Number(g.annual_working_days) }} days/yr · {{ label(g.proration_method) }} · {{ label(g.rounding_mode) }}</td>
                  <td class="text-end text-no-wrap">
                    <v-btn size="small" variant="text" @click="showPeriods(g)">Periods</v-btn>
                    <v-btn v-if="can.manage" size="small" variant="text" @click="openCalendar(g)">Generate year</v-btn>
                    <v-btn v-if="can.manage" size="small" variant="text" @click="openGroup(g)">Edit</v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card>

          <v-card v-if="selectedPayGroupId" variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">Periods</v-card-title>
            <v-table density="compact">
              <thead><tr><th>#</th><th>Period</th><th>Cut-off</th><th>Pay date</th><th>Tax year</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <tr v-if="!periods.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No periods. Generate a calendar year.</td></tr>
                <tr v-for="p in periods" :key="p.id">
                  <td>{{ p.period_number }}</td>
                  <td>{{ date(p.period_start) }} – {{ date(p.period_end) }}</td>
                  <td>{{ date(p.cutoff_date) }}</td>
                  <td>{{ date(p.pay_date) }}</td>
                  <td>{{ p.tax_year }}</td>
                  <td><v-chip size="x-small" variant="tonal" :color="p.status === 'open' ? 'success' : 'grey'">{{ p.status }}</v-chip></td>
                  <td class="text-end text-no-wrap">
                    <v-btn v-if="can.manage && !p.runs_count" size="small" variant="text" @click="openPeriod(p)">Edit dates</v-btn>
                    <v-btn v-if="can.manage" size="small" variant="text" @click="togglePeriod(p)">{{ p.status === 'open' ? 'Close' : 'Reopen' }}</v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>

        <!-- Pay components -->
        <v-window-item value="components">
          <v-card variant="outlined" class="rounded-lg">
            <div class="d-flex pa-3"><v-spacer /><v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="openComponent()">Pay component</v-btn></div>
            <v-table density="comfortable">
              <thead><tr><th>Code</th><th>Name</th><th>Class</th><th>Category</th><th>Country treatments</th><th></th></tr></thead>
              <tbody>
                <tr v-for="c in payComponents" :key="c.id">
                  <td>{{ c.code }} <v-chip v-if="c.is_system" size="x-small" class="ml-1">system</v-chip></td>
                  <td>{{ c.name }} <v-chip v-if="!c.is_active" size="x-small" class="ml-1">Inactive</v-chip></td>
                  <td>{{ lineClass[c.line_class] }}</td>
                  <td>{{ label(c.category) }}</td>
                  <td class="text-caption">
                    <div v-for="t in c.treatments" :key="t.id">{{ t.country_code }}: {{ label(t.tax_treatment) }} / {{ label(t.contribution_treatment) }}</div>
                    <span v-if="!c.treatments.length" class="text-medium-emphasis">Localization default / not set</span>
                  </td>
                  <td class="text-end"><v-btn v-if="can.manage" size="small" variant="text" @click="openComponent(c)">Edit</v-btn></td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>

        <!-- Salary structures -->
        <v-window-item value="structures">
          <v-card variant="outlined" class="rounded-lg">
            <div class="d-flex pa-3"><v-spacer /><v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="openStructure()">Salary structure</v-btn></div>
            <v-table density="comfortable">
              <thead><tr><th>Name</th><th>Basis / currency</th><th>Effective</th><th>Grades</th><th></th></tr></thead>
              <tbody>
                <tr v-if="!salaryStructures.length"><td colspan="5" class="text-center text-medium-emphasis pa-6">No salary structures yet.</td></tr>
                <tr v-for="s in salaryStructures" :key="s.id">
                  <td>{{ s.name }}</td>
                  <td>{{ label(s.basis) }} · {{ s.currency }}</td>
                  <td>{{ date(s.effective_from) }} – {{ s.effective_to ? date(s.effective_to) : 'open' }}</td>
                  <td class="text-caption">
                    <div v-for="g in s.grades" :key="g.id">{{ g.pay_grade?.name }}: {{ money(g.minimum_amount, s.currency) }} – {{ money(g.maximum_amount, s.currency) }}</div>
                  </td>
                  <td class="text-end"><v-btn v-if="can.manage" size="small" variant="text" @click="openStructure(s)">Edit</v-btn></td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>

        <!-- Exchange rates -->
        <v-window-item value="rates">
          <v-card variant="outlined" class="rounded-lg">
            <div class="d-flex pa-3 align-center">
              <span class="text-caption text-medium-emphasis">A run converts with the latest rate on or before the period end and stores it in each payslip.</span>
              <v-spacer /><v-btn v-if="can.manage" color="primary" prepend-icon="mdi-plus" @click="openRate()">Exchange rate</v-btn>
            </div>
            <v-table density="compact">
              <thead><tr><th>Date</th><th>Pair</th><th class="text-end">Rate</th><th>Source</th></tr></thead>
              <tbody>
                <tr v-if="!exchangeRates.length"><td colspan="4" class="text-center text-medium-emphasis pa-6">No exchange rates.</td></tr>
                <tr v-for="r in exchangeRates" :key="r.id">
                  <td>{{ date(r.rate_date) }}</td>
                  <td>1 {{ r.base_currency }} = … {{ r.quote_currency }}</td>
                  <td class="text-end">{{ r.rate }}</td>
                  <td>{{ r.source }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>
      </v-window>

      <!-- Dialogs -->
      <v-dialog v-model="dialogs.entity" max-width="720">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ entityForm.id ? 'Edit' : 'New' }} legal entity</v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" md="4"><v-text-field v-model="entityForm.code" label="Code" variant="outlined" density="compact" :error-messages="entityForm.errors.code" /></v-col>
              <v-col cols="12" md="8"><v-text-field v-model="entityForm.name" label="Name" variant="outlined" density="compact" :error-messages="entityForm.errors.name" /></v-col>
              <v-col cols="12"><v-text-field v-model="entityForm.legal_name" label="Registered legal name" variant="outlined" density="compact" :error-messages="entityForm.errors.legal_name" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="entityForm.country_code" label="Country (ISO alpha-2)" variant="outlined" density="compact" maxlength="2" :error-messages="entityForm.errors.country_code" @update:model-value="(v) => (entityForm.country_code = (v || '').toUpperCase())" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="entityForm.subdivision_code" label="Subdivision (ISO 3166-2)" variant="outlined" density="compact" :error-messages="entityForm.errors.subdivision_code" /></v-col>
              <v-col cols="6" md="3"><v-select v-model="entityForm.functional_currency" :items="currencyItems" label="Functional currency" variant="outlined" density="compact" :error-messages="entityForm.errors.functional_currency" /></v-col>
              <v-col cols="6" md="3"><v-select v-model="entityForm.fiscal_year_start_month" :items="months" label="Fiscal year starts" variant="outlined" density="compact" :error-messages="entityForm.errors.fiscal_year_start_month" /></v-col>
              <v-col cols="12" md="6"><v-autocomplete v-model="entityForm.timezone" :items="options.timezones" label="Timezone" variant="outlined" density="compact" :error-messages="entityForm.errors.timezone" /></v-col>
              <v-col cols="12" md="6"><v-text-field v-model="entityForm.locale" label="Locale (e.g. en-PH)" variant="outlined" density="compact" :error-messages="entityForm.errors.locale" /></v-col>
              <v-col cols="12" md="6"><v-text-field v-model="entityForm.registration_number" label="Registration number" variant="outlined" density="compact" :error-messages="entityForm.errors.registration_number" /></v-col>
              <v-col cols="12" md="6"><v-text-field v-model="entityForm.tax_identifier" label="Employer tax ID" variant="outlined" density="compact" :error-messages="entityForm.errors.tax_identifier" /></v-col>
              <v-col cols="12"><v-text-field v-model="entityForm.address" label="Address" variant="outlined" density="compact" :error-messages="entityForm.errors.address" /></v-col>
              <v-col v-if="entityForm.country_code === 'PH'" cols="12" md="6">
                <v-select v-model="entityForm.ph_contribution_schedule" :items="[{ title: 'Whole month in the last period', value: 'last_period' }, { title: 'Split evenly, true up last period', value: 'split' }]" label="PH monthly contribution schedule (company policy)" variant="outlined" density="compact" clearable />
              </v-col>
              <v-col cols="12" md="6"><v-switch v-model="entityForm.is_active" label="Active" color="primary" density="compact" hide-details /></v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.entity = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="entityForm.processing" @click="saveEntity">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.users" max-width="520">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Payroll users · {{ usersForm.entityName }}</v-card-title>
          <v-card-text>
            <p class="text-body-2 mb-3">Users without access to all legal entities see this entity's payroll only when listed here.</p>
            <v-autocomplete v-model="usersForm.user_ids" :items="payrollUsers" item-title="username" item-value="id" label="Users" variant="outlined" density="compact" multiple chips closable-chips />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.users = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="usersForm.processing" @click="saveUsers">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.group" max-width="720">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ groupForm.id ? 'Edit' : 'New' }} pay group</v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" md="6"><v-select v-model="groupForm.legal_entity_id" :items="legalEntities" item-title="name" item-value="id" label="Legal entity" variant="outlined" density="compact" :error-messages="groupForm.errors.legal_entity_id" /></v-col>
              <v-col cols="12" md="2"><v-text-field v-model="groupForm.code" label="Code" variant="outlined" density="compact" :error-messages="groupForm.errors.code" /></v-col>
              <v-col cols="12" md="4"><v-text-field v-model="groupForm.name" label="Name" variant="outlined" density="compact" :error-messages="groupForm.errors.name" /></v-col>
              <v-col cols="6" md="4"><v-select v-model="groupForm.payroll_country_code" :items="localizationItems" label="Payroll country (localization)" variant="outlined" density="compact" :error-messages="groupForm.errors.payroll_country_code" /></v-col>
              <v-col cols="6" md="4"><v-select v-model="groupForm.pay_frequency" :items="frequencyItems" label="Pay frequency" variant="outlined" density="compact" :error-messages="groupForm.errors.pay_frequency" /></v-col>
              <v-col cols="6" md="4"><v-select v-model="groupForm.pay_currency" :items="currencyItems" label="Pay currency" variant="outlined" density="compact" :error-messages="groupForm.errors.pay_currency" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="groupForm.hours_per_day" label="Hours per day" variant="outlined" density="compact" :error-messages="groupForm.errors.hours_per_day" /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="groupForm.annual_working_days" label="Working days per year" variant="outlined" density="compact" hint="Divisor for daily rates (company/localization policy)" persistent-hint :error-messages="groupForm.errors.annual_working_days" /></v-col>
              <v-col cols="6" md="3"><v-select v-model="groupForm.proration_method" :items="optionItems(options.prorationMethods)" label="Proration" variant="outlined" density="compact" :error-messages="groupForm.errors.proration_method" /></v-col>
              <v-col cols="6" md="3"><v-select v-model="groupForm.rounding_mode" :items="optionItems(options.roundingModes)" label="Rounding (non-statutory)" variant="outlined" density="compact" :error-messages="groupForm.errors.rounding_mode" /></v-col>
              <v-col cols="12" md="6"><v-select v-model="groupForm.time_source" :items="[{ value: 'manual_inputs', title: 'Payroll time inputs (entered / imported)' }, { value: 'attendance', title: 'Approved attendance summaries' }]" label="Time quantities come from" variant="outlined" density="compact" :error-messages="groupForm.errors.time_source" /></v-col>
              <v-col cols="12" md="6"><v-switch v-model="groupForm.is_active" label="Active" color="primary" density="compact" hide-details /></v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.group = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="groupForm.processing" @click="saveGroup">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.calendar" max-width="480">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Generate calendar year</v-card-title>
          <v-card-text>
            <v-text-field v-model="calendarForm.calendar_year" label="Year" type="number" variant="outlined" density="compact" class="mb-2" :error-messages="calendarForm.errors.calendar_year" />
            <v-text-field v-model="calendarForm.pay_date_offset_days" label="Pay date = period end + days" type="number" variant="outlined" density="compact" class="mb-2" :error-messages="calendarForm.errors.pay_date_offset_days" />
            <v-text-field v-if="['weekly', 'biweekly'].includes(calendarGroup?.pay_frequency)" v-model="calendarForm.first_period_start" label="First period starts (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="calendarForm.errors.first_period_start" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.calendar = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="calendarForm.processing" @click="generateCalendar">Generate</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.period" max-width="420">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Period dates</v-card-title>
          <v-card-text>
            <v-text-field v-model="periodForm.cutoff_date" label="Cut-off (YYYY-MM-DD)" variant="outlined" density="compact" class="mb-2" :error-messages="periodForm.errors.cutoff_date" />
            <v-text-field v-model="periodForm.pay_date" label="Pay date (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="periodForm.errors.pay_date" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.period = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="periodForm.processing" @click="savePeriod">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.component" max-width="720">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ componentForm.id ? 'Edit' : 'New' }} pay component</v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" md="4"><v-text-field v-model="componentForm.code" label="Code (A-Z, 0-9, _)" variant="outlined" density="compact" :disabled="componentForm.is_system" :error-messages="componentForm.errors.code" /></v-col>
              <v-col cols="12" md="8"><v-text-field v-model="componentForm.name" label="Name" variant="outlined" density="compact" :error-messages="componentForm.errors.name" /></v-col>
              <v-col cols="12" md="4"><v-select v-model="componentForm.line_class" :items="lineClassItems" label="Class" variant="outlined" density="compact" :disabled="componentForm.is_system" :error-messages="componentForm.errors.line_class" /></v-col>
              <v-col cols="12" md="4"><v-select v-model="componentForm.category" :items="optionItems(options.categories)" label="Category" variant="outlined" density="compact" :disabled="componentForm.is_system" :error-messages="componentForm.errors.category" /></v-col>
              <v-col cols="12" md="4"><v-text-field v-model="componentForm.priority" label="Order" type="number" variant="outlined" density="compact" :disabled="componentForm.is_system" :error-messages="componentForm.errors.priority" /></v-col>
            </v-row>
            <div class="text-subtitle-2 mt-2 mb-1">Treatment per country</div>
            <div v-for="(t, i) in componentForm.treatments" :key="i" class="d-flex ga-2 mb-2">
              <v-select v-model="t.country_code" :items="localizationItems" label="Country" variant="outlined" density="compact" hide-details style="max-width: 160px" />
              <v-select v-model="t.tax_treatment" :items="treatmentItems(t.country_code, 'taxTreatments')" label="Tax" variant="outlined" density="compact" hide-details />
              <v-select v-model="t.contribution_treatment" :items="treatmentItems(t.country_code, 'contributionTreatments')" label="Contributions" variant="outlined" density="compact" hide-details />
              <v-btn icon="mdi-close" variant="text" size="small" @click="componentForm.treatments.splice(i, 1)" />
            </div>
            <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="componentForm.treatments.push({ country_code: null, tax_treatment: null, contribution_treatment: null })">Country treatment</v-btn>
            <div v-if="componentForm.errors.treatments" class="text-error text-caption mt-1">{{ componentForm.errors.treatments }}</div>
            <v-switch v-if="!componentForm.is_system" v-model="componentForm.is_active" label="Active" color="primary" density="compact" hide-details class="mt-2" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.component = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="componentForm.processing" @click="saveComponent">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.structure" max-width="720">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ structureForm.id ? 'Edit' : 'New' }} salary structure</v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" md="6"><v-text-field v-model="structureForm.name" label="Name" variant="outlined" density="compact" :error-messages="structureForm.errors.name" /></v-col>
              <v-col cols="6" md="3"><v-select v-model="structureForm.basis" :items="optionItems(options.bases)" label="Basis" variant="outlined" density="compact" :error-messages="structureForm.errors.basis" /></v-col>
              <v-col cols="6" md="3"><v-select v-model="structureForm.currency" :items="currencyItems" label="Currency" variant="outlined" density="compact" :error-messages="structureForm.errors.currency" /></v-col>
              <v-col cols="6" md="4"><v-text-field v-model="structureForm.effective_from" label="Effective from (YYYY-MM-DD)" variant="outlined" density="compact" :error-messages="structureForm.errors.effective_from" /></v-col>
              <v-col cols="6" md="4"><v-text-field v-model="structureForm.effective_to" label="Effective to (optional)" variant="outlined" density="compact" :error-messages="structureForm.errors.effective_to" /></v-col>
              <v-col cols="12" md="4"><v-select v-model="structureForm.legal_entity_id" :items="legalEntities" item-title="name" item-value="id" label="Legal entity (blank = all)" variant="outlined" density="compact" clearable /></v-col>
            </v-row>
            <div v-for="(g, i) in structureForm.grades" :key="i" class="d-flex ga-2 mb-2">
              <v-select v-model="g.pay_grade_id" :items="options.payGrades" item-title="name" item-value="id" label="Pay grade" variant="outlined" density="compact" hide-details />
              <v-text-field v-model="g.minimum_amount" label="Minimum" variant="outlined" density="compact" hide-details />
              <v-text-field v-model="g.midpoint_amount" label="Midpoint" variant="outlined" density="compact" hide-details />
              <v-text-field v-model="g.maximum_amount" label="Maximum" variant="outlined" density="compact" hide-details />
              <v-btn icon="mdi-close" variant="text" size="small" @click="structureForm.grades.splice(i, 1)" />
            </div>
            <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="structureForm.grades.push({ pay_grade_id: null, minimum_amount: '', midpoint_amount: '', maximum_amount: '' })">Grade range</v-btn>
            <div v-if="firstError(structureForm.errors)" class="text-error text-caption mt-2">{{ firstError(structureForm.errors) }}</div>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.structure = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="structureForm.processing" @click="saveStructure">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.rate" max-width="480">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Exchange rate</v-card-title>
          <v-card-text>
            <div class="d-flex ga-2">
              <v-select v-model="rateForm.base_currency" :items="currencyItems" label="1 unit of" variant="outlined" density="compact" :error-messages="rateForm.errors.base_currency" />
              <v-select v-model="rateForm.quote_currency" :items="currencyItems" label="equals … of" variant="outlined" density="compact" :error-messages="rateForm.errors.quote_currency" />
            </div>
            <v-text-field v-model="rateForm.rate" label="Rate" variant="outlined" density="compact" class="mb-2" :error-messages="rateForm.errors.rate" />
            <v-text-field v-model="rateForm.rate_date" label="Rate date (YYYY-MM-DD)" variant="outlined" density="compact" class="mb-2" :error-messages="rateForm.errors.rate_date" />
            <v-text-field v-model="rateForm.source" label="Source (e.g. central bank reference rate)" variant="outlined" density="compact" :error-messages="rateForm.errors.source" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.rate = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="rateForm.processing" @click="saveRate">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import payrollPage from "@/mixins/payrollPage";
import { FREQUENCY, LINE_CLASS } from "@/utils/payroll";

const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

export default {
  name: "PayrollSetup",
  components: { SidebarLayout, Head },
  mixins: [payrollPage],
  props: {
    legalEntities: { type: Array, default: () => [] },
    payGroups: { type: Array, default: () => [] },
    payComponents: { type: Array, default: () => [] },
    salaryStructures: { type: Array, default: () => [] },
    exchangeRates: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    selectedPayGroupId: { type: Number, default: null },
    options: { type: Object, required: true },
    tab: { type: String, default: "entities" },
    entityUsers: { type: Object, default: null },
    payrollUsers: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      activeTab: this.tab,
      frequency: FREQUENCY,
      lineClass: LINE_CLASS,
      months: MONTHS.map((m, i) => ({ title: m, value: i + 1 })),
      usersForm: useForm({ id: null, entityName: "", user_ids: [] }),
      dialogs: { users: false, entity: false, group: false, calendar: false, period: false, component: false, structure: false, rate: false },
      calendarGroup: null,
      entityForm: useForm({}),
      groupForm: useForm({}),
      calendarForm: useForm({ calendar_year: new Date().getFullYear() + 1, pay_date_offset_days: 0, first_period_start: "" }),
      periodForm: useForm({ id: null, cutoff_date: "", pay_date: "" }),
      componentForm: useForm({}),
      structureForm: useForm({}),
      rateForm: useForm({ base_currency: null, quote_currency: null, rate: "", rate_date: "", source: "" }),
    };
  },
  computed: {
    currencyItems() {
      return this.options.currencies.map((c) => ({ title: `${c.code} · ${c.name}`, value: c.code }));
    },
    localizationItems() {
      return this.options.localizations.map((l) => ({ title: `${l.code} · ${l.name}`, value: l.code }));
    },
    frequencyItems() {
      return this.options.frequencies.map((f) => ({ title: FREQUENCY[f] || f, value: f }));
    },
    lineClassItems() {
      return this.options.lineClasses.map((c) => ({ title: LINE_CLASS[c] || c, value: c }));
    },
  },
  methods: {
    optionItems(values) {
      return (values || []).map((v) => ({ title: this.label(v), value: v }));
    },
    treatmentItems(country, key) {
      const localization = this.options.localizations.find((l) => l.code === country);
      return localization ? Object.entries(localization[key]).map(([value, title]) => ({ value, title })) : [];
    },
    submit(form, method, url, dialog, transform = null) {
      const options = { preserveScroll: true, onSuccess: () => (this.dialogs[dialog] = false), onError: (errors) => this.showToast(this.firstError(errors), "error") };
      if (transform) form.transform(transform);
      form[method](url, options);
    },
    openUsers(e) {
      this.usersForm = useForm({ id: e.id, entityName: e.name, user_ids: [...(this.entityUsers?.[e.id] || [])] });
      this.dialogs.users = true;
    },
    saveUsers() {
      this.submit(this.usersForm, "put", route("payroll.setup.legal-entities.users", this.usersForm.id), "users", ({ user_ids }) => ({ user_ids }));
    },
    openEntity(e = null) {
      this.entityForm = useForm({
        id: e?.id || null,
        code: e?.code || "", name: e?.name || "", legal_name: e?.legal_name || "", country_code: e?.country_code || "",
        subdivision_code: e?.subdivision_code || "", registration_number: e?.registration_number || "", tax_identifier: e?.tax_identifier || "",
        functional_currency: e?.functional_currency || null, timezone: e?.timezone || "UTC", locale: e?.locale || "en",
        fiscal_year_start_month: e?.fiscal_year_start_month || 1, address: e?.address || "", is_active: e ? !!e.is_active : true,
        localization_settings: e?.localization_settings || {},
        ph_contribution_schedule: e?.localization_settings?.ph?.contribution_schedule || null,
      });
      this.dialogs.entity = true;
    },
    saveEntity() {
      const transform = ({ id, ph_contribution_schedule, ...data }) => {
        const settings = { ...(data.localization_settings || {}) };
        if (data.country_code === "PH") settings.ph = { ...(settings.ph || {}), contribution_schedule: ph_contribution_schedule };
        return { ...data, localization_settings: settings };
      };
      const f = this.entityForm;
      this.submit(f, f.id ? "put" : "post", f.id ? route("payroll.setup.legal-entities.update", f.id) : route("payroll.setup.legal-entities.store"), "entity", transform);
    },
    openGroup(g = null) {
      this.groupForm = useForm({
        id: g?.id || null, legal_entity_id: g?.legal_entity_id || this.legalEntities[0]?.id || null, code: g?.code || "", name: g?.name || "",
        payroll_country_code: g?.payroll_country_code || null, pay_frequency: g?.pay_frequency || null, pay_currency: g?.pay_currency || null,
        hours_per_day: g ? String(Number(g.hours_per_day)) : "", annual_working_days: g ? String(Number(g.annual_working_days)) : "",
        proration_method: g?.proration_method || "calendar_days", rounding_mode: g?.rounding_mode || "half_up", time_source: g?.time_source || "manual_inputs", is_active: g ? !!g.is_active : true,
      });
      this.dialogs.group = true;
    },
    saveGroup() {
      const f = this.groupForm;
      this.submit(f, f.id ? "put" : "post", f.id ? route("payroll.setup.pay-groups.update", f.id) : route("payroll.setup.pay-groups.store"), "group", ({ id, ...d }) => d);
    },
    showPeriods(g) {
      router.get(route("payroll.setup.index"), { tab: "groups", pay_group_id: g.id }, { preserveScroll: true, preserveState: true, only: ["periods", "selectedPayGroupId"] });
    },
    openCalendar(g) {
      this.calendarGroup = g;
      this.calendarForm.clearErrors();
      this.dialogs.calendar = true;
    },
    generateCalendar() {
      this.submit(this.calendarForm, "post", route("payroll.setup.pay-groups.calendars.store", this.calendarGroup.id), "calendar");
    },
    openPeriod(p) {
      this.periodForm = useForm({ id: p.id, cutoff_date: (p.cutoff_date || "").substring(0, 10), pay_date: (p.pay_date || "").substring(0, 10) });
      this.dialogs.period = true;
    },
    savePeriod() {
      this.submit(this.periodForm, "put", route("payroll.setup.periods.update", this.periodForm.id), "period", ({ id, ...d }) => d);
    },
    togglePeriod(p) {
      router.put(route("payroll.setup.periods.status", p.id), { status: p.status === "open" ? "closed" : "open" }, {
        preserveScroll: true,
        onError: (errors) => this.showToast(this.firstError(errors), "error"),
      });
    },
    openComponent(c = null) {
      this.componentForm = useForm({
        id: c?.id || null, is_system: !!c?.is_system, code: c?.code || "", name: c?.name || "", line_class: c?.line_class || "earning",
        category: c?.category || "allowance", priority: c?.priority || 100, is_active: c ? !!c.is_active : true, description: c?.description || "",
        legal_entity_id: c?.legal_entity_id || null,
        treatments: (c?.treatments || []).map((t) => ({ country_code: t.country_code, tax_treatment: t.tax_treatment, contribution_treatment: t.contribution_treatment })),
      });
      this.dialogs.component = true;
    },
    saveComponent() {
      const f = this.componentForm;
      this.submit(f, f.id ? "put" : "post", f.id ? route("payroll.setup.pay-components.update", f.id) : route("payroll.setup.pay-components.store"), "component", ({ id, is_system, ...d }) => d);
    },
    openStructure(s = null) {
      this.structureForm = useForm({
        id: s?.id || null, name: s?.name || "", basis: s?.basis || "monthly", currency: s?.currency || null,
        effective_from: (s?.effective_from || "").substring(0, 10), effective_to: s?.effective_to ? s.effective_to.substring(0, 10) : "",
        legal_entity_id: s?.legal_entity_id || null, is_active: s ? !!s.is_active : true,
        grades: (s?.grades || []).map((g) => ({ pay_grade_id: g.pay_grade_id, minimum_amount: g.minimum_amount, midpoint_amount: g.midpoint_amount || "", maximum_amount: g.maximum_amount })),
      });
      this.dialogs.structure = true;
    },
    saveStructure() {
      const f = this.structureForm;
      const transform = ({ id, ...d }) => ({ ...d, effective_to: d.effective_to || null, grades: d.grades.map((g) => ({ ...g, midpoint_amount: g.midpoint_amount || null })) });
      this.submit(f, f.id ? "put" : "post", f.id ? route("payroll.setup.salary-structures.update", f.id) : route("payroll.setup.salary-structures.store"), "structure", transform);
    },
    openRate() {
      this.rateForm.reset();
      this.rateForm.clearErrors();
      this.dialogs.rate = true;
    },
    saveRate() {
      this.submit(this.rateForm, "post", route("payroll.setup.exchange-rates.store"), "rate");
    },
  },
};
</script>

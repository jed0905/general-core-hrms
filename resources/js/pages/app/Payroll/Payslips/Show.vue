<template>
  <SidebarLayout>
    <Head :title="`Payslip ${payslip.payslip_number || ''}`" />
    <v-container fluid class="pa-6" style="max-width: 900px">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">Payslip {{ payslip.payslip_number || '(not final)' }}</h1>
          <p class="text-body-2 text-medium-emphasis">
            {{ payslip.employer.name }} · {{ date(payslip.period.start, payslip.locale) }} – {{ date(payslip.period.end, payslip.locale) }} · pay date {{ date(payslip.period.pay_date, payslip.locale) }} · {{ payslip.currency }}
          </p>
        </div>
        <v-btn variant="outlined" prepend-icon="mdi-file-pdf-box" :href="route('payroll.payslips.pdf', payslip.id)">PDF</v-btn>
      </div>

      <v-alert v-if="payslip.status !== 'finalized'" type="info" variant="tonal" density="compact" class="mb-4">
        This payslip belongs to a run that is {{ payslip.status }}; its figures can still change.
      </v-alert>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <div class="font-weight-medium">{{ payslip.employee.name }}</div>
        <div class="text-caption text-medium-emphasis">{{ payslip.employee.number }} · {{ payslip.employee.department || '—' }}</div>
      </v-card>

      <v-row>
        <v-col cols="12" md="6">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">Earnings</v-card-title>
            <v-table density="compact">
              <tbody>
                <tr v-for="(l, i) in payslip.earnings" :key="'e' + i">
                  <td>{{ l.description }} <span v-if="l.quantity" class="text-caption text-medium-emphasis">× {{ l.quantity }}</span></td>
                  <td class="text-end">{{ money(l.amount, payslip.currency, payslip.locale) }}</td>
                </tr>
                <tr class="font-weight-bold"><td>Gross pay</td><td class="text-end">{{ money(payslip.totals.gross_pay, payslip.currency, payslip.locale) }}</td></tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">Deductions</v-card-title>
            <v-table density="compact">
              <tbody>
                <tr v-if="!payslip.deductions.length"><td colspan="2" class="text-medium-emphasis">None</td></tr>
                <tr v-for="(l, i) in payslip.deductions" :key="'d' + i">
                  <td>{{ l.description }}</td>
                  <td class="text-end">{{ money(l.amount, payslip.currency, payslip.locale) }}</td>
                </tr>
                <tr class="font-weight-bold"><td>Total deductions</td><td class="text-end">{{ money(payslip.totals.deductions, payslip.currency, payslip.locale) }}</td></tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>
      </v-row>

      <v-card variant="tonal" color="primary" class="rounded-lg my-4 pa-4 d-flex justify-space-between">
        <span class="text-h6">Net pay</span>
        <span class="text-h6 font-weight-bold">{{ money(payslip.totals.net_pay, payslip.currency, payslip.locale) }}</span>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-card-title class="text-subtitle-1 font-weight-bold">Paid by your employer (not deducted from your pay)</v-card-title>
        <v-table density="compact">
          <tbody>
            <tr v-if="!payslip.employer_lines.length"><td colspan="2" class="text-medium-emphasis">None</td></tr>
            <tr v-for="(l, i) in payslip.employer_lines" :key="'r' + i">
              <td>{{ l.description }}</td>
              <td class="text-end">{{ money(l.amount, payslip.currency, payslip.locale) }}</td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-3 text-caption text-medium-emphasis">Taxable income this period: {{ money(payslip.totals.taxable_income, payslip.currency, payslip.locale) }}</div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import payrollPage from "@/mixins/payrollPage";

export default {
  name: "PayslipShow",
  components: { SidebarLayout, Head },
  mixins: [payrollPage],
  props: { payslip: { type: Object, required: true } },
};
</script>

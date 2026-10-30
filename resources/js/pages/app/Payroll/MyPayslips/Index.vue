<template>
  <SidebarLayout>
    <Head title="My Payslips" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">My Payslips</h1>
        <p class="text-body-2 text-medium-emphasis">Payslips appear here once a payroll run is finalized.</p>
      </div>
      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead><tr><th>Payslip</th><th>Employer</th><th>Period</th><th>Pay date</th><th class="text-end">Gross</th><th class="text-end">Net pay</th><th></th></tr></thead>
          <tbody>
            <tr v-if="!payslips.data.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No payslips yet.</td></tr>
            <tr v-for="p in payslips.data" :key="p.id">
              <td>{{ p.payslip_number }} <span v-if="p.run_type !== 'regular'" class="text-caption text-medium-emphasis">({{ p.run_type }})</span></td>
              <td>{{ p.entity }}</td>
              <td class="text-no-wrap">{{ date(p.period.start) }} – {{ date(p.period.end) }}</td>
              <td>{{ date(p.period.pay_date) }}</td>
              <td class="text-end">{{ money(p.gross_pay, p.currency) }}</td>
              <td class="text-end font-weight-medium">{{ money(p.net_pay, p.currency) }}</td>
              <td class="text-end text-no-wrap">
                <v-btn size="small" variant="text" :href="route('payroll.payslips.show', p.id)" @click.prevent="go(route('payroll.payslips.show', p.id))">View</v-btn>
                <v-btn size="small" variant="text" icon="mdi-file-pdf-box" :href="route('payroll.payslips.pdf', p.id)" />
              </td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="payslips" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import payrollPage from "@/mixins/payrollPage";

export default {
  name: "MyPayslips",
  components: { SidebarLayout, Head, Pagination },
  mixins: [payrollPage],
  props: { payslips: { type: Object, required: true } },
};
</script>

<template>
  <SidebarLayout>
    <Head title="Leave Policy Rules" />

    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-6">
        <div>
          <h1 class="text-h5 font-weight-bold">Leave Policy Rules</h1>
          <p class="text-body-2 text-medium-emphasis">
            Configure specific accrual rates, caps, and options per leave
            policy.
          </p>
        </div>

        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          elevation="0"
          v-if="can('leave_policy_rule.create')"
          @click="openModal()"
        >
          Add Policy Rule
        </v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg">
        <v-table hover>
          <thead>
            <tr>
              <th class="font-weight-bold">Policy</th>
              <th class="font-weight-bold">Leave Type</th>
              <th class="font-weight-bold">Accrual Method</th>
              <th class="font-weight-bold">Rate</th>
              <th class="font-weight-bold">Max Balance</th>
              <th class="font-weight-bold">Carry Forward</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rules.data || !rules.data.length">
              <td colspan="7" class="text-center py-6 text-medium-emphasis">
                No policy rules found.
              </td>
            </tr>

            <tr v-for="r in rules.data" :key="r.id">
              <td class="font-weight-medium">{{ r.policy?.name }}</td>
              <td>{{ r.leave_type?.name }} ({{ r.leave_type?.code }})</td>
              <td>
                <v-chip size="small" variant="tonal">
                  {{ r.accrual_method }}
                </v-chip>
              </td>
              <td>{{ r.accrual_rate }} / {{ r.grant_frequency }}</td>
              <td>{{ r.maximum_balance }} days</td>
              <td>{{ r.carry_forward_limit }} days</td>
              <td class="text-end">
                <v-btn
                  v-if="can('leave_policy_rule.update')"
                  icon="mdi-pencil-outline"
                  variant="text"
                  size="small"
                  @click="openModal(r)"
                />
                <v-btn
                  v-if="can('leave_policy_rule.delete')"
                  icon="mdi-delete-outline"
                  variant="text"
                  size="small"
                  color="error"
                  @click="confirmDelete(r)"
                />
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <!-- Create / Edit Dialog -->
      <v-dialog v-model="dialog" max-width="650" persistent>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">
            {{ isEditing ? "Edit Policy Rule" : "Add Policy Rule" }}
          </v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="6">
                <v-select
                  v-model="form.leave_policy_id"
                  label="Policy *"
                  :items="policies"
                  item-title="name"
                  item-value="id"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.leave_policy_id"
                />
              </v-col>
              <v-col cols="6">
                <v-select
                  v-model="form.leave_type_id"
                  label="Leave Type *"
                  :items="leaveTypes"
                  item-title="name"
                  item-value="id"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.leave_type_id"
                />
              </v-col>

              <v-col cols="6">
                <v-select
                  v-model="form.accrual_method"
                  label="Accrual Method *"
                  :items="[
                    'fixed',
                    'monthly',
                    'annually',
                    'per_payroll',
                    'none',
                  ]"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.accrual_method"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="form.accrual_rate"
                  type="number"
                  step="0.01"
                  label="Accrual Rate"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.accrual_rate"
                />
              </v-col>

              <v-col cols="6">
                <v-select
                  v-model="form.grant_frequency"
                  label="Grant Frequency *"
                  :items="[
                    'monthly',
                    'quarterly',
                    'annually',
                    'per_payroll',
                    'none',
                  ]"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.grant_frequency"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="form.maximum_balance"
                  type="number"
                  step="0.5"
                  label="Max Balance"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.maximum_balance"
                />
              </v-col>

              <v-col cols="6">
                <v-text-field
                  v-model="form.carry_forward_limit"
                  type="number"
                  step="0.5"
                  label="Carry Forward Limit"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.carry_forward_limit"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="form.minimum_service_months"
                  type="number"
                  label="Min. Service Months *"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.minimum_service_months"
                />
              </v-col>

              <v-col cols="6">
                <v-text-field
                  v-model="form.waiting_period"
                  type="number"
                  label="Waiting Period (Days)"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.waiting_period"
                />
              </v-col>
              <v-col cols="6">
                <v-switch
                  v-model="form.is_active"
                  label="Is Active?"
                  color="primary"
                  hide-details
                />
              </v-col>

              <v-col cols="6">
                <v-switch
                  v-model="form.allow_negative"
                  label="Allow Negative Bal.?"
                  color="primary"
                  hide-details
                />
              </v-col>
              <v-col cols="6">
                <v-switch
                  v-model="form.requires_approval"
                  label="Requires Approval?"
                  color="primary"
                  hide-details
                />
              </v-col>
              <v-col cols="6">
                <v-switch
                  v-model="form.requires_attachment"
                  label="Requires Attachment?"
                  color="primary"
                  hide-details
                />
              </v-col>
              <v-col cols="6">
                <v-switch
                  v-model="form.allows_half_day"
                  label="Allows Half Day?"
                  color="primary"
                  hide-details
                />
              </v-col>
              <v-col cols="6">
                <v-switch
                  v-model="form.allows_hourly"
                  label="Allows Hourly?"
                  color="primary"
                  hide-details
                />
              </v-col>
              <v-col cols="6">
                <v-switch
                  v-model="form.expires"
                  label="Expires End of Period?"
                  color="primary"
                  hide-details
                />
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="dialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="form.processing" @click="submit">
              Save Rule
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Delete Confirmation Dialog -->
      <v-dialog v-model="deleteDialog" max-width="420" persistent>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="text-h6 font-weight-bold">
            Confirm Delete
          </v-card-title>
          <v-card-text>
            Are you sure you want to delete the policy rule for
            <strong>{{ itemToDelete?.leave_type?.name }}</strong> under
            <strong>{{ itemToDelete?.policy?.name }}</strong
            >?
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="deleteDialog = false">
              Cancel
            </v-btn>
            <v-btn color="error" :loading="deleting" @click="destroy">
              Delete
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Toast Notification Snackbar -->
      <v-snackbar
        v-model="toast.show"
        :color="toast.color"
        :timeout="3500"
        location="top right"
      >
        {{ toast.message }}
        <template #actions>
          <v-btn
            variant="text"
            icon="mdi-close"
            size="small"
            @click="toast.show = false"
          />
        </template>
      </v-snackbar>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import permissions from "@/mixins/permissions";

export default {
  components: { SidebarLayout, Head },
  mixins: [permissions],
  props: { rules: Object, policies: Array, leaveTypes: Array },
  data() {
    return {
      dialog: false,
      isEditing: false,
      selectedId: null,
      deleteDialog: false,
      deleting: false,
      itemToDelete: null,
      toast: {
        show: false,
        message: "",
        color: "success",
      },
      form: useForm({
        leave_policy_id: null,
        leave_type_id: null,
        accrual_method: "monthly",
        accrual_rate: 1.25,
        grant_frequency: "monthly",
        maximum_balance: 15.0,
        carry_forward_limit: 5.0,
        minimum_service_months: 6,
        waiting_period: 0,
        is_active: true,
        allow_negative: false,
        requires_approval: true,
        requires_attachment: false,
        allows_half_day: true,
        allows_hourly: false,
        expires: false,
      }),
    };
  },
  methods: {


    openModal(r = null) {
      this.form.reset();
      this.form.clearErrors();

      if (r) {
        this.isEditing = true;
        this.selectedId = r.id;

        this.form.leave_policy_id = r.leave_policy_id;
        this.form.leave_type_id = r.leave_type_id;
        this.form.accrual_method = r.accrual_method;
        this.form.accrual_rate = r.accrual_rate;
        this.form.grant_frequency = r.grant_frequency;
        this.form.maximum_balance = r.maximum_balance;
        this.form.carry_forward_limit = r.carry_forward_limit;
        this.form.minimum_service_months = r.minimum_service_months;
        this.form.waiting_period = r.waiting_period ?? 0;

        // Strict Boolean cast for Vuetify v-switch compatibility
        this.form.is_active = Boolean(r.is_active ?? true);
        this.form.allow_negative = Boolean(r.allow_negative);
        this.form.requires_approval = Boolean(r.requires_approval);
        this.form.requires_attachment = Boolean(r.requires_attachment);
        this.form.allows_half_day = Boolean(r.allows_half_day);
        this.form.allows_hourly = Boolean(r.allows_hourly);
        this.form.expires = Boolean(r.expires);
      } else {
        this.isEditing = false;
        this.selectedId = null;
      }
      this.dialog = true;
    },

    submit() {
      if (this.isEditing) {
        this.form.put(route("leave.config.rules.update", this.selectedId), {
          onSuccess: () => {
            this.dialog = false;
            this.showToast("Policy rule updated successfully.", "success");
          },
          onError: () => {
            this.showToast(
              "Failed to update policy rule. Check highlighted inputs.",
              "error"
            );
          },
        });
      } else {
        this.form.post(route("leave.config.rules.store"), {
          onSuccess: () => {
            this.dialog = false;
            this.showToast("Policy rule created successfully.", "success");
          },
          onError: () => {
            this.showToast(
              "Failed to create policy rule. Check highlighted inputs.",
              "error"
            );
          },
        });
      }
    },
    
    confirmDelete(rule) {
      this.itemToDelete = rule;
      this.deleteDialog = true;
    },

    destroy() {
      if (!this.itemToDelete) return;
      this.deleting = true;
      router.delete(route("leave.config.rules.destroy", this.itemToDelete.id), {
        onSuccess: () => {
          this.showToast("Policy rule deleted successfully.");
        },
        onError: () => {
          this.showToast("Failed to delete policy rule.", "error");
        },
        onFinish: () => {
          this.deleting = false;
          this.deleteDialog = false;
          this.itemToDelete = null;
        },
      });
    },
  },
};
</script>
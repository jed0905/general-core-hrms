<template>
  <SidebarLayout>
    <Head title="Approval Workflows" />

    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between flex-wrap ga-4 mb-6">
        <div>
          <h1 class="text-h5 font-weight-bold">Approval Workflows</h1>
          <p class="text-body-2 text-medium-emphasis">
            Define who approves leave, in order. Changes apply to applications
            submitted afterwards; existing applications keep their approvers.
          </p>
        </div>

        <v-btn
          v-if="can('leave_approval_workflow.create')"
          color="primary"
          prepend-icon="mdi-plus"
          elevation="0"
          @click="openModal()"
        >
          Add Workflow
        </v-btn>
      </div>

      <v-row class="mb-2" density="compact">
        <v-col cols="12" sm="6" md="4">
          <v-text-field
            v-model="search"
            label="Search workflows"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            clearable
            hide-details
            @keyup.enter="applyFilters"
            @click:clear="clearSearch"
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="status"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            hide-details
            @update:model-value="applyFilters"
          />
        </v-col>
      </v-row>

      <v-card variant="outlined" class="rounded-lg">
        <v-table hover>
          <thead>
            <tr>
              <th class="font-weight-bold">Workflow</th>
              <th class="font-weight-bold">Applies To</th>
              <th class="font-weight-bold">Steps</th>
              <th class="font-weight-bold">Status</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!workflows.data.length">
              <td colspan="5" class="text-center py-6 text-medium-emphasis">
                No approval workflows found.
              </td>
            </tr>

            <tr v-for="w in workflows.data" :key="w.id">
              <td>
                <div class="font-weight-medium">{{ w.name }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ w.description || "N/A" }}
                </div>
              </td>
              <td>
                <span v-if="w.leave_policy">{{ w.leave_policy.name }}</span>
                <span v-else class="text-medium-emphasis">Company default</span>
              </td>
              <td>
                <div
                  v-for="step in w.steps"
                  :key="step.id"
                  class="text-body-2 py-1"
                >
                  <span class="font-weight-medium">{{ step.step_order }}.</span>
                  {{ describeStep(step) }}
                  <v-chip
                    v-if="!step.is_required"
                    size="x-small"
                    variant="tonal"
                    class="ml-1"
                    >optional</v-chip
                  >
                </div>
              </td>
              <td>
                <v-chip
                  size="small"
                  :color="w.is_active ? 'success' : 'error'"
                  variant="tonal"
                >
                  {{ w.is_active ? "Active" : "Archived" }}
                </v-chip>
              </td>
              <td class="text-end">
                <v-btn
                  v-if="can('leave_approval_workflow.update')"
                  icon="mdi-pencil-outline"
                  variant="text"
                  size="small"
                  @click="openModal(w)"
                />
                <v-btn
                  v-if="w.is_active && can('leave_approval_workflow.archive')"
                  icon="mdi-archive-outline"
                  variant="text"
                  size="small"
                  color="warning"
                  @click="confirmArchive(w)"
                />
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <div class="mt-4">
        <Pagination :meta="workflows" />
      </div>

      <!-- Create / Edit Dialog -->
      <v-dialog v-model="dialog" max-width="760" persistent scrollable>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">
            {{ isEditing ? "Edit Approval Workflow" : "Add Approval Workflow" }}
          </v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" sm="7">
                <v-text-field
                  v-model="form.name"
                  label="Name *"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.name"
                />
              </v-col>
              <v-col cols="12" sm="5">
                <v-select
                  v-model="form.leave_policy_id"
                  :items="policyOptions"
                  label="Applies to"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.leave_policy_id"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.description"
                  label="Description"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.description"
                />
              </v-col>
              <v-col cols="12">
                <v-switch
                  v-model="form.is_active"
                  label="Active"
                  color="primary"
                  density="compact"
                  hide-details
                />
              </v-col>

              <v-col cols="12">
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-subtitle-2 font-weight-bold"
                    >Approval Steps *</span
                  >
                  <v-btn
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    prepend-icon="mdi-plus"
                    :disabled="form.steps.length >= maxSteps"
                    @click="addStep"
                    >Add Step</v-btn
                  >
                </div>
                <div
                  v-if="form.errors.steps"
                  class="text-caption text-error mb-2"
                >
                  {{ form.errors.steps }}
                </div>

                <v-card
                  v-for="(step, idx) in form.steps"
                  :key="step.key"
                  variant="outlined"
                  class="pa-3 mb-2 rounded-lg"
                >
                  <v-row density="compact" align="center">
                    <v-col cols="12" sm="1" class="font-weight-bold">
                      {{ idx + 1 }}.
                    </v-col>
                    <v-col cols="12" sm="4">
                      <v-select
                        v-model="step.approver_type"
                        :items="approverTypeOptions"
                        label="Approver *"
                        variant="outlined"
                        density="compact"
                        :error-messages="form.errors[`steps.${idx}.approver_type`]"
                      />
                    </v-col>
                    <v-col cols="12" sm="4">
                      <v-autocomplete
                        v-if="step.approver_type === 'specific_employee'"
                        v-model="step.approver_employee_id"
                        :items="employeeOptions"
                        label="Employee *"
                        variant="outlined"
                        density="compact"
                        :error-messages="
                          form.errors[`steps.${idx}.approver_employee_id`]
                        "
                      />
                      <v-checkbox
                        v-else
                        v-model="step.is_required"
                        label="Required"
                        density="compact"
                        hide-details
                      />
                    </v-col>
                    <v-col cols="12" sm="3" class="text-end">
                      <v-btn
                        icon="mdi-arrow-up"
                        variant="text"
                        size="small"
                        :disabled="idx === 0"
                        @click="moveStep(idx, -1)"
                      />
                      <v-btn
                        icon="mdi-arrow-down"
                        variant="text"
                        size="small"
                        :disabled="idx === form.steps.length - 1"
                        @click="moveStep(idx, 1)"
                      />
                      <v-btn
                        icon="mdi-delete-outline"
                        variant="text"
                        size="small"
                        color="error"
                        :disabled="form.steps.length <= 1"
                        @click="removeStep(idx)"
                      />
                    </v-col>
                    <v-col
                      v-if="step.approver_type === 'specific_employee'"
                      cols="12"
                      class="pt-0"
                    >
                      <v-checkbox
                        v-model="step.is_required"
                        label="Required"
                        density="compact"
                        hide-details
                      />
                    </v-col>
                  </v-row>
                </v-card>

                <p class="text-caption text-medium-emphasis mt-2">
                  If a required step can't be assigned (for example, the
                  employee has no supervisor), the employee can't submit until
                  HR fixes it. Optional steps are skipped instead.
                </p>
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="dialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="form.processing" @click="submit">
              Save
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Archive Confirmation -->
      <v-dialog v-model="archiveDialog" max-width="420">
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">Archive workflow?</v-card-title>
          <v-card-text>
            <strong>{{ itemToArchive?.name }}</strong> will no longer be used
            for new applications. Applications already submitted keep their
            approvers.
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="archiveDialog = false">Cancel</v-btn>
            <v-btn color="warning" :loading="archiving" @click="archive">
              Archive
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import permissions from "@/mixins/permissions";

const APPROVER_TYPE_LABELS = {
  immediate_supervisor: "Immediate supervisor",
  higher_supervisor: "Supervisor's supervisor",
  specific_employee: "Specific employee",
};

let stepKey = 0;

export default {
  name: "LeaveApprovalWorkflowsIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [permissions],
  props: {
    workflows: Object,
    policies: Array,
    employees: Array,
    approverTypes: Array,
    filters: Object,
  },
  data() {
    return {
      maxSteps: 10,
      dialog: false,
      isEditing: false,
      selectedId: null,
      archiveDialog: false,
      archiving: false,
      itemToArchive: null,
      search: this.filters?.search ?? "",
      status: this.filters?.is_active ?? null,
      statusOptions: [
        { title: "All", value: null },
        { title: "Active", value: "1" },
        { title: "Archived", value: "0" },
      ],
      form: useForm({
        name: "",
        description: "",
        leave_policy_id: null,
        is_active: true,
        steps: [],
      }),
    };
  },
  computed: {
    policyOptions() {
      return [
        { title: "Company default (all policies)", value: null },
        ...(this.policies || []).map((p) => ({
          title: p.is_active ? p.name : `${p.name} (archived)`,
          value: p.id,
        })),
      ];
    },
    approverTypeOptions() {
      return (this.approverTypes || []).map((type) => ({
        title: APPROVER_TYPE_LABELS[type] ?? type,
        value: type,
      }));
    },
    employeeOptions() {
      return (this.employees || []).map((e) => ({
        title: `${e.emp_last_name}, ${e.emp_first_name} (${e.employee_number})`,
        value: e.id,
      }));
    },
  },
  methods: {
    newStep(overrides = {}) {
      stepKey += 1;
      return {
        key: stepKey,
        id: null,
        approver_type: "immediate_supervisor",
        approver_employee_id: null,
        is_required: true,
        ...overrides,
      };
    },
    describeStep(step) {
      const label = APPROVER_TYPE_LABELS[step.approver_type] ?? step.approver_type;
      if (step.approver_type === "specific_employee" && step.approver_employee) {
        return `${label}: ${step.approver_employee.emp_first_name} ${step.approver_employee.emp_last_name}`;
      }
      return label;
    },
    openModal(workflow = null) {
      this.form.reset();
      this.form.clearErrors();
      if (workflow) {
        this.isEditing = true;
        this.selectedId = workflow.id;
        this.form.name = workflow.name;
        this.form.description = workflow.description ?? "";
        this.form.leave_policy_id = workflow.leave_policy_id;
        this.form.is_active = workflow.is_active;
        this.form.steps = (workflow.steps || []).map((s) =>
          this.newStep({
            id: s.id,
            approver_type: s.approver_type,
            approver_employee_id: s.approver_employee_id,
            is_required: Boolean(s.is_required),
          })
        );
      } else {
        this.isEditing = false;
        this.selectedId = null;
        this.form.steps = [this.newStep()];
      }
      this.dialog = true;
    },
    addStep() {
      if (this.form.steps.length < this.maxSteps) {
        this.form.steps.push(this.newStep());
      }
    },
    removeStep(idx) {
      if (this.form.steps.length > 1) this.form.steps.splice(idx, 1);
    },
    moveStep(idx, delta) {
      const target = idx + delta;
      if (target < 0 || target >= this.form.steps.length) return;
      const steps = [...this.form.steps];
      [steps[idx], steps[target]] = [steps[target], steps[idx]];
      this.form.steps = steps;
    },
    submit() {
      const payload = (data) => ({
        ...data,
        steps: data.steps.map(({ key, id, ...rest }) =>
          this.isEditing && id ? { id, ...rest } : rest
        ),
      });
      const options = { onSuccess: () => (this.dialog = false) };

      if (this.isEditing) {
        this.form
          .transform(payload)
          .put(
            route("leave.config.workflows.update", {
              leaveApprovalWorkflow: this.selectedId,
            }),
            options
          );
      } else {
        this.form
          .transform(payload)
          .post(route("leave.config.workflows.store"), options);
      }
    },
    confirmArchive(workflow) {
      this.itemToArchive = workflow;
      this.archiveDialog = true;
    },
    archive() {
      if (!this.itemToArchive) return;
      this.archiving = true;
      router.delete(
        route("leave.config.workflows.destroy", {
          leaveApprovalWorkflow: this.itemToArchive.id,
        }),
        {
          preserveScroll: true,
          onFinish: () => {
            this.archiving = false;
            this.archiveDialog = false;
            this.itemToArchive = null;
          },
        }
      );
    },
    applyFilters() {
      router.get(
        route("leave.config.workflows.index"),
        { search: this.search || undefined, is_active: this.status ?? undefined },
        { preserveState: true, preserveScroll: true }
      );
    },
    clearSearch() {
      this.search = "";
      this.applyFilters();
    },
  },
};
</script>

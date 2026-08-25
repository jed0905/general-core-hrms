<template>
  <JobStructureTabs v-model:activeTab="activeTab" />
  <!-- <pre>{{ salaryLists }}</pre> -->

  <v-row>
    <v-col cols="12">
      <v-card rounded="xl">
        <v-card-text>
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="v-card-title">{{ salary_schedule.data.name }}</div>
            <ButtonSuccess
              name="Add"
              prepend-icon="mdi-plus"
              @click="addSalaryGradeDialog = true"
            />
          </div>

          <div class="d-flex align-center justify-end mb-3">
            <v-btn
              prepend-icon="mdi-upload"
              color="primary"
              class="mr-2"
              rounded="xl"
              :loading="uploading"
              @click="isUploadFileDialog = true"
            >
              {{ uploading ? "Uploading..." : "Upload File" }}
            </v-btn>
            <v-btn
              prepend-icon="mdi-download"
              color="secondary"
              class="mr-2"
              rounded="xl"
              @click="downloadTemplate"
            >
              Download Template
            </v-btn>
          </div>

          <v-divider class="my-4"></v-divider>

          <!-- Matrix View Table-->
          <v-table rounded="xl">
            <thead>
              <tr>
                <th class="text-center">Salary Grade</th>
                <th class="text-center">Step 1</th>
                <th class="text-center">Step 2</th>
                <th class="text-center">Step 3</th>
                <th class="text-center">Step 4</th>
                <th class="text-center">Step 5</th>
                <th class="text-center">Step 6</th>
                <th class="text-center">Step 7</th>
                <th class="text-center">Step 8</th>
                <!-- <th class="text-center">Actions</th> -->
              </tr>
            </thead>
            <tbody>
              <tr v-for="grade in salary_grades" :key="grade.salary_grade_id">
                <td class="text-center font-weight-bold">
                  {{ grade.salary_grade }}
                </td>

                <td
                  v-for="step in 8"
                  :key="step"
                  class="text-center clickable-cell"
                  @click="
                    openEditDialog(
                      grade.salary_grade,
                      step,
                      grade.steps[step] ?? null
                    )
                  "
                >
                  {{ formatCurrency(grade.steps[step] ?? 0) }}
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <!-- Add Salary Grade Dialog -->
  <v-dialog v-model="addSalaryGradeDialog" max-width="600px">
    <v-form @submit.prevent="saveCustomSalaryGrade()">
      <v-card class="pa-4 ma-4" rounded="xl">
        <v-card-title class="text-h6">Add Custom Salary Grade</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12">
              <v-text-field
                label="Custom Grade( This will add custom salary grade e.g. COS-1 )"
                variant="outlined"
                density="compact"
                v-model="customeSalaryGradeForm.grade"
                :error-messages="
                  this.v$.customeSalaryGradeForm.grade.$errors.map(
                    (e) => e.$message
                  )
                "
                required
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Custom Step ( This will add custom salary step e.g. COS-STEP-1 )"
                variant="outlined"
                density="compact"
                v-model="customeSalaryGradeForm.step"
                :error-messages="
                  this.v$.customeSalaryGradeForm.step.$errors.map(
                    (e) => e.$message
                  )
                "
                required
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Amount"
                variant="outlined"
                density="compact"
                v-model="customeSalaryGradeForm.amount"
                :error-messages="
                  this.v$.customeSalaryGradeForm.amount.$errors.map(
                    (e) => e.$message
                  )
                "
                required
                type="number"
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>
        <div class="d-flex align-center justify-end">
          <ButtonMuted
            name="Cancel"
            class="mr-2"
            @click="addSalaryGradeDialog = false"
          />
          <ButtonSuccess type="submit" name="Save" />
        </div>
      </v-card>
    </v-form>
  </v-dialog>

  <!-- Edit Salary Dialog -->
  <v-dialog v-model="editDialog" max-width="500px">
    <v-form @submit.prevent="saveUpdatedSalaryGradePerRow()">
      <v-card class="pa-4 ma-4">
        <v-card-title class="text-h6">
          Edit Salary - Grade {{ editingGrade }} Step {{ editingStep }}
        </v-card-title>

        <v-card-text>
          <input
            type="hidden"
            v-model="updateSalaryGradeperRowForm.grade"
            model-value="editingGrade"
          />
          <input
            type="hidden"
            v-model="updateSalaryGradeperRowForm.step"
            model-value="editingStep"
          />
          <v-text-field
            v-model="updateSalaryGradeperRowForm.amount"
            variant="outlined"
            density="compact"
            label="Salary Amount"
            type="number"
            step="0.01"
            min="0"
            prefix="₱"
            :error-messages="
              this.v$.updateSalaryGradeperRowForm.amount.$errors.map(
                (e) => e.$message
              )
            "
            required
          ></v-text-field>
        </v-card-text>
        <div class="d-flex align-center justify-end">
          <ButtonMuted name="Cancel" class="mr-2" @click="editDialog = false" />
          <ButtonUpdate name="Update" type="submit" />
        </div>
      </v-card>
    </v-form>
  </v-dialog>

  <!-- Upload File Dialog: choose file then upload -->
  <v-dialog v-model="isUploadFileDialog" max-width="500px" persistent>
    <v-card class="pa-4 ma-4" rounded="xl">
      <v-card-title class="text-h6">Upload Salary File</v-card-title>
      <v-card-text>
        <p class="text-body-2 text-medium-emphasis mb-3">
          Choose a CSV or Excel file (max 5MB). It will update salary grades to
          match the file contents.
        </p>
        <input
          ref="dialogFileInput"
          type="file"
          accept=".csv,.xlsx,.xls"
          style="display: none"
          @change="onDialogFileSelected"
        />
        <v-btn
          block
          variant="outlined"
          color="primary"
          prepend-icon="mdi-file-upload"
          @click="$refs.dialogFileInput?.click()"
        >
          Choose File
        </v-btn>
        <p v-if="pendingFile" class="text-body-2 mt-2 mb-0">
          Selected: <strong>{{ pendingFile.name }}</strong>
          <span class="text-medium-emphasis">
            ({{ formatFileSize(pendingFile.size) }})</span
          >
        </p>
      </v-card-text>
      <v-card-actions class="pt-0">
        <div class="d-flex align-center justify-end w-100">
          <ButtonMuted name="Cancel" class="mr-2" @click="closeUploadDialog" />
          <ButtonSuccess
            :disabled="!pendingFile"
            :loading="uploading"
            name="Upload"
            @click="uploadFile"
          />
        </div>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- <pre>{{ salary_grades }}</pre> -->
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import JobStructureTabs from "@/components/JobStructureTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required } from "@vuelidate/validators";
import ButtonUpdate from "@/components/ButtonUpdate.vue";

export default {
  layout: SidebarLayout,
  components: {
    JobStructureTabs,
    ButtonSuccess,
    ButtonMuted,
    ButtonUpdate,
  },

  props: {
    salary_schedule: Object,
    salary_grades: Array,
  },
  data() {
    return {
      activeTab: "salary",
      isUploadFileDialog: false,
      addSalaryGradeDialog: false,
      editSalaryGradePerRowDialog: false,

      editDialog: false,
      editingGrade: null,
      editingStep: null,
      editingAmount: null,
      saving: false,
      uploading: false,
      pendingFile: null,

      sg: [
        "1",
        "2",
        "3",
        "4",
        "5",
        "6",
        "7",
        "8",
        "9",
        "10",
        "11",
        "12",
        "13",
        "14",
        "15",
        "16",
        "17",
        "18",
        "19",
        "20",
        "21",
        "22",
        "23",
        "24",
        "25",
        "26",
        "27",
        "28",
        "29",
        "30",
        "31",
        "32",
        "33",
      ],
      step: ["1", "2", "3", "4", "5", "6", "7", "8"],
      v$: useVuelidate(),

      customeSalaryGradeForm: useForm({
        grade: null,
        step: null,
        amount: null,
      }),

      salaryRowId: null,

      updateSalaryGradeperRowForm: useForm({
        grade: null,
        step: null,
        amount: null,
      }),

      uploadFileForm: useForm({
        file: null,
      }),
    };
  },

  validations: {
    customeSalaryGradeForm: {
      grade: { required },
      step: { required },
      amount: { required },
    },

    updateSalaryGradeperRowForm: {
      grade: { required },
      step: { required },
      amount: { required },
    },
  },

  computed: {
    salaryList() {
      const list = [];
      this.salary_grades.forEach((grade) => {
        for (let step = 1; step <= 8; step++) {
          list.push({
            grade: grade.grade,
            step: step,
            amount: grade[`step${step}`],
            id: `${grade.grade}-${step}`,
          });
        }
      });
      return list;
    },
  },

  methods: {
    formatCurrency(amount) {
      return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
      }).format(amount);
    },

    openEditDialog(grade, step, amount) {
      this.updateSalaryGradeperRowForm.grade = grade;
      this.updateSalaryGradeperRowForm.step = step;
      this.updateSalaryGradeperRowForm.amount = amount;
      this.editDialog = true;
    },

    openEditPerRow(id) {
      this.editSalaryGradePerRowDialog = true;
      this.salaryRowid = id;
    },

    closeEditDialog() {
      this.editDialog = false;
      this.updateSalaryGradeperRowForm.grade = null;
      this.updateSalaryGradeperRowForm.step = null;
      this.updateSalaryGradeperRowForm.amount = null;
      if (this.$refs.editForm) {
        this.$refs.editForm.reset();
      }
    },

    saveCustomSalaryGrade() {
      this.v$.customeSalaryGradeForm.$validate();

      if (!this.v$.customeSalaryGradeForm.$invalid) {
        alert("backend save");
      }
    },

    saveUpdatedSalaryGradePerRow() {
      this.v$.updateSalaryGradeperRowForm.$validate();
      if (!this.v$.updateSalaryGradeperRowForm.$invalid) {
        alert("backend save");
      }
    },

    onDialogFileSelected(event) {
      const file = event.target.files?.[0];
      if (!file) return;

      const allowedTypes = [
        "text/csv",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "application/vnd.ms-excel",
      ];
      const isCsv = file.name.toLowerCase().endsWith(".csv");

      if (!allowedTypes.includes(file.type) && !isCsv) {
        this.showToast("Please upload a valid CSV or Excel file.", "error");
        this.clearDialogFileInput();
        return;
      }

      if (file.size > 5 * 1024 * 1024) {
        this.showToast("File size must be less than 5MB.", "error");
        this.clearDialogFileInput();
        return;
      }

      this.pendingFile = file;
    },

    uploadFile() {
      if (!this.pendingFile) {
        this.showToast("Please choose a file first.", "error");
        return;
      }

      this.uploading = true;

      // Set file properly
      this.uploadFileForm.file = this.pendingFile;

      this.uploadFileForm.post(
        route("hrmanagement.jobstructure.salary.upload", this.salary_schedule.data.id),
        {
          forceFormData: true, // IMPORTANT for file upload

          onSuccess: () => {
            // Inertia flash is already reactive via $page.props
            const flash = this.$page.props.flash;

            if (flash?.error) {
              this.showToast(flash.error, "error");
            } else {
              this.showToast(
                flash?.success || "Salary matrix uploaded successfully!",
                "success"
              );
            }

            // Reset file after success
            this.pendingFile = null;
            this.uploadFileForm.reset();

            this.closeUploadDialog();
          },

          onError: (errors) => {
            const errorMessages = Object.values(errors || {})
              .flat()
              .join(" ");

            this.showToast(
              errorMessages || "Failed to upload file. Please try again.",
              "error"
            );
          },

          onFinish: () => {
            this.uploading = false;
          },
        }
      );
    },

    closeUploadDialog() {
      this.isUploadFileDialog = false;
      this.pendingFile = null;
      this.clearDialogFileInput();
    },

    clearDialogFileInput() {
      if (this.$refs.dialogFileInput) {
        this.$refs.dialogFileInput.value = "";
      }
    },

    formatFileSize(bytes) {
      if (bytes < 1024) return `${bytes} B`;
      if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
      return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    },

    downloadTemplate() {
      window.location.href = route(
        "hrmanagement.jobstructure.salary.download-template"
      );
    },
  },
};
</script>

<style scoped>
.v-table {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
}

.v-table th {
  background-color: #f5f5f5;
  font-weight: 600;
  padding: 12px 8px;
  border-bottom: 2px solid #e0e0e0;
}

.v-table td {
  padding: 12px 8px;
  border-bottom: 1px solid #e0e0e0;
}

.v-table tbody tr:hover {
  background-color: #f8f9fa;
}

.clickable-cell {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.clickable-cell:hover {
  background-color: #e3f2fd !important;
  font-weight: 500;
}
</style>

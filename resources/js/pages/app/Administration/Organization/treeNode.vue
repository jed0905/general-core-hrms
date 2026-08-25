<template>
  <li>
    <div class="node-box" :class="`level-${currentDepth}`">
      <div class="node-content">
        <!-- Expand/Collapse Button -->
        <button
          v-if="node.children.length"
          class="toggle"
          @click="node.expanded = !node.expanded"
        >
          {{ node.expanded ? "▾" : "▸" }}
        </button>

        <!-- Node Label -->
        <span class="node-label">
          <template v-if="node.code">{{ node.code }}: </template
          >{{ node.name }} ({{ node.shortcut }})
        </span>
      </div>

      <!-- Actions -->
      <div v-if="editMode" class="actions">
        <v-btn
          size="x-small"
          color="red-darken-4"
          icon="mdi-delete"
          variant="tonal"
          @click="openDeleteDialog"
        ></v-btn>
        <v-btn
          size="x-small"
          color="yellow-darken-4"
          icon="mdi-pencil"
          variant="tonal"
          @click="openEditDialog"
        ></v-btn>
        <v-btn
          size="x-small"
          color="starbucks-green"
          icon="mdi-plus"
          variant="tonal"
          @click="openAddSubUnitDialog"
          v-if="currentDepth != 5"
          :title="
            currentDepth >= 5 ? 'Maximum 5 levels allowed' : getAddButtonTitle()
          "
        ></v-btn>
      </div>
    </div>

    <!-- Children -->
    <ul v-show="node.expanded" v-if="node.children.length">
      <TreeNode
        v-for="(child, idx) in node.children"
        :key="child.id"
        :node="child"
        :parent="node"
        :index="idx"
        :edit-mode="editMode"
        :depth="currentDepth + 1"
        @add="$emit('add', $event)"
        @edit="$emit('edit', $event)"
        @delete="$emit('delete', $event)"
      />
    </ul>
  </li>

  <!-- add department dialog -->
  <v-dialog v-model="addDepartmentUnitDialog" max-width="800">
    <v-form @submit.prevent="submitDepartmentForm()">
      <v-card class="pa-4 ma-4">
        <v-card-title>
          <h4>Add Department</h4>
        </v-card-title>
        <v-card-text>
          <v-row>
            <input
              type="hidden"
              v-model="addDepartmentUnitForm.operating_unit_id"
            />
            <input type="hidden" v-model="addDepartmentUnitForm.parent_id" />
            <v-col cols="12">
              <v-text-field
                label="Department Name"
                variant="outlined"
                density="compact"
                v-model="addDepartmentUnitForm.name"
                :error-messages="
                  v$.addDepartmentUnitForm.name.$errors.map((e) => e.$message)
                "
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Shortcut"
                variant="outlined"
                density="compact"
                v-model="addDepartmentUnitForm.shortcut"
                :error-messages="
                  v$.addDepartmentUnitForm.shortcut.$errors.map(
                    (e) => e.$message
                  )
                "
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>
        <div class="d-flex align-center justify-end">
          <ButtonMuted
            class="mr-2"
            @click="addDepartmentUnitDialog = false"
            name="Cancel"
          />
          <ButtonSuccess name="Save" type="submit" />
        </div>
      </v-card>
    </v-form>
  </v-dialog>

  <!-- Add Sub-Unit Dialog -->
  <v-dialog v-model="addSubUnitDialog" max-width="800">
    <v-form @submit.prevent="submitSubUnitForm()">
      <v-card class="pa-4 ma-4">
        <v-card-title class="d-flex justify-space-between align-center">
          <h4>{{ getAddDialogTitle() }}</h4>
        </v-card-title>
        <v-card-text>
          <!-- department_unit_id -->
          <input
            type="hidden"
            v-model="addDepartmentUnitForm.operating_unit_id"
          />
          <!-- Parent ID -->
          <input type="hidden" v-model="addDepartmentUnitForm.parent_id" />

          <v-row>
            <v-col cols="12">
              <v-text-field
                :label="getAddDialogNameLabel()"
                variant="outlined"
                density="compact"
                v-model="addDepartmentUnitForm.name"
                :error-messages="
                  v$.addDepartmentUnitForm.name.$errors.map((e) => e.$message)
                "
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Shortcut"
                variant="outlined"
                density="compact"
                v-model="addDepartmentUnitForm.shortcut"
                :error-messages="
                  v$.addDepartmentUnitForm.shortcut.$errors.map(
                    (e) => e.$message
                  )
                "
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>
        <div class="d-flex align-center justify-end">
          <ButtonMuted
            class="mr-2"
            @click="addSubUnitDialog = false"
            name="Cancel"
          />
          <ButtonSuccess name="Save" type="submit" />
        </div>
      </v-card>
    </v-form>
    <!-- <pre>{{ this.node }}</pre> -->
  </v-dialog>

  <!-- Edit Operating Unit Dialog (for root level) -->
  <v-dialog v-model="editOperatingUnitDialog" max-width="800">
    <v-form @submit.prevent="submitEditOperatingUnitForm()">
      <v-card class="pa-4 ma-4">
        <v-card-title class="d-flex justify-space-between align-center">
          <h4>Edit Operating Unit</h4>
        </v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12">
              <v-text-field
                label="Operating Unit Prefix"
                variant="outlined"
                density="compact"
                v-model="v$.editOperatingUnitForm.prefix_id.$model"
                :error-messages="
                  v$.editOperatingUnitForm.prefix_id.$errors.map(
                    (e) => e.$message
                  )
                "
                @keypress="onlyNumbers"
                @input="filterOperatingUnitNumbers"
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Operating Unit Name"
                variant="outlined"
                density="compact"
                v-model="v$.editOperatingUnitForm.name.$model"
                :error-messages="
                  v$.editOperatingUnitForm.name.$errors.map((e) => e.$message)
                "
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Shortcut"
                variant="outlined"
                density="compact"
                v-model="v$.editOperatingUnitForm.shortcut.$model"
                :error-messages="
                  v$.editOperatingUnitForm.shortcut.$errors.map(
                    (e) => e.$message
                  )
                "
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-autocomplete
                label="Province"
                variant="outlined"
                density="compact"
                :items="provinces"
                item-value="name"
                item-title="name"
                hidden-no-data
                v-model="v$.editOperatingUnitForm.province.$model"
                :error-messages="
                  v$.editOperatingUnitForm.province.$errors.map(
                    (e) => e.$message
                  )
                "
                return-object
              ></v-autocomplete>
            </v-col>
            <v-col cols="12">
              <v-autocomplete
                label="City/Municipality"
                variant="outlined"
                density="compact"
                :items="filtered_municipality_edit"
                item-value="name"
                item-title="name"
                v-model="v$.editOperatingUnitForm.city_municipality.$model"
                :error-messages="
                  v$.editOperatingUnitForm.city_municipality.$errors.map(
                    (e) => e.$message
                  )
                "
                return-object
              ></v-autocomplete>
            </v-col>
            <v-col cols="12">
              <v-autocomplete
                label="Barangay"
                variant="outlined"
                density="compact"
                :items="filtered_barangay_edit"
                item-title="name"
                item-value="name"
                v-model="v$.editOperatingUnitForm.barangay.$model"
                :error-messages="
                  v$.editOperatingUnitForm.barangay.$errors.map(
                    (e) => e.$message
                  )
                "
                return-object
              ></v-autocomplete>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Zip Code"
                variant="outlined"
                density="compact"
                readonly
                v-model="editOperatingUnitForm.zip"
                return-object
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>
        <!-- <pre>{{ node }}</pre> -->
        <div class="d-flex align-center justify-end">
          <ButtonMuted
            class="mr-2"
            @click="editOperatingUnitDialog = false"
            name="Cancel"
          />
          <ButtonSuccess name="Save" type="submit" />
        </div>
      </v-card>
    </v-form>
  </v-dialog>

  <!-- Edit Department Dialog (for level 2) -->
  <v-dialog v-model="editDepartmentDialog" max-width="800">
    <v-form @submit.prevent="submitEditDepartmentForm()">
      <v-card class="pa-4 ma-4">
        <v-card-title class="d-flex justify-space-between align-center">
          <h4>Edit Department</h4>
        </v-card-title>
        <v-card-text>
          <v-row>
            <input
              type="hidden"
              v-model="editDepartmentForm.operating_unit_id"
            />
            <input type="hidden" v-model="editDepartmentForm.parent_id" />
            <v-col cols="12">
              <v-text-field
                label="Department Name"
                variant="outlined"
                density="compact"
                v-model="v$.editDepartmentForm.name.$model"
                :error-messages="
                  v$.editDepartmentForm.name.$errors.map((e) => e.$message)
                "
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Shortcut"
                variant="outlined"
                density="compact"
                v-model="v$.editDepartmentForm.shortcut.$model"
                :error-messages="
                  v$.editDepartmentForm.shortcut.$errors.map((e) => e.$message)
                "
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>
        <div class="d-flex align-center justify-end">
          <ButtonMuted
            class="mr-2"
            @click="editDepartmentDialog = false"
            name="Cancel"
          />
          <ButtonSuccess name="Save" type="submit" />
        </div>
      </v-card>
    </v-form>
  </v-dialog>

  <!-- Edit Sub-Unit Dialog (for level 3+) -->
  <v-dialog v-model="editSubUnitDialog" max-width="800">
    <v-form @submit.prevent="submitEditSubUnitForm()">
      <v-card class="pa-4 ma-4">
        <v-card-title class="d-flex justify-space-between align-center">
          <h4>Edit Sub-Unit</h4>
        </v-card-title>
        <v-card-text>
          <v-row>
            <input
              type="hidden"
              v-model="editDepartmentForm.operating_unit_id"
            />
            <input type="hidden" v-model="editDepartmentForm.parent_id" />
            <v-col cols="12">
              <v-text-field
                label="Sub-Unit Name"
                variant="outlined"
                density="compact"
                v-model="editDepartmentForm.name"
                :error-messages="
                  v$.editDepartmentForm.name.$errors.map((e) => e.$message)
                "
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Shortcut"
                variant="outlined"
                density="compact"
                v-model="editDepartmentForm.shortcut"
                :error-messages="
                  v$.editDepartmentForm.shortcut.$errors.map((e) => e.$message)
                "
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>
        <div class="d-flex align-center justify-end">
          <ButtonMuted
            class="mr-2"
            @click="editSubUnitDialog = false"
            name="Cancel"
          />
          <ButtonSuccess name="Save" type="submit" />
        </div>
      </v-card>
    </v-form>
  </v-dialog>

  <!-- Delete Confirmation Dialog -->
  <v-dialog v-model="deleteConfirmationDialog" max-width="500">
    <v-form @submit.prevent="submitDeleteDepartmentForm()">
      <v-card class="pa-4">
        <v-card-title class="d-flex align-center">
          <v-icon color="red-darken-4" class="mr-3">mdi-alert-circle</v-icon>
          <span>Confirm Delete</span>
        </v-card-title>
        <v-card-text>
          <p class="text-body-1">
            Are you sure you want to delete
            <strong>{{ itemToDelete?.name }}</strong
            >?
          </p>
          <p
            v-if="getTotalChildrenCount(itemToDelete) > 0"
            class="text-caption text-red-darken-4 mt-2"
          >
            ⚠️ This will also delete
            {{ getTotalChildrenCount(itemToDelete) }} sub-unit{{
              getTotalChildrenCount(itemToDelete) > 1 ? "s" : ""
            }}.
          </p>
        </v-card-text>
        <div class="d-flex justify-end">
          <ButtonMuted
            class="mr-2"
            @click="deleteConfirmationDialog = false"
            name="Cancel"
          />
          <ButtonSuccess type="submit" :loading="deleteLoading" name="Delete" />
        </div>
      </v-card>
    </v-form>
  </v-dialog>
  <!-- <pre>{{ sub_units }}</pre> -->
</template>

<script>
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, minLength, maxLength } from "@vuelidate/validators";
import { province, municipality } from "@/composables/psgc.js";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";

export default {
  name: "TreeNode",
  components: {
    ButtonSuccess,
    ButtonMuted,
  },
  props: {
    node: Object,
    parent: Object,
    index: Number,
    editMode: Boolean,

    depth: {
      type: Number,
      default: 1,
    },
  },

  computed: {
    currentDepth() {
      return this.depth;
    },

    filtered_municipality_edit() {
      const provinceObj = this.provinces.find(
        (p) => p.name === this.node.province
      );

      this.province = provinceObj.code;

      if (this.editOperatingUnitForm.province) {
        return province.getMunicipalities(this.province);
      }
      return [];
    },

    filtered_barangay_edit() {
      const municipalityObj = this.municipality.find(
        (m) => m.name === this.node.city_municipality
      );

      this.city_municipality = municipalityObj.code;

      if (this.editOperatingUnitForm.city_municipality) {
        return municipality.getBarangays(this.city_municipality);
      }
      return [];
    },
  },

  data() {
    return {
      addDepartmentUnitDialog: false,
      addSubUnitDialog: false,
      editOperatingUnitDialog: false,
      editDepartmentDialog: false,
      editSubUnitDialog: false, // Added for sub-units
      deleteConfirmationDialog: false,
      deleteLoading: false,
      itemToDelete: null,
      v$: useVuelidate(),

      provinces: province.all(),
      municipality: municipality.all(),

      addDepartmentUnitForm: useForm({
        name: null,
        shortcut: null,
        operating_unit_id:
          this.node.type === "unit"
            ? this.node.id
            : this.node.operating_unit_id, // if it's department or sub-unit, inherit the OU id
        parent_id: this.node.type === "department" ? this.node.id : null,
      }),

      addSubUnitForm: useForm({
        name: null,
        shortcut: null,
        department_unit_id: this.node.department_unit_id ?? this.node.id,
        parent_id: this.node.type === "sub_unit" ? this.node.id : null,
      }),

      editOperatingUnitForm: useForm({
        prefix_id: null,
        name: null,
        shortcut: null,
        province: null,
        city_municipality: null,
        barangay: null,
        zip: null,
      }),

      editDepartmentForm: useForm({
        name: null,
        shortcut: null,
        operating_unit_id: null,
        parent_id: null,
      }),

      provinces: province.all(),
      province: "",
      city_municipality: "",
      barangay: "",
      zip: "",
    };
  },

  validations: {
    addDepartmentUnitForm: {
      name: { required, minLength: minLength(2), maxLength: maxLength(100) },
      shortcut: { required, minLength: minLength(1), maxLength: maxLength(10) },
    },

    addSubUnitForm: {
      name: { required, minLength: minLength(2), maxLength: maxLength(100) },
      shortcut: { required, minLength: minLength(1), maxLength: maxLength(10) },
      department_unit_id: { required },
    },

    editOperatingUnitForm: {
      prefix_id: {
        required,
        minLength: minLength(1),
        maxLength: maxLength(10),
      },
      name: { required, minLength: minLength(2), maxLength: maxLength(100) },
      shortcut: { required, minLength: minLength(1), maxLength: maxLength(10) },
      province: { required },
      city_municipality: { required },
      barangay: { required },
    },

    editDepartmentForm: {
      name: { required, minLength: minLength(2), maxLength: maxLength(100) },
      shortcut: { required, minLength: minLength(1), maxLength: maxLength(10) },
      operating_unit_id: { required },
    },

    editSubUnitForm: {
      // Added for sub-units
      name: { required, minLength: minLength(2), maxLength: maxLength(100) },
      shortcut: { required, minLength: minLength(1), maxLength: maxLength(10) },
    },
  },

  methods: {
    openEditDialog() {
      // Check if this is a root level operating unit (depth 1), department (depth 2), or sub-unit (depth 3+)
      if (this.currentDepth === 1) {
        // Edit Operating Unit Dialog (root level)

        // this.editOperatingUnitForm.province = provinceObj || null;
        console.log(1);
        this.editOperatingUnitForm.prefix_id = this.node.prefix ?? null;
        this.editOperatingUnitForm.name = this.node.name ?? null;
        this.editOperatingUnitForm.shortcut = this.node.shortcut || null;
        this.editOperatingUnitForm.province = this.node.province || null;
        this.editOperatingUnitForm.city_municipality =
          this.node.city_municipality || null;
        this.editOperatingUnitForm.barangay = this.node.barangay || null;
        this.editOperatingUnitForm.zip = this.node.zip || null;
        this.editOperatingUnitDialog = true;
      } else if (this.currentDepth === 2) {
        // Edit Department Dialog (level 2)
        this.editDepartmentForm.name = this.node.name || null;
        this.editDepartmentForm.shortcut = this.node.shortcut || null;
        this.editDepartmentForm.operating_unit_id = this.node.operating_unit_id;
        this.editDepartmentForm.parent_id = null;
        this.editDepartmentDialog = true;
      } else {
        // Edit Sub-Unit Dialog (level 3+)
        this.editDepartmentForm.name = this.node.name || null;
        this.editDepartmentForm.shortcut = this.node.shortcut || null;
        this.editDepartmentForm.operating_unit_id = this.node.operating_unit_id;
        this.editDepartmentForm.parent_id = this.node.parent_id;
        this.editSubUnitDialog = true;
      }
    },

    openDeleteDialog() {
      this.itemToDelete = this.node;
      this.deleteConfirmationDialog = true;
    },

    openAddSubUnitDialog() {
      // Check if we've reached the maximum depth
      // console.log(this.currentDepth);
      if (this.currentDepth === 1) {
        this.addDepartmentUnitDialog = true;
      } else if (this.currentDepth === 2) {
        this.addSubUnitDialog = true;
      } else {
        this.addSubUnitDialog = true;
      }
    },

    getAddDialogTitle() {
      if (this.currentDepth === 1) {
        return `Add Department to ${this.node.name}`;
      } else if (this.currentDepth === 2) {
        return `Add Sub-Unit to ${this.node.name}`;
      } else {
        return `Add Sub-Unit to ${this.node.name}`;
      }
    },

    getAddDialogNameLabel() {
      if (this.currentDepth === 1) {
        return "Department Name";
      } else if (this.currentDepth === 2) {
        return "Sub-Unit Name";
      } else {
        return "Sub-Unit Name";
      }
    },

    getAddButtonTitle() {
      if (this.currentDepth === 1) {
        return "Add Department";
      } else if (this.currentDepth === 2) {
        return "Add Sub-Unit";
      } else {
        return "Add Sub-Unit";
      }
    },

    submitDepartmentForm() {
      this.v$.addDepartmentUnitForm.$touch();

      if (this.v$.addDepartmentUnitForm.$invalid) return;

      this.addDepartmentUnitForm.post(
        route("administration.organization.storeDepartment"),
        {
          onSuccess: () => {
            this.addDepartmentUnitDialog = false;
            this.showToast("Department added successfully!", "success");
            this.$emit("add", {
              id: this.addDepartmentUnitForm.id,
              name: this.addDepartmentUnitForm.name,
              shortcut: this.addDepartmentUnitForm.shortcut,
              operating_unit_id: this.addDepartmentUnitForm.operating_unit_id,
            });
            this.resetAddDepartmentForm();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
            this.resetAddDepartmentForm();
          },
        }
      );
    },

    submitSubUnitForm() {
      if (this.currentDepth >= 5) {
        this.addSubUnitDialog = false;
        return;
      }

      this.v$.addDepartmentUnitForm.$touch();

      if (this.v$.addDepartmentUnitForm.$invalid) return;

      this.addDepartmentUnitForm.post(
        route("administration.organization.storeDepartment"),
        {
          onSuccess: () => {
            this.addSubUnitDialog = false;
            this.showToast("Sub-Unit added successfully!", "success");
            this.$emit("add", {
              id: this.addDepartmentUnitForm.id,
              name: this.addDepartmentUnitForm.name,
              shortcut: this.addDepartmentUnitForm.shortcut,
              operating_unit_id: this.addDepartmentUnitForm.operating_unit_id,
              parent_id: this.addDepartmentUnitForm.parent_id,
            });
            this.resetAddDepartmentForm();
          },
          onError: (errors) => {
            this.addDepartmentUnitForm = false;
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
            this.resetAddDepartmentForm();
          },
        }
      );
    },

    submitEditOperatingUnitForm() {
      this.v$.editOperatingUnitForm.$touch();

      if (this.v$.editOperatingUnitForm.$invalid) return;

      this.editOperatingUnitForm.put(
        route("administration.organization.updateOperatingUnit", {
          id: this.node.id,
        }),
        {
          onSuccess: () => {
            this.editDepartmentDialog = false;
            this.showToast("Operating unit edited successfully!", "success");
          },

          onError: (errors) => {
            this.editDepartmentDialog = false;
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    submitEditDepartmentForm() {
      console.log(this.editDepartmentForm);
      this.v$.editDepartmentForm.$touch();

      if (this.v$.editDepartmentForm.$invalid) return;

      this.editDepartmentForm.put(
        route("administration.organization.updateDepartment", {
          id: this.node.id,
        }),
        {
          onSuccess: () => {
            this.editDepartmentDialog = false;
            this.showToast("Department edited successfully!", "success");
          },

          onError: (errors) => {
            this.editDepartmentDialog = false;
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    submitEditSubUnitForm() {
      this.v$.editDepartmentForm.$touch();

      if (this.v$.editDepartmentForm.$invalid) return;

      this.editDepartmentForm.put(
        route("administration.organization.updateDepartment", {
          id: this.node.id,
        }),
        {
          onSuccess: () => {
            this.editSubUnitDialog = false;
            this.showToast("Sub-Unit edited successfully!", "success");
          },

          onError: (errors) => {
            this.editSubUnitDialog = false;
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    submitDeleteDepartmentForm() {
      if (this.currentDepth > 1) {
        this.$inertia.delete(
          route("administration.organization.deleteDepartment", {
            id: this.node.id,
          }),
          {
            onSuccess: () => {
              this.deleteConfirmationDialog = false;
              this.showToast("Department/Sub-Unit deleted successfully!", "success");
            },

            onError: (errors) => {
              this.deleteConfirmationDialog = false;
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      }
    },

    resetAddSubUnitForm() {
      this.addDepartmentUnitForm.name = null;
      this.addDepartmentUnitForm.shortcut = null;
      this.addDepartmentUnitForm.operating_unit_id = null;
      this.addDepartmentUnitForm.parent_id = null;
      this.v$.addDepartmentUnitForm.$reset();
    },

    resetAddSubUnitForm() {
      // this.addSubUnitForm.code = '';
      this.addSubUnitForm.name = "";
      this.addSubUnitForm.shortcut = "";
      this.v$.addSubUnitForm.$reset();
    },

    resetEditOperatingUnitForm() {
      this.editOperatingUnitForm.code = "";
      this.editOperatingUnitForm.name = "";
      this.editOperatingUnitForm.shortcut = "";
      this.editOperatingUnitForm.province = null;
      this.editOperatingUnitForm.municipality = null;
      this.editOperatingUnitForm.barangay = null;
      this.v$.editOperatingUnitForm.$reset();
    },

    resetEditDepartmentForm() {
      this.editDepartmentForm.name = "";
      this.editDepartmentForm.shortcut = "";
      this.v$.editDepartmentForm.$reset();
    },

    resetEditSubUnitForm() {
      this.editSubUnitForm.name = "";
      this.editSubUnitForm.shortcut = "";
      this.v$.editSubUnitForm.$reset();
    },

    handleEditOperatingUnit() {
      this.v$.editOperatingUnitForm.$touch();
      if (!this.v$.editOperatingUnitForm.$invalid) {
        // Update the node data
        this.node.code = this.editOperatingUnitForm.code;
        this.node.name = this.editOperatingUnitForm.name;
        this.node.shortcut = this.editOperatingUnitForm.shortcut;
        this.node.province = this.editOperatingUnitForm.province;
        this.node.municipality = this.editOperatingUnitForm.municipality;
        this.node.barangay = this.editOperatingUnitForm.barangay;
        this.editOperatingUnitDialog = false;
      }
    },

    handleEditDepartment() {
      this.v$.editDepartmentForm.$touch();
      if (!this.v$.editDepartmentForm.$invalid) {
        // Update the node data
        this.node.name = this.editDepartmentForm.name;
        this.node.shortcut = this.editDepartmentForm.shortcut;
        this.editDepartmentDialog = false;
      }
    },

    handleEditSubUnit() {
      // Added for sub-units
      this.v$.editSubUnitForm.$touch();
      if (!this.v$.editSubUnitForm.$invalid) {
        // Update the node data
        this.node.name = this.editSubUnitForm.name;
        this.node.shortcut = this.editSubUnitForm.shortcut;
        this.editSubUnitDialog = false;
      }
    },

    onlyNumbers(event) {
      const charCode = event.which ? event.which : event.keyCode;
      if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        event.preventDefault();
      }
    },

    filterOperatingUnitNumbers() {
      this.editOperatingUnitForm.code = this.editOperatingUnitForm.code.replace(
        /[^0-9]/g,
        ""
      );
    },

    filterDepartmentNumbers() {
      this.editDepartmentForm.code = this.editDepartmentForm.code.replace(
        /[^0-9]/g,
        ""
      );
    },

    filterSubUnitNumbers() {
      this.addSubUnitForm.code = this.addSubUnitForm.code.replace(
        /[^0-9]/g,
        ""
      );
    },

    filterSubUnitEditNumbers() {
      this.editOperatingUnitForm.code = this.editOperatingUnitForm.code.replace(
        /[^0-9]/g,
        ""
      );
    },

    getTotalChildrenCount(node) {
      if (!node || !node.children) return 0;

      let count = node.children.length;
      for (let child of node.children) {
        count += this.getTotalChildrenCount(child);
      }
      return count;
    },

    confirmDelete() {
      this.deleteLoading = true;
      this.$emit("delete", this.itemToDelete);
      this.deleteConfirmationDialog = false;
      this.itemToDelete = null;
      this.deleteLoading = false;
    },
  },
};
</script>

<style scoped lang="scss">
/* Tree Lines */
ul {
  list-style-type: none; /* removes bullets */
  list-style: none;
  margin: 0;
  padding-left: 20px;
  position: relative;

  /* &::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    border-left: 5px solid rgba(0, 0, 0, 0.2);
  } */

  li {
    list-style-type: none; /* removes bullets */
    position: relative;
    padding-left: 20px;
    margin-bottom: 15px;
    border-left: 2px solid rgba(0, 0, 0, 1);

    &::before {
      content: "";
      position: absolute;
      transform: translateY(-50%);
      top: 22px;
      left: 0;
      width: 21px;
      border-top: 3px solid rgba(0, 0, 0, 1);
    }

    &:last-child::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: 0;
      border-left: none;
    }
  }
}

/* Node Styles */
.node-box {
  border-radius: 8px;
  padding: 8px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 15px;
  transition: background-color 0.3s ease;
}

/* Level-specific background colors */
.node-box.level-1 {
  background: #e3f2fd; /* Light blue for operating units (root level) */
}

.node-box.level-2 {
  background: #f3e5f5; /* Light purple for departments */
}

.node-box.level-3 {
  background: #e8f5e8; /* Light green for sub-units */
}

.node-box.level-4 {
  background: #fff3e0; /* Light orange for sub-sub-units */
}

.node-box.level-5 {
  background: #fde4e3; /* Light blue for sub-sub-units */
}

.node-content {
  display: flex;
  align-items: center;
  flex: 1;
}

.toggle {
  background: none;
  border: none;
  cursor: pointer;
  margin-right: 5px;
}

.actions button {
  background: none;
  border: none;
  cursor: pointer;
  margin-left: 4px;
}
</style>

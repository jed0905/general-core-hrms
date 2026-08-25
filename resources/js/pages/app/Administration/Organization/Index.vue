<template>
  <div class="organization-structure">
    <!-- Header -->
    <!-- Edit Toggle Button -->
    <v-row class="mb-4">
      <v-col cols="12" class="d-flex justify-space-between align-center">
        <h2>Organization Structure</h2>
        <div class="d-flex align-center">
          <span class="mr-2 text-body-2">Edit Mode</span>
          <v-switch
            v-model="editMode"
            color="starbucks-green"
            hide-details
            inset
          ></v-switch>
        </div>
      </v-col>
    </v-row>

    <v-row class="mb-4">
      <v-col cols="12">
        <div class="d-flex align-center justify-space-between">
          <h4>{{ $page.props.company_name }}</h4>
          <ButtonSuccess
            v-if="editMode === true"
            @click="addOperatingUnitDialog = true"
            name="Add"
            prepend-icon="mdi-plus"
          />
        </div>
      </v-col>
    </v-row>

    <!-- Tree -->
    <div class="tree">
      <ul>
        <TreeNode
          v-for="unit in operatingUnits"
          :key="unit.id"
          :node="unit"
          :edit-mode="editMode"
          :depth="1"
          @add="addChild"
          @edit="editNode"
          @delete="deleteNode"
        />
      </ul>
    </div>
  </div>

  <!-- Add Operating Unit Dialog -->
  <v-dialog v-model="addOperatingUnitDialog" max-width="800">
    <v-form @submit.prevent="submitOperatingUnitForm()">
      <v-card class="pa-4 ma-4">
        <v-card-title class="d-flex justify-space-between align-center">
          <h4>Add Operating Unit</h4>
          <v-btn
            color="red-darken-4"
            icon="mdi-close"
            size="x-small"
            @click="
              (addOperatingUnitDialog = false), handleResetAddOperatingUnit()
            "
          ></v-btn>
        </v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12">
              <v-text-field
                label="Prefix"
                variant="outlined"
                density="compact"
                v-model="v$.addOperatingUnitForm.prefix_id.$model"
                :error-messages="
                  v$.addOperatingUnitForm.prefix_id.$errors.map(
                    (e) => e.$message
                  )
                "
                @keypress="onlyNumbers"
                @input="filterNumbers"
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Name"
                variant="outlined"
                density="compact"
                v-model="v$.addOperatingUnitForm.name.$model"
                :error-messages="
                  v$.addOperatingUnitForm.name.$errors.map((e) => e.$message)
                "
              ></v-text-field>
            </v-col>
            <v-col cols="12">
              <v-text-field
                label="Shortcut"
                variant="outlined"
                density="compact"
                v-model="v$.addOperatingUnitForm.shortcut.$model"
                :error-messages="
                  v$.addOperatingUnitForm.shortcut.$errors.map(
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
                v-model="v$.addOperatingUnitForm.province.$model"
                :error-messages="
                  v$.addOperatingUnitForm.province.$errors.map(
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
                :items="filtered_municipalities"
                item-value="name"
                item-title="name"
                v-model="v$.addOperatingUnitForm.city_municipality.$model"
                :error-messages="
                  v$.addOperatingUnitForm.city_municipality.$errors.map(
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
                :items="filtered_barangay"
                item-title="name"
                item-value="name"
                v-model="v$.addOperatingUnitForm.barangay.$model"
                :error-messages="
                  v$.addOperatingUnitForm.barangay.$errors.map(
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
                :model-value="
                  addOperatingUnitForm.city_municipality
                    ? addOperatingUnitForm.city_municipality.zip_code
                    : null
                "
                return-object
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>
        <div class="d-flex align-center justify-end">
          <ButtonMuted
            class="mr-2"
            @click="addOperatingUnitDialog = false"
            name="Cancel"
          />
          <ButtonSuccess type="submit" name="Save" />
        </div>
      </v-card>
    </v-form>
  </v-dialog>

  <!-- <pre>{{ sub_units }}</pre> -->
</template>

<script>
import TreeNode from "../Organization/treeNode.vue";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, minLength, maxLength } from "@vuelidate/validators";
import { province, municipality } from "@/composables/psgc.js";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";

export default {
  layout: SidebarLayout,

  name: "OrganizationStructure",

  components: {
    TreeNode,
    SidebarLayout,
    ButtonSuccess,
    ButtonMuted,
  },

  props: {
    operating_units: Array,
    departments: Array,
  },

  data() {
    return {
      editMode: true,
      addOperatingUnitDialog: false,

      editOperatingUnitDialog: false,
      itemToDelete: null,
      deleteParent: null,
      deleteIndex: null,
      addSubUnitDialog: false,

      provinces: province.all(),
      province: "",
      municipality: "",
      barangay: "",
      zip: "",

      v$: useVuelidate(),

      addOperatingUnitForm: useForm({
        prefix_id: null,
        name: null,
        shortcut: null,
        province: null,
        city_municipality: null,
        barangay: null,
        zip: null,
      }),

      operatingUnits: [],
    };
  },

  computed: {
    filtered_municipalities() {
      /* if current model of province is not null */
      this.addOperatingUnitForm.city_municipality = null;
      if (this.addOperatingUnitForm.province) {
        return province.getMunicipalities(
          this.addOperatingUnitForm.province.code
        );
      }
      return [];
    },

    filtered_barangay() {
      if (this.addOperatingUnitForm.city_municipality) {
        return municipality.getBarangays(
          this.addOperatingUnitForm.city_municipality.code
        );
      }
      return [];
    },

    // Remove the computed property - we'll use a method instead
  },

  validations() {
    return {
      addOperatingUnitForm: {
        prefix_id: { required },
        name: { required },
        shortcut: { required },
        province: { required },
        city_municipality: { required },
        barangay: { required },
        zip: { required },
      },
    };
  },

  mounted() {
    this.buildTreeStructure();
  },

  watch: {
    operating_units: {
      handler() {
        this.buildTreeStructure();
      },
      deep: true,
    },
    departments: {
      handler() {
        this.buildTreeStructure();
      },
      deep: true,
    },
    sub_units: {
      handler() {
        this.buildTreeStructure();
      },
      deep: true,
    },
  },

  methods: {
    buildTreeStructure() {
      this.operatingUnits = this.operating_units.map((unit) => {
        // recursive function to build department tree
        const buildDepartmentTree = (parentId = null) => {
          return this.departments
            .filter(
              (dep) =>
                dep.operating_unit_id === unit.id && dep.parent_id === parentId
            )
            .map((dep) => ({
              id: dep.id,
              code: dep.code ?? "",
              name: dep.name,
              shortcut: dep.shortcut,
              operating_unit_id: dep.operating_unit_id,
              parent_id: dep.parent_id,
              type: "department",
              expanded: true,
              children: buildDepartmentTree(dep.id), // recursion
            }));
        };

        return {
          id: unit.id,
          prefix: unit.prefix_id,
          name: unit.name,
          shortcut: unit.shortcut,
          province: unit.province,
          city_municipality: unit.city_municipality,
          barangay: unit.barangay,
          zip: unit.zip,
          type: "unit",
          expanded: true,
          children: buildDepartmentTree(null), // start from root (no parent)
        };
      });
    },

    addChild(newNode) {
      // Refresh the operating units and departments props
      if (newNode) {
        this.$inertia.reload({
          only: ["operating_units", "departments", "sub_units"],
        });
      }
    },

    editNode(node) {
      alert(`Edit: ${node.name}`);
    },

    deleteNode(nodeToDelete) {
      // Find and remove the node from the appropriate array
      // Check if it's a root level operating unit
      const rootIndex = this.operatingUnits.findIndex(
        (unit) => unit.id === nodeToDelete.id
      );
      if (rootIndex !== -1) {
        // Delete from operating units array
        this.operatingUnits.splice(rootIndex, 1);
      } else {
        // Delete from parent's children array
        const parent = this.findParentNode(nodeToDelete);
        if (parent && parent.children) {
          const index = parent.children.findIndex(
            (child) => child.id === nodeToDelete.id
          );
          if (index !== -1) {
            parent.children.splice(index, 1);
          }
        }
      }
    },

    findParentNode(nodeToFind) {
      // Recursively search for the parent of the node to delete
      const searchInArray = (nodes) => {
        for (let node of nodes) {
          if (node.children) {
            const childIndex = node.children.findIndex(
              (child) => child.id === nodeToFind.id
            );
            if (childIndex !== -1) {
              return node;
            }
            const found = searchInArray(node.children);
            if (found) return found;
          }
        }
        return null;
      };

      return searchInArray(this.operatingUnits);
    },

    handleResetAddOperatingUnit() {
      this.addOperatingUnitForm.reset();
      this.v$.$reset();
    },

    onlyNumbers(event) {
      const charCode = event.which ? event.which : event.keyCode;
      if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        event.preventDefault();
      }
    },

    filterNumbers() {
      this.addOperatingUnitForm.prefix_id =
        this.addOperatingUnitForm.prefix_id.replace(/[^0-9]/g, "");
    },

    // Submit Form Methods

    submitOperatingUnitForm() {
      this.v$.$touch();

      if (this.addOperatingUnitForm.province) {
        this.addOperatingUnitForm.province =
          this.addOperatingUnitForm.province.name;
      }
      if (this.addOperatingUnitForm.city_municipality) {
        this.addOperatingUnitForm.zip =
          this.addOperatingUnitForm.city_municipality.zip_code;
        this.addOperatingUnitForm.city_municipality =
          this.addOperatingUnitForm.city_municipality.name;
      }

      if (this.addOperatingUnitForm.barangay) {
        this.addOperatingUnitForm.barangay =
          this.addOperatingUnitForm.barangay.name;
      }

      this.addOperatingUnitForm.post(
        route("administration.organization.storeOperatingUnit"),
        {
          onSuccess: () => {
            this.showToast("Operating Unit created successfully!", "success");
            this.addOperatingUnitDialog = false;
            this.editMode = true;
            this.addOperatingUnitForm.reset();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.addOperatingUnitForm.reset();
            this.addOperatingUnitDialog = false;
            this.editMode = true;
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },
  },
};
</script>

<style scoped lang="scss">
.organization-structure {
  background: #fff;
  padding: 20px;
  border-radius: 8px;

  .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
}
</style>

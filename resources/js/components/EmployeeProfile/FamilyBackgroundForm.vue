<template>
  <v-form @submit.prevent="submitFamilyBackgroundForm()">
    <v-row>
      <v-col cols="12">
        <v-card-title>Spouse Information</v-card-title>
      </v-col>

      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="First Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.spouse_firstname"
          :error-messages="
            v$.familyBackgroundForm.spouse_firstname.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Middle Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.spouse_middlename"
          :error-messages="
            v$.familyBackgroundForm.spouse_middlename.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Last Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.spouse_lastname"
          :error-messages="
            v$.familyBackgroundForm.spouse_lastname.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Name Extension"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.spouse_suffix"
          :error-messages="
            v$.familyBackgroundForm.spouse_suffix.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Occupation"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.occupation"
          :error-messages="
            v$.familyBackgroundForm.occupation.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Employer/Business Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.employer_business_name"
          :error-messages="
            v$.familyBackgroundForm.employer_business_name.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="6">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Business Address"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.business_address"
          :error-messages="
            v$.familyBackgroundForm.business_address.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Telephone No."
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.telephone_no"
          :error-messages="
            v$.familyBackgroundForm.telephone_no.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-divider></v-divider>
      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Children
          <v-btn
            class="starbucks-green"
            size="x-small"
            icon
            @click="addChild()"
            :disabled="isFormEditable"
          >
            <v-icon>mdi-plus</v-icon>
          </v-btn>
        </v-card-title>
      </v-col>


      <v-col
        v-for="(child, index) in familyBackgroundForm.children"
        :key="index"
        cols="12"
      >
        <v-row>
          <v-col cols="12" md="8">
            <v-text-field
              density="compact"
              variant="outlined"
              :label="`Full Name`"
              rounded="lg"
              v-model="child.fullname"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              type="date"
              density="compact"
              variant="outlined"
              :label="`Date of Birth`"
              rounded="lg"
              v-model="child.date_of_birth"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>

          <v-col cols="12" md="1">
            <div class="d-flex align-center justify-center">
              <v-btn
                color="error"
                size="x-small"
                icon
                @click="removeChild(index)"
                :disabled="isFormEditable"
              >
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
          </v-col>
        </v-row>
      </v-col>
      <v-divider></v-divider>
      <v-col cols="12">
        <v-card-title>Father's Information</v-card-title>
      </v-col>

      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="First Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.father_firstname"
          :error-messages="
            v$.familyBackgroundForm.father_firstname.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Middle Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.father_middlename"
          :error-messages="
            v$.familyBackgroundForm.father_middlename.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Last Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.father_lastname"
          :error-messages="
            v$.familyBackgroundForm.father_lastname.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Name Extension"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.father_suffix"
          :error-messages="
            v$.familyBackgroundForm.father_suffix.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-divider></v-divider>
      <v-col cols="12">
        <v-card-title>Mother's Maiden Name Information</v-card-title>
      </v-col>

      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="First Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.mother_firstname"
          :error-messages="
            v$.familyBackgroundForm.mother_firstname.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Middle Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.mother_middlename"
          :error-messages="
            v$.familyBackgroundForm.mother_middlename.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Last Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="familyBackgroundForm.mother_lastname"
          :error-messages="
            v$.familyBackgroundForm.mother_lastname.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12">
        <div class="d-flex align-center justify-end">
          <v-btn
            type="submit"
            rounded="xl"
            class="starbucks-green"
            min-width="120"
            :disabled="isFormEditable"
            >Save</v-btn
          >
        </div>
      </v-col>
    </v-row>
  </v-form>

  <!-- Delete Dialog -->
  <DeleteDialog
    v-model="isDeleteDialog"
    message="Are you sure you want to delete child entry?"
    :loading="loading"
    @confirm="handleDeleteChild"
    @cancel="isDeleteDialog = false"
  />
  <!-- <pre>{{ employeeChildren  }}</pre> -->
</template>
<script>
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import {
  required,
  minLength,
  maxLength,
  helpers,
  email,
} from "@vuelidate/validators";
import onBuildLogo from "../../../images/coming-soon-design.png";
import DeleteDialog from "@/components/DeleteDialog.vue";

export default {
  props: {
    isFormEditable: {
      type: Boolean,
      default: null,
    },

    employee_id: Number,
    employeeFamilyBackground: Object,
    employeeSpouseInformation: Object,
    employeeChildren: Object,
  },

  components: {
    DeleteDialog,
  },

  data() {
    const fb = this.employeeFamilyBackground?.data || {};
    const esi = this.employeeSpouseInformation?.data || {};
    const child = this.employeeChildren?.data || [];

    return {
      emp_id: this.$page.props.auth.user.id,
      v$: useVuelidate(),

      isDeleteDialog: false,
      selectedChild: null,

      familyBackgroundForm: useForm({
        // Spouse
        spouse_firstname: esi.spouse_firstname || "",
        spouse_middlename: esi.spouse_middlename || "",
        spouse_lastname: esi.spouse_lastname || "",
        spouse_suffix: esi.spouse_suffix || "",
        occupation: esi.occupation || "",
        employer_business_name: esi.employer_business_name || "",
        business_address: esi.business_address || "",
        telephone_no: esi.telephone_no || "",

        children: [],

        // Father
        father_firstname: fb.father_firstname || "",
        father_middlename: fb.father_middlename || "",
        father_lastname: fb.father_lastname || "",
        father_suffix: fb.father_suffix || "",

        // Mother
        mother_firstname: fb.mother_firstname || "",
        mother_middlename: fb.mother_middlename || "",
        mother_lastname: fb.mother_lastname || "",
      }),
    };
  },

  validations() {
    return {
      familyBackgroundForm: {
        // Spouse Information
        spouse_firstname: {
          minLength: minLength(0),
        },
        spouse_middlename: {
          minLength: minLength(0),
        },
        spouse_lastname: {
          minLength: minLength(0),
        },
        spouse_suffix: {
          minLength: minLength(0),
        },
        occupation: {
          minLength: minLength(0),
        },
        employer_business_name: {
          minLength: minLength(0),
        },
        business_address: {
          minLength: minLength(0),
        },
        telephone_no: {
          minLength: minLength(0),
        },

        // Children Information
        children: {
          minLength: minLength(0),
        },

        // Father's Information
        father_firstname: {
          minLength: minLength(0),
        },
        father_middlename: {
          minLength: minLength(0),
        },
        father_lastname: {
          minLength: minLength(0),
        },
        father_suffix: {
          minLength: minLength(0),
        },

        // Mother's Information
        mother_firstname: {
          minLength: minLength(0),
        },
        mother_middlename: {
          minLength: minLength(0),
        },
        mother_lastname: {
          minLength: minLength(0),
        },
        motherSuffix: {
          minLength: minLength(0),
        },
      },
    };
  },

  watch: {
    employeeChildren: {
      handler(newVal) {
        // Load existing children from database on initial load (when form is empty)
        if (this.familyBackgroundForm.children.length === 0) {
          if (newVal?.data?.length > 0) {
            this.familyBackgroundForm.children = newVal.data.map(child => ({
              id: child.id || null,
              fullname: child.fullname || "",
              date_of_birth: child.date_of_birth || "",
            }));
          }
        } else {
          // If form has children, sync by removing children that no longer exist in database
          const dbChildIds = new Set((newVal?.data || []).map(c => c.id).filter(Boolean));
          this.familyBackgroundForm.children = this.familyBackgroundForm.children.filter(
            child => !child.id || dbChildIds.has(child.id)
          );
        }
      },
      deep: true,
      immediate: true,
    },
  },

  methods: {
    addChild() {
      this.familyBackgroundForm.children.push({
        id: null,
        fullname: "",
        date_of_birth: "",
      });
    },

    removeChild(index) {
      const childId = this.familyBackgroundForm.children[index].id;

      if (childId) {
        this.isDeleteDialog = true;
        this.selectedChild = childId;
      } else {
        this.familyBackgroundForm.children.splice(index, 1);
      }
    },

    handleDeleteChild() {
      this.$inertia.delete(
        route("hrmanagement.employee.deleteEmployeeChild", {
          id: this.selectedChild,
        }),
        {
          onSuccess: () => {
            this.showToast("Child successfully deleted", "success");
            this.isDeleteDialog = false;
            this.$emit("childrenRefresh");
          },

          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    submitFamilyBackgroundForm() {
      this.v$.$touch();

      if (this.v$.$invalid) {
        return;
      }

      if (route().current().startsWith("self-service.")) {
        this.familyBackgroundForm.put(
          route("self-service.my-profile.updateFamilyBackground", {
            id: this.employee_id,
          }),
          {
            preserveScroll: true,
            onSuccess: () => {
              this.showToast(
                "Family Background Updated Successfully",
                "success"
              );
              this.$emit("childrenRefresh");
              this.v$.$reset();
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");

              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      } else {
        this.familyBackgroundForm.put(
          route("hrmanagement.employee.updateFamilyBackground", {
            id: this.employee_id,
          }),
          {
            preserveScroll: true,
            onSuccess: () => {
              this.showToast(
                "Family Background Updated Successfully",
                "success"
              );
              this.$emit("childrenRefresh");
              this.v$.$reset();
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");

              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      }
    },
  },
};
</script>

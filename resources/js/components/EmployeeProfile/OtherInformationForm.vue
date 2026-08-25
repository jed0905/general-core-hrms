<template>
  <!-- Other Information Form -->

  <v-form @submit.prevent="handleSubmitSpecialSkills()" class="mb-5">
    <v-row>
      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Special Skills and Hobbies
          <v-btn
            variant="tonal"
            class="starbucks-green"
            size="x-small"
            icon
            @click="addSpecialSkill"
            :disabled="isFormEditable"
          >
            <v-icon>mdi-plus</v-icon>
          </v-btn>
        </v-card-title>
      </v-col>

      <v-col
        v-for="(skill, index) in localSpecialSkills"
        :key="index"
        cols="12"
      >
        <v-row>
          <v-col cols="12" md="11">
            <v-text-field
              density="compact"
              variant="outlined"
              :label="`Special Skill/Hobby `"
              v-model="skill.skill"
              :disabled="isFormEditable"
              rounded="lg"
            ></v-text-field>
          </v-col>

          <v-col cols="12" md="1">
            <div class="d-flex align-center justify-center">
              <v-btn
                color="red-darken-4"
                size="x-small"
                icon
                @click="removeSpecialSkill(index)"
                :disabled="isFormEditable"
                variant="tonal"
              >
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
          </v-col>
        </v-row>
      </v-col>
      <v-col v-if="localSpecialSkills.length > 0" cols="12">
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
  <v-divider></v-divider>
  <!-- Non-Academic Distinctions/Recognition -->
  <v-form @submit.prevent="handleSubmitNonAcademicDistinctions()" class="mb-5">
    <v-row>
      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Non-Academic Distinctions/Recognition
          <v-btn
            variant="tonal"
            class="starbucks-green"
            size="x-small"
            icon
            @click="addNonAcademicDistinction"
            :disabled="isFormEditable"
          >
            <v-icon>mdi-plus</v-icon>
          </v-btn>
        </v-card-title>
      </v-col>
      <v-col
        v-for="(distinction, index) in localNonAcademicDistinctions"
        :key="index"
        cols="12"
      >
        <v-row>
          <v-col cols="12" md="11">
            <v-text-field
              density="compact"
              variant="outlined"
              :label="`Non-Academic Distinction/Recognition`"
              v-model="distinction.distinction"
              :disabled="isFormEditable"
              rounded="lg"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="1">
            <div class="d-flex align-center justify-center">
              <v-btn
                variant="tonal"
                color="red-darken-4"
                size="x-small"
                icon
                @click="removeNonAcademicDistinction(index)"
                :disabled="isFormEditable"
              >
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
          </v-col>
        </v-row>
        <v-divider
          v-if="index < localNonAcademicDistinctions.length - 1"
          class="my-4"
        ></v-divider>
      </v-col>
      <v-col v-if="localNonAcademicDistinctions.length > 0" cols="12">
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

  <v-divider></v-divider>
  <v-form @submit.prevent="handleSubmitMemberships()" class="mb-5">
    <v-row>
      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Membership in Association/Organization
          <v-btn
            variant="tonal"
            class="starbucks-green"
            size="x-small"
            icon
            @click="addMembership"
            :disabled="isFormEditable"
          >
            <v-icon>mdi-plus</v-icon>
          </v-btn>
        </v-card-title>
      </v-col>
      <v-col
        v-for="(membership, index) in localMemberships"
        :key="index"
        cols="12"
      >
        <v-row>
          <v-col cols="12" md="11">
            <v-text-field
              density="compact"
              variant="outlined"
              :label="`Membership in Association/Organization`"
              v-model="membership.organization_name"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="1">
            <div class="d-flex align-center justify-center">
              <v-btn
                variant="tonal"
                color="red-darken-4"
                size="x-small"
                icon
                @click="removeMembership(index)"
                :disabled="isFormEditable"
              >
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
          </v-col>
        </v-row>
        <v-divider
          v-if="index < localMemberships.length - 1"
          class="my-4"
        ></v-divider>
      </v-col>
      <v-col v-if="localMemberships.length > 0" cols="12">
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

  <DeleteDialog
    v-model="confirmDialogDeleteForSpecialSkills"
    message="Are you sure you want to delete this special skill?"
    :loading="loading"
    @confirm="handleDelete"
    @cancel="handleCancelDelete"
  />

  <DeleteDialog
    v-model="confirmDialogDeleteForNonAcademicDistinctions"
    message="Are you sure you want to delete this non-academic distinction?"
    :loading="loading"
    @confirm="handleDeleteNonAcademicDistinction"
    @cancel="handleCancelDeleteNonAcademicDistinction"
  />

  <DeleteDialog
    v-model="confirmDialogDeleteForMemberships"
    message="Are you sure you want to delete this membership?"
    :loading="loading"
    @confirm="handleDeleteMembership"
    @cancel="handleCancelDeleteMembership"
  />
</template>
<script>
import { useForm } from "@inertiajs/vue3";
import { helpers, minLength } from "@vuelidate/validators";
import useVuelidate from "@vuelidate/core";
import DeleteDialog from "../DeleteDialog.vue";

export default {
  components: {
    DeleteDialog,
  },
  props: {
    isFormEditable: {
      type: Boolean,
      default: false,
    },
    employee_id: Object,
    employee: Object,
    employeeJobDetails: Object,
    errors: Object,
    employeeSpecialSkills: {
      type: Array,
      default: () => [],
    },
    employeeNonAcademicDistinctions: {
      type: Array,
      default: () => [],
    },
    employeeOtherInfoOrganizations: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      // isFormEditable: this.$page.props.auth.roles[0] !== 'employee' ? true : false,
      confirmDialogDeleteForSpecialSkills: false,
      itemToDeleteIndex: null,
      itemToDeleteId: null,
      confirmDialogDeleteForNonAcademicDistinctions: false,
      itemToDeleteIndexForNonAcademicDistinctions: null,
      itemToDeleteIdForNonAcademicDistinctions: null,
      confirmDialogDeleteForMemberships: false,
      itemToDeleteIndexForMemberships: null,
      itemToDeleteIdForMemberships: null,
      loading: false,
      otherInformationForm: useForm({
        //special skills
        specialSkills: "",
        //non-academic distinctions
        nonAcademicDistinctions: "",
        //memberships
        memberships: "",
      }),
      localSpecialSkills: [],
      localNonAcademicDistinctions: [],
      localMemberships: [],
    };
  },
  setup() {
    return { v$: useVuelidate() };
  },

  validations() {
    return {
      //Special Skills validation (only validate non-empty skills)
      localSpecialSkills: {
        $each: helpers.forEach({
          skill: {
            // Only require minimum length if the field has content
            minLength: (value) =>
              !value || value.length === 0 || value.length >= 1,
          },
        }),
      },

      //Non-Academic Distinctions validation
      nonAcademicDistinctions: {
        $each: helpers.forEach({
          distinction: {
            minLength: minLength(0),
          },
        }),
      },

      //Memberships validation
      localMemberships: {
        $each: helpers.forEach({
          organization_name: {
            // Only require minimum length if the field has content
            minLength: (value) =>
              !value || value.length === 0 || value.length >= 1,
          },
        }),
      },
    };
  },
  mounted() {
    this.initializeSpecialSkills();
    this.initializeNonAcademicDistinctions();
    this.initializeMemberships();
  },

  watch: {
    employeeSpecialSkills: {
      handler() {
        this.initializeSpecialSkills();
      },
      deep: true,
      immediate: true,
    },
    employeeNonAcademicDistinctions: {
      handler() {
        this.initializeNonAcademicDistinctions();
      },
      deep: true,
      immediate: true,
    },
    employeeOtherInfoOrganizations: {
      handler() {
        this.initializeMemberships();
      },
      deep: true,
      immediate: true,
    },
  },
  methods: {
    // Initialize special skills from props
    initializeSpecialSkills() {
      if (
        this.employeeSpecialSkills &&
        Array.isArray(this.employeeSpecialSkills) &&
        this.employeeSpecialSkills.length > 0
      ) {
        this.localSpecialSkills = this.employeeSpecialSkills.map((skill) => ({
          id: skill.id, // Preserve the database ID
          skill: skill.special_skill || skill.skill || "",
          employee_id: skill.employee_id,
          created_at: skill.created_at,
          updated_at: skill.updated_at,
        }));
      } else {
        // If no prop data or empty array, start with one empty input row
        this.localSpecialSkills = [{ skill: "" }];
      }
    },

    initializeNonAcademicDistinctions() {
      if (
        this.employeeNonAcademicDistinctions &&
        Array.isArray(this.employeeNonAcademicDistinctions) &&
        this.employeeNonAcademicDistinctions.length > 0
      ) {
        this.localNonAcademicDistinctions =
          this.employeeNonAcademicDistinctions.map((distinction) => ({
            id: distinction.id,
            distinction: distinction.distinction,
            employee_id: distinction.employee_id,
            created_at: distinction.created_at,
            updated_at: distinction.updated_at,
          }));
      } else {
        // If no prop data or empty array, start with one empty input row
        this.localNonAcademicDistinctions = [{ distinction: "" }];
      }
    },

    initializeMemberships() {
      if (
        this.employeeOtherInfoOrganizations &&
        Array.isArray(this.employeeOtherInfoOrganizations) &&
        this.employeeOtherInfoOrganizations.length > 0
      ) {
        this.localMemberships = this.employeeOtherInfoOrganizations.map(
          (membership) => ({
            id: membership.id,
            organization_name: membership.organization_name,
            employee_id: membership.employee_id,
            created_at: membership.created_at,
            updated_at: membership.updated_at,
          })
        );
      } else {
        // If no prop data or empty array, start with one empty input row
        this.localMemberships = [{ organization_name: "" }];
      }
    },

    handleSubmitSpecialSkills() {
      // Filter out empty skills before validation and submission
      const filteredSkills = this.localSpecialSkills.filter(
        (skill) => skill.skill && skill.skill.trim() !== ""
      );

      if (filteredSkills.length === 0) {
        this.showToast("Please add at least one special skill.", "warning");
        return;
      }

      // Update form data with filtered array values (include IDs for existing records)
      this.otherInformationForm.specialSkills = filteredSkills.map((skill) => ({
        id: skill.id || null, // Include ID if it exists, null for new records
        skill: skill.skill.trim(),
      }));

      this.otherInformationForm.put(
        route("self-service.my-profile.updateSpecialSkills", {
          id: this.employee_id,
        }),
        {
          preserveState: true,
          preserveScroll: true,
          onSuccess: (page) => {
            this.showToast("Special Skills updated successfully.", "success");
            // Update local data with the saved data (now with IDs for new items)
            this.updateLocalDataAfterSave();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    handleSubmitNonAcademicDistinctions() {
      // Filter out empty distinctions before validation and submission
      const filteredDistinctions = this.localNonAcademicDistinctions.filter(
        (distinction) =>
          distinction.distinction && distinction.distinction.trim() !== ""
      );

      if (filteredDistinctions.length === 0) {
        this.showToast(
          "Please add at least one non-academic distinction.",
          "warning"
        );
        return;
      }

      // Update form data with filtered array values (include IDs for existing records)
      this.otherInformationForm.nonAcademicDistinctions =
        filteredDistinctions.map((distinction) => ({
          id: distinction.id || null, // Include ID if it exists, null for new records
          distinction: distinction.distinction.trim(),
        }));

      this.otherInformationForm.put(
        route("self-service.my-profile.updateNonAcademicDistinctions", {
          id: this.employee_id,
        }),
        {
          preserveState: true,
          preserveScroll: true,
          onSuccess: (page) => {
            this.showToast(
              "Non-Academic Distinctions updated successfully.",
              "success"
            );
            // Update local data with the saved data (now with IDs for new items)
            this.updateLocalNonAcademicDistinctionsAfterSave();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    handleSubmitMemberships() {
      //
      const filteredMemberships = this.localMemberships.filter(
        (membership) =>
          membership.organization_name &&
          membership.organization_name.trim() !== ""
      );

      if (filteredMemberships.length === 0) {
        this.showToast("Please add at least one membership.", "warning");
        return;
      }

      // Update form data with filtered array values (include IDs for existing records)
      this.otherInformationForm.memberships = filteredMemberships.map(
        (membership) => ({
          id: membership.id || null, // Include ID if it exists, null for new records
          organization_name: membership.organization_name.trim(),
        })
      );

      this.otherInformationForm.put(
        route("self-service.my-profile.updateMemberships", {
          id: this.employee_id,
        }),
        {
          preserveState: true,
          preserveScroll: true,
          onSuccess: (page) => {
            this.showToast("Memberships updated successfully.", "success");
            // Update local data with the saved data (now with IDs for new items)
            this.updateLocalMembershipsAfterSave();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    addSpecialSkill() {
      this.localSpecialSkills.push({ skill: "" });
    },

    removeSpecialSkill(index) {
      // Check if the input has data
      const skillItem = this.localSpecialSkills[index];
      const skillValue = skillItem.skill;

      if (skillValue && skillValue.trim() !== "") {
        // If input has data, show confirmation dialog
        this.itemToDeleteIndex = index;
        this.itemToDeleteId = skillItem.id || null; // Store the ID if it exists
        this.confirmDialogDeleteForSpecialSkills = true;
      } else {
        // If input is empty, remove immediately
        this.localSpecialSkills.splice(index, 1);
        // Note: Array can be empty after removal - that's fine, no inputs will be shown
      }
    },

    // Remove non-academic distinction
    removeNonAcademicDistinction(index) {
      // this.localNonAcademicDistinctions.splice(index, 1);
      const distinctionItem = this.localNonAcademicDistinctions[index];
      const distinctionValue = distinctionItem.distinction;

      if (distinctionValue && distinctionValue.trim() !== "") {
        this.itemToDeleteIndexForNonAcademicDistinctions = index;
        this.itemToDeleteIdForNonAcademicDistinctions =
          distinctionItem.id || null;
        this.confirmDialogDeleteForNonAcademicDistinctions = true;
      } else {
        this.localNonAcademicDistinctions.splice(index, 1);
      }
    },

    // Remove membership
    removeMembership(index) {
      const membershipItem = this.localMemberships[index];
      const membershipValue = membershipItem.organization_name;

      if (membershipValue && membershipValue.trim() !== "") {
        // If input has data, show confirmation dialog
        this.itemToDeleteIndexForMemberships = index;
        this.itemToDeleteIdForMemberships = membershipItem.id || null; // Store the ID if it exists
        this.confirmDialogDeleteForMemberships = true;
      } else {
        // If input is empty, remove immediately
        this.localMemberships.splice(index, 1);
        // Note: Array can be empty after removal - that's fine, no inputs will be shown
      }
    },

    addNonAcademicDistinction() {
      this.localNonAcademicDistinctions.push({ distinction: "" });
    },

    addMembership() {
      this.localMemberships.push({ organization_name: "" });
    },

    // Handle delete confirmation from DeleteDialog
    async handleDelete() {
      if (this.itemToDeleteIndex !== null) {
        console.log("Deleting special skill with ID:", this.itemToDeleteId);
        console.log("Deleting special skill at index:", this.itemToDeleteIndex);

        // If item has an ID, delete from database first
        if (this.itemToDeleteId) {
          await this.deleteSpecialSkillFromDatabase(this.itemToDeleteId);
        }

        // Remove from local array (only if database deletion was successful or no ID)
        this.localSpecialSkills.splice(this.itemToDeleteIndex, 1);

        // Reset tracking variables
        this.itemToDeleteIndex = null;
        this.itemToDeleteId = null;
      }
      this.confirmDialogDeleteForSpecialSkills = false;
    },

    // Handle cancel from DeleteDialog
    handleCancelDelete() {
      this.itemToDeleteIndex = null;
      this.itemToDeleteId = null;
    },

    async handleDeleteNonAcademicDistinction() {
      if (this.itemToDeleteIndexForNonAcademicDistinctions !== null) {
        console.log(
          "Deleting non-academic distinction with ID:",
          this.itemToDeleteIdForNonAcademicDistinctions
        );
        console.log(
          "Deleting non-academic distinction at index:",
          this.itemToDeleteIndexForNonAcademicDistinctions
        );

        if (this.itemToDeleteIdForNonAcademicDistinctions) {
          await this.deleteNonAcademicDistinctionFromDatabase(
            this.itemToDeleteIdForNonAcademicDistinctions
          );
        }

        this.localNonAcademicDistinctions.splice(
          this.itemToDeleteIndexForNonAcademicDistinctions,
          1
        );
        this.itemToDeleteIndexForNonAcademicDistinctions = null;
        this.itemToDeleteIdForNonAcademicDistinctions = null;
      }
      this.confirmDialogDeleteForNonAcademicDistinctions = false;
    },

    handleCancelDeleteNonAcademicDistinction() {
      this.itemToDeleteIndexForNonAcademicDistinctions = null;
      this.itemToDeleteIdForNonAcademicDistinctions = null;
    },

    async handleDeleteMembership() {
      if (this.itemToDeleteIndexForMemberships !== null) {
        console.log(
          "Deleting membership with ID:",
          this.itemToDeleteIdForMemberships
        );
        console.log(
          "Deleting membership at index:",
          this.itemToDeleteIndexForMemberships
        );

        if (this.itemToDeleteIdForMemberships) {
          await this.deleteMembershipFromDatabase(
            this.itemToDeleteIdForMemberships
          );
        }
        this.localMemberships.splice(this.itemToDeleteIndexForMemberships, 1);
        this.itemToDeleteIndexForMemberships = null;
        this.itemToDeleteIdForMemberships = null;
      }

      this.confirmDialogDeleteForMemberships = false;
    },

    handleCancelDeleteMembership() {
      this.itemToDeleteIndexForMemberships = null;
      this.itemToDeleteIdForMemberships = null;
      this.confirmDialogDeleteForMemberships = false;
    },

    // Delete special skill from database
    async deleteSpecialSkillFromDatabase(skillId) {
      this.loading = true;
      try {
        await this.$inertia.delete(
          route("self-service.my-profile.deleteSpecialSkills", skillId),
          {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
              this.showToast("Special skill deleted successfully", "success");
              // No need to refresh - item is already removed from local array
            },
            onError: (errors) => {
              this.showToast("Failed to delete special skill", "error");
              console.error("Delete error:", errors);
            },
          }
        );
      } catch (error) {
        this.showToast("Failed to delete special skill", "error");
        console.error("Delete error:", error);
      } finally {
        this.loading = false;
      }
    },

    async deleteNonAcademicDistinctionFromDatabase(distinctionId) {
      this.loading = true;
      try {
        await this.$inertia.delete(
          route(
            "self-service.my-profile.deleteNonAcademicDistinctions",
            distinctionId
          ),
          {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
              this.showToast(
                "Non-academic distinction deleted successfully",
                "success"
              );
            },
            onError: (errors) => {
              this.showToast(
                "Failed to delete non-academic distinction",
                "error"
              );
              console.error("Delete error:", errors);
            },
          }
        );
      } catch (error) {
        this.showToast("Failed to delete non-academic distinction", "error");
        console.error("Delete error:", error);
      } finally {
        this.loading = false;
      }
    },

    async deleteMembershipFromDatabase(membershipId) {
      this.loading = true;
      try {
        await this.$inertia.delete(
          route("self-service.my-profile.deleteMemberships", membershipId),
          {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
              this.showToast("Membership deleted successfully", "success");
            },
          }
        );
      } catch (error) {
        this.showToast("Failed to delete membership", "error");
        console.error("Delete error:", error);
      } finally {
        this.loading = false;
      }
    },

    // Update local data after successful save without page refresh
    updateLocalDataAfterSave() {
      // Make a simple API call to get the updated special skills data
      this.$inertia.get(
        route("self-service.my-profile.index"),
        {},
        {
          preserveState: true,
          preserveScroll: true,
          only: ["employeeSpecialSkills"],
          onSuccess: (page) => {
            // Update local data with fresh server data (now includes IDs for new items)
            if (
              page.props.employeeSpecialSkills &&
              Array.isArray(page.props.employeeSpecialSkills) &&
              page.props.employeeSpecialSkills.length > 0
            ) {
              this.localSpecialSkills = page.props.employeeSpecialSkills.map(
                (skill) => ({
                  id: skill.id,
                  skill: skill.special_skill || skill.skill || "",
                  employee_id: skill.employee_id,
                  created_at: skill.created_at,
                  updated_at: skill.updated_at,
                })
              );
            } else {
              // If no data from server, keep empty array
              this.localSpecialSkills = [];
            }
          },
        }
      );
    },

    // Update local non-academic distinctions data after successful save without page refresh
    updateLocalNonAcademicDistinctionsAfterSave() {
      // Make a simple API call to get the updated non-academic distinctions data
      this.$inertia.get(
        route("self-service.my-profile.index"),
        {},
        {
          preserveState: true,
          preserveScroll: true,
          only: ["employeeNonAcademicDistinctions"],
          onSuccess: (page) => {
            // Update local data with fresh server data (now includes IDs for new items)
            if (
              page.props.employeeNonAcademicDistinctions &&
              Array.isArray(page.props.employeeNonAcademicDistinctions) &&
              page.props.employeeNonAcademicDistinctions.length > 0
            ) {
              this.localNonAcademicDistinctions =
                page.props.employeeNonAcademicDistinctions.map(
                  (distinction) => ({
                    id: distinction.id,
                    distinction: distinction.distinction || "",
                    employee_id: distinction.employee_id,
                    created_at: distinction.created_at,
                    updated_at: distinction.updated_at,
                  })
                );
            } else {
              // If no data from server, keep empty array
              this.localNonAcademicDistinctions = [];
            }
          },
        }
      );
    },

    updateLocalMembershipsAfterSave() {
      this.$inertia.get(
        route("self-service.my-profile.index"),
        {},
        {
          preserveState: true,
          preserveScroll: true,
          only: ["employeeOtherInfoOrganizations"],
          onSuccess: (page) => {
            if (
              page.props.employeeOtherInfoOrganizations &&
              Array.isArray(page.props.employeeOtherInfoOrganizations) &&
              page.props.employeeOtherInfoOrganizations.length > 0
            ) {
              this.localMemberships =
                page.props.employeeOtherInfoOrganizations.map((membership) => ({
                  id: membership.id,
                  organization_name: membership.organization_name,
                }));
            } else {
              // If no data from server, keep empty array
              this.localMemberships = [];
            }
          },
        }
      );
    },
  },
};
</script>

<template>
  <div class="mb-3 d-flex justify-space-between align-center">
    <Breadcrumbs
      :items="[
        {
          title: 'Permissions',
          disabled: false,
          href: '#',
        },
      ]"
    />
    <Link
      :href="route('permission.management.create')"
      class="text-decoration-none"
    >
      <ButtonSuccess name="Create" />
    </Link>
  </div>
  <v-row>
    <v-col cols="12">
      <TableWrapper>
        <v-table>
          <thead>
            <tr>
              <th>Permission Name</th>
              <th class="text-center">
                <v-icon>mdi-lightning-bolt-outline</v-icon>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(permission, index) in permissions" :key="index">
              <td>{{ permission.name }}</td>
              <td class="text-center">
                <v-btn
                  icon="mdi-delete"
                  color="red-darken-2"
                  size="x-small"
                  class="mr-2"
                  variant="tonal"
                  @click="
                    isDeleteDialog = true;
                    permissionID = permission.id;
                  "
                ></v-btn>
                <Link
                  :href="route('permission.management.edit', { id: permission.id })"
                  class="text-decoration-none"
                >
                  <v-btn
                    icon="mdi-pencil"
                    color="yellow-darken-4"
                    size="x-small"
                    variant="tonal"
                  ></v-btn>
                </Link>
                
              </td>
            </tr>
          </tbody>
        </v-table>
      </TableWrapper>
    </v-col>
  </v-row>

  <!-- Delete Dialog -->
  <DeleteDialog
    v-model="isDeleteDialog"
    message="Are you sure you want to delete this permission?"
    :loading="loading"
    @confirm="handleDelete"
    @cancel="isDeleteDialog = false"
  />
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import DeleteDialog from "@/components/DeleteDialog.vue";
export default {
  layout: SidebarLayout,
  components: {
    Breadcrumbs,
    ButtonSuccess,
    TableWrapper,
    DeleteDialog,
  },
  props: {
    permissions: Object,
  },
  data() {
    return {
      isDeleteDialog: false,
      permissionID: null,
    };
  },
  methods: {
    handleDelete() {
      this.$inertia.delete(
        route("permission.management.destroy", { id: this.permissionID }),
        {
          onSuccess: () => {
            this.showToast("Permission deleted successfully", "success");
            this.isDeleteDialog = false;
          },
          onError: (errors) => {
            for (const error in errors) {
              this.showToast(error[key][0], "error");
            }
          },
        }
      );
    },
  },
};
</script>

/**
 * Capability checks against the permissions shared by HandleInertiaRequests
 * (auth.permissions). Use for page-level actions such as "create" or
 * "export". Record-level actions (approve, cancel, edit a specific
 * application) must use the `can` flags the server computes per record.
 * The server always re-checks; this only hides what the user can't do.
 */
export default {
  methods: {
    can(permission) {
      return (this.$page.props.auth?.permissions || []).includes(permission);
    },
  },
};

import {
  VBtn,
  VPicker,
  VPickerTitle
} from "./chunk-PU2CW3NP.js";
import {
  useLocale,
  useProxiedModel
} from "./chunk-ZBWQYIMS.js";
import "./chunk-GJSAIMLY.js";
import "./chunk-3CODNLVM.js";
import {
  deepEqual,
  genericComponent,
  propsFactory,
  useRender
} from "./chunk-ZDQHOJIR.js";
import {
  Fragment,
  computed,
  createVNode,
  ref,
  toRaw,
  watchEffect
} from "./chunk-RS55Z2JH.js";
import "./chunk-5WWUZCGV.js";

// node_modules/vuetify/lib/labs/VConfirmEdit/VConfirmEdit.mjs
var makeVConfirmEditProps = propsFactory({
  modelValue: null,
  color: String,
  cancelText: {
    type: String,
    default: "$vuetify.confirmEdit.cancel"
  },
  okText: {
    type: String,
    default: "$vuetify.confirmEdit.ok"
  }
}, "VConfirmEdit");
var VConfirmEdit = genericComponent()({
  name: "VConfirmEdit",
  props: makeVConfirmEditProps(),
  emits: {
    cancel: () => true,
    save: (value) => true,
    "update:modelValue": (value) => true
  },
  setup(props, _ref) {
    let {
      emit,
      slots
    } = _ref;
    const model = useProxiedModel(props, "modelValue");
    const internalModel = ref();
    watchEffect(() => {
      internalModel.value = structuredClone(toRaw(model.value));
    });
    const {
      t
    } = useLocale();
    const isPristine = computed(() => {
      return deepEqual(model.value, internalModel.value);
    });
    function save() {
      model.value = internalModel.value;
      emit("save", internalModel.value);
    }
    function cancel() {
      internalModel.value = structuredClone(toRaw(model.value));
      emit("cancel");
    }
    let actionsUsed = false;
    useRender(() => {
      var _a;
      const actions = createVNode(Fragment, null, [createVNode(VBtn, {
        "disabled": isPristine.value,
        "variant": "text",
        "color": props.color,
        "onClick": cancel,
        "text": t(props.cancelText)
      }, null), createVNode(VBtn, {
        "disabled": isPristine.value,
        "variant": "text",
        "color": props.color,
        "onClick": save,
        "text": t(props.okText)
      }, null)]);
      return createVNode(Fragment, null, [(_a = slots.default) == null ? void 0 : _a.call(slots, {
        model: internalModel,
        get actions() {
          actionsUsed = true;
          return actions;
        }
      }), !actionsUsed && actions]);
    });
  }
});
export {
  VConfirmEdit,
  VPicker,
  VPickerTitle
};
//# sourceMappingURL=vuetify_labs_components.js.map

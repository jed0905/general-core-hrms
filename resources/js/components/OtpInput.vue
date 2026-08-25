<template>
  <div class="otp-input-container">
    <div class="otp-boxes">
      <input
        v-for="(digit, index) in 6"
        :key="index"
        ref="inputs"
        v-model="otpDigits[index]"
        type="text"
        maxlength="1"
        class="otp-box"
        :class="{ 'error': error }"
        @input="handleInput(index)"
        @keydown="handleKeydown($event, index)"
        @paste="handlePaste"
        :disabled="disabled"
      >
    </div>
  </div>
</template>

<script>
export default {
  name: 'OtpInput',
  props: {
    value: {
      type: String,
      default: ''
    },
    error: {
      type: Boolean,
      default: false
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      otpDigits: Array(6).fill('')
    }
  },
  watch: {
    value: {
      immediate: true,
      handler(newValue) {
        if (newValue) {
          this.otpDigits = newValue.split('').slice(0, 6);
          while (this.otpDigits.length < 6) {
            this.otpDigits.push('');
          }
        }
      }
    }
  },
  methods: {
    handleInput(index) {
      const input = this.otpDigits[index];
      
      // Ensure only numbers
      if (!/^\d*$/.test(input)) {
        this.otpDigits[index] = '';
        return;
      }

      // Move to next input if value entered
      if (input && index < 5) {
        this.$refs.inputs[index + 1].focus();
      }

      this.emitValue();
    },
    handleKeydown(event, index) {
      // Handle backspace
      if (event.key === 'Backspace') {
        event.preventDefault();
        this.otpDigits[index] = '';
        if (index > 0) {
          this.$refs.inputs[index - 1].focus();
        }
        this.emitValue();
      }
      // Handle left arrow
      else if (event.key === 'ArrowLeft' && index > 0) {
        this.$refs.inputs[index - 1].focus();
      }
      // Handle right arrow
      else if (event.key === 'ArrowRight' && index < 5) {
        this.$refs.inputs[index + 1].focus();
      }
    },
    handlePaste(event) {
      event.preventDefault();
      const pastedData = event.clipboardData.getData('text');
      const numbers = pastedData.replace(/\D/g, '').slice(0, 6);
      
      this.otpDigits = numbers.split('');
      while (this.otpDigits.length < 6) {
        this.otpDigits.push('');
      }
      
      this.emitValue();
    },
    emitValue() {
      const value = this.otpDigits.join('');
      this.$emit('update:value', value);
      this.$emit('input', value);
    }
  }
}
</script>

<style scoped>
.otp-input-container {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
}

.otp-boxes {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.otp-box {
  width: 45px;
  height: 45px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 20px;
  text-align: center;
  transition: all 0.2s ease;
  background: white;
  color: #333;
}

.otp-box:focus {
  border-color: #006241;
  outline: none;
  box-shadow: 0 0 0 2px rgba(0, 98, 65, 0.2);
}

.otp-box.error {
  border-color: #ff5252;
}

.otp-box:disabled {
  background: #f5f5f5;
  cursor: not-allowed;
}
</style>

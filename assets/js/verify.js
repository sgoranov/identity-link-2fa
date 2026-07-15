const inputs = document.querySelectorAll('.otp-field');
const hiddenInput = document.getElementById('two_fa_secret_code');
const submitBtn = document.getElementById('submit-btn');
const form = document.getElementById('tfa-form');

inputs.forEach((input, index) => {
  input.addEventListener('input', (e) => {
    const val = e.target.value;
    if (val.length >= 1) {
      // Keep only the last character if they somehow typed more
      input.value = val.substring(val.length - 1);
      if (index < inputs.length - 1) {
        inputs[index + 1].focus();
      }
    }
    updateHiddenInput();
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !input.value && index > 0) {
      inputs[index - 1].focus();
    }
  });

  input.addEventListener('paste', (e) => {
    e.preventDefault();
    const data = (e.clipboardData || window.clipboardData).getData('text').trim();
    if (data.length === 6 && /^\d+$/.test(data)) {
      inputs.forEach((inp, idx) => inp.value = data[idx]);
      updateHiddenInput();
      inputs[5].focus();
    }
  });
});

function updateHiddenInput() {
  const code = Array.from(inputs).map(inp => inp.value).join('');
  hiddenInput.value = code;

  if (code.length === 6) {
    submitBtn.disabled = false;
  } else {
    submitBtn.disabled = true;
  }
}

submitBtn.addEventListener('click', () => {
  if (hiddenInput.value.length === 6) {
    form.submit();
  }
});
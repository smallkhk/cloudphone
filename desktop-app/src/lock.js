const form = document.getElementById('form');
const input = document.getElementById('code');
const button = document.getElementById('submit');
const errorEl = document.getElementById('error');

input.addEventListener('input', () => {
  // Auto-format as XXXX-XXXX-XXXX-XXXX while typing.
  let raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 16);
  input.value = raw.match(/.{1,4}/g)?.join('-') ?? raw;
});

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  errorEl.textContent = '';

  const code = input.value.trim();
  if (!code) {
    errorEl.textContent = 'Enter your access code.';
    return;
  }

  button.disabled = true;
  button.textContent = 'Checking…';

  const result = await window.modova.verify(code);

  if (result.ok) {
    button.textContent = 'Unlocked';
    await window.modova.launch();
    return;
  }

  button.disabled = false;
  button.textContent = 'Unlock';
  errorEl.textContent = result.error || 'That code is not valid or has expired.';
});

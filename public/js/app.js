document.addEventListener('DOMContentLoaded', () => {
  document.querySelector('[data-toggle-menu]')?.addEventListener('click', (e) => {
    const button = e.currentTarget;
    const open = document.getElementById('sidebar').classList.toggle('open');
    button.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { document.getElementById('sidebar')?.classList.remove('open'); document.querySelector('[data-toggle-menu]')?.setAttribute('aria-expanded','false'); }
  });
  document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
    const field = document.getElementById(button.dataset.passwordToggle);
    const show = field.type === 'password'; field.type = show ? 'text' : 'password';
    button.textContent = show ? 'Hide' : 'Show'; button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }));
  document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
  document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', (e) => {
    if (!window.confirm(form.dataset.confirm)) e.preventDefault();
  }));
  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', (e) => {
    if (e.defaultPrevented || form.method.toLowerCase() === 'get') return;
    const button = e.submitter;
    if (button) { setTimeout(() => { button.disabled = true; }, 0); setTimeout(() => { button.disabled = false; }, 6000); }
  }));
  const orderForm = document.querySelector('[data-order-form]');
  if (orderForm) {
    const rows = document.getElementById('order-items'); const template = document.getElementById('item-template');
    let nextIndex = Math.max(-1, ...Array.from(rows.querySelectorAll('select[name]'), el => Number(el.name.match(/items\[(\d+)\]/)?.[1] || 0))) + 1;
    const minor = (value) => { const [whole, decimal = ''] = String(value || '0').split('.'); return Number(whole) * 100 + Number(decimal.padEnd(2, '0').slice(0,2)); };
    const format = (cents) => orderForm.dataset.currency + ' ' + (cents / 100).toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2});
    const update = () => {
      let subtotal = 0;
      rows.querySelectorAll('.item-row').forEach(row => {
        const option = row.querySelector('select').selectedOptions[0];
        const quantity = Math.max(0, minor(row.querySelector('input').value));
        const price = Number(option?.dataset.price || 0);
        const total = Math.floor((price * quantity + 50) / 100); subtotal += total;
        row.querySelector('.line-price').textContent = format(total);
      });
      const discount = Math.max(0, minor(document.getElementById('discount').value));
      const taxable = Math.max(0, subtotal - discount);
      const tax = Math.floor((taxable * Number(orderForm.dataset.tax) + 5000) / 10000);
      document.getElementById('estimate-total').textContent = format(taxable + tax);
      document.getElementById('estimate-details').textContent = 'Subtotal ' + format(subtotal) + ' · Tax ' + format(tax);
    };
    document.getElementById('add-item').addEventListener('click', () => {
      if (rows.children.length >= 20) return;
      const clone = template.content.cloneNode(true);
      clone.querySelectorAll('[name]').forEach(el => { el.name = el.name.replace('__INDEX__', String(nextIndex)); });
      nextIndex++; rows.append(clone); update();
    });
    rows.addEventListener('click', (e) => { if (e.target.closest('.remove-item') && rows.children.length > 1) { e.target.closest('.item-row').remove(); update(); } });
    orderForm.addEventListener('input', update); orderForm.addEventListener('change', update); update();
  }
  if (document.getElementById('form-errors')) document.getElementById('form-errors').focus();
});

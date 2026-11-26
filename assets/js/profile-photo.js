document.addEventListener('DOMContentLoaded', () => {
  const input = document.querySelector('[data-profile-photo-input]');
  const preview = document.querySelector('[data-photo-preview]');
  const status = document.querySelector('[data-photo-status]');
  const edit = document.querySelector('[data-photo-edit]');
  const save = document.querySelector('[data-photo-save]');
  if (!input || !preview || !status) return;
  edit?.addEventListener('click', () => input.click());
  let previewUrl;
  input.addEventListener('change', () => {
    if (save) save.disabled = true;
    input.setCustomValidity('');
    status.textContent = '';
    const file = input.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      const message = 'Pilih foto JPG, PNG, atau WebP berukuran maksimal 5 MB.';
      input.setCustomValidity(message);
      status.textContent = message;
      return;
    }
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = URL.createObjectURL(file);
    const image = new Image();
    const imageUrl = previewUrl;
    image.alt = 'Pratinjau foto profil baru';
    image.onload = () => {
      if (previewUrl !== imageUrl) return;
      if (image.naturalWidth > 4096 || image.naturalHeight > 4096) {
        input.setCustomValidity('Dimensi foto maksimal 4096 × 4096 piksel.');
        status.textContent = input.validationMessage;
        return;
      }
      preview.replaceChildren(image);
      if (save) save.disabled = false;
      status.textContent = 'Pratinjau foto baru. Klik Simpan foto untuk menerapkan.';
    };
    image.onerror = () => {
      if (previewUrl !== imageUrl) return;
      input.setCustomValidity('Foto tidak dapat dibaca. Pilih gambar lain.');
      status.textContent = input.validationMessage;
    };
    image.src = previewUrl;
  });
});

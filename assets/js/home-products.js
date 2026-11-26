document.addEventListener('DOMContentLoaded', () => {
  const track = document.querySelector('.home-products-track');
  if (!track) return;
  const cards = [...track.querySelectorAll('.home-product')];
  const dots = document.querySelector('.home-products-dots');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  let positions = [];
  const update = () => {
    let active = 0;
    positions.forEach((position, index) => {
      if (Math.abs(track.scrollLeft - position) < Math.abs(track.scrollLeft - positions[active])) active = index;
    });
    const mobile = window.matchMedia('(max-width: 700px)').matches;
    dots.querySelectorAll('button').forEach((dot, index) => {
      dot.setAttribute('aria-pressed', String(index === active));
      dot.hidden = mobile && index !== 0 && index !== positions.length - 1 && Math.abs(index - active) > 1;
    });
    dots.querySelectorAll('.home-products-gap').forEach(gap => {
      gap.hidden = !mobile || (gap.dataset.side === 'start' ? active <= 2 : active >= positions.length - 3);
    });
  };
  const build = () => {
    if (!cards.length) return;
    const style = getComputedStyle(track);
    const visible = Number(style.getPropertyValue('--products-visible'));
    const step = cards[0].getBoundingClientRect().width + parseFloat(style.columnGap);
    const maximum = Math.max(0, track.scrollWidth - track.clientWidth);
    positions = Array.from({length: Math.ceil(cards.length / visible)}, (_, i) => Math.min(i * visible * step, maximum));
    dots.replaceChildren();
    if (positions.length < 2) return;
    positions.forEach((position, index) => {
      const dot = document.createElement('button');
      dot.type = 'button'; dot.className = 'home-products-dot';
      dot.textContent = String(index + 1);
      dot.setAttribute('aria-label', `Tampilkan halaman produk ${index + 1}`);
      dot.setAttribute('aria-controls', 'home-products-track');
      dot.addEventListener('click', () => track.scrollTo({left: position, behavior: reduced.matches ? 'instant' : 'smooth'}));
      dots.append(dot);
      if (positions.length > 5 && (index === 0 || index === positions.length - 2)) {
        const gap = document.createElement('span');
        gap.className = 'home-products-gap'; gap.textContent = '…';
        gap.dataset.side = index === 0 ? 'start' : 'end';
        gap.setAttribute('aria-hidden', 'true'); gap.hidden = true;
        dots.append(gap);
      }
    });
    update();
  };
  track.addEventListener('scroll', update, {passive:true});
  window.addEventListener('resize', build);
  build();
});

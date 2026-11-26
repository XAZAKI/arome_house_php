document.addEventListener('DOMContentLoaded', () => {
  const gallery = document.querySelector('[data-product-gallery]');
  if (gallery) {
    const thumbs = [...gallery.querySelectorAll('[data-image-index]')];
    const main = gallery.querySelector('.product-gallery-main');
    const track = gallery.querySelector('.product-gallery-track');
    const slides = [...gallery.querySelectorAll('.product-gallery-slide')];
    const zoom = gallery.querySelector('.product-gallery-zoom');
    const lens = gallery.querySelector('.product-gallery-lens');
    const hover = window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 1000px)');
    let index = 0, start = null;
    const hideZoom = () => { zoom.hidden = true; lens.hidden = true; };
    const show = number => {
      if (!slides.length) return;
      hideZoom();
      index = Math.max(0, Math.min(number, slides.length - 1));
      track.style.transform = `translateX(-${index * 100}%)`;
      slides.forEach((slide, i) => slide.setAttribute('aria-hidden', String(i !== index)));
      thumbs.forEach((thumb, i) => thumb.setAttribute('aria-pressed', String(i === index)));
      const counter = gallery.querySelector('.product-gallery-counter');
      if (counter) counter.textContent = `${index + 1} / ${slides.length}`;
      gallery.querySelectorAll('[data-gallery-step]').forEach(button => {
        button.disabled = Number(button.dataset.galleryStep) < 0 ? index === 0 : index === slides.length - 1;
      });
    };
    thumbs.forEach((thumb, i) => thumb.addEventListener('click', () => show(i)));
    gallery.querySelectorAll('[data-gallery-step]').forEach(button => button.addEventListener('click', () => show(index + Number(button.dataset.galleryStep))));
    main.addEventListener('keydown', event => {
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault(); show(index + (event.key === 'ArrowRight' ? 1 : -1));
      }
      if (event.key === 'Escape') hideZoom();
    });
    main.addEventListener('pointermove', event => {
      if (!hover.matches || event.pointerType === 'touch') return;
      const image = slides[index]?.querySelector('img');
      if (!image?.naturalWidth) return;
      const rect = main.getBoundingClientRect();
      const scale = Math.min(rect.width / image.naturalWidth, rect.height / image.naturalHeight);
      const width = image.naturalWidth * scale, height = image.naturalHeight * scale;
      const left = (rect.width - width) / 2, top = (rect.height - height) / 2;
      const x = event.clientX - rect.left - left, y = event.clientY - rect.top - top;
      if (x < 0 || y < 0 || x > width || y > height) { hideZoom(); return; }
      zoom.hidden = false;
      const factor = 2.5;
      const lensWidth = Math.min(width, zoom.clientWidth / factor);
      const lensHeight = Math.min(height, zoom.clientHeight / factor);
      const lensX = Math.max(0, Math.min(x - lensWidth / 2, width - lensWidth));
      const lensY = Math.max(0, Math.min(y - lensHeight / 2, height - lensHeight));
      lens.hidden = false;
      Object.assign(lens.style, {width:`${lensWidth}px`,height:`${lensHeight}px`,left:`${left + lensX}px`,top:`${top + lensY}px`});
      zoom.style.backgroundImage = `url(${JSON.stringify(image.currentSrc || image.src)})`;
      zoom.style.backgroundSize = `${width * factor}px ${height * factor}px`;
      zoom.style.backgroundPosition = `${-lensX * factor}px ${-lensY * factor}px`;
    });
    main.addEventListener('pointerleave', hideZoom);
    main.addEventListener('blur', hideZoom);
    window.addEventListener('resize', hideZoom);
    window.addEventListener('scroll', hideZoom, {passive:true});
    hover.addEventListener('change', hideZoom);
    main.addEventListener('touchstart', event => { const touch = event.changedTouches[0]; start = {x:touch.clientX,y:touch.clientY}; }, {passive:true});
    main.addEventListener('touchend', event => {
      if (!start) return;
      const touch = event.changedTouches[0], dx = touch.clientX - start.x, dy = touch.clientY - start.y;
      if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) show(index + (dx < 0 ? 1 : -1));
      start = null;
    }, {passive:true});
    main.addEventListener('touchcancel', () => { start = null; }, {passive:true});
    show(0);
  }
  const form = document.getElementById('catalog-filters');
  if (!form) return;
  const catalog = document.querySelector('.shop-catalog');
  const status = document.createElement('p');
  status.className = 'shop-filter-status'; status.setAttribute('role','status');
  form.append(status);
  const headerOffset = () => document.documentElement.style.setProperty('--store-header-height', `${document.querySelector('.site-header')?.getBoundingClientRect().height || 70}px`);
  headerOffset(); window.addEventListener('resize',headerOffset);
  let controller;
  const update = async (url, push = true) => {
    controller?.abort();
    const request = new AbortController(); controller = request;
    const results = catalog.querySelector('.shop-results');
    results.setAttribute('aria-busy','true'); status.textContent = 'Memuat produk…';
    try {
      const response = await fetch(url, {signal:request.signal, headers:{'X-Requested-With':'XMLHttpRequest'}});
      if (!response.ok) throw new Error('Tidak dapat memuat produk.');
      const doc = new DOMParser().parseFromString(await response.text(),'text/html');
      const next = doc.querySelector('.shop-results'), count = doc.querySelector('.shop-count');
      if (!next || !count) throw new Error('Tidak dapat memuat produk.');
      if (request.signal.aborted) return;
      results.replaceWith(next); catalog.querySelector('.shop-count').replaceWith(count);
      if (push) history.pushState({},'',url);
      status.textContent = count.textContent.trim();
    } catch(error) {
      if (error.name !== 'AbortError') status.textContent = 'Produk gagal dimuat. Coba klik pilihan kembali.';
    } finally { if(controller===request)catalog.querySelector('.shop-results').removeAttribute('aria-busy'); }
  };
  const submit = () => {
    const params = new URLSearchParams(new FormData(form));
    params.delete('p');
    update(`?${params.toString()}`);
  };
  form.addEventListener('change',submit);
  document.querySelector('.shop-sort select')?.addEventListener('change',submit);
  form.addEventListener('submit',event=>{event.preventDefault();submit();});
  const search = document.getElementById('catalog-search');
  let searchTimer;
  search?.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(submit,350);});
  catalog.addEventListener('click',event=>{
    const link=event.target.closest('.shop-pagination a');
    if(!link||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
    event.preventDefault();update(link.href);
    catalog.querySelector('.shop-results').scrollIntoView({behavior:'auto',block:'start'});
  });
  window.addEventListener('popstate',()=>{
    const params=new URLSearchParams(location.search);
    form.querySelectorAll('input[type="checkbox"]').forEach(input=>{input.checked=params.getAll(input.name).includes(input.value);});
    if(search)search.value=params.get('q')||'';
    const sort=document.querySelector('.shop-sort select');if(sort)sort.value=params.get('sort')||'';
    update(location.href,false);
  });
});

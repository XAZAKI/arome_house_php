(() => {
  const button = document.querySelector('.erp-menu-toggle');
  const panel = document.getElementById('erp-navigation-panel');
  if (!button || !panel) return;
  const mobile = window.matchMedia('(max-width: 900px)');
  function setOpen(open) { panel.hidden = !open; button.setAttribute('aria-expanded', String(open)); }
  function resize() { setOpen(!mobile.matches); }
  button.addEventListener('click', () => setOpen(panel.hidden));
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && mobile.matches && !panel.hidden) { setOpen(false); button.focus(); } });
  mobile.addEventListener('change', resize);
  resize();
  const search = document.getElementById('erp-menu-search');
  const empty = document.querySelector('.erp-search-empty');
  if (search) search.addEventListener('input', () => {
    const query = search.value.trim().toLocaleLowerCase('id');
    let found = 0;
    document.querySelectorAll('.erp-nav-section').forEach(section => {
      const headingMatches = section.querySelector('h2').textContent.toLocaleLowerCase('id').includes(query);
      let visible = 0;
      section.querySelectorAll('a').forEach(link => {
        const match = headingMatches || link.textContent.toLocaleLowerCase('id').includes(query);
        link.hidden = !match;
        if (match) visible++;
      });
      section.hidden = visible === 0;
      found += visible;
    });
    const dashboard = document.querySelector('.erp-dashboard-link');
    if (dashboard) {
      dashboard.hidden = !dashboard.textContent.toLocaleLowerCase('id').includes(query);
      if (!dashboard.hidden) found++;
    }
    if (empty) empty.hidden = found > 0;
  });
})();

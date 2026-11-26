document.addEventListener('DOMContentLoaded', () => {
  const period = document.getElementById('finance-period');
  if (!period) return;
  const change = () => {
    document.querySelectorAll('[data-finance-custom]').forEach(label => { label.hidden = period.value !== 'custom'; label.querySelector('input').required = period.value === 'custom'; });
    document.querySelector('[data-finance-anchor]').hidden = period.value === 'custom';
  };
  period.addEventListener('change', change); change();
  if (!window.Chart) { document.querySelector('[data-finance-chart-status]').textContent = 'Grafik belum dapat dimuat. Semua nilai tersedia pada tabel laporan.'; return; }
  const data = JSON.parse(document.getElementById('finance-chart-data').textContent);
  const money = value => new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(value);
  new Chart(document.getElementById('finance-trend'), {type:'line', data:{labels:data.trend.labels, datasets:[
    {label:'Pendapatan',data:data.trend.revenue,borderColor:'#477e69',tension:0.2},
    {label:'Total biaya (termasuk HPP)',data:data.trend.cost,borderColor:'#bf9654',tension:0.2},
    {label:'Laba bersih',data:data.trend.net,borderColor:'#8474ac',tension:0.2}
  ]}, options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom'},tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${money(ctx.parsed.y)}`}}},scales:{y:{ticks:{callback:v=>money(v)}}}}});
  new Chart(document.getElementById('finance-costs'), {type:'doughnut',data:{labels:data.categories,datasets:[{data:data.amounts,backgroundColor:['#8474ac','#477e69','#bf9654','#719cb3','#bc7b8e','#807465','#8eab75']}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'},tooltip:{callbacks:{label:ctx=>`${ctx.label}: ${money(ctx.parsed)}`}}}}});
});

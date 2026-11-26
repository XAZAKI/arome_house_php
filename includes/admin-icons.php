<?php
function erp_icon(string $name): string {
    $icons=[
        'overview'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'box'=>'<path d="m12 3 9 5-9 5-9-5 9-5Z M3 8v9l9 5 9-5V8 M12 13v9 M7.5 5.5l9 5"/>',
        'users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3 M16 5a3 3 0 0 1 0 6 M21 21v-3a6 6 0 0 0-4-5"/>',
        'person'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="9" r="3"/><path d="M6 19v-1a6 6 0 0 1 12 0v1"/>',
        'chart'=>'<path d="M3 3v18h18 M7 15l4-5 4 3 6-8 M17 5h4v4"/>',
        'clipboard'=>'<path d="M8 5H5v16h14V5h-3"/><rect x="8" y="3" width="8" height="4" rx="1"/><path d="m8 13 3 3 6-7"/>',
        'list'=>'<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6 M9 12h6 M9 16h4"/>',
        'money'=>'<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M2 9a4 4 0 0 0 4-4 M18 5a4 4 0 0 0 4 4 M2 15a4 4 0 0 1 4 4 M18 19a4 4 0 0 1 4-4"/>',
        'plus'=>'<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M12 7v10 M7 12h10"/>',
        'truck'=>'<path d="M1 5h13v12H1z M14 9h5l3 4v4h-8"/><circle cx="5" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'arrows'=>'<path d="M3 7h17l-4-4 M21 17H4l4 4 M20 7l-4 4 M4 17l4-4"/>',
        'store'=>'<path d="M3 10v11h18V10 M2 4h20l-2 6H4L2 4Z M9 21v-7h6v7"/>',
        'logout'=>'<path d="M9 3H4v18h5 M9 12h13 M17 7l5 5-5 5"/>',
        'menu'=>'<path d="M3 6h18 M3 12h18 M3 18h18"/>',
    ];
    return '<svg class="erp-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$name]??$icons['list']).'</svg>';
}

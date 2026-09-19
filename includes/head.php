<?php
/**
 * Partial <head> bersama.
 * Wajib set $pageTitle sebelum me-require file ini.
 * Konfigurasi Tailwind dipusatkan di sini supaya semua halaman
 * punya tampilan (warna, spacing, font) yang konsisten satu sama lain.
 */
$pageTitle = $pageTitle ?? 'Portal Guru & Admin';
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?> - SMA Negeri 1 Bumiayu</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
    // ---------------------------------------------------------------
    // Design tokens portal SMAN 1 Bumiayu — jangan diubah per halaman,
    // cukup ubah di sini agar seluruh portal tetap konsisten.
    // ---------------------------------------------------------------
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    background: '#f8f9fa',
                    surface: '#f8f9fa',
                    'surface-dim': '#d9dadb',
                    'surface-bright': '#f8f9fa',
                    'surface-container-lowest': '#ffffff',
                    'surface-container-low': '#f3f4f5',
                    'surface-container': '#edeeef',
                    'surface-container-high': '#e7e8e9',
                    'surface-container-highest': '#e1e3e4',
                    'surface-variant': '#e1e3e4',
                    'surface-white': '#FFFFFF',
                    'on-surface': '#191c1d',
                    'on-surface-variant': '#434653',
                    'on-background': '#191c1d',
                    'inverse-surface': '#2e3132',
                    'inverse-on-surface': '#f0f1f2',
                    outline: '#737784',
                    'outline-variant': '#c3c6d5',
                    'surface-tint': '#2259bf',
                    primary: '#094cb2',
                    'on-primary': '#ffffff',
                    'primary-container': '#3366cc',
                    'on-primary-container': '#e7ebff',
                    'inverse-primary': '#b1c5ff',
                    'primary-fixed': '#d9e2ff',
                    'primary-fixed-dim': '#b1c5ff',
                    'on-primary-fixed': '#001946',
                    'on-primary-fixed-variant': '#00419d',
                    secondary: '#705d00',
                    'on-secondary': '#ffffff',
                    'secondary-container': '#fcd400',
                    'on-secondary-container': '#6e5c00',
                    'secondary-fixed': '#ffe16d',
                    'secondary-fixed-dim': '#e9c400',
                    'on-secondary-fixed': '#221b00',
                    'on-secondary-fixed-variant': '#544600',
                    tertiary: '#a80716',
                    'on-tertiary': '#ffffff',
                    'tertiary-container': '#cc292b',
                    'on-tertiary-container': '#ffe7e4',
                    'tertiary-fixed': '#ffdad6',
                    'tertiary-fixed-dim': '#ffb3ac',
                    'on-tertiary-fixed': '#410003',
                    'on-tertiary-fixed-variant': '#930010',
                    error: '#ba1a1a',
                    'on-error': '#ffffff',
                    'error-container': '#ffdad6',
                    'on-error-container': '#93000a',
                    'sidebar-bg': '#1A3A7A',
                    'text-main': '#202122',
                    'text-muted': '#5F6368',
                    'status-gold': '#F8F5C1',
                },
                borderRadius: {
                    DEFAULT: '0.25rem',
                    lg: '0.5rem',
                    xl: '0.75rem',
                    full: '9999px',
                },
                spacing: {
                    xs: '4px',
                    sm: '8px',
                    base: '8px',
                    md: '16px',
                    lg: '24px',
                    xl: '32px',
                    gutter: '24px',
                    'sidebar-width': '280px',
                    'container-max': '1440px',
                },
                fontFamily: {
                    'display-lg': ['Inter'],
                    'headline-lg': ['Inter'],
                    'headline-lg-mobile': ['Inter'],
                    'headline-md': ['Inter'],
                    'headline-sm': ['Inter'],
                    'body-lg': ['Inter'],
                    'body-md': ['Inter'],
                    'body-sm': ['Inter'],
                    'label-lg': ['Inter'],
                    'label-md': ['Inter'],
                },
                fontSize: {
                    'display-lg': ['48px', { lineHeight: '56px', letterSpacing: '-0.02em', fontWeight: '700' }],
                    'headline-lg': ['32px', { lineHeight: '40px', letterSpacing: '-0.01em', fontWeight: '600' }],
                    'headline-lg-mobile': ['24px', { lineHeight: '32px', fontWeight: '600' }],
                    'headline-md': ['24px', { lineHeight: '32px', fontWeight: '600' }],
                    'headline-sm': ['20px', { lineHeight: '28px', fontWeight: '600' }],
                    'body-lg': ['18px', { lineHeight: '28px', fontWeight: '400' }],
                    'body-md': ['16px', { lineHeight: '24px', fontWeight: '400' }],
                    'body-sm': ['14px', { lineHeight: '20px', fontWeight: '400' }],
                    'label-lg': ['14px', { lineHeight: '20px', letterSpacing: '0.01em', fontWeight: '600' }],
                    'label-md': ['12px', { lineHeight: '16px', letterSpacing: '0.02em', fontWeight: '500' }],
                },
            },
        },
    };
</script>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
    }
    .material-symbols-outlined[data-weight="fill"] { font-variation-settings: 'FILL' 1; }

    /* Sidebar: item navigasi yang sedang aktif */
    .sidebar-active {
        background-color: rgba(255, 255, 255, 0.1);
        border-left: 4px solid #ffe16d;
        color: #ffe16d !important;
        font-weight: 700;
    }

    /* Bayangan kartu standar dipakai di semua halaman */
    .card-shadow { box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.05); }
    .ambient-shadow-1 { box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.05); }
    .ambient-shadow-2 { box-shadow: 0px 8px 32px rgba(0, 0, 0, 0.12); }

    .glass-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }

    .announcement-watermark {
        background-image: radial-gradient(circle at 2px 2px, rgba(26, 58, 122, 0.03) 1px, transparent 0);
        background-size: 24px 24px;
    }

    /* Toggle switch (dipakai di halaman Pengaturan) */
    .toggle-checkbox:checked { right: 0; border-color: #094cb2; }
    .toggle-checkbox:checked + .toggle-label { background-color: #094cb2; }
    .toggle-checkbox:checked + .toggle-label:after { transform: translateX(100%); border-color: white; }
    .toggle-label {
        width: 44px; height: 24px; background-color: #e1e3e4;
        border-radius: 9999px; position: relative; cursor: pointer; transition: all .3s;
    }
    .toggle-label:after {
        content: ''; position: absolute; top: 2px; left: 2px;
        width: 20px; height: 20px; background-color: #fff; border-radius: 50%; transition: all .3s;
    }

    /* Scrollbar kustom untuk tabel yang lebar (Rekap Nilai) */
    .table-container::-webkit-scrollbar { height: 8px; width: 8px; }
    .table-container::-webkit-scrollbar-track { background: #f8f9fa; }
    .table-container::-webkit-scrollbar-thumb { background: #c3c6d5; border-radius: 4px; }
    .table-container::-webkit-scrollbar-thumb:hover { background: #737784; }
</style>
</head>
<body class="text-text-main antialiased">

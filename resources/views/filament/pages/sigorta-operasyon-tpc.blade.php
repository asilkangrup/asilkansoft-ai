@include('filament.pages.sigorta-operasyon-merkezi')

<style>
/* TPC Insurance OS — dedicated customer shell */
.fi-header,
.fi-page-header,
#wai-sidebar-toggle {
    display: none !important;
}

.fi-main,
.fi-page,
.fi-page-content {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

.fi-main-ctn,
.fi-main {
    max-width: none !important;
}

body.fi-body {
    background: #06111f !important;
}

.ins-page {
    min-height: 100vh;
}

.ins-hero,
.ins-kpi,
.ins-panel {
    backdrop-filter: blur(18px);
}

.ins-hero {
    box-shadow: 0 30px 90px rgba(0,0,0,.28) !important;
}

.ins-kpi,
.ins-panel {
    box-shadow: 0 18px 46px rgba(0,0,0,.16) !important;
}

.ins-kpi:hover,
.ins-panel:hover {
    border-color: rgba(56,189,248,.22) !important;
}

@media (min-width: 1024px) {
    .fi-main {
        padding-left: 22px !important;
        padding-right: 22px !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('*').forEach((el) => {
        if (el.children.length === 0 && el.textContent.trim() === 'WAI Insurance OS') {
            el.textContent = 'TPC Insurance OS';
        }
        if (el.children.length === 0 && el.textContent.trim() === 'Kurumsal Sigorta Operasyon Platformu') {
            el.textContent = 'Doğuş Topçu Sigorta • Kurumsal Operasyon Platformu';
        }
    });
});
</script>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>WAI by AsilkanSoft — Yapay Zeka, CRM ve Dijital İşletme Platformu</title>

    <meta
        name="description"
        content="WAI by AsilkanSoft ile yapay zekâ, CRM, satış otomasyonu, web sitesi, e-ticaret, domain, hosting ve dijital büyüme çözümlerini tek ekosistemde yönetin."
    >

    <meta name="theme-color" content="#050806">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --bg: #050806;
            --bg-soft: #080d0a;
            --bg-card: #0c120e;
            --bg-card-2: #101812;

            --white: #f7fff9;
            --text: #edf7f0;
            --muted: #8e9b92;
            --muted-2: #667169;

            --green: #5cff9d;
            --green-strong: #21e77f;
            --green-dark: #0d8f4c;
            --green-deep: #071d11;

            --line: rgba(255,255,255,.075);
            --line-green: rgba(92,255,157,.18);

            --shadow:
                0 30px 100px rgba(0,0,0,.45),
                0 10px 35px rgba(0,0,0,.25);

            --radius-sm: 14px;
            --radius-md: 22px;
            --radius-lg: 32px;
            --radius-xl: 44px;

            --container: 1240px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 8% -10%, rgba(92,255,157,.10), transparent 26%),
                radial-gradient(circle at 90% 5%, rgba(92,255,157,.06), transparent 24%),
                var(--bg);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        body.menu-open {
            overflow: hidden;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font: inherit;
        }

        svg {
            display: block;
        }

        ::selection {
            background: var(--green);
            color: #07100a;
        }

        .container {
            width: min(var(--container), calc(100% - 40px));
            margin: 0 auto;
        }

        .noise {
            position: fixed;
            inset: 0;
            z-index: 9999;
            pointer-events: none;
            opacity: .025;
            background-image:
                url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.9'/%3E%3C/svg%3E");
        }

        .grid-overlay {
            position: fixed;
            inset: 0;
            z-index: -1;
            opacity: .24;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,.018) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.018) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: linear-gradient(to bottom, #000 0%, transparent 70%);
        }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: var(--green);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.8px;
        }

        .section-tag::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            box-shadow:
                0 0 0 5px rgba(92,255,157,.08),
                0 0 22px rgba(92,255,157,.38);
        }

        .section-title {
            max-width: 900px;
            margin-top: 18px;
            font-family: 'Manrope', sans-serif;
            font-size: clamp(42px, 5.5vw, 74px);
            line-height: .98;
            font-weight: 800;
            letter-spacing: -4px;
        }

        .section-copy {
            max-width: 650px;
            margin-top: 22px;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.78;
        }

        /* ============================================================
           NAVIGATION
        ============================================================ */

        .nav-spacer {
            height: 96px;
        }

        .nav-wrap {
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            right: 0;
            padding-top: 14px;
            transition: .28s ease;
        }

        .nav-wrap.scrolled {
            padding-top: 8px;
        }

        .navbar {
            min-height: 68px;
            padding: 9px 10px 9px 16px;

            display: flex;
            align-items: center;
            gap: 28px;

            border: 1px solid rgba(255,255,255,.08);
            border-radius: 21px;

            background:
                linear-gradient(
                    180deg,
                    rgba(14,20,16,.83),
                    rgba(7,11,8,.74)
                );

            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);

            box-shadow:
                0 20px 55px rgba(0,0,0,.32),
                inset 0 0 0 1px rgba(255,255,255,.025);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            flex-shrink: 0;
        }

        .brand-logo {
            position: relative;
            width: 42px;
            height: 42px;
            overflow: hidden;
            display: grid;
            place-items: center;

            border-radius: 13px;
            background:
                radial-gradient(circle at 25% 15%, rgba(92,255,157,.35), transparent 35%),
                #07100a;

            border: 1px solid rgba(92,255,157,.12);

            box-shadow:
                inset 0 0 20px rgba(92,255,157,.04),
                0 10px 30px rgba(0,0,0,.25);
        }

        .brand-logo::before,
        .brand-logo::after {
            content: "";
            position: absolute;
            width: 18px;
            height: 3px;
            border-radius: 999px;
            background: var(--green);
        }

        .brand-logo::before {
            transform: rotate(55deg);
            left: 9px;
        }

        .brand-logo::after {
            transform: rotate(-55deg);
            right: 9px;
        }

        .brand-name {
            font-family: 'Manrope', sans-serif;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .brand-name small {
            display: block;
            margin-top: 2px;
            color: #67736b;
            font-family: 'Inter', sans-serif;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
            margin-left: auto;
        }

        .nav-links a {
            position: relative;
            color: #9aa69e;
            font-size: 13px;
            font-weight: 600;
            transition: .2s ease;
        }

        .nav-links a:hover {
            color: white;
        }

        .nav-links a::before {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -10px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--green);
            transform: translateX(-50%) scale(0);
            transition: .2s ease;
        }

        .nav-links a:hover::before {
            transform: translateX(-50%) scale(1);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-login {
            min-height: 46px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #adb8b0;
            font-size: 13px;
            font-weight: 700;
            border-radius: 13px;
            transition: .2s ease;
        }

        .nav-login:hover {
            color: white;
            background: rgba(255,255,255,.04);
        }

        .nav-cta {
            min-height: 48px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            color: #06150c;
            background: var(--green);

            border-radius: 13px;

            font-size: 13px;
            font-weight: 800;

            box-shadow:
                0 12px 28px rgba(92,255,157,.12),
                inset 0 -1px 0 rgba(0,0,0,.15);

            transition: .22s ease;
        }

        .nav-cta:hover {
            transform: translateY(-2px);
            background: #79ffae;
            box-shadow: 0 18px 38px rgba(92,255,157,.18);
        }

        .nav-cta svg {
            width: 15px;
        }

        .menu-button {
            display: none;
            width: 44px;
            height: 44px;
            border: 1px solid var(--line);
            border-radius: 12px;
            color: white;
            background: rgba(255,255,255,.04);
            cursor: pointer;
        }

        .menu-button span,
        .menu-button span::before,
        .menu-button span::after {
            position: absolute;
            width: 18px;
            height: 2px;
            border-radius: 999px;
            background: currentColor;
        }

        .menu-button span {
            position: relative;
            display: block;
            margin: 0 auto;
        }

        .menu-button span::before,
        .menu-button span::after {
            content: "";
            left: 0;
        }

        .menu-button span::before {
            top: -6px;
        }

        .menu-button span::after {
            top: 6px;
        }

        .mobile-menu {
            display: none;
        }

        /* ============================================================
           HERO
        ============================================================ */

        .hero {
            position: relative;
            min-height: calc(100vh - 96px);
            padding: 65px 0 100px;
            overflow: hidden;
        }

        .hero-light {
            position: absolute;
            width: 820px;
            height: 820px;
            top: -320px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 50%;
            background:
                radial-gradient(circle,
                    rgba(92,255,157,.14) 0%,
                    rgba(92,255,157,.05) 30%,
                    transparent 68%
                );
            pointer-events: none;
        }

        .hero-ray {
            position: absolute;
            width: 1100px;
            height: 800px;
            top: -300px;
            left: 50%;
            transform: translateX(-50%);
            opacity: .28;
            background:
                conic-gradient(
                    from 180deg at 50% 0%,
                    transparent 0deg,
                    rgba(92,255,157,.08) 8deg,
                    transparent 16deg,
                    transparent 35deg,
                    rgba(92,255,157,.05) 44deg,
                    transparent 52deg
                );
            mask-image: linear-gradient(to bottom, black, transparent 75%);
            pointer-events: none;
        }

        .hero-grid {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: minmax(0, .96fr) minmax(490px, 1.04fr);
            gap: 40px;
            align-items: center;
        }

        .hero-copy {
            padding-top: 25px;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 38px;
            padding: 0 14px;

            border: 1px solid rgba(92,255,157,.12);
            border-radius: 999px;

            background:
                linear-gradient(
                    90deg,
                    rgba(92,255,157,.07),
                    rgba(255,255,255,.02)
                );

            color: #a6ffca;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
        }

        .live-dot {
            position: relative;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 15px rgba(92,255,157,.65);
        }

        .live-dot::after {
            content: "";
            position: absolute;
            inset: -6px;
            border: 1px solid rgba(92,255,157,.35);
            border-radius: 50%;
            animation: pulse 2s ease-out infinite;
        }

        @keyframes pulse {
            from {
                opacity: 1;
                transform: scale(.5);
            }

            to {
                opacity: 0;
                transform: scale(1.8);
            }
        }

        .hero h1 {
            max-width: 780px;
            margin-top: 25px;

            font-family: 'Manrope', sans-serif;
            font-size: clamp(59px, 6.6vw, 94px);
            line-height: .91;
            font-weight: 800;
            letter-spacing: -6px;
        }

        .hero h1 .muted {
            color: #616c64;
        }

        .hero h1 .green {
            position: relative;
            display: inline-block;
            color: var(--green);
            text-shadow: 0 0 50px rgba(92,255,157,.08);
        }

        .hero h1 .green::after {
            content: "";
            position: absolute;
            left: 0;
            right: -4px;
            bottom: 2px;
            height: 10px;
            z-index: -1;
            border-radius: 999px;
            background: rgba(92,255,157,.13);
            filter: blur(2px);
        }

        .hero-description {
            max-width: 640px;
            margin-top: 27px;
            color: #929e96;
            font-size: 17px;
            line-height: 1.75;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 32px;
        }

        .button-main,
        .button-ghost {
            min-height: 58px;
            padding: 0 23px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 11px;

            border-radius: 16px;

            font-size: 14px;
            font-weight: 800;

            transition: .24s ease;
        }

        .button-main {
            color: #06130b;
            background: var(--green);

            box-shadow:
                0 20px 50px rgba(92,255,157,.12),
                inset 0 -2px 0 rgba(0,0,0,.12);
        }

        .button-main:hover {
            transform: translateY(-3px);
            background: #7affae;
            box-shadow: 0 26px 60px rgba(92,255,157,.20);
        }

        .button-icon {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: rgba(4,17,9,.09);
        }

        .button-icon svg {
            width: 14px;
        }

        .button-ghost {
            color: #d8e1da;
            border: 1px solid rgba(255,255,255,.09);
            background: rgba(255,255,255,.035);
        }

        .button-ghost:hover {
            transform: translateY(-2px);
            background: rgba(255,255,255,.07);
        }

        .hero-trust {
            display: flex;
            flex-wrap: wrap;
            gap: 14px 18px;
            margin-top: 20px;
            color: #657168;
            font-size: 10px;
            font-weight: 600;
        }

        .hero-trust span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .hero-trust svg {
            width: 13px;
            color: var(--green);
        }

        /* ============================================================
           HERO PRODUCT VISUAL
        ============================================================ */

        .hero-visual {
            position: relative;
            min-height: 680px;
            perspective: 1500px;
        }

        .halo {
            position: absolute;
            width: 540px;
            height: 540px;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            border-radius: 50%;

            background:
                radial-gradient(circle,
                    rgba(92,255,157,.13),
                    rgba(92,255,157,.035) 36%,
                    transparent 68%
                );

            border: 1px solid rgba(92,255,157,.06);
        }

        .halo::before,
        .halo::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(92,255,157,.055);
        }

        .halo::before {
            inset: 55px;
        }

        .halo::after {
            inset: 120px;
        }

        .dashboard-card {
            position: absolute;
            z-index: 3;
            width: 570px;
            height: 500px;
            left: 50%;
            top: 50%;
            overflow: hidden;

            transform:
                translate(-50%, -50%)
                rotateY(-7deg)
                rotateX(4deg)
                rotateZ(1deg);

            border-radius: 27px;
            border: 1px solid rgba(255,255,255,.08);

            background:
                linear-gradient(145deg, #101712, #090d0a);

            box-shadow:
                0 80px 140px rgba(0,0,0,.46),
                0 25px 60px rgba(0,0,0,.35),
                inset 0 0 0 1px rgba(255,255,255,.02);

            transition: .5s cubic-bezier(.2,.8,.2,1);
        }

        .hero-visual:hover .dashboard-card {
            transform:
                translate(-50%, -52%)
                rotateY(-2deg)
                rotateX(1deg)
                rotateZ(.2deg);
        }

        .dash-top {
            height: 51px;
            padding: 0 15px;

            display: flex;
            align-items: center;
            gap: 7px;

            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .dash-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #303a33;
        }

        .dash-address {
            width: 190px;
            height: 26px;
            margin-left: 8px;
            padding: 0 10px;

            display: flex;
            align-items: center;

            border-radius: 8px;
            background: rgba(255,255,255,.035);

            color: #536057;
            font-size: 7px;
        }

        .dash-grid {
            height: calc(100% - 51px);
            display: grid;
            grid-template-columns: 165px 1fr 150px;
        }

        .dash-sidebar {
            padding: 14px 10px;
            border-right: 1px solid rgba(255,255,255,.055);
        }

        .dash-logo {
            height: 31px;
            margin-bottom: 14px;
            padding: 0 9px;
            display: flex;
            align-items: center;
            gap: 7px;

            border-radius: 9px;
            color: #c9d5cd;
            font-size: 8px;
            font-weight: 700;
        }

        .dash-logo .mini-w {
            width: 20px;
            height: 20px;
            border-radius: 7px;

            display: grid;
            place-items: center;

            background: rgba(92,255,157,.12);
            color: var(--green);

            font-size: 7px;
        }

        .dash-nav-label {
            padding: 8px 9px 5px;
            color: #455148;
            font-size: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .dash-nav-item {
            height: 31px;
            padding: 0 9px;
            margin-bottom: 3px;

            display: flex;
            align-items: center;
            gap: 7px;

            border-radius: 8px;

            color: #667269;
            font-size: 7px;
        }

        .dash-nav-item.active {
            color: #baffd4;
            background: rgba(92,255,157,.08);
        }

        .nav-square {
            width: 17px;
            height: 17px;
            display: grid;
            place-items: center;
            border-radius: 5px;
            background: rgba(255,255,255,.035);
        }

        .dash-nav-item.active .nav-square {
            background: rgba(92,255,157,.10);
        }

        .dash-chat {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .dash-chat-header {
            height: 57px;
            padding: 0 13px;

            display: flex;
            align-items: center;
            gap: 8px;

            border-bottom: 1px solid rgba(255,255,255,.055);
        }

        .client-avatar {
            width: 29px;
            height: 29px;
            flex-shrink: 0;

            display: grid;
            place-items: center;

            border-radius: 9px;

            background:
                linear-gradient(
                    145deg,
                    rgba(92,255,157,.17),
                    rgba(92,255,157,.05)
                );

            color: #8effb8;
            font-size: 7px;
            font-weight: 800;
        }

        .client-name {
            flex: 1;
        }

        .client-name strong {
            display: block;
            font-size: 8px;
        }

        .client-name span {
            display: block;
            margin-top: 2px;
            color: #546158;
            font-size: 5px;
        }

        .ai-status {
            height: 24px;
            padding: 0 7px;

            display: inline-flex;
            align-items: center;
            gap: 5px;

            border-radius: 7px;
            background: rgba(92,255,157,.08);
            color: var(--green);

            font-size: 5px;
            font-weight: 800;
        }

        .ai-status::before {
            content: "";
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 8px rgba(92,255,157,.6);
        }

        .dash-messages {
            flex: 1;
            padding: 19px 13px;

            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;

            background:
                radial-gradient(circle at 50% 100%, rgba(92,255,157,.025), transparent 32%);
        }

        .dash-message {
            max-width: 78%;
            padding: 8px 9px;

            border-radius: 9px;
            font-size: 6.5px;
            line-height: 1.55;
        }

        .dash-message.in {
            align-self: flex-start;
            color: #a9b5ad;
            background: rgba(255,255,255,.05);
            border-top-left-radius: 3px;
        }

        .dash-message.out {
            align-self: flex-end;
            color: #c3ffdb;
            background: rgba(92,255,157,.09);
            border: 1px solid rgba(92,255,157,.04);
            border-top-right-radius: 3px;
        }

        .dash-message time {
            display: block;
            margin-top: 3px;
            color: #47534a;
            font-size: 4.5px;
            text-align: right;
        }

        .dash-compose {
            height: 54px;
            padding: 8px 10px;
            display: flex;
            gap: 6px;
            border-top: 1px solid rgba(255,255,255,.055);
        }

        .dash-input {
            flex: 1;
            height: 34px;
            padding: 0 10px;

            display: flex;
            align-items: center;

            border-radius: 9px;
            background: rgba(255,255,255,.035);

            color: #465149;
            font-size: 5px;
        }

        .dash-send {
            width: 34px;
            height: 34px;

            display: grid;
            place-items: center;

            border-radius: 9px;
            background: var(--green);
            color: #051209;
        }

        .dash-send svg {
            width: 11px;
        }

        .dash-crm {
            padding: 13px 10px;
            border-left: 1px solid rgba(255,255,255,.055);
        }

        .crm-title {
            color: #aeb9b1;
            font-size: 7px;
            font-weight: 700;
        }

        .crm-profile {
            margin-top: 12px;
            padding: 11px;

            border: 1px solid rgba(255,255,255,.05);
            border-radius: 11px;

            background: rgba(255,255,255,.02);
        }

        .crm-profile strong {
            display: block;
            font-size: 7px;
        }

        .crm-profile span {
            display: block;
            margin-top: 3px;
            color: #505c53;
            font-size: 5px;
        }

        .crm-divider {
            height: 1px;
            margin: 10px 0;
            background: rgba(255,255,255,.05);
        }

        .crm-field {
            margin-top: 9px;
        }

        .crm-field label {
            display: block;
            color: #455047;
            font-size: 4.5px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .crm-field strong {
            display: block;
            margin-top: 4px;
            color: #9faa9f;
            font-size: 6px;
        }

        .hot-tag {
            display: inline-flex;
            margin-top: 6px;
            padding: 4px 6px;

            border-radius: 6px;

            color: #ffd079;
            background: rgba(255,193,79,.08);

            font-size: 5px;
            font-weight: 700;
        }

        .takeover-btn {
            height: 27px;
            margin-top: 11px;

            display: grid;
            place-items: center;

            border-radius: 7px;
            background: var(--green);

            color: #05150a;
            font-size: 5px;
            font-weight: 800;
        }

        .float-panel {
            position: absolute;
            z-index: 8;

            border: 1px solid rgba(255,255,255,.08);
            border-radius: 17px;

            background:
                linear-gradient(
                    145deg,
                    rgba(17,25,19,.94),
                    rgba(8,13,9,.91)
                );

            backdrop-filter: blur(18px);

            box-shadow:
                0 30px 65px rgba(0,0,0,.34),
                inset 0 0 0 1px rgba(255,255,255,.015);

            animation: float 5s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .float-panel.lead {
            width: 190px;
            top: 71px;
            right: -5px;
            padding: 14px;
        }

        .float-panel.ai {
            width: 176px;
            left: -14px;
            bottom: 89px;
            padding: 13px;
            animation-delay: -2s;
        }

        .float-panel.follow {
            width: 185px;
            right: -18px;
            bottom: 45px;
            padding: 13px;
            animation-delay: -1s;
        }

        .float-head {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .float-icon {
            width: 29px;
            height: 29px;
            display: grid;
            place-items: center;

            border-radius: 9px;

            background: rgba(92,255,157,.09);
            color: var(--green);
        }

        .float-icon svg {
            width: 13px;
        }

        .float-head strong {
            display: block;
            font-size: 8px;
        }

        .float-head span {
            display: block;
            margin-top: 2px;
            color: #58655c;
            font-size: 5px;
        }

        .mini-meter {
            height: 4px;
            margin-top: 11px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(255,255,255,.05);
        }

        .mini-meter i {
            display: block;
            width: 76%;
            height: 100%;
            border-radius: inherit;
            background: var(--green);
            box-shadow: 0 0 10px rgba(92,255,157,.5);
        }

        /* ============================================================
           INDUSTRY MARQUEE
        ============================================================ */

        .industry-strip {
            padding: 20px 0 95px;
        }

        .industry-shell {
            overflow: hidden;
            padding: 20px 0;

            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);

            mask-image:
                linear-gradient(
                    90deg,
                    transparent,
                    #000 8%,
                    #000 92%,
                    transparent
                );
        }

        .industry-track {
            width: max-content;
            display: flex;
            gap: 10px;
            animation: marquee 33s linear infinite;
        }

        @keyframes marquee {
            from {
                transform: translateX(0);
            }

            to {
                transform: translateX(-50%);
            }
        }

        .industry-pill {
            min-width: 170px;
            height: 54px;
            padding: 0 18px;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            border: 1px solid rgba(255,255,255,.065);
            border-radius: 15px;

            background: rgba(255,255,255,.022);

            color: #78847b;
            font-size: 11px;
            font-weight: 700;
        }

        .industry-pill svg {
            width: 16px;
            color: var(--green);
        }

        /* ============================================================
           MANIFESTO
        ============================================================ */

        .manifesto {
            padding: 125px 0;
        }

        .manifesto-grid {
            display: grid;
            grid-template-columns: .85fr 1.15fr;
            gap: 90px;
        }

        .manifesto-copy {
            position: sticky;
            top: 130px;
            align-self: start;
        }

        .manifesto-list {
            border-top: 1px solid var(--line);
        }

        .manifesto-item {
            position: relative;
            min-height: 150px;
            padding: 24px 20px;

            display: grid;
            grid-template-columns: 70px 1fr;
            align-items: center;

            overflow: hidden;
            border-bottom: 1px solid var(--line);
        }

        .manifesto-item::before {
            content: "";
            position: absolute;
            inset: 0;

            opacity: 0;

            background:
                linear-gradient(
                    90deg,
                    rgba(92,255,157,.08),
                    transparent 70%
                );

            transition: .35s ease;
        }

        .manifesto-item:hover::before {
            opacity: 1;
        }

        .manifesto-no {
            position: relative;
            color: #4c5850;
            font-size: 10px;
            font-weight: 700;
        }

        .manifesto-item h3 {
            position: relative;
            font-family: 'Manrope', sans-serif;
            font-size: clamp(29px, 3.2vw, 45px);
            line-height: 1.05;
            letter-spacing: -2px;
        }

        .manifesto-item h3 span {
            color: #616d65;
        }

        /* ============================================================
           COMMAND CENTER
        ============================================================ */

        .command-section {
            position: relative;
            padding: 120px 0;
            overflow: hidden;
            background: #070b08;
        }

        .command-section::before {
            content: "";
            position: absolute;
            width: 720px;
            height: 720px;
            top: -300px;
            right: -220px;
            border-radius: 50%;
            background:
                radial-gradient(circle, rgba(92,255,157,.09), transparent 67%);
        }

        .command-header {
            position: relative;
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 80px;
            align-items: end;
        }

        .command-description {
            max-width: 520px;
            justify-self: end;
            color: #839087;
            font-size: 16px;
            line-height: 1.75;
        }

        .command-board {
            position: relative;
            margin-top: 60px;
            padding: 1px;
            border-radius: 33px;

            background:
                linear-gradient(
                    135deg,
                    rgba(92,255,157,.18),
                    rgba(255,255,255,.055),
                    rgba(92,255,157,.03)
                );

            box-shadow:
                0 70px 130px rgba(0,0,0,.45);
        }

        .command-inner {
            position: relative;
            min-height: 650px;
            overflow: hidden;
            border-radius: 32px;
            background:
                linear-gradient(180deg, #101712, #090e0a);
        }

        .command-inner::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 65% 100%, rgba(92,255,157,.045), transparent 38%);
        }

        .command-top {
            height: 62px;
            padding: 0 19px;

            display: flex;
            align-items: center;
            gap: 8px;

            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .command-browser-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #303a33;
        }

        .command-url {
            width: 280px;
            height: 30px;
            margin-left: 11px;
            padding: 0 12px;

            display: flex;
            align-items: center;

            border-radius: 8px;
            background: rgba(255,255,255,.035);

            color: #516057;
            font-size: 8px;
        }

        .command-grid {
            display: grid;
            grid-template-columns: 245px minmax(0,1fr) 275px;
            min-height: 588px;
        }

        .conversation-list {
            border-right: 1px solid rgba(255,255,255,.055);
        }

        .panel-title {
            height: 61px;
            padding: 0 17px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            border-bottom: 1px solid rgba(255,255,255,.05);
        }

        .panel-title strong {
            font-size: 11px;
        }

        .badge-count {
            min-width: 25px;
            height: 23px;
            padding: 0 7px;

            display: grid;
            place-items: center;

            border-radius: 7px;
            color: var(--green);
            background: rgba(92,255,157,.08);

            font-size: 7px;
            font-weight: 800;
        }

        .conversation-filters {
            display: flex;
            gap: 5px;
            padding: 10px;
            overflow: hidden;
        }

        .conv-filter {
            min-width: max-content;
            height: 27px;
            padding: 0 8px;

            display: grid;
            place-items: center;

            border-radius: 7px;

            color: #59665d;
            background: rgba(255,255,255,.03);

            font-size: 6px;
            font-weight: 700;
        }

        .conv-filter.active {
            color: var(--green);
            background: rgba(92,255,157,.08);
        }

        .conversation {
            padding: 12px 12px;
            display: flex;
            gap: 10px;

            border-bottom: 1px solid rgba(255,255,255,.03);
        }

        .conversation.active {
            background:
                linear-gradient(
                    90deg,
                    rgba(92,255,157,.075),
                    rgba(92,255,157,.015)
                );
        }

        .conversation-avatar {
            width: 35px;
            height: 35px;
            flex-shrink: 0;

            display: grid;
            place-items: center;

            border-radius: 10px;
            background: #172019;

            color: #89958c;
            font-size: 8px;
            font-weight: 800;
        }

        .conversation.active .conversation-avatar {
            color: #c4ffdc;
            background: rgba(92,255,157,.12);
        }

        .conversation-content {
            min-width: 0;
            flex: 1;
        }

        .conversation-line {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .conversation-line strong {
            color: #abb6ae;
            font-size: 8px;
            white-space: nowrap;
        }

        .conversation-line time {
            color: #455149;
            font-size: 5px;
        }

        .conversation-content p {
            margin-top: 4px;
            overflow: hidden;
            color: #556159;
            font-size: 6px;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .conversation-tag {
            display: inline-flex;
            margin-top: 5px;
            padding: 3px 5px;
            border-radius: 5px;

            color: #ffd17a;
            background: rgba(255,200,91,.07);

            font-size: 5px;
            font-weight: 800;
        }

        .main-chat-panel {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .main-chat-head {
            height: 61px;
            padding: 0 16px;

            display: flex;
            align-items: center;
            gap: 10px;

            border-bottom: 1px solid rgba(255,255,255,.05);
        }

        .chat-person-data {
            flex: 1;
        }

        .chat-person-data strong {
            display: block;
            font-size: 9px;
        }

        .chat-person-data span {
            display: block;
            margin-top: 2px;
            color: #526057;
            font-size: 6px;
        }

        .main-ai-badge {
            height: 29px;
            padding: 0 9px;

            display: inline-flex;
            align-items: center;
            gap: 6px;

            border-radius: 8px;

            background: rgba(92,255,157,.075);
            color: var(--green);

            font-size: 6px;
            font-weight: 800;
        }

        .main-ai-badge i {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 11px rgba(92,255,157,.5);
        }

        .main-messages {
            flex: 1;
            padding: 27px 21px;

            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
        }

        .main-message {
            max-width: 73%;
            padding: 10px 11px;
            border-radius: 10px;

            font-size: 7.5px;
            line-height: 1.55;
        }

        .main-message.customer {
            align-self: flex-start;
            color: #aab5ad;
            background: rgba(255,255,255,.05);
            border-top-left-radius: 3px;
        }

        .main-message.ai {
            align-self: flex-end;
            color: #c9ffe0;
            background: rgba(92,255,157,.09);
            border: 1px solid rgba(92,255,157,.045);
            border-top-right-radius: 3px;
        }

        .main-message time {
            display: block;
            margin-top: 4px;
            color: #465249;
            font-size: 5px;
            text-align: right;
        }

        .main-compose {
            height: 63px;
            padding: 10px 13px;

            display: flex;
            gap: 7px;

            border-top: 1px solid rgba(255,255,255,.05);
        }

        .main-input {
            flex: 1;
            height: 39px;
            padding: 0 12px;

            display: flex;
            align-items: center;

            border-radius: 10px;
            background: rgba(255,255,255,.035);

            color: #46534a;
            font-size: 6px;
        }

        .main-send {
            width: 39px;
            height: 39px;

            display: grid;
            place-items: center;

            border-radius: 10px;
            background: var(--green);
            color: #05150a;
        }

        .main-send svg {
            width: 13px;
        }

        .customer-panel {
            border-left: 1px solid rgba(255,255,255,.055);
        }

        .customer-body {
            padding: 15px;
        }

        .customer-card {
            padding: 14px;
            border-radius: 13px;
            border: 1px solid rgba(255,255,255,.05);
            background: rgba(255,255,255,.02);
        }

        .customer-name {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .customer-name strong {
            display: block;
            font-size: 8px;
        }

        .customer-name span {
            display: block;
            margin-top: 3px;
            color: #526057;
            font-size: 5px;
        }

        .customer-field {
            margin-top: 12px;
            padding-top: 11px;
            border-top: 1px solid rgba(255,255,255,.045);
        }

        .customer-field label {
            color: #465249;
            font-size: 5px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .customer-field strong {
            display: block;
            margin-top: 5px;
            color: #9ea9a1;
            font-size: 7px;
        }

        .customer-tags {
            display: flex;
            gap: 5px;
            margin-top: 6px;
        }

        .customer-tag {
            padding: 4px 6px;
            border-radius: 5px;
            font-size: 5px;
            font-weight: 800;
        }

        .customer-tag.hot {
            color: #ffd278;
            background: rgba(255,200,80,.08);
        }

        .customer-tag.new {
            color: #8dffb9;
            background: rgba(92,255,157,.08);
        }

        .human-control {
            margin-top: 12px;
            padding: 13px;

            border-radius: 12px;
            border: 1px solid rgba(92,255,157,.07);

            background:
                linear-gradient(
                    145deg,
                    rgba(92,255,157,.055),
                    rgba(92,255,157,.012)
                );
        }

        .human-control strong {
            font-size: 7px;
        }

        .human-control p {
            margin-top: 5px;
            color: #526057;
            font-size: 5px;
            line-height: 1.5;
        }

        .human-button {
            height: 30px;
            margin-top: 9px;

            display: grid;
            place-items: center;

            border-radius: 8px;

            background: var(--green);
            color: #051309;

            font-size: 6px;
            font-weight: 800;
        }

        /* ============================================================
           BENTO
        ============================================================ */

        .bento-section {
            padding: 130px 0;
        }

        .bento-header {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 70px;
            margin-bottom: 55px;
        }

        .bento-header .section-copy {
            max-width: 430px;
            margin-top: 0;
        }

        .bento {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            grid-template-rows: 390px 350px;
            gap: 16px;
        }

        .bento-card {
            position: relative;
            overflow: hidden;
            padding: 31px;

            border-radius: 28px;
            border: 1px solid var(--line);

            background:
                linear-gradient(145deg, #0d140f, #080d09);

            box-shadow:
                inset 0 0 0 1px rgba(255,255,255,.01);
        }

        .bento-card.featured {
            background:
                radial-gradient(circle at 90% 10%, rgba(92,255,157,.12), transparent 30%),
                linear-gradient(145deg, #0d1710, #08100a);
            border-color: rgba(92,255,157,.10);
        }

        .bento-mini {
            color: var(--green);
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.3px;
        }

        .bento-card h3 {
            max-width: 510px;
            margin-top: 12px;

            font-family: 'Manrope', sans-serif;
            font-size: 28px;
            line-height: 1.06;
            letter-spacing: -1.5px;
        }

        .bento-card p {
            max-width: 470px;
            margin-top: 11px;

            color: #748078;
            font-size: 13px;
            line-height: 1.65;
        }

        .flow {
            position: absolute;
            left: 31px;
            right: 31px;
            bottom: 27px;

            display: grid;
            grid-template-columns: 1fr 25px 1fr 25px 1fr;
            gap: 7px;
            align-items: center;
        }

        .flow-box {
            min-height: 84px;
            padding: 12px;

            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.055);

            background: rgba(255,255,255,.025);
        }

        .flow-box strong {
            display: block;
            font-size: 9px;
        }

        .flow-box span {
            display: block;
            margin-top: 5px;
            color: #56635a;
            font-size: 6px;
            line-height: 1.5;
        }

        .flow-arrow {
            color: #356948;
        }

        .flow-arrow svg {
            width: 20px;
        }

        .control-demo {
            position: absolute;
            left: 31px;
            right: 31px;
            bottom: 28px;

            display: grid;
            grid-template-columns: 1fr 82px 1fr;
            gap: 10px;
            align-items: center;
        }

        .control-side {
            min-height: 70px;
            padding: 12px;

            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.05);
            background: rgba(255,255,255,.025);
        }

        .control-side span {
            color: #4f5b53;
            font-size: 5px;
        }

        .control-side strong {
            display: block;
            margin-top: 4px;
            font-size: 8px;
        }

        .toggle {
            height: 37px;
            padding: 4px;

            display: flex;
            justify-content: flex-end;
            align-items: center;

            border-radius: 999px;
            background: rgba(92,255,157,.08);
        }

        .toggle-knob {
            width: 29px;
            height: 29px;

            border-radius: 50%;

            background: var(--green);

            box-shadow: 0 0 20px rgba(92,255,157,.22);
        }

        .memory-grid {
            position: absolute;
            left: 31px;
            right: 31px;
            bottom: 28px;

            display: grid;
            grid-template-columns: repeat(2,1fr);
            gap: 8px;
        }

        .memory-box {
            min-height: 58px;
            padding: 11px;

            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.045);

            background: rgba(255,255,255,.022);
        }

        .memory-box label {
            color: #4d5a51;
            font-size: 5px;
        }

        .memory-box strong {
            display: block;
            margin-top: 5px;
            color: #a4b0a7;
            font-size: 8px;
        }

        .timeline {
            position: absolute;
            left: 31px;
            right: 31px;
            bottom: 33px;

            display: grid;
            grid-template-columns: repeat(4,1fr);
            gap: 8px;
        }

        .timeline::before {
            content: "";
            position: absolute;
            left: 10%;
            right: 10%;
            top: 18px;
            height: 1px;
            background: rgba(255,255,255,.06);
        }

        .timeline-step {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .timeline-dot {
            width: 37px;
            height: 37px;
            margin: 0 auto;

            display: grid;
            place-items: center;

            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.06);

            background: #101712;
            color: #536057;

            font-size: 7px;
            font-weight: 800;
        }

        .timeline-step.active .timeline-dot {
            color: #041208;
            background: var(--green);
            border-color: transparent;

            box-shadow: 0 0 25px rgba(92,255,157,.15);
        }

        .timeline-step strong {
            display: block;
            margin-top: 8px;
            font-size: 7px;
        }

        .timeline-step span {
            display: block;
            margin-top: 3px;
            color: #465249;
            font-size: 5px;
        }

        /* ============================================================
           STEPS
        ============================================================ */

        .steps-section {
            padding: 130px 0;
            background: #070b08;
        }

        .steps-intro {
            text-align: center;
        }

        .steps-intro .section-tag {
            justify-content: center;
        }

        .steps-intro .section-title,
        .steps-intro .section-copy {
            margin-left: auto;
            margin-right: auto;
        }

        .steps-grid {
            margin-top: 64px;
            display: grid;
            grid-template-columns: repeat(3,1fr);
            gap: 15px;
        }

        .step {
            position: relative;
            min-height: 420px;
            overflow: hidden;
            padding: 27px;

            border-radius: 27px;
            border: 1px solid var(--line);

            background:
                linear-gradient(160deg, #0d140f, #080d09);
        }

        .step-no {
            width: 43px;
            height: 43px;

            display: grid;
            place-items: center;

            border-radius: 13px;

            color: var(--green);
            background: rgba(92,255,157,.07);
            border: 1px solid rgba(92,255,157,.08);

            font-family: 'Manrope', sans-serif;
            font-size: 10px;
            font-weight: 800;
        }

        .step h3 {
            margin-top: 27px;

            font-family: 'Manrope', sans-serif;
            font-size: 24px;
            line-height: 1.08;
            letter-spacing: -1.2px;
        }

        .step p {
            margin-top: 11px;
            color: #6f7b73;
            font-size: 12px;
            line-height: 1.65;
        }

        .step-visual {
            position: absolute;
            left: 27px;
            right: 27px;
            bottom: 25px;
            height: 142px;
        }

        .qr-area {
            height: 100%;
            padding: 17px;

            display: flex;
            align-items: center;
            gap: 17px;

            border-radius: 17px;
            border: 1px solid rgba(255,255,255,.05);
            background: rgba(255,255,255,.022);
        }

        .qr {
            width: 85px;
            height: 85px;
            flex-shrink: 0;
            border-radius: 12px;

            background:
                linear-gradient(90deg,#d9ffe8 8px,transparent 8px) 0 0/20px 20px,
                linear-gradient(#d9ffe8 8px,transparent 8px) 0 0/20px 20px,
                #0b130e;

            border: 8px solid #e7fff0;
        }

        .qr-text strong {
            font-size: 9px;
        }

        .qr-text span {
            display: block;
            margin-top: 5px;
            color: #536057;
            font-size: 6px;
            line-height: 1.5;
        }

        .knowledge-list {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .knowledge {
            height: 37px;
            padding: 0 10px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.045);

            background: rgba(255,255,255,.022);

            color: #89958d;
            font-size: 7px;
            font-weight: 700;
        }

        .knowledge-check {
            width: 18px;
            height: 18px;

            display: grid;
            place-items: center;

            border-radius: 6px;

            background: rgba(92,255,157,.08);
            color: var(--green);
        }

        .knowledge-check svg {
            width: 9px;
        }

        .ready-box {
            height: 100%;
            padding: 20px;

            display: flex;
            flex-direction: column;
            justify-content: center;

            border-radius: 17px;
            border: 1px solid rgba(92,255,157,.08);

            background:
                radial-gradient(circle at 90% 10%, rgba(92,255,157,.12), transparent 32%),
                #09100b;
        }

        .ready-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ready-orb {
            width: 34px;
            height: 34px;

            display: grid;
            place-items: center;

            border-radius: 50%;
            background: rgba(92,255,157,.075);
        }

        .ready-orb::after {
            content: "";
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: var(--green);

            box-shadow: 0 0 18px rgba(92,255,157,.75);
        }

        .ready-status strong {
            font-size: 9px;
        }

        .ready-box p {
            margin-top: 10px;
            color: #536057;
            font-size: 6px;
        }

        /* ============================================================
           SECTORS
        ============================================================ */

        .sectors-section {
            padding: 130px 0;
        }

        .sector-shell {
            padding: 60px;

            border-radius: 36px;
            border: 1px solid rgba(92,255,157,.07);

            background:
                radial-gradient(circle at 100% 0%, rgba(92,255,157,.09), transparent 29%),
                linear-gradient(150deg, #0a110c, #070b08);
        }

        .sector-top {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 60px;
            align-items: end;
        }

        .sector-copy {
            max-width: 500px;
            justify-self: end;
            color: #78857c;
            font-size: 15px;
            line-height: 1.72;
        }

        .sector-grid {
            margin-top: 50px;
            display: grid;
            grid-template-columns: repeat(4,1fr);
            gap: 9px;
        }

        .sector {
            min-height: 205px;
            padding: 20px;

            border-radius: 19px;
            border: 1px solid rgba(255,255,255,.05);

            background: rgba(255,255,255,.02);

            transition: .25s ease;
        }

        .sector:hover {
            transform: translateY(-5px);
            border-color: rgba(92,255,157,.10);
            background: rgba(92,255,157,.035);
        }

        .sector-icon {
            width: 41px;
            height: 41px;

            display: grid;
            place-items: center;

            border-radius: 12px;

            color: var(--green);
            background: rgba(92,255,157,.07);
        }

        .sector-icon svg {
            width: 18px;
        }

        .sector h3 {
            margin-top: 31px;
            font-family: 'Manrope', sans-serif;
            font-size: 17px;
            letter-spacing: -.6px;
        }

        .sector p {
            margin-top: 8px;

            color: #5e6a62;
            font-size: 10px;
            line-height: 1.65;
        }

        /* ============================================================
           STATS
        ============================================================ */

        .stats {
            padding: 110px 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1.1fr repeat(3,.9fr);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .stat-intro,
        .stat {
            padding: 41px 29px;
            border-right: 1px solid var(--line);
        }

        .stat-intro {
            padding-left: 0;
        }

        .stat:last-child {
            border-right: 0;
        }

        .stat-intro strong {
            max-width: 300px;
            display: block;

            font-family: 'Manrope', sans-serif;
            font-size: 22px;
            line-height: 1.15;
            letter-spacing: -1px;
        }

        .stat-intro span {
            display: block;
            margin-top: 10px;
            color: #626f66;
            font-size: 10px;
        }

        .stat strong {
            display: block;

            font-family: 'Manrope', sans-serif;
            font-size: 44px;
            line-height: 1;
            letter-spacing: -2.2px;
        }

        .stat strong .green {
            color: var(--green);
        }

        .stat span {
            display: block;
            margin-top: 10px;
            color: #657169;
            font-size: 10px;
            line-height: 1.5;
        }

        /* ============================================================
           FAQ
        ============================================================ */

        .faq-section {
            padding: 110px 0 130px;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: .8fr 1.2fr;
            gap: 85px;
        }

        .faq-intro {
            position: sticky;
            top: 130px;
            align-self: start;
        }

        .faq-list {
            border-top: 1px solid var(--line);
        }

        .faq-item {
            border-bottom: 1px solid var(--line);
        }

        .faq-question {
            width: 100%;
            min-height: 82px;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;

            border: 0;
            background: transparent;

            color: white;
            text-align: left;
            cursor: pointer;
        }

        .faq-question strong {
            font-family: 'Manrope', sans-serif;
            font-size: 17px;
            letter-spacing: -.45px;
        }

        .faq-icon {
            position: relative;
            width: 32px;
            height: 32px;
            flex-shrink: 0;

            border-radius: 9px;

            background: rgba(255,255,255,.035);
        }

        .faq-icon::before,
        .faq-icon::after {
            content: "";
            position: absolute;
            width: 11px;
            height: 1.5px;
            left: 10.5px;
            top: 15px;

            border-radius: 999px;
            background: #88958c;

            transition: .25s ease;
        }

        .faq-icon::after {
            transform: rotate(90deg);
        }

        .faq-item.open .faq-icon {
            background: rgba(92,255,157,.07);
        }

        .faq-item.open .faq-icon::before,
        .faq-item.open .faq-icon::after {
            background: var(--green);
        }

        .faq-item.open .faq-icon::after {
            transform: rotate(0);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height .35s ease;
        }

        .faq-answer-inner {
            max-width: 700px;
            padding: 0 45px 26px 0;

            color: #6e7a71;
            font-size: 13px;
            line-height: 1.72;
        }

        /* ============================================================
           FINAL CTA
        ============================================================ */

        .final-section {
            padding: 25px 0 65px;
        }

        .final-card {
            position: relative;
            min-height: 620px;
            padding: 65px 40px;

            display: flex;
            align-items: center;
            justify-content: center;

            overflow: hidden;

            border-radius: 42px;
            border: 1px solid rgba(92,255,157,.09);

            text-align: center;

            background:
                radial-gradient(circle at 50% 110%, rgba(92,255,157,.23), transparent 33%),
                radial-gradient(circle at 0 0, rgba(92,255,157,.06), transparent 25%),
                #070c08;
        }

        .final-card::before,
        .final-card::after {
            content: "";
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 50%;
            border: 1px solid rgba(92,255,157,.07);
        }

        .final-card::before {
            width: 650px;
            height: 650px;
            bottom: -410px;
        }

        .final-card::after {
            width: 870px;
            height: 870px;
            bottom: -560px;
        }

        .final-content {
            position: relative;
            z-index: 2;
        }

        .final-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 23px;

            color: var(--green);

            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .final-kicker::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 17px rgba(92,255,157,.7);
        }

        .final-card h2 {
            max-width: 950px;

            font-family: 'Manrope', sans-serif;
            font-size: clamp(52px, 7.3vw, 96px);
            line-height: .91;
            font-weight: 800;
            letter-spacing: -6px;
        }

        .final-card h2 span {
            color: var(--green);
        }

        .final-card p {
            max-width: 600px;
            margin: 25px auto 0;

            color: #829087;

            font-size: 15px;
            line-height: 1.72;
        }

        .final-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 31px;
        }

        /* ============================================================
           FOOTER
        ============================================================ */

        footer {
            padding-bottom: 35px;
        }

        .footer-top {
            padding: 55px 0 48px;

            display: grid;
            grid-template-columns: 1.35fr .65fr .65fr .65fr;
            gap: 60px;

            border-bottom: 1px solid var(--line);
        }

        .footer-brand p {
            max-width: 340px;
            margin-top: 16px;

            color: #657169;

            font-size: 11px;
            line-height: 1.7;
        }

        .footer-col strong {
            display: block;
            margin-bottom: 15px;

            color: #a6b0a9;

            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1.1px;
        }

        .footer-col a {
            display: block;
            width: fit-content;
            margin-top: 10px;

            color: #59665d;
            font-size: 10px;

            transition: .2s ease;
        }

        .footer-col a:hover {
            color: var(--green);
        }

        .footer-bottom {
            padding-top: 23px;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;

            color: #505d54;
            font-size: 9px;
        }

        .footer-bottom strong {
            color: #7a877e;
        }

        /* ============================================================
           REVEAL
        ============================================================ */

        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition:
                opacity .72s ease,
                transform .72s cubic-bezier(.2,.7,.2,1);
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }


        /* ============================================================
           ASILKANSOFT DIGITAL ECOSYSTEM
        ============================================================ */
        .ecosystem-section{position:relative;padding:135px 0;overflow:hidden;background:radial-gradient(circle at 50% 0%,rgba(92,255,157,.065),transparent 28%),#060a07}
        .ecosystem-head{display:grid;grid-template-columns:1.08fr .92fr;gap:80px;align-items:end}
        .ecosystem-copy{max-width:520px;justify-self:end;color:#7d8a81;font-size:16px;line-height:1.78}
        .ecosystem-grid{margin-top:58px;display:grid;grid-template-columns:repeat(12,1fr);gap:14px}
        .solution-card{--accent:92,255,157;position:relative;min-height:278px;grid-column:span 4;overflow:hidden;padding:27px;border:1px solid rgba(255,255,255,.065);border-radius:25px;background:radial-gradient(circle at 100% 0%,rgba(var(--accent),.12),transparent 36%),linear-gradient(150deg,#0e1510,#080d09);transition:.28s ease}
        .solution-card:hover{transform:translateY(-6px);border-color:rgba(var(--accent),.22);box-shadow:0 28px 65px rgba(0,0,0,.24)}
        .solution-card.wide{grid-column:span 6;min-height:292px}
        .solution-card.ai{--accent:139,92,246}.solution-card.crm{--accent:59,130,246}.solution-card.phone{--accent:249,115,22}.solution-card.web{--accent:16,185,129}.solution-card.hosting{--accent:14,165,233}.solution-card.commerce{--accent:236,72,153}.solution-card.scripts{--accent:245,158,11}.solution-card.ads{--accent:239,68,68}.solution-card.dealer{--accent:168,85,247}.solution-card.integrations{--accent:34,197,94}
        .solution-top{display:flex;align-items:center;justify-content:space-between;gap:15px}
        .solution-icon{width:52px;height:52px;display:grid;place-items:center;border:1px solid rgba(var(--accent),.18);border-radius:16px;color:rgb(var(--accent));background:rgba(var(--accent),.075)}
        .solution-icon svg{width:24px;height:24px}
        .solution-status{min-height:28px;padding:0 10px;display:inline-flex;align-items:center;border:1px solid rgba(var(--accent),.14);border-radius:999px;color:rgb(var(--accent));background:rgba(var(--accent),.055);font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.8px}
        .solution-card h3{margin-top:29px;font-family:'Manrope',sans-serif;font-size:23px;line-height:1.08;letter-spacing:-1px}
        .solution-card p{max-width:450px;margin-top:10px;color:#6d7971;font-size:12px;line-height:1.7}
        .solution-foot{position:absolute;left:27px;right:27px;bottom:23px;display:flex;justify-content:space-between;gap:12px;color:#536058;font-size:9px;font-weight:700}
        .solution-foot strong{color:rgb(var(--accent));font-size:9px}
        .business-flow{margin-top:18px;padding-top:24px;display:grid;grid-template-columns:repeat(7,1fr);gap:8px;border-top:1px solid var(--line)}
        .business-flow-item{min-height:88px;padding:13px;border:1px solid rgba(255,255,255,.05);border-radius:14px;background:rgba(255,255,255,.018)}
        .business-flow-item b{display:block;color:var(--green);font-size:8px;text-transform:uppercase;letter-spacing:.8px}
        .business-flow-item span{display:block;margin-top:7px;color:#77837b;font-size:10px;line-height:1.45}
        @media(max-width:1080px){.ecosystem-head{grid-template-columns:1fr;gap:25px}.ecosystem-copy{justify-self:start}.solution-card,.solution-card.wide{grid-column:span 6}.business-flow{grid-template-columns:repeat(4,1fr)}}
        @media(max-width:720px){.ecosystem-section{padding:85px 0}.ecosystem-grid{margin-top:38px}.solution-card,.solution-card.wide{grid-column:1/-1;min-height:260px;padding:23px}.solution-card h3{font-size:21px}.solution-foot{left:23px;right:23px}.business-flow{grid-template-columns:1fr 1fr}}


        /* ============================================================
           WAI CHANNEL + CRM PRODUCT SHOWCASE
        ============================================================ */

        .product-showcase {
            position: relative;
            padding: 135px 0;
            overflow: hidden;
            background:
                radial-gradient(circle at 10% 0%, rgba(92,255,157,.07), transparent 28%),
                radial-gradient(circle at 90% 15%, rgba(59,130,246,.05), transparent 26%),
                #070b08;
        }

        .product-showcase-header {
            display: grid;
            grid-template-columns: 1.02fr .98fr;
            gap: 70px;
            align-items: end;
        }

        .product-showcase-copy {
            max-width: 560px;
            justify-self: end;
            color: #7c8980;
            font-size: 16px;
            line-height: 1.78;
        }

        .channel-hero {
            position: relative;
            margin-top: 58px;
            min-height: 540px;
            overflow: hidden;
            padding: 54px;
            display: grid;
            grid-template-columns: 1.14fr .86fr;
            gap: 55px;
            align-items: center;
            border: 1px solid rgba(92,255,157,.10);
            border-radius: 34px;
            background:
                radial-gradient(circle at 100% 100%, rgba(92,255,157,.15), transparent 34%),
                radial-gradient(circle at 88% 0%, rgba(92,255,157,.05), transparent 25%),
                linear-gradient(145deg,#0d140f,#080d09);
            box-shadow: 0 55px 110px rgba(0,0,0,.24);
        }

        .channel-hero::after {
            content:"";
            position:absolute;
            width:460px;
            height:460px;
            right:-130px;
            top:-175px;
            border-radius:50%;
            border:1px solid rgba(92,255,157,.08);
            box-shadow:
                0 0 0 28px rgba(92,255,157,.018),
                0 0 0 58px rgba(92,255,157,.012),
                0 0 0 88px rgba(92,255,157,.008);
        }

        .channel-kicker {
            display:inline-flex;
            align-items:center;
            gap:9px;
            min-height:36px;
            padding:0 13px;
            border:1px solid rgba(92,255,157,.17);
            border-radius:999px;
            color:#b8ffd2;
            background:rgba(92,255,157,.06);
            font-size:10px;
            font-weight:800;
            letter-spacing:.6px;
        }

        .channel-kicker i {
            width:8px;
            height:8px;
            border-radius:50%;
            background:var(--green);
            box-shadow:0 0 14px rgba(92,255,157,.7);
        }

        .channel-hero h3 {
            max-width:720px;
            margin-top:25px;
            font-family:'Manrope',sans-serif;
            font-size:clamp(48px,5.3vw,76px);
            line-height:.96;
            letter-spacing:-4.2px;
        }

        .channel-hero h3 span {
            color:var(--green);
        }

        .channel-hero-copy p {
            max-width:720px;
            margin-top:22px;
            color:#87948b;
            font-size:16px;
            line-height:1.75;
        }

        .channel-benefits {
            margin-top:31px;
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:11px;
        }

        .channel-benefit {
            min-height:89px;
            padding:14px;
            border:1px solid rgba(255,255,255,.055);
            border-radius:15px;
            background:rgba(255,255,255,.02);
        }

        .channel-benefit b {
            display:block;
            color:#d9e4dc;
            font-size:10px;
        }

        .channel-benefit span {
            display:block;
            margin-top:6px;
            color:#59665d;
            font-size:9px;
            line-height:1.5;
        }

        .channel-network {
            position:relative;
            z-index:2;
            min-height:390px;
            padding:31px;
            border:1px solid rgba(255,255,255,.07);
            border-radius:27px;
            background:
                radial-gradient(circle at 100% 100%, rgba(92,255,157,.08), transparent 42%),
                rgba(11,17,13,.88);
            box-shadow:
                0 30px 70px rgba(0,0,0,.27),
                inset 0 1px 0 rgba(255,255,255,.03);
        }

        .network-top {
            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        .network-top strong {
            color:#c9d5cd;
            font-size:12px;
            letter-spacing:.2px;
        }

        .network-live {
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:7px 10px;
            border-radius:999px;
            color:#8effb8;
            background:rgba(92,255,157,.07);
            font-size:8px;
            font-weight:800;
        }

        .network-live i {
            width:6px;
            height:6px;
            border-radius:50%;
            background:var(--green);
        }

        .network-count {
            margin-top:30px;
            font-family:'Manrope',sans-serif;
            font-size:55px;
            font-weight:800;
            letter-spacing:-3px;
        }

        .network-count span { color:var(--green); }

        .network-sub {
            margin-top:2px;
            color:#6c796f;
            font-size:11px;
        }

        .channel-icons {
            margin-top:30px;
            display:flex;
            gap:11px;
        }

        .channel-circle {
            width:56px;
            height:56px;
            display:grid;
            place-items:center;
            border-radius:50%;
            border:1px solid rgba(255,255,255,.07);
            color:#fff;
            font-size:18px;
            font-weight:800;
            box-shadow:0 9px 25px rgba(0,0,0,.18);
        }

        .channel-circle.wa { background:linear-gradient(145deg,#44d982,#169c56); }
        .channel-circle.ig { background:linear-gradient(145deg,#833ab4,#fd1d1d,#fcb045); }
        .channel-circle.fb { background:linear-gradient(145deg,#60a5fa,#2563eb); }
        .channel-circle.web { color:#79ffae;background:rgba(92,255,157,.055); }

        .channel-circle svg { width:24px;height:24px; }

        .network-meter {
            height:9px;
            margin-top:31px;
            overflow:hidden;
            border-radius:999px;
            background:rgba(255,255,255,.06);
        }

        .network-meter i {
            display:block;
            width:25%;
            height:100%;
            border-radius:inherit;
            background:linear-gradient(90deg,var(--green),#22c55e);
            box-shadow:0 0 16px rgba(92,255,157,.35);
        }

        .network-foot {
            margin-top:13px;
            color:#637067;
            font-size:9px;
        }

        .crm-showcase {
            margin-top:85px;
        }

        .crm-showcase-head {
            max-width:850px;
            margin-bottom:44px;
        }

        .crm-showcase-head h3 {
            margin-top:15px;
            font-family:'Manrope',sans-serif;
            font-size:clamp(38px,4.6vw,62px);
            line-height:1;
            letter-spacing:-3px;
        }

        .crm-showcase-head p {
            max-width:650px;
            margin-top:18px;
            color:#77847b;
            font-size:15px;
            line-height:1.72;
        }

        .crm-products {
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:17px;
        }

        .crm-product {
            position:relative;
            overflow:hidden;
            min-height:570px;
            padding:1px;
            border-radius:28px;
            background:linear-gradient(140deg,rgba(92,255,157,.15),rgba(255,255,255,.045),rgba(59,130,246,.08));
            box-shadow:0 35px 80px rgba(0,0,0,.20);
        }

        .crm-product.full {
            grid-column:1/-1;
            min-height:610px;
        }

        .crm-product-inner {
            height:100%;
            min-height:inherit;
            overflow:hidden;
            border-radius:27px;
            background:linear-gradient(155deg,#0e1510,#080d09);
        }

        .mock-browser {
            height:53px;
            padding:0 16px;
            display:flex;
            align-items:center;
            gap:7px;
            border-bottom:1px solid rgba(255,255,255,.055);
        }

        .mock-browser i {
            width:8px;height:8px;border-radius:50%;background:#2d3930;
        }

        .mock-url {
            width:220px;
            height:27px;
            margin-left:7px;
            padding:0 10px;
            display:flex;
            align-items:center;
            border-radius:8px;
            color:#48564c;
            background:rgba(255,255,255,.03);
            font-size:6px;
        }

        .mock-page {
            padding:22px;
        }

        .mock-heading {
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:20px;
        }

        .mock-heading small {
            color:var(--green);
            font-size:6px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:1px;
        }

        .mock-heading h4 {
            margin-top:6px;
            font-family:'Manrope',sans-serif;
            font-size:20px;
            line-height:1.05;
            letter-spacing:-1px;
        }

        .mock-button {
            min-height:29px;
            padding:0 10px;
            display:inline-flex;
            align-items:center;
            border:1px solid rgba(92,255,157,.10);
            border-radius:8px;
            color:#9bffbf;
            background:rgba(92,255,157,.05);
            font-size:6px;
            font-weight:800;
        }

        .mock-kpis {
            margin-top:18px;
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:8px;
        }

        .mock-kpi {
            min-height:89px;
            padding:12px;
            overflow:hidden;
            border-radius:13px;
            border:1px solid rgba(255,255,255,.05);
            background:rgba(255,255,255,.02);
        }

        .mock-kpi.blue { background:linear-gradient(145deg,#3b82f6,#1d4ed8); }
        .mock-kpi.orange { background:linear-gradient(145deg,#fb923c,#ea580c); }
        .mock-kpi.green { background:linear-gradient(145deg,#34d399,#059669); }
        .mock-kpi.red { background:linear-gradient(145deg,#f87171,#dc2626); }
        .mock-kpi.purple { background:linear-gradient(145deg,#8b5cf6,#6d28d9); }

        .mock-kpi strong {
            display:block;
            font-family:'Manrope',sans-serif;
            font-size:21px;
            color:#fff;
        }

        .mock-kpi span {
            display:block;
            margin-top:7px;
            color:rgba(255,255,255,.78);
            font-size:6px;
            font-weight:700;
        }

        .mock-pipeline {
            margin-top:14px;
            display:grid;
            grid-template-columns:repeat(5,1fr);
            gap:7px;
        }

        .mock-column {
            min-height:320px;
            border:1px solid rgba(255,255,255,.05);
            border-radius:12px;
            background:rgba(255,255,255,.015);
        }

        .mock-column-head {
            height:39px;
            padding:0 10px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            border-radius:11px 11px 0 0;
            color:#fff;
            font-size:6px;
            font-weight:800;
        }

        .mock-column:nth-child(1) .mock-column-head { background:#475569; }
        .mock-column:nth-child(2) .mock-column-head { background:#f97316; }
        .mock-column:nth-child(3) .mock-column-head { background:#4f46e5; }
        .mock-column:nth-child(4) .mock-column-head { background:#7c3aed; }
        .mock-column:nth-child(5) .mock-column-head { background:#059669; }

        .mock-lead {
            margin:8px;
            padding:10px;
            border:1px solid rgba(255,255,255,.045);
            border-radius:10px;
            background:#101712;
        }

        .mock-lead b {
            display:block;
            color:#c7d2cb;
            font-size:6.5px;
        }

        .mock-lead span {
            display:block;
            margin-top:4px;
            color:#526057;
            font-size:5px;
        }

        .mock-score {
            height:3px;
            margin-top:8px;
            overflow:hidden;
            border-radius:999px;
            background:rgba(255,255,255,.05);
        }

        .mock-score i {
            display:block;
            height:100%;
            width:72%;
            background:var(--green);
        }

        .alarm-grid {
            margin-top:16px;
            display:grid;
            grid-template-columns:1fr 1.2fr;
            gap:10px;
        }

        .alarm-panel,
        .report-panel {
            min-height:325px;
            padding:14px;
            border:1px solid rgba(255,255,255,.05);
            border-radius:14px;
            background:rgba(255,255,255,.018);
        }

        .mini-table {
            width:100%;
            margin-top:12px;
            border-collapse:collapse;
        }

        .mini-table th,
        .mini-table td {
            padding:8px 4px;
            border-bottom:1px solid rgba(255,255,255,.04);
            text-align:left;
            color:#68756c;
            font-size:5.5px;
        }

        .mini-table th {
            color:#97a39a;
            font-weight:800;
        }

        .risk-row {
            margin-top:8px;
            padding:10px;
            display:flex;
            gap:10px;
            align-items:center;
            border:1px solid rgba(239,68,68,.08);
            border-radius:10px;
            background:rgba(239,68,68,.025);
        }

        .risk-score {
            width:38px;height:38px;display:grid;place-items:center;flex-shrink:0;border-radius:10px;color:#ff9696;background:rgba(239,68,68,.08);font-size:7px;font-weight:900;
        }

        .risk-row strong { display:block;font-size:6.5px;color:#c8d2cb; }
        .risk-row span { display:block;margin-top:3px;font-size:5px;color:#59665d; }

        .chart-bars {
            height:160px;
            margin-top:22px;
            display:flex;
            align-items:flex-end;
            gap:7px;
        }

        .chart-bars i {
            flex:1;
            border-radius:5px 5px 2px 2px;
            background:linear-gradient(180deg,#5cff9d,#0d8f4c);
            opacity:.85;
        }

        .product-caption {
            padding:22px 25px 25px;
            border-top:1px solid rgba(255,255,255,.045);
        }

        .product-caption span {
            color:var(--green);
            font-size:8px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:1px;
        }

        .product-caption h4 {
            margin-top:9px;
            font-family:'Manrope',sans-serif;
            font-size:22px;
            letter-spacing:-1px;
        }

        .product-caption p {
            margin-top:8px;
            color:#68756d;
            font-size:11px;
            line-height:1.62;
        }

        @media(max-width:1080px){
            .product-showcase-header,.channel-hero{grid-template-columns:1fr}
            .product-showcase-copy{justify-self:start}
            .crm-products{grid-template-columns:1fr}
            .crm-product.full{grid-column:auto}
            .mock-pipeline{grid-template-columns:repeat(3,1fr)}
            .channel-benefits{grid-template-columns:1fr 1fr 1fr}
        }

        @media(max-width:720px){
            .product-showcase{padding:85px 0}
            .channel-hero{padding:25px;min-height:auto;border-radius:25px}
            .channel-hero h3{font-size:48px;letter-spacing:-3px}
            .channel-benefits{grid-template-columns:1fr}
            .channel-network{min-height:350px;padding:23px}
            .crm-showcase{margin-top:65px}
            .crm-product,.crm-product.full{min-height:520px}
            .mock-kpis{grid-template-columns:repeat(2,1fr)}
            .mock-pipeline{grid-template-columns:1fr 1fr;overflow:hidden}
            .mock-column:nth-child(n+3){display:none}
            .alarm-grid{grid-template-columns:1fr}
            .report-panel{display:none}
        }

        /* ============================================================
           TABLET
        ============================================================ */

        @media (max-width: 1080px) {

            .nav-links {
                display: none;
            }

            .menu-button {
                display: block;
            }

            .hero-grid {
                grid-template-columns: 1fr;
            }

            .hero-copy {
                max-width: 850px;
                margin: 0 auto;
                text-align: center;
            }

            .hero-description {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-actions,
            .hero-trust {
                justify-content: center;
            }

            .hero-visual {
                margin-top: 20px;
            }

            .manifesto-grid,
            .faq-grid {
                grid-template-columns: 1fr;
                gap: 45px;
            }

            .manifesto-copy,
            .faq-intro {
                position: static;
            }

            .command-header,
            .sector-top {
                grid-template-columns: 1fr;
            }

            .command-description,
            .sector-copy {
                justify-self: start;
            }

            .command-grid {
                grid-template-columns: 200px 1fr;
            }

            .customer-panel {
                display: none;
            }

            .bento {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(4, 365px);
            }

            .steps-grid {
                grid-template-columns: 1fr;
            }

            .step {
                min-height: 365px;
            }

            .sector-grid {
                grid-template-columns: repeat(2,1fr);
            }

            .stats-grid {
                grid-template-columns: repeat(2,1fr);
            }

            .stat-intro,
            .stat {
                border-bottom: 1px solid var(--line);
            }

            .stat:nth-child(2) {
                border-right: 0;
            }

            .footer-top {
                grid-template-columns: 1fr 1fr;
            }

            .mobile-menu {
                position: fixed;
                z-index: 999;
                top: 87px;
                left: 20px;
                right: 20px;

                display: block;
                padding: 12px;

                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px);

                border: 1px solid rgba(255,255,255,.08);
                border-radius: 19px;

                background: rgba(9,14,10,.96);

                backdrop-filter: blur(22px);

                box-shadow: 0 30px 80px rgba(0,0,0,.45);

                transition: .23s ease;
            }

            body.menu-open .mobile-menu {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            .mobile-menu a {
                min-height: 48px;
                padding: 0 12px;

                display: flex;
                align-items: center;

                border-radius: 11px;

                color: #a6b2a9;

                font-size: 13px;
                font-weight: 700;
            }

            .mobile-menu a:hover {
                color: white;
                background: rgba(255,255,255,.04);
            }

            .mobile-menu-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 7px;

                margin-top: 8px;
                padding-top: 11px;

                border-top: 1px solid var(--line);
            }

            .mobile-menu-actions a {
                justify-content: center;
            }

            .mobile-menu-actions .primary {
                color: #06140b;
                background: var(--green);
            }
        }

        /* ============================================================
           MOBILE
        ============================================================ */

        @media (max-width: 720px) {

            .container {
                width: min(100% - 24px, var(--container));
            }

            .nav-spacer {
                height: 76px;
            }

            .nav-wrap {
                padding-top: 8px;
            }

            .navbar {
                min-height: 58px;
                padding: 7px 7px 7px 11px;
                border-radius: 17px;
            }

            .brand-logo {
                width: 36px;
                height: 36px;
                border-radius: 11px;
            }

            .brand-name {
                font-size: 18px;
            }

            .brand-name small {
                display: none;
            }

            .nav-actions {
                display: none;
            }

            .menu-button {
                width: 42px;
                height: 42px;
            }

            .mobile-menu {
                left: 12px;
                right: 12px;
                top: 75px;
            }

            .hero {
                min-height: auto;
                padding: 46px 0 40px;
            }

            .hero-light {
                width: 500px;
                height: 500px;
                top: -200px;
            }

            .hero-ray {
                width: 600px;
                height: 600px;
            }

            .hero-kicker {
                min-height: 35px;
                padding: 0 12px;
                font-size: 9px;
            }

            .hero h1 {
                margin-top: 21px;

                font-size: clamp(49px, 15.5vw, 67px);
                line-height: .93;
                letter-spacing: -4px;
            }

            .hero-description {
                margin-top: 21px;
                font-size: 14px;
                line-height: 1.68;
            }

            .hero-actions {
                width: 100%;
                flex-direction: column;
                margin-top: 26px;
            }

            .button-main,
            .button-ghost {
                width: 100%;
                min-height: 57px;
            }

            .hero-trust {
                gap: 8px 12px;
                font-size: 8px;
            }

            .hero-visual {
                min-height: 520px;
                margin-top: 43px;
            }

            .halo {
                width: 380px;
                height: 380px;
            }

            .dashboard-card {
                width: 385px;
                height: 425px;

                transform:
                    translate(-50%, -50%)
                    rotateY(-3deg)
                    rotateX(2deg)
                    rotateZ(.5deg);
            }

            .hero-visual:hover .dashboard-card {
                transform:
                    translate(-50%, -51%)
                    rotateY(-1deg);
            }

            .dash-grid {
                grid-template-columns: 100px 1fr;
            }

            .dash-crm {
                display: none;
            }

            .dash-sidebar {
                padding-left: 6px;
                padding-right: 6px;
            }

            .dash-logo {
                padding-left: 5px;
                padding-right: 5px;
            }

            .dash-nav-label {
                padding-left: 5px;
            }

            .dash-nav-item {
                padding: 0 5px;
                font-size: 5px;
            }

            .dash-message {
                font-size: 5.5px;
            }

            .float-panel.lead {
                width: 145px;
                top: 46px;
                right: -2px;
                padding: 10px;
            }

            .float-panel.ai {
                width: 140px;
                left: -2px;
                bottom: 60px;
                padding: 10px;
            }

            .float-panel.follow {
                display: none;
            }

            .float-icon {
                width: 24px;
                height: 24px;
            }

            .float-head strong {
                font-size: 6px;
            }

            .float-head span {
                font-size: 4px;
            }

            .industry-strip {
                padding-bottom: 70px;
            }

            .industry-pill {
                min-width: 145px;
                height: 50px;
                font-size: 9px;
            }

            .manifesto,
            .bento-section,
            .steps-section,
            .sectors-section {
                padding: 85px 0;
            }

            .section-title {
                font-size: 42px;
                letter-spacing: -2.6px;
            }

            .section-copy {
                font-size: 14px;
                line-height: 1.67;
            }

            .manifesto-item {
                min-height: 118px;
                padding: 18px 2px;
                grid-template-columns: 43px 1fr;
            }

            .manifesto-item h3 {
                font-size: 29px;
                letter-spacing: -1.4px;
            }

            .command-section {
                padding: 85px 0;
            }

            .command-board {
                margin-top: 38px;
                border-radius: 22px;
            }

            .command-inner {
                min-height: 455px;
                border-radius: 21px;
            }

            .command-top {
                height: 46px;
            }

            .command-url {
                width: 170px;
            }

            .command-grid {
                grid-template-columns: 105px 1fr;
                min-height: 409px;
            }

            .panel-title,
            .main-chat-head {
                height: 48px;
                padding: 0 8px;
            }

            .conversation-filters {
                padding: 7px 5px;
            }

            .conv-filter {
                height: 22px;
                padding: 0 5px;
                font-size: 4px;
            }

            .conversation {
                padding: 8px 6px;
                gap: 5px;
            }

            .conversation-avatar {
                display: none;
            }

            .conversation-line strong {
                font-size: 6px;
            }

            .conversation-content p {
                font-size: 4.5px;
            }

            .main-chat-panel .conversation-avatar {
                display: grid;
                width: 27px;
                height: 27px;
            }

            .main-ai-badge {
                height: 24px;
                font-size: 4px;
            }

            .main-messages {
                padding: 16px 8px;
            }

            .main-message {
                max-width: 90%;
                font-size: 5.4px;
            }

            .main-compose {
                height: 49px;
                padding: 7px;
            }

            .main-input,
            .main-send {
                height: 33px;
            }

            .main-send {
                width: 33px;
            }

            .bento-header {
                display: block;
                margin-bottom: 37px;
            }

            .bento-header .section-copy {
                margin-top: 18px;
            }

            .bento {
                grid-template-rows: 405px 375px 380px 395px;
            }

            .bento-card {
                padding: 23px;
                border-radius: 22px;
            }

            .bento-card h3 {
                font-size: 23px;
            }

            .flow {
                left: 23px;
                right: 23px;
                bottom: 22px;
                grid-template-columns: 1fr;
            }

            .flow-arrow {
                display: none;
            }

            .flow-box {
                min-height: 56px;
            }

            .control-demo {
                left: 23px;
                right: 23px;
                grid-template-columns: 1fr;
            }

            .toggle {
                width: 72px;
                justify-self: center;
            }

            .memory-grid {
                left: 23px;
                right: 23px;
            }

            .timeline {
                left: 23px;
                right: 23px;
                grid-template-columns: repeat(2,1fr);
                gap: 15px;
            }

            .timeline::before {
                display: none;
            }

            .steps-grid {
                margin-top: 42px;
            }

            .step {
                min-height: 380px;
                padding: 23px;
                border-radius: 22px;
            }

            .step-visual {
                left: 23px;
                right: 23px;
            }

            .sector-shell {
                padding: 37px 21px;
                border-radius: 27px;
            }

            .sector-grid {
                grid-template-columns: 1fr;
                margin-top: 37px;
            }

            .sector {
                min-height: 175px;
            }

            .sector h3 {
                margin-top: 25px;
            }

            .stats {
                padding: 75px 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-intro,
            .stat {
                padding: 27px 3px;
                border-right: 0;
            }

            .stat {
                display: grid;
                grid-template-columns: 125px 1fr;
                align-items: center;
                gap: 15px;
            }

            .stat strong {
                font-size: 37px;
            }

            .stat span {
                margin-top: 0;
            }

            .faq-section {
                padding: 80px 0 100px;
            }

            .faq-question {
                min-height: 73px;
            }

            .faq-question strong {
                font-size: 14px;
            }

            .faq-answer-inner {
                padding-right: 10px;
                font-size: 12px;
            }

            .final-section {
                padding-bottom: 40px;
            }

            .final-card {
                min-height: 540px;
                padding: 52px 18px;
                border-radius: 28px;
            }

            .final-card h2 {
                font-size: 51px;
                line-height: .93;
                letter-spacing: -3.5px;
            }

            .final-card p {
                font-size: 13px;
            }

            .final-actions {
                width: 100%;
                flex-direction: column;
            }

            .footer-top {
                grid-template-columns: 1fr;
                gap: 34px;
            }

            .footer-bottom {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 410px) {

            .hero h1 {
                font-size: 47px;
            }

            .dashboard-card {
                width: 350px;
            }

            .float-panel.lead {
                right: -4px;
            }

            .float-panel.ai {
                left: -4px;
            }

            .final-card h2 {
                font-size: 46px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .001ms !important;
            }
        }
    
        /* ============================================================
           WAI LIGHT PREMIUM THEME — FINAL
           Panel arayüzüyle aynı açık, ferah ve premium görünüm.
        ============================================================ */

        :root {
            --bg: #f4f7f5;
            --bg-soft: #eef4f0;
            --bg-card: #ffffff;
            --bg-card-2: #f8fbf9;

            --white: #09120c;
            --text: #101813;
            --muted: #68756d;
            --muted-2: #8b9790;

            --green: #17c86f;
            --green-strong: #0fb966;
            --green-dark: #087a42;
            --green-deep: #eafaf1;

            --line: rgba(15,23,42,.085);
            --line-green: rgba(16,185,129,.18);

            --shadow:
                0 24px 65px rgba(15,23,42,.08),
                0 8px 24px rgba(15,23,42,.05);
        }

        body {
            color: #101813 !important;
            background:
                radial-gradient(circle at 8% -10%, rgba(16,185,129,.10), transparent 26%),
                radial-gradient(circle at 90% 5%, rgba(59,130,246,.055), transparent 24%),
                #f4f7f5 !important;
        }

        .grid-overlay {
            opacity: .42 !important;
            background-image:
                linear-gradient(rgba(15,23,42,.027) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15,23,42,.027) 1px, transparent 1px) !important;
        }

        .noise {
            opacity: .012 !important;
        }

        .navbar {
            border-color: rgba(15,23,42,.08) !important;
            background: rgba(255,255,255,.88) !important;
            box-shadow:
                0 16px 45px rgba(15,23,42,.08),
                inset 0 1px 0 rgba(255,255,255,.88) !important;
        }

        .brand-name,
        .nav-links a:hover,
        .mobile-menu a:hover,
        .faq-question,
        .section-title,
        .hero h1,
        .manifesto-item h3,
        .command-section .section-title,
        .bento-card h3,
        .step h3,
        .sector h3,
        .final-card h2,
        .crm-showcase-head h3,
        .channel-hero h3,
        .solution-card h3 {
            color: #0c140f !important;
        }

        .nav-links a,
        .nav-login {
            color: #657169 !important;
        }

        .nav-login:hover {
            color: #0c140f !important;
            background: #f2f6f3 !important;
        }

        .menu-button {
            color: #17211b !important;
            border-color: rgba(15,23,42,.09) !important;
            background: #fff !important;
        }

        .mobile-menu {
            background: rgba(255,255,255,.97) !important;
            border-color: rgba(15,23,42,.08) !important;
            box-shadow: 0 28px 70px rgba(15,23,42,.13) !important;
        }

        .mobile-menu a {
            color: #5f6b63 !important;
        }

        .hero,
        .manifesto,
        .bento-section,
        .sectors-section,
        .stats,
        .faq-section {
            background: transparent !important;
        }

        .command-section,
        .steps-section,
        .ecosystem-section,
        .product-showcase {
            background:
                radial-gradient(circle at 90% 0%, rgba(16,185,129,.055), transparent 28%),
                linear-gradient(180deg,#f8fbf9 0%,#f1f5f2 100%) !important;
        }

        .hero h1 .muted,
        .manifesto-item h3 span {
            color: #77837b !important;
        }

        .hero-description,
        .section-copy,
        .command-description,
        .sector-copy,
        .ecosystem-copy,
        .product-showcase-copy,
        .channel-hero-copy p,
        .crm-showcase-head p,
        .final-card p {
            color: #66736b !important;
        }

        .hero-kicker,
        .channel-kicker {
            color: #087a42 !important;
            background: #edfbf3 !important;
            border-color: #ccefdc !important;
        }

        .hero-trust {
            color: #748078 !important;
        }

        /* Large WAI dashboard visuals: light panel look */
        .dashboard-card,
        .command-inner,
        .channel-network,
        .crm-product-inner {
            border-color: rgba(15,23,42,.085) !important;
            background:
                radial-gradient(circle at 100% 0%, rgba(16,185,129,.055), transparent 28%),
                #ffffff !important;
            box-shadow:
                0 32px 80px rgba(15,23,42,.10),
                0 10px 28px rgba(15,23,42,.05) !important;
        }

        .command-board,
        .crm-product {
            background:
                linear-gradient(
                    135deg,
                    rgba(16,185,129,.28),
                    rgba(59,130,246,.10),
                    rgba(15,23,42,.055)
                ) !important;
            box-shadow: 0 35px 85px rgba(15,23,42,.09) !important;
        }

        .dash-top,
        .command-top,
        .mock-browser,
        .dash-chat-header,
        .main-chat-head,
        .main-compose,
        .dash-compose,
        .panel-title {
            border-color: rgba(15,23,42,.07) !important;
        }

        .dash-address,
        .command-url,
        .mock-url,
        .dash-input,
        .main-input {
            color: #8a958e !important;
            background: #f2f6f3 !important;
        }

        .dash-sidebar,
        .conversation-list,
        .dash-crm,
        .customer-panel {
            border-color: rgba(15,23,42,.07) !important;
        }

        .dash-logo,
        .crm-title,
        .client-name strong,
        .panel-title strong,
        .conversation-line strong,
        .chat-person-data strong,
        .customer-name strong,
        .crm-profile strong,
        .customer-field strong,
        .human-control strong {
            color: #263129 !important;
        }

        .dash-nav-label,
        .dash-nav-item,
        .client-name span,
        .crm-profile span,
        .crm-field label,
        .crm-field strong,
        .conversation-content p,
        .conversation-line time,
        .chat-person-data span,
        .customer-name span,
        .customer-field label,
        .human-control p,
        .main-input,
        .dash-input {
            color: #7a867e !important;
        }

        .dash-nav-item.active,
        .conversation.active {
            color: #087a42 !important;
            background: #edf9f2 !important;
        }

        .nav-square,
        .conversation-avatar,
        .client-avatar {
            background: #eef4f0 !important;
            color: #43805e !important;
        }

        .dash-message.in,
        .main-message.customer {
            color: #47534b !important;
            background: #f1f5f2 !important;
        }

        .dash-message.out,
        .main-message.ai {
            color: #0b7040 !important;
            background: #eaf9f1 !important;
            border-color: #d7f1e2 !important;
        }

        .dash-message time,
        .main-message time {
            color: #89948d !important;
        }

        .dash-messages,
        .main-messages {
            background:
                radial-gradient(circle at 50% 100%, rgba(16,185,129,.045), transparent 35%) !important;
        }

        .crm-profile,
        .customer-card,
        .human-control,
        .alarm-panel,
        .report-panel,
        .mock-column,
        .mock-lead {
            border-color: rgba(15,23,42,.075) !important;
            background: #f8faf9 !important;
        }

        .mock-heading h4,
        .mock-lead b,
        .risk-row strong,
        .network-top strong {
            color: #152019 !important;
        }

        .mock-lead span,
        .risk-row span,
        .network-sub,
        .network-foot {
            color: #748078 !important;
        }

        .risk-row {
            border-color: #f5d2d2 !important;
            background: #fff7f7 !important;
        }

        .risk-score {
            color: #b91c1c !important;
            background: #feecec !important;
        }

        .mini-table th,
        .mini-table td {
            border-color: rgba(15,23,42,.07) !important;
        }

        .mini-table th {
            color: #47544c !important;
        }

        .mini-table td {
            color: #748078 !important;
        }

        /* Feature/service cards become premium white cards */
        .bento-card,
        .step,
        .sector,
        .solution-card,
        .channel-hero,
        .sector-shell {
            border-color: rgba(15,23,42,.08) !important;
            background:
                radial-gradient(circle at 100% 0%, rgba(16,185,129,.055), transparent 28%),
                #ffffff !important;
            box-shadow: 0 18px 48px rgba(15,23,42,.055) !important;
        }

        .bento-card.featured,
        .ready-box {
            background:
                radial-gradient(circle at 92% 8%, rgba(16,185,129,.085), transparent 32%),
                #ffffff !important;
        }

        .bento-card p,
        .step p,
        .sector p,
        .solution-card p,
        .product-caption p {
            color: #6a766e !important;
        }

        .flow-box,
        .control-side,
        .memory-box,
        .knowledge,
        .business-flow-item,
        .channel-benefit {
            border-color: rgba(15,23,42,.075) !important;
            background: #f8faf9 !important;
        }

        .flow-box strong,
        .control-side strong,
        .memory-box strong,
        .knowledge,
        .channel-benefit b {
            color: #2d3931 !important;
        }

        .flow-box span,
        .control-side span,
        .memory-box label,
        .business-flow-item span,
        .channel-benefit span {
            color: #77837b !important;
        }

        .timeline-dot {
            color: #627068 !important;
            border-color: rgba(15,23,42,.08) !important;
            background: #fff !important;
        }

        .stats-grid,
        .faq-list,
        .faq-item,
        .industry-shell {
            border-color: rgba(15,23,42,.08) !important;
        }

        .industry-pill {
            border-color: rgba(15,23,42,.075) !important;
            background: #fff !important;
            color: #657169 !important;
        }

        .faq-icon {
            background: #eef3ef !important;
        }

        .faq-answer-inner {
            color: #68756d !important;
        }

        .final-card {
            border-color: #cdeedb !important;
            background:
                radial-gradient(circle at 50% 110%, rgba(16,185,129,.18), transparent 36%),
                radial-gradient(circle at 0 0, rgba(59,130,246,.045), transparent 25%),
                #ffffff !important;
            box-shadow: 0 28px 80px rgba(15,23,42,.07) !important;
        }

        footer {
            color: #647168 !important;
        }

        .footer-top {
            border-color: rgba(15,23,42,.08) !important;
        }

        .footer-col strong {
            color: #334038 !important;
        }

        .footer-col a,
        .footer-brand p,
        .footer-bottom {
            color: #748078 !important;
        }

        /* ============================================================
           READABILITY LOCK
           Ana sayfada çok küçük kalan yazıları güvenli alt sınıra çek.
        ============================================================ */

        .section-tag,
        .hero-kicker,
        .channel-kicker {
            font-size: 12px !important;
        }

        .nav-links a,
        .nav-login,
        .nav-cta {
            font-size: 14px !important;
        }

        .hero-description,
        .section-copy,
        .command-description,
        .sector-copy,
        .ecosystem-copy,
        .product-showcase-copy,
        .channel-hero-copy p,
        .crm-showcase-head p {
            font-size: 17px !important;
        }

        .bento-card p,
        .step p,
        .sector p,
        .solution-card p,
        .product-caption p,
        .faq-answer-inner {
            font-size: 14px !important;
            line-height: 1.7 !important;
        }

        .industry-pill {
            font-size: 13px !important;
        }

        .solution-status,
        .solution-foot,
        .solution-foot strong,
        .business-flow-item b {
            font-size: 10px !important;
        }

        .business-flow-item span,
        .channel-benefit b,
        .channel-benefit span,
        .network-sub,
        .network-foot {
            font-size: 11px !important;
        }

        .stat-intro span,
        .stat span,
        .footer-brand p,
        .footer-col a,
        .footer-bottom {
            font-size: 12px !important;
        }

        /* Panel mockup yazıları küçük kalmasın */
        .mock-heading small,
        .panel-title strong,
        .conversation-line strong,
        .chat-person-data strong,
        .customer-name strong,
        .crm-title,
        .mock-button,
        .mock-column-head,
        .mock-lead b,
        .risk-row strong,
        .mini-table th,
        .mini-table td {
            font-size: 8px !important;
        }

        .mock-lead span,
        .risk-row span,
        .conversation-content p,
        .conversation-line time,
        .chat-person-data span,
        .customer-name span,
        .customer-field label,
        .customer-field strong,
        .main-ai-badge {
            font-size: 7px !important;
        }

        .mock-kpi span {
            font-size: 8px !important;
        }

        /* ============================================================
           LIVE DEMO — LARGE / PREMIUM
        ============================================================ */

        .wai-demo-stage {
            width: min(1500px, calc(100% - 34px));
            margin: 35px auto 105px;
            position: relative;
            z-index: 4;
        }

        .wai-demo-stage > *,
        .wai-demo-stage section {
            width: 100% !important;
            max-width: none !important;
        }

        .wai-demo-stage .container {
            width: 100% !important;
            max-width: none !important;
        }

        .wai-demo-stage input,
        .wai-demo-stage textarea,
        .wai-demo-stage button {
            font-size: 15px !important;
        }

        .wai-demo-stage p,
        .wai-demo-stage label,
        .wai-demo-stage span {
            font-size: max(12px, 1em);
        }

        .wai-demo-stage h2 {
            font-size: clamp(38px, 4.8vw, 64px) !important;
        }

        .wai-demo-stage h3 {
            font-size: clamp(24px, 2.8vw, 36px) !important;
        }

        @media (max-width: 720px) {
            .wai-demo-stage {
                width: calc(100% - 18px);
                margin-top: 20px;
                margin-bottom: 75px;
            }

            .hero-description,
            .section-copy,
            .command-description,
            .sector-copy,
            .ecosystem-copy,
            .product-showcase-copy,
            .channel-hero-copy p,
            .crm-showcase-head p {
                font-size: 15px !important;
            }

            .bento-card p,
            .step p,
            .sector p,
            .solution-card p,
            .product-caption p,
            .faq-answer-inner {
                font-size: 13px !important;
            }
        }


/* ============================================================
   FINAL HOME COMPACT / SAFE OVERRIDE
   ============================================================ */

.hero {
    min-height: auto !important;
    padding: 135px 0 85px !important;
}

.hero-grid {
    min-height: 630px !important;
    gap: 60px !important;
}

.hero h1 {
    max-width: 760px !important;
    font-size: clamp(62px, 6.4vw, 108px) !important;
    line-height: .91 !important;
    letter-spacing: -5.8px !important;
}

.hero-description {
    max-width: 760px !important;
    margin-top: 27px !important;
}

.hero-visual {
    min-height: 600px !important;
}

.ecosystem-section,
.product-showcase {
    padding-top: 100px !important;
    padding-bottom: 100px !important;
}

.ecosystem-grid,
.channel-hero {
    margin-top: 40px !important;
}

@media (max-width: 720px) {
    .hero {
        padding: 100px 0 62px !important;
    }

    .hero-grid {
        min-height: auto !important;
    }

    .hero h1 {
        font-size: 54px !important;
        letter-spacing: -3.4px !important;
    }
}

</style>
</head>

<body>

<div class="noise"></div>
<div class="grid-overlay"></div>

<div class="nav-spacer"></div>

<header class="nav-wrap" id="navbar">
    <div class="container">

        <nav class="navbar">

            <a href="/" class="brand">

                <span class="brand-logo"></span>

                <span class="brand-name">
                    WAI
                    <small>WhatsApp Intelligence</small>
                </span>

            </a>

            <div class="nav-links">
                <a href="#hizmetler">Çözümler</a>
                <a href="#platform">Platform</a>
                <a href="#urun">WAI</a>
                <a href="#ozellikler">Özellikler</a>
                <a href="#nasil-calisir">Nasıl Çalışır?</a>
                <a href="#sektorler">Sektörler</a>
                <a href="#sss">SSS</a>
            </div>

            <div class="nav-actions">

                <a href="/admin/login" class="nav-login">
                    Giriş Yap
                </a>

                <a href="/admin/register" class="nav-cta">

                    Ücretsiz Başla

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M5 12h14"></path>
                        <path d="m13 6 6 6-6 6"></path>
                    </svg>

                </a>

            </div>

            <button
                class="menu-button"
                id="menuButton"
                aria-label="Menüyü aç"
            >
                <span></span>
            </button>

        </nav>

    </div>
</header>

<div class="mobile-menu" id="mobileMenu">

    <a href="#hizmetler">Çözümler</a>
    <a href="#platform">Platform</a>
    <a href="#urun">WAI</a>
    <a href="#ozellikler">Özellikler</a>
    <a href="#nasil-calisir">Nasıl Çalışır?</a>
    <a href="#sektorler">Sektörler</a>
    <a href="#sss">SSS</a>

    <div class="mobile-menu-actions">
        <a href="/admin/login">Giriş Yap</a>
        <a href="/admin/register" class="primary">Ücretsiz Başla</a>
    </div>

</div>

<main>

    {{-- =========================================================
         HERO
    ========================================================== --}}

    <section class="hero">

        <div class="hero-light"></div>
        <div class="hero-ray"></div>

        <div class="container">

            <div class="hero-grid">

                <div class="hero-copy">

                    <div class="hero-kicker">
                        <span class="live-dot"></span>
                        İşletmeniz için yapay zekâ, CRM ve dijital büyüme ekosistemi
                    </div>

                    <h1>
                        Tek yazılım değil.
                        <span class="muted">Tüm dijital altyapınız.</span>
                        <span class="green">Tek ekosistem.</span>
                    </h1>

                    <p class="hero-description">
                        WAI by AsilkanSoft; yapay zekâ destekli müşteri iletişimini, CRM'i,
                        satış otomasyonunu ve işletmenizin dijital altyapısını tek merkezde
                        birleştirir. WhatsApp'tan web sitesine, e-ticaretten hosting'e kadar
                        büyümek için ihtiyaç duyduğunuz sistemleri tek ekosistemde yönetin.
                    </p>

                    <div class="hero-actions">

                        <a href="/admin/register" class="button-main">

                            Ücretsiz Başlayın

                            <span class="button-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M5 12h14"></path>
                                    <path d="m13 6 6 6-6 6"></path>
                                </svg>

                            </span>

                        </a>

                        <a href="#hizmetler" class="button-ghost">
                            Tüm Çözümleri Keşfet
                        </a>

                    </div>

                    <div class="hero-trust">

                        <span>

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="m5 12 4 4L19 6"></path>
                            </svg>

                            Kredi kartı gerekmez

                        </span>

                        <span>

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="m5 12 4 4L19 6"></path>
                            </svg>

                            Dakikalar içinde kurulum

                        </span>

                        <span>

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="m5 12 4 4L19 6"></path>
                            </svg>

                            AI + insan kontrolü

                        </span>

                    </div>

                </div>

                {{-- HERO PRODUCT --}}

                <div class="hero-visual">

                    <div class="halo"></div>

                    <div class="dashboard-card">

                        <div class="dash-top">

                            <span class="dash-dot"></span>
                            <span class="dash-dot"></span>
                            <span class="dash-dot"></span>

                            <div class="dash-address">
                                wai.asilkansoft.com.tr/admin
                            </div>

                        </div>

                        <div class="dash-grid">

                            <aside class="dash-sidebar">

                                <div class="dash-logo">

                                    <span class="mini-w">W</span>

                                    WAI Panel

                                </div>

                                <div class="dash-nav-label">
                                    Çalışma Alanı
                                </div>

                                <div class="dash-nav-item active">
                                    <span class="nav-square">◉</span>
                                    Gelen Kutusu
                                </div>

                                <div class="dash-nav-item">
                                    <span class="nav-square">◇</span>
                                    AI Botlar
                                </div>

                                <div class="dash-nav-item">
                                    <span class="nav-square">◎</span>
                                    CRM
                                </div>

                                <div class="dash-nav-item">
                                    <span class="nav-square">↗</span>
                                    Takipler
                                </div>

                                <div class="dash-nav-label">
                                    Yönetim
                                </div>

                                <div class="dash-nav-item">
                                    <span class="nav-square">□</span>
                                    Ürünler
                                </div>

                                <div class="dash-nav-item">
                                    <span class="nav-square">▢</span>
                                    Siparişler
                                </div>

                            </aside>

                            <div class="dash-chat">

                                <div class="dash-chat-header">

                                    <div class="client-avatar">
                                        AY
                                    </div>

                                    <div class="client-name">
                                        <strong>Ahmet Yılmaz</strong>
                                        <span>WhatsApp · az önce</span>
                                    </div>

                                    <span class="ai-status">
                                        AI AKTİF
                                    </span>

                                </div>

                                <div class="dash-messages">

                                    <div class="dash-message in">
                                        Merhaba, 5 litrelik zeytinyağı
                                        almak istiyorum. Kargo ücretsiz mi?
                                        <time>14:22</time>
                                    </div>

                                    <div class="dash-message out">
                                        Merhaba 👋 Evet, 5 litre ve üzeri
                                        zeytinyağı siparişlerinde kargo ücretsizdir.
                                        İsterseniz siparişinizi birlikte oluşturabiliriz.
                                        <time>14:22 ✓✓</time>
                                    </div>

                                    <div class="dash-message in">
                                        Olur, sipariş vereyim.
                                        <time>14:23</time>
                                    </div>

                                    <div class="dash-message out">
                                        Harika 🌿 Öncelikle adınızı
                                        ve teslimat ilinizi öğrenebilir miyim?
                                        <time>14:23 ✓✓</time>
                                    </div>

                                </div>

                                <div class="dash-compose">

                                    <div class="dash-input">
                                        Mesaj yazın...
                                    </div>

                                    <div class="dash-send">

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path d="m22 2-7 20-4-9-9-4Z"></path>
                                            <path d="M22 2 11 13"></path>
                                        </svg>

                                    </div>

                                </div>

                            </div>

                            <aside class="dash-crm">

                                <div class="crm-title">
                                    Müşteri Profili
                                </div>

                                <div class="crm-profile">

                                    <strong>Ahmet Yılmaz</strong>
                                    <span>Yeni müşteri</span>

                                    <div class="crm-divider"></div>

                                    <div class="crm-field">
                                        <label>Durum</label>

                                        <span class="hot-tag">
                                            🔥 Sıcak Müşteri
                                        </span>
                                    </div>

                                    <div class="crm-field">
                                        <label>İlgilendiği ürün</label>
                                        <strong>5L Zeytinyağı</strong>
                                    </div>

                                    <div class="crm-field">
                                        <label>Son Görüşme</label>
                                        <strong>Az önce</strong>
                                    </div>

                                    <div class="takeover-btn">
                                        Görüşmeyi Devral
                                    </div>

                                </div>

                            </aside>

                        </div>

                    </div>

                    <div class="float-panel lead">

                        <div class="float-head">

                            <div class="float-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M20 7h-9"></path>
                                    <path d="M14 17H5"></path>
                                    <circle cx="17" cy="17" r="3"></circle>
                                    <circle cx="7" cy="7" r="3"></circle>
                                </svg>

                            </div>

                            <div>
                                <strong>Sıcak müşteri</strong>
                                <span>Satın alma niyeti yüksek</span>
                            </div>

                        </div>

                        <div class="mini-meter">
                            <i></i>
                        </div>

                    </div>

                    <div class="float-panel ai">

                        <div class="float-head">

                            <div class="float-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M12 2v4"></path>
                                    <path d="M12 18v4"></path>
                                    <path d="M4.93 4.93l2.83 2.83"></path>
                                    <path d="M16.24 16.24l2.83 2.83"></path>
                                    <path d="M2 12h4"></path>
                                    <path d="M18 12h4"></path>
                                </svg>

                            </div>

                            <div>
                                <strong>WAI düşünüyor</strong>
                                <span>Doğru cevabı hazırlıyor</span>
                            </div>

                        </div>

                    </div>

                    <div class="float-panel follow">

                        <div class="float-head">

                            <div class="float-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M12 7v5l3 2"></path>
                                </svg>

                            </div>

                            <div>
                                <strong>Takip hazır</strong>
                                <span>Cevap gelmezse hatırlat</span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>

{{-- =========================================================
     LIVE WAI DEMO
========================================================== --}}

{{-- =========================================================
     LIVE WAI DEMO
========================================================== --}}

<div class="wai-demo-stage reveal">
    @include('components.wai-demo')
</div>


{{-- =========================================================
     WAI PRICING
========================================================== --}}

@include('components.wai-pricing')



    {{-- =========================================================
         ASILKANSOFT DIGITAL ECOSYSTEM
    ========================================================== --}}
    <section class="ecosystem-section" id="hizmetler">
        <div class="container">
            <div class="ecosystem-head reveal">
                <div>
                    <div class="section-tag">AsilkanSoft Dijital Ekosistemi</div>
                    <h2 class="section-title">Bir işletmenin ihtiyacı olan her şey.</h2>
                </div>
                <p class="ecosystem-copy">
                    Müşteriyi bulmaktan satışa, web sitesinden e-ticarete,
                    yapay zekâdan CRM'e kadar işletmenizin dijital altyapısını
                    birbirinden kopuk sistemler yerine tek bir ekosistemle büyütün.
                </p>
            </div>
            <div class="ecosystem-grid">
                <article class="solution-card ai wide reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">WAI AI</span></div>
                    <h3>WhatsApp Yapay Zekâ</h3>
                    <p>Müşterilerinize 7/24 cevap verir, işletmenizin kurallarına göre konuşur, satış fırsatlarını takip eder ve gerektiğinde ekibinize devreder.</p>
                    <div class="solution-foot"><span>AI satış · takip · insan devralma</span><strong>CANLI</strong></div>
                </article>

                <article class="solution-card crm wide reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">WAI CRM</span></div>
                    <h3>CRM & Satış Yönetimi</h3>
                    <p>Lead skoru, satış pipeline, görevler, alarm merkezi, yönetici özetleri ve gerçek zamanlı KPI'larla satış ekibinizi yönetin.</p>
                    <div class="solution-foot"><span>Pipeline · görev · alarm · rapor</span><strong>CANLI</strong></div>
                </article>

                <article class="solution-card phone reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">AI VOICE</span></div>
                    <h3>AI Telefon Sekreteri</h3>
                    <p>Aramaları karşılayan, bilgi veren, talep toplayan ve satış ekibinize lead hazırlayan yapay zekâ telefon asistanı.</p>
                    <div class="solution-foot"><span>Sesli AI · lead · randevu</span><strong>YENİ</strong></div>
                </article>

                <article class="solution-card web reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">WEB</span></div>
                    <h3>Web Sitesi Oluşturucu</h3>
                    <p>Sektörünüzü ve tasarımınızı seçin; profesyonel, mobil uyumlu işletme sitenizi kolayca yayına alın.</p>
                    <div class="solution-foot"><span>Şablon · mobil · hızlı yayın</span><strong>PLATFORM</strong></div>
                </article>

                <article class="solution-card hosting reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">CLOUD</span></div>
                    <h3>Domain & Hosting</h3>
                    <p>Alan adı, hosting, SSL ve web altyapınızı tek noktadan yönetin. İşletmeniz büyüdükçe altyapınız da büyüsün.</p>
                    <div class="solution-foot"><span>Domain · SSL · hosting</span><strong>ALTYAPI</strong></div>
                </article>

                <article class="solution-card commerce reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">COMMERCE</span></div>
                    <h3>E-Ticaret Sistemleri</h3>
                    <p>Ürün, sipariş, ödeme, kargo ve müşteri süreçlerini yönetebileceğiniz satış odaklı e-ticaret altyapıları.</p>
                    <div class="solution-foot"><span>Mağaza · ödeme · sipariş</span><strong>SATIŞ</strong></div>
                </article>

                <article class="solution-card scripts reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">SEKTÖREL</span></div>
                    <h3>Hazır Profesyonel Yazılımlar</h3>
                    <p>Emlak, klinik, turizm, otomotiv, hizmet ve farklı sektörlere özel kullanıma hazır profesyonel sistemler.</p>
                    <div class="solution-foot"><span>Sektörel · hazır · özelleştirilebilir</span><strong>SCRIPT</strong></div>
                </article>

                <article class="solution-card ads reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">GROWTH</span></div>
                    <h3>Google & Meta Reklam Yönetimi</h3>
                    <p>Reklam kampanyaları, lead üretimi ve satış odaklı dijital büyüme operasyonlarını profesyonel olarak yönetin.</p>
                    <div class="solution-foot"><span>Google Ads · Meta Ads · lead</span><strong>BÜYÜME</strong></div>
                </article>

                <article class="solution-card dealer reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">PARTNER</span></div>
                    <h3>Bayilik & Ajans Sistemi</h3>
                    <p>AsilkanSoft ürün ve hizmetlerini kendi müşterilerinize sunabileceğiniz ölçeklenebilir bayi ve ajans modeli.</p>
                    <div class="solution-foot"><span>Bayi · ajans · tekrar eden gelir</span><strong>PARTNER</strong></div>
                </article>

                <article class="solution-card integrations reveal">
                    <div class="solution-top"><div class="solution-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8 12h8M12 8v8"/></svg></div><span class="solution-status">CONNECT</span></div>
                    <h3>Otomasyon & Entegrasyonlar</h3>
                    <p>WhatsApp, CRM, web sitesi, telefon ve satış süreçlerini birbirine bağlayan otomasyonlarla manuel işi azaltın.</p>
                    <div class="solution-foot"><span>WhatsApp · CRM · API · otomasyon</span><strong>CONNECT</strong></div>
                </article>

            </div>
            <div class="business-flow reveal">
                <div class="business-flow-item"><b>01 · Bul</b><span>Google & Meta reklamları</span></div>
                <div class="business-flow-item"><b>02 · Karşıla</b><span>WhatsApp AI & Telefon AI</span></div>
                <div class="business-flow-item"><b>03 · Yönet</b><span>WAI CRM & müşteri merkezi</span></div>
                <div class="business-flow-item"><b>04 · Sat</b><span>Pipeline & e-ticaret</span></div>
                <div class="business-flow-item"><b>05 · Yayınla</b><span>Web sitesi & domain</span></div>
                <div class="business-flow-item"><b>06 · Otomatize</b><span>Takip & entegrasyonlar</span></div>
                <div class="business-flow-item"><b>07 · Büyüt</b><span>Raporlama & bayilik</span></div>
            </div>
        </div>
    </section>

    
    {{-- =========================================================
         WAI CHANNEL CENTER + CRM PRODUCT SHOWCASE
    ========================================================== --}}

    <section class="product-showcase" id="platform">
        <div class="container">

            <div class="product-showcase-header reveal">
                <div>
                    <div class="section-tag">WAI Platform</div>
                    <h2 class="section-title">
                        Tek yapay zekâ. Tüm müşteri kanallarınız.
                    </h2>
                </div>

                <p class="product-showcase-copy">
                    WhatsApp, Instagram, Facebook ve web sitenizden gelen görüşmeleri
                    aynı yapay zekâ bilgisi ve aynı müşteri hafızasıyla yönetin.
                    Ardından oluşan fırsatları CRM, satış pipeline, görev, alarm ve
                    raporlama ekranlarında gerçek bir satış operasyonuna dönüştürün.
                </p>
            </div>

            <div class="channel-hero reveal">

                <div class="channel-hero-copy">
                    <div class="channel-kicker">
                        <i></i>
                        WAI · KANAL MERKEZİ
                    </div>

                    <h3>
                        Müşteriniz nerede,
                        <span>WAI orada.</span>
                    </h3>

                    <p>
                        WhatsApp, Instagram, Facebook ve web sitenizden gelen müşteri
                        görüşmelerini tek yapay zekâ ile yönetin. İşletmenizi bir kez
                        öğretin; WAI tüm kanallarda aynı bilgi, aynı kurallar ve aynı
                        satış yaklaşımıyla çalışsın.
                    </p>

                    <div class="channel-benefits">
                        <div class="channel-benefit">
                            <b>Tek AI Motoru</b>
                            <span>Tüm kanallarda aynı işletme zekâsı.</span>
                        </div>

                        <div class="channel-benefit">
                            <b>Ortak Müşteri Hafızası</b>
                            <span>Konuşmalar kanal değiştirse bile bağlam korunur.</span>
                        </div>

                        <div class="channel-benefit">
                            <b>Kanal Performansı</b>
                            <span>Hangi kanaldan ne kadar lead ve satış geldiğini görün.</span>
                        </div>
                    </div>
                </div>

                <div class="channel-network">
                    <div class="network-top">
                        <strong>WAI NETWORK</strong>
                        <span class="network-live"><i></i> Canlı</span>
                    </div>

                    <div class="network-count">
                        <span>4</span>/4
                    </div>

                    <div class="network-sub">
                        kanal altyapısı
                    </div>

                    <div class="channel-icons">
                        <div class="channel-circle wa" title="WhatsApp">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="m7.5 18 .9-3a6 6 0 1 1 2.6 2.1Z"/>
                                <path d="M9.5 9.2c.3 2.5 2 4.2 4.7 4.8"/>
                            </svg>
                        </div>

                        <div class="channel-circle ig" title="Instagram">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <rect x="4" y="4" width="16" height="16" rx="5"/>
                                <circle cx="12" cy="12" r="3.5"/>
                                <circle cx="17.3" cy="6.8" r=".7" fill="currentColor"/>
                            </svg>
                        </div>

                        <div class="channel-circle fb" title="Facebook Messenger">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <path d="M4 11.5a8 8 0 1 1 3.1 6.3L4 20l.9-3.6A7.9 7.9 0 0 1 4 11.5Z"/>
                                <path d="m8 13 3-3 2 2 3-3"/>
                            </svg>
                        </div>

                        <div class="channel-circle web" title="Web Chat">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>
                            </svg>
                        </div>
                    </div>

                    <div class="network-meter">
                        <i></i>
                    </div>

                    <div class="network-foot">
                        WhatsApp · Instagram · Messenger · Web Chat
                    </div>
                </div>

            </div>

            <div class="crm-showcase">

                <div class="crm-showcase-head reveal">
                    <div class="section-tag">WAI CRM</div>

                    <h3>
                        Konuşma bittikten sonra satış süreci başlar.
                    </h3>

                    <p>
                        WAI müşteri görüşmesini yalnızca cevaplamakla bırakmaz.
                        Lead'i CRM'e taşır, skorlar, satış aşamasına yerleştirir,
                        takip görevlerini oluşturur, riskleri alarm merkezine taşır
                        ve yöneticinize gerçek zamanlı satış görünümü sunar.
                    </p>
                </div>

                <div class="crm-products">

                    <article class="crm-product full reveal">
                        <div class="crm-product-inner">
                            <div class="mock-browser">
                                <i></i><i></i><i></i>
                                <div class="mock-url">wai.asilkansoft.com.tr/admin/satis-pipeline</div>
                            </div>

                            <div class="mock-page">
                                <div class="mock-heading">
                                    <div>
                                        <small>WAI SALES PIPELINE</small>
                                        <h4>Satış fırsatlarınızı yönetin.</h4>
                                    </div>
                                    <span class="mock-button">Canlı CRM</span>
                                </div>

                                <div class="mock-kpis">
                                    <div class="mock-kpi blue"><strong>175</strong><span>Açık Fırsat</span></div>
                                    <div class="mock-kpi orange"><strong>5</strong><span>Sıcak Lead</span></div>
                                    <div class="mock-kpi purple"><strong>3</strong><span>Teklif Aşamasında</span></div>
                                    <div class="mock-kpi green"><strong>12</strong><span>Kazanılan</span></div>
                                </div>

                                <div class="mock-pipeline">
                                    <div class="mock-column">
                                        <div class="mock-column-head"><span>Yeni Lead</span><span>100</span></div>
                                        <div class="mock-lead"><b>Abdullah Bolat</b><span>WhatsApp · Lead 25/100</span><div class="mock-score"><i style="width:25%"></i></div></div>
                                        <div class="mock-lead"><b>Ahmet Çil</b><span>Web · Lead 42/100</span><div class="mock-score"><i style="width:42%"></i></div></div>
                                    </div>

                                    <div class="mock-column">
                                        <div class="mock-column-head"><span>Görüşülüyor</span><span>4</span></div>
                                        <div class="mock-lead"><b>Ali Aydemir</b><span>Instagram · Lead 66/100</span><div class="mock-score"><i style="width:66%"></i></div></div>
                                    </div>

                                    <div class="mock-column">
                                        <div class="mock-column-head"><span>Nitelikli</span><span>3</span></div>
                                        <div class="mock-lead"><b>Zeynep Acar</b><span>WhatsApp · Lead 100/100</span><div class="mock-score"><i style="width:100%"></i></div></div>
                                        <div class="mock-lead"><b>Ömer</b><span>Messenger · Lead 99/100</span><div class="mock-score"><i style="width:99%"></i></div></div>
                                    </div>

                                    <div class="mock-column">
                                        <div class="mock-column-head"><span>Teklif</span><span>3</span></div>
                                        <div class="mock-lead"><b>Mert Kaya</b><span>WhatsApp · Lead 95/100</span><div class="mock-score"><i style="width:95%"></i></div></div>
                                    </div>

                                    <div class="mock-column">
                                        <div class="mock-column-head"><span>Kazanıldı</span><span>12</span></div>
                                        <div class="mock-lead"><b>Yeni Müşteri</b><span>Satış tamamlandı</span><div class="mock-score"><i style="width:100%"></i></div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="product-caption">
                                <span>01 · Satış Pipeline</span>
                                <h4>Her lead'in nerede olduğunu görün.</h4>
                                <p>Yeni lead'den kazanılan satışa kadar tüm müşteri fırsatlarını sürükle-bırak satış aşamalarıyla yönetin.</p>
                            </div>
                        </div>
                    </article>

                    <article class="crm-product reveal">
                        <div class="crm-product-inner">
                            <div class="mock-browser">
                                <i></i><i></i><i></i>
                                <div class="mock-url">/admin/alarm-merkezi</div>
                            </div>

                            <div class="mock-page">
                                <div class="mock-heading">
                                    <div>
                                        <small>WAI ALARM CENTER</small>
                                        <h4>Satış fırsatlarını kaçırmayın.</h4>
                                    </div>
                                </div>

                                <div class="mock-kpis">
                                    <div class="mock-kpi red"><strong>3</strong><span>Aktif Alarm</span></div>
                                    <div class="mock-kpi orange"><strong>2</strong><span>Kritik</span></div>
                                    <div class="mock-kpi green"><strong>8</strong><span>Çözülen</span></div>
                                    <div class="mock-kpi blue"><strong>1</strong><span>Ertelenen</span></div>
                                </div>

                                <div class="alarm-grid">
                                    <div class="alarm-panel">
                                        <small style="color:#ff8d8d;font-size:6px;font-weight:800;">RİSKLİ MÜŞTERİLER</small>
                                        <div class="risk-row"><div class="risk-score">94</div><div><strong>Zeynep Acar</strong><span>Kritik lead · dönüş bekliyor</span></div></div>
                                        <div class="risk-row"><div class="risk-score">87</div><div><strong>ÖMER</strong><span>Teklif sonrası sessiz</span></div></div>
                                        <div class="risk-row"><div class="risk-score">81</div><div><strong>Mert Kaya</strong><span>Takip zamanı geçti</span></div></div>
                                    </div>

                                    <div class="report-panel">
                                        <small style="color:#88a9ff;font-size:6px;font-weight:800;">PERSONEL PERFORMANSI</small>
                                        <table class="mini-table">
                                            <tr><th>Personel</th><th>Aktif</th><th>Kritik</th><th>SLA</th></tr>
                                            <tr><td>Furkan Demir</td><td>5</td><td>1</td><td>0</td></tr>
                                            <tr><td>Satış Ekibi</td><td>12</td><td>2</td><td>1</td></tr>
                                            <tr><td>Destek</td><td>4</td><td>0</td><td>0</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="product-caption">
                                <span>02 · Alarm Merkezi</span>
                                <h4>WAI size neye müdahale etmeniz gerektiğini söyler.</h4>
                                <p>Kritik lead, geciken takip, SLA ve risk sinyallerini tek ekrandan görün.</p>
                            </div>
                        </div>
                    </article>

                    <article class="crm-product reveal">
                        <div class="crm-product-inner">
                            <div class="mock-browser">
                                <i></i><i></i><i></i>
                                <div class="mock-url">/admin/raporlar</div>
                            </div>

                            <div class="mock-page">
                                <div class="mock-heading">
                                    <div>
                                        <small>WAI REPORTS</small>
                                        <h4>Yönetici özeti ve satış analitiği.</h4>
                                    </div>
                                </div>

                                <div class="mock-kpis">
                                    <div class="mock-kpi blue"><strong>24</strong><span>Bugünkü Lead</span></div>
                                    <div class="mock-kpi orange"><strong>9</strong><span>Sıcak Lead</span></div>
                                    <div class="mock-kpi green"><strong>6</strong><span>Kazanılan</span></div>
                                    <div class="mock-kpi purple"><strong>18K</strong><span>Pipeline ₺</span></div>
                                </div>

                                <div class="report-panel" style="margin-top:15px;min-height:250px;">
                                    <small style="color:#8effb8;font-size:6px;font-weight:800;">SON 7 GÜN SATIŞ HAREKETİ</small>

                                    <div class="chart-bars">
                                        <i style="height:38%"></i>
                                        <i style="height:51%"></i>
                                        <i style="height:46%"></i>
                                        <i style="height:69%"></i>
                                        <i style="height:60%"></i>
                                        <i style="height:82%"></i>
                                        <i style="height:95%"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="product-caption">
                                <span>03 · Raporlar</span>
                                <h4>Bugün ne oldu, yarın neye odaklanmalısınız?</h4>
                                <p>Yönetici özetleri, KPI'lar, pipeline değeri, personel performansı ve satış trendleri tek ekranda.</p>
                            </div>
                        </div>
                    </article>

                </div>

            </div>

        </div>
    </section>


{{-- =========================================================
     INDUSTRIES
========================================================== --}}

<section class="industry-strip">

        <div class="industry-shell">

            <div class="industry-track">

                @for ($i = 0; $i < 2; $i++)

                    <div class="industry-pill">
                        <span>✦</span>
                        E-Ticaret
                    </div>

                    <div class="industry-pill">
                        <span>✦</span>
                        Klinikler
                    </div>

                    <div class="industry-pill">
                        <span>✦</span>
                        Emlak
                    </div>

                    <div class="industry-pill">
                        <span>✦</span>
                        Otomotiv
                    </div>

                    <div class="industry-pill">
                        <span>✦</span>
                        Finans
                    </div>

                    <div class="industry-pill">
                        <span>✦</span>
                        Turizm
                    </div>

                    <div class="industry-pill">
                        <span>✦</span>
                        Ajanslar
                    </div>

                    <div class="industry-pill">
                        <span>✦</span>
                        Hizmet Sektörü
                    </div>

                @endfor

            </div>

        </div>

    </section>

    {{-- =========================================================
         MANIFESTO
    ========================================================== --}}

    <section class="manifesto" id="ozellikler">

        <div class="container">

            <div class="manifesto-grid">

                <div class="manifesto-copy reveal">

                    <div class="section-tag">
                        WAI yaklaşımı
                    </div>

                    <h2 class="section-title">
                        Bir chatbot değil.
                        Dijital satış çalışanı.
                    </h2>

                    <p class="section-copy">
                        WAI yalnızca cevap üretmez. Müşteriyi anlamak,
                        konuşmayı sürdürmek ve satış fırsatını yönetmek
                        için tasarlanmış bir yapay zekâ çalışma alanıdır.
                    </p>

                </div>

                <div class="manifesto-list">

                    <div class="manifesto-item reveal">

                        <div class="manifesto-no">
                            01
                        </div>

                        <h3>
                            İşletmenizi
                            <span>öğrenir.</span>
                        </h3>

                    </div>

                    <div class="manifesto-item reveal">

                        <div class="manifesto-no">
                            02
                        </div>

                        <h3>
                            Müşterinizi
                            <span>hatırlar.</span>
                        </h3>

                    </div>

                    <div class="manifesto-item reveal">

                        <div class="manifesto-no">
                            03
                        </div>

                        <h3>
                            Fırsatı
                            <span>takip eder.</span>
                        </h3>

                    </div>

                    <div class="manifesto-item reveal">

                        <div class="manifesto-no">
                            04
                        </div>

                        <h3>
                            Gerektiğinde
                            <span>insana devreder.</span>
                        </h3>

                    </div>

                </div>

            </div>

        </div>

    </section>

    {{-- =========================================================
         COMMAND CENTER
    ========================================================== --}}

    <section class="command-section" id="urun">

        <div class="container">

            <div class="command-header reveal">

                <div>

                    <div class="section-tag">
                        WAI Kontrol Merkezi
                    </div>

                    <h2 class="section-title">
                        WhatsApp operasyonunuz artık tek ekranda.
                    </h2>

                </div>

                <p class="command-description">
                    Gelen mesajları yönetin, müşterileri etiketleyin,
                    yapay zekâyı izleyin, sıcak fırsatları görün ve
                    istediğiniz görüşmeyi tek tıkla ekibinize devredin.
                </p>

            </div>

            <div class="command-board reveal">

                <div class="command-inner">

                    <div class="command-top">

                        <span class="command-browser-dot"></span>
                        <span class="command-browser-dot"></span>
                        <span class="command-browser-dot"></span>

                        <div class="command-url">
                            wai.asilkansoft.com.tr/admin/gelen-kutusu
                        </div>

                    </div>

                    <div class="command-grid">

                        <aside class="conversation-list">

                            <div class="panel-title">

                                <strong>Gelen Kutusu</strong>

                                <span class="badge-count">
                                    12
                                </span>

                            </div>

                            <div class="conversation-filters">

                                <span class="conv-filter active">
                                    Tümü
                                </span>

                                <span class="conv-filter">
                                    Okunmamış
                                </span>

                                <span class="conv-filter">
                                    İnsan
                                </span>

                            </div>

                            <div class="conversation active">

                                <div class="conversation-avatar">
                                    AY
                                </div>

                                <div class="conversation-content">

                                    <div class="conversation-line">
                                        <strong>Ahmet Yılmaz</strong>
                                        <time>14:23</time>
                                    </div>

                                    <p>
                                        Sipariş vermek istiyorum...
                                    </p>

                                    <span class="conversation-tag">
                                        SICAK MÜŞTERİ
                                    </span>

                                </div>

                            </div>

                            <div class="conversation">

                                <div class="conversation-avatar">
                                    EK
                                </div>

                                <div class="conversation-content">

                                    <div class="conversation-line">
                                        <strong>Elif Kaya</strong>
                                        <time>13:51</time>
                                    </div>

                                    <p>
                                        Fiyat bilgisi alabilir miyim?
                                    </p>

                                </div>

                            </div>

                            <div class="conversation">

                                <div class="conversation-avatar">
                                    MD
                                </div>

                                <div class="conversation-content">

                                    <div class="conversation-line">
                                        <strong>Mehmet Demir</strong>
                                        <time>12:18</time>
                                    </div>

                                    <p>
                                        Kargo kaç günde gelir?
                                    </p>

                                </div>

                            </div>

                            <div class="conversation">

                                <div class="conversation-avatar">
                                    SA
                                </div>

                                <div class="conversation-content">

                                    <div class="conversation-line">
                                        <strong>Selin Aydın</strong>
                                        <time>11:42</time>
                                    </div>

                                    <p>
                                        Hangi ürünleriniz var?
                                    </p>

                                </div>

                            </div>

                            <div class="conversation">

                                <div class="conversation-avatar">
                                    BK
                                </div>

                                <div class="conversation-content">

                                    <div class="conversation-line">
                                        <strong>Burak Kaya</strong>
                                        <time>10:17</time>
                                    </div>

                                    <p>
                                        Teklif almak istiyorum.
                                    </p>

                                </div>

                            </div>

                        </aside>

                        <div class="main-chat-panel">

                            <div class="main-chat-head">

                                <div class="conversation-avatar">
                                    AY
                                </div>

                                <div class="chat-person-data">
                                    <strong>Ahmet Yılmaz</strong>
                                    <span>+90 5•• ••• ••21</span>
                                </div>

                                <div class="main-ai-badge">
                                    <i></i>
                                    AI AKTİF
                                </div>

                            </div>

                            <div class="main-messages">

                                <div class="main-message customer">
                                    Merhaba, zeytinyağlarınız hakkında bilgi
                                    almak istiyorum.
                                    <time>14:21</time>
                                </div>

                                <div class="main-message ai">
                                    Merhaba 👋 Memnuniyetle yardımcı olayım.
                                    Hangi boy zeytinyağımızla ilgileniyorsunuz?
                                    <time>14:21 ✓✓</time>
                                </div>

                                <div class="main-message customer">
                                    5 litrelik almak istiyorum.
                                    Kargo ücretsiz mi?
                                    <time>14:22</time>
                                </div>

                                <div class="main-message ai">
                                    Evet 🌿 5 litre ve üzeri siparişlerde
                                    kargo ücretsizdir. İsterseniz siparişinizi
                                    birlikte oluşturabiliriz.
                                    <time>14:22 ✓✓</time>
                                </div>

                                <div class="main-message customer">
                                    Olur, sipariş vereyim.
                                    <time>14:23</time>
                                </div>

                            </div>

                            <div class="main-compose">

                                <div class="main-input">
                                    Mesaj yazın...
                                </div>

                                <div class="main-send">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path d="m22 2-7 20-4-9-9-4Z"></path>
                                        <path d="M22 2 11 13"></path>
                                    </svg>

                                </div>

                            </div>

                        </div>

                        <aside class="customer-panel">

                            <div class="panel-title">
                                <strong>Müşteri</strong>
                            </div>

                            <div class="customer-body">

                                <div class="customer-card">

                                    <div class="customer-name">

                                        <div class="conversation-avatar">
                                            AY
                                        </div>

                                        <div>
                                            <strong>Ahmet Yılmaz</strong>
                                            <span>Yeni müşteri</span>
                                        </div>

                                    </div>

                                    <div class="customer-field">

                                        <label>
                                            Durum
                                        </label>

                                        <div class="customer-tags">

                                            <span class="customer-tag hot">
                                                🔥 Sıcak
                                            </span>

                                            <span class="customer-tag new">
                                                Yeni
                                            </span>

                                        </div>

                                    </div>

                                    <div class="customer-field">
                                        <label>İlgilendiği ürün</label>
                                        <strong>5 L Zeytinyağı</strong>
                                    </div>

                                    <div class="customer-field">
                                        <label>Son görüşme</label>
                                        <strong>Az önce</strong>
                                    </div>

                                </div>

                                <div class="human-control">

                                    <strong>
                                        İnsan kontrolü
                                    </strong>

                                    <p>
                                        İsterseniz görüşmeyi
                                        yapay zekâdan devralabilirsiniz.
                                    </p>

                                    <div class="human-button">
                                        Görüşmeyi Devral
                                    </div>

                                </div>

                            </div>

                        </aside>

                    </div>

                </div>

            </div>

        </div>

    </section>

    {{-- =========================================================
         BENTO FEATURES
    ========================================================== --}}

    <section class="bento-section">

        <div class="container">

            <div class="bento-header reveal">

                <div>

                    <div class="section-tag">
                        WAI yetenekleri
                    </div>

                    <h2 class="section-title">
                        Mesajdan satışa kadar tek sistem.
                    </h2>

                </div>

                <p class="section-copy">
                    Müşteri konuşmasını yalnızca yanıtlamak yerine
                    satış sürecinin tamamına bağlayın.
                </p>

            </div>

            <div class="bento">

                <article class="bento-card featured reveal">

                    <div class="bento-mini">
                        Satış otomasyonu
                    </div>

                    <h3>
                        Mesaj geldiği anda doğru satış akışı başlasın.
                    </h3>

                    <p>
                        WAI müşterinin ihtiyacını anlar ve görüşmeyi
                        uygun satış akışında ilerletir.
                    </p>

                    <div class="flow">

                        <div class="flow-box">
                            <strong>Yeni mesaj</strong>
                            <span>Müşteri WhatsApp'tan yazdı.</span>
                        </div>

                        <div class="flow-arrow">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M5 12h14"></path>
                                <path d="m13 6 6 6-6 6"></path>
                            </svg>

                        </div>

                        <div class="flow-box">
                            <strong>WAI görüşmesi</strong>
                            <span>İhtiyaç otomatik anlaşıldı.</span>
                        </div>

                        <div class="flow-arrow">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M5 12h14"></path>
                                <path d="m13 6 6 6-6 6"></path>
                            </svg>

                        </div>

                        <div class="flow-box">
                            <strong>Satış fırsatı</strong>
                            <span>CRM otomatik güncellendi.</span>
                        </div>

                    </div>

                </article>

                <article class="bento-card reveal">

                    <div class="bento-mini">
                        İnsan devralma
                    </div>

                    <h3>
                        Yapay zekâ çalışsın. Kontrol sizde kalsın.
                    </h3>

                    <p>
                        İstediğiniz konuşmayı ekibiniz saniyeler içinde devralabilir.
                    </p>

                    <div class="control-demo">

                        <div class="control-side">
                            <span>Şu anda</span>
                            <strong>WAI konuşuyor</strong>
                        </div>

                        <div class="toggle">
                            <div class="toggle-knob"></div>
                        </div>

                        <div class="control-side">
                            <span>Devralınca</span>
                            <strong>Ekibiniz konuşur</strong>
                        </div>

                    </div>

                </article>

                <article class="bento-card reveal">

                    <div class="bento-mini">
                        Konuşma hafızası
                    </div>

                    <h3>
                        Müşteriye aynı bilgiyi tekrar sormayın.
                    </h3>

                    <p>
                        Görüşmede verilen önemli bilgiler müşteri bağlamında korunur.
                    </p>

                    <div class="memory-grid">

                        <div class="memory-box">
                            <label>Müşteri</label>
                            <strong>Ahmet Yılmaz</strong>
                        </div>

                        <div class="memory-box">
                            <label>Ürün</label>
                            <strong>5 L Zeytinyağı</strong>
                        </div>

                        <div class="memory-box">
                            <label>Satış aşaması</label>
                            <strong>Siparişe hazır</strong>
                        </div>

                        <div class="memory-box">
                            <label>Son görüşme</label>
                            <strong>14:23</strong>
                        </div>

                    </div>

                </article>

                <article class="bento-card featured reveal">

                    <div class="bento-mini">
                        Akıllı takip
                    </div>

                    <h3>
                        Cevapsız kalan müşteri satıştan düşmesin.
                    </h3>

                    <p>
                        WAI satış fırsatlarını takip ederek
                        gerektiğinde müşteriye yeniden ulaşır.
                    </p>

                    <div class="timeline">

                        <div class="timeline-step active">
                            <div class="timeline-dot">✓</div>
                            <strong>Mesaj</strong>
                            <span>Şimdi</span>
                        </div>

                        <div class="timeline-step">
                            <div class="timeline-dot">1H</div>
                            <strong>Bekle</strong>
                            <span>Kontrol</span>
                        </div>

                        <div class="timeline-step">
                            <div class="timeline-dot">24H</div>
                            <strong>Takip</strong>
                            <span>Hatırlatma</span>
                        </div>

                        <div class="timeline-step">
                            <div class="timeline-dot">CRM</div>
                            <strong>Güncelle</strong>
                            <span>Durum</span>
                        </div>

                    </div>

                </article>

            </div>

        </div>

    </section>

    {{-- =========================================================
         HOW IT WORKS
    ========================================================== --}}

    <section class="steps-section" id="nasil-calisir">

        <div class="container">

            <div class="steps-intro reveal">

                <div class="section-tag">
                    3 adımda başlayın
                </div>

                <h2 class="section-title">
                    İşletmenizin AI çalışanını birkaç dakikada oluşturun.
                </h2>

                <p class="section-copy">
                    Teknik ekip gerekmeden WhatsApp'ınızı bağlayın,
                    işletmenizi öğretin ve WAI'yi çalıştırın.
                </p>

            </div>

            <div class="steps-grid">

                <article class="step reveal">

                    <div class="step-no">
                        01
                    </div>

                    <h3>
                        WhatsApp'ınızı bağlayın.
                    </h3>

                    <p>
                        QR kodu okutun ve işletme numaranızı WAI'ye bağlayın.
                    </p>

                    <div class="step-visual">

                        <div class="qr-area">

                            <div class="qr"></div>

                            <div class="qr-text">
                                <strong>QR kodu okutun</strong>

                                <span>
                                    WhatsApp bağlantınız birkaç saniye
                                    içerisinde hazırlanır.
                                </span>
                            </div>

                        </div>

                    </div>

                </article>

                <article class="step reveal">

                    <div class="step-no">
                        02
                    </div>

                    <h3>
                        İşletmenizi öğretin.
                    </h3>

                    <p>
                        Ürün, hizmet, fiyat ve firma kurallarınızı WAI'ye tanımlayın.
                    </p>

                    <div class="step-visual">

                        <div class="knowledge-list">

                            <div class="knowledge">

                                Ürünler ve hizmetler

                                <span class="knowledge-check">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.4"
                                    >
                                        <path d="m5 12 4 4L19 6"></path>
                                    </svg>

                                </span>

                            </div>

                            <div class="knowledge">

                                Fiyat ve ödeme bilgileri

                                <span class="knowledge-check">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.4"
                                    >
                                        <path d="m5 12 4 4L19 6"></path>
                                    </svg>

                                </span>

                            </div>

                            <div class="knowledge">

                                Firma kuralları

                                <span class="knowledge-check">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.4"
                                    >
                                        <path d="m5 12 4 4L19 6"></path>
                                    </svg>

                                </span>

                            </div>

                        </div>

                    </div>

                </article>

                <article class="step reveal">

                    <div class="step-no">
                        03
                    </div>

                    <h3>
                        WAI çalışmaya başlasın.
                    </h3>

                    <p>
                        Artık müşteriniz yazdığında yapay zekâ satış ekibiniz hazır.
                    </p>

                    <div class="step-visual">

                        <div class="ready-box">

                            <div class="ready-status">

                                <span class="ready-orb"></span>

                                <strong>
                                    WAI yayında
                                </strong>

                            </div>

                            <p>
                                WhatsApp mesajları otomatik olarak işleniyor.
                            </p>

                        </div>

                    </div>

                </article>

            </div>

        </div>

    </section>

    {{-- =========================================================
         SECTORS
    ========================================================== --}}

    <section class="sectors-section" id="sektorler">

        <div class="container">

            <div class="sector-shell">

                <div class="sector-top reveal">

                    <div>

                        <div class="section-tag">
                            Her sektöre uyarlanabilir
                        </div>

                        <h2 class="section-title">
                            İşiniz farklı olabilir. WAI müşteriyi kaçırmaz.
                        </h2>

                    </div>

                    <p class="sector-copy">
                        İşletmenizin çalışma biçimini WAI'ye öğreterek
                        sektörünüze özel bir WhatsApp yapay zekâ çalışanı oluşturabilirsiniz.
                    </p>

                </div>

                <div class="sector-grid">

                    <article class="sector reveal">

                        <div class="sector-icon">🛒</div>

                        <h3>
                            E-Ticaret
                        </h3>

                        <p>
                            Ürün bilgisi, ödeme, kargo, sipariş ve satış takibi.
                        </p>

                    </article>

                    <article class="sector reveal">

                        <div class="sector-icon">🏠</div>

                        <h3>
                            Emlak
                        </h3>

                        <p>
                            Portföy soruları, ihtiyaç analizi ve müşteri yönlendirme.
                        </p>

                    </article>

                    <article class="sector reveal">

                        <div class="sector-icon">✚</div>

                        <h3>
                            Klinikler
                        </h3>

                        <p>
                            Hizmet bilgisi, randevu talebi ve danışan iletişimi.
                        </p>

                    </article>

                    <article class="sector reveal">

                        <div class="sector-icon">₺</div>

                        <h3>
                            Finans
                        </h3>

                        <p>
                            Ön bilgi toplama, başvuru akışı ve müşteri yönlendirme.
                        </p>

                    </article>

                    <article class="sector reveal">

                        <div class="sector-icon">◈</div>

                        <h3>
                            Otomotiv
                        </h3>

                        <p>
                            Araç bilgisi, servis, teklif ve satış görüşmeleri.
                        </p>

                    </article>

                    <article class="sector reveal">

                        <div class="sector-icon">✦</div>

                        <h3>
                            Turizm
                        </h3>

                        <p>
                            Rezervasyon soruları, fiyatlar ve müşteri iletişimi.
                        </p>

                    </article>

                    <article class="sector reveal">

                        <div class="sector-icon">⌘</div>

                        <h3>
                            Ajanslar
                        </h3>

                        <p>
                            Lead karşılama, müşteri ön eleme ve satış otomasyonu.
                        </p>

                    </article>

                    <article class="sector reveal">

                        <div class="sector-icon">◆</div>

                        <h3>
                            Hizmet Sektörü
                        </h3>

                        <p>
                            Fiyat, keşif, randevu ve satış sonrası destek.
                        </p>

                    </article>

                </div>

            </div>

        </div>

    </section>

    {{-- =========================================================
         STATS
    ========================================================== --}}

    <section class="stats">

        <div class="container">

            <div class="stats-grid reveal">

                <div class="stat-intro">

                    <strong>
                        WhatsApp satış operasyonunu sadeleştirin.
                    </strong>

                    <span>
                        Daha az manuel işlem. Daha hızlı iletişim.
                    </span>

                </div>

                <div class="stat">

                    <strong>
                        <span class="green">7/24</span>
                    </strong>

                    <span>
                        Müşterileriniz için sürekli erişilebilir yapay zekâ.
                    </span>

                </div>

                <div class="stat">

                    <strong>
                        &lt;10 sn
                    </strong>

                    <span>
                        Mesajlara saniyeler içinde otomatik yanıt.
                    </span>

                </div>

                <div class="stat">

                    <strong>
                        1 Panel
                    </strong>

                    <span>
                        AI, CRM, gelen kutusu ve insan devralma tek yerde.
                    </span>

                </div>

            </div>

        </div>

    </section>

    {{-- =========================================================
         FAQ
    ========================================================== --}}

    <section class="faq-section" id="sss">

        <div class="container">

            <div class="faq-grid">

                <div class="faq-intro reveal">

                    <div class="section-tag">
                        Sık sorulanlar
                    </div>

                    <h2 class="section-title">
                        WAI hakkında kısa cevaplar.
                    </h2>

                    <p class="section-copy">
                        Başlamadan önce en çok merak edilen konuları burada topladık.
                    </p>

                </div>

                <div class="faq-list reveal">

                    <div class="faq-item open">

                        <button class="faq-question">

                            <strong>
                                WAI normal bir chatbot mu?
                            </strong>

                            <span class="faq-icon"></span>

                        </button>

                        <div class="faq-answer">

                            <div class="faq-answer-inner">
                                Hayır. WAI yalnızca hazır cevap gönderen bir chatbot değildir.
                                İşletmenizin bilgileri, belirlediğiniz kurallar ve konuşma bağlamı
                                üzerinden müşterilerinizle doğal şekilde görüşmek için tasarlanmıştır.
                            </div>

                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">

                            <strong>
                                Mevcut WhatsApp numaramı kullanabilir miyim?
                            </strong>

                            <span class="faq-icon"></span>

                        </button>

                        <div class="faq-answer">

                            <div class="faq-answer-inner">
                                Uygun bağlantı yöntemiyle işletme WhatsApp numaranızı
                                WAI sistemine bağlayabilir ve bağlantıyı panel üzerinden yönetebilirsiniz.
                            </div>

                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">

                            <strong>
                                Yapay zekânın ne söyleyeceğini belirleyebilir miyim?
                            </strong>

                            <span class="faq-icon"></span>

                        </button>

                        <div class="faq-answer">

                            <div class="faq-answer-inner">
                                Evet. Firma açıklamanızı, ürünlerinizi, hizmetlerinizi,
                                çalışma saatlerinizi, ödeme ve kargo bilgilerinizi,
                                şirket kurallarınızı ve özel konuşma talimatlarınızı tanımlayabilirsiniz.
                            </div>

                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">

                            <strong>
                                Çalışanım görüşmeyi devralabilir mi?
                            </strong>

                            <span class="faq-icon"></span>

                        </button>

                        <div class="faq-answer">

                            <div class="faq-answer-inner">
                                Evet. Gelen Kutusu üzerinden istediğiniz görüşmeyi
                                yapay zekâdan devralabilir ve müşteriye manuel mesaj gönderebilirsiniz.
                            </div>

                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">

                            <strong>
                                Cevap vermeyen müşteriler takip edilebilir mi?
                            </strong>

                            <span class="faq-icon"></span>

                        </button>

                        <div class="faq-answer">

                            <div class="faq-answer-inner">
                                WAI'nin otomatik takip sistemi sayesinde satış süreci yarım kalan
                                veya yanıt vermeyen müşteriler belirlenen senaryolara göre yeniden
                                takip edilebilir.
                            </div>

                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">

                            <strong>
                                Teknik bilgiye ihtiyacım var mı?
                            </strong>

                            <span class="faq-icon"></span>

                        </button>

                        <div class="faq-answer">

                            <div class="faq-answer-inner">
                                Hayır. WAI işletmelerin teknik ekip olmadan kullanabilmesi için
                                panel üzerinden yönetilebilir şekilde geliştirilmiştir.
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>

    {{-- =========================================================
         FINAL CTA
    ========================================================== --}}

    <section class="final-section">

        <div class="container">

            <div class="final-card reveal">

                <div class="final-content">

                    <div class="final-kicker">
                        AsilkanSoft ekosistemine katılın
                    </div>

                    <h2>
                        İşletmenizin
                        <span>dijital geleceğini</span>
                        tek merkezden kurun.
                    </h2>

                    <p>
                        Yapay zekâ, CRM, satış, web, e-ticaret ve dijital altyapınızı
                        AsilkanSoft teknolojileriyle birlikte büyütün.
                    </p>

                    <div class="final-actions">

                        <a href="/admin/register" class="button-main">

                            Ücretsiz Hesap Oluştur

                            <span class="button-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M5 12h14"></path>
                                    <path d="m13 6 6 6-6 6"></path>
                                </svg>

                            </span>

                        </a>

                        <a href="/admin/login" class="button-ghost">
                            Zaten hesabım var
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </section>

</main>

<footer>

    <div class="container">

        <div class="footer-top">

            <div class="footer-brand">

                <a href="/" class="brand">

                    <span class="brand-logo"></span>

                    <span class="brand-name">
                        WAI
                        <small>WhatsApp Intelligence</small>
                    </span>

                </a>

                <p>
                    AsilkanSoft tarafından geliştirilen yapay zekâ, CRM,
                    satış otomasyonu ve dijital işletme ekosistemi.
                </p>

            </div>

            <div class="footer-col">

                <strong>
                    Ürün
                </strong>

                <a href="#urun">
                    Kontrol Merkezi
                </a>

                <a href="#ozellikler">
                    Yapay Zeka
                </a>

                <a href="#nasil-calisir">
                    Nasıl Çalışır?
                </a>

            </div>

            <div class="footer-col">

                <strong>
                    Hesap
                </strong>

                <a href="/admin/login">
                    Giriş Yap
                </a>

                <a href="/admin/register">
                    Ücretsiz Başla
                </a>

            </div>

            <div class="footer-col">

                <strong>
                    WAI
                </strong>

                <a href="#sektorler">
                    Sektörler
                </a>

                <a href="#sss">
                    SSS
                </a>

            </div>

        </div>

        <div class="footer-bottom">

            <span>
                © {{ date('Y') }} WAI. Tüm hakları saklıdır.
            </span>

            <span>
                Bir <strong>AsilkanSoft</strong> teknolojisidir.
            </span>

        </div>

    </div>

</footer>

<script>
    document.addEventListener('DOMContentLoaded', () => {

        /*
        |--------------------------------------------------------------------------
        | Navbar
        |--------------------------------------------------------------------------
        */

        const navbar = document.getElementById('navbar');

        const updateNavbar = () => {

            if (window.scrollY > 18) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

        };

        updateNavbar();

        window.addEventListener(
            'scroll',
            updateNavbar,
            {
                passive: true
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Mobile Menu
        |--------------------------------------------------------------------------
        */

        const menuButton = document.getElementById('menuButton');
        const mobileMenuLinks = document.querySelectorAll('#mobileMenu a');

        menuButton?.addEventListener('click', () => {
            document.body.classList.toggle('menu-open');
        });

        mobileMenuLinks.forEach(link => {

            link.addEventListener('click', () => {
                document.body.classList.remove('menu-open');
            });

        });


        /*
        |--------------------------------------------------------------------------
        | Scroll Reveal
        |--------------------------------------------------------------------------
        */

        const revealElements = document.querySelectorAll('.reveal');

        if ('IntersectionObserver' in window) {

            const observer = new IntersectionObserver(

                entries => {

                    entries.forEach(entry => {

                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add('visible');

                        observer.unobserve(entry.target);

                    });

                },

                {
                    threshold: 0.10,
                    rootMargin: '0px 0px -35px 0px'
                }

            );

            revealElements.forEach(element => {
                observer.observe(element);
            });

        } else {

            revealElements.forEach(element => {
                element.classList.add('visible');
            });

        }


        /*
        |--------------------------------------------------------------------------
        | FAQ
        |--------------------------------------------------------------------------
        */

        const faqItems = document.querySelectorAll('.faq-item');

        const updateFaqHeight = item => {

            const answer = item.querySelector('.faq-answer');

            if (!answer) {
                return;
            }

            if (item.classList.contains('open')) {
                answer.style.maxHeight = answer.scrollHeight + 'px';
            } else {
                answer.style.maxHeight = '0px';
            }

        };

        faqItems.forEach(item => {

            const button = item.querySelector('.faq-question');

            updateFaqHeight(item);

            button?.addEventListener('click', () => {

                const alreadyOpen = item.classList.contains('open');

                faqItems.forEach(otherItem => {

                    otherItem.classList.remove('open');

                    updateFaqHeight(otherItem);

                });

                if (!alreadyOpen) {

                    item.classList.add('open');

                    updateFaqHeight(item);

                }

            });

        });

        window.addEventListener('resize', () => {

            faqItems.forEach(item => {
                updateFaqHeight(item);
            });

        });

    });
</script>

</body>
</html>
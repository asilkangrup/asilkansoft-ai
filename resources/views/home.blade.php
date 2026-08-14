<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>WAI — WhatsApp Yapay Zeka Satış Asistanı</title>

    <meta name="description"
          content="WAI, WhatsApp mesajlarınızı 7/24 yanıtlayan, müşterileri takip eden, satış süreçlerini yöneten ve gerektiğinde görüşmeleri ekibinize devreden yapay zeka satış asistanıdır.">

    <meta name="theme-color" content="#070a09">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --black: #070a09;
            --black-2: #0b0f0d;
            --black-3: #101613;

            --white: #ffffff;
            --paper: #f7f9f7;
            --paper-2: #eef3ef;

            --text: #101411;
            --muted: #69736d;
            --muted-dark: #9aa59f;

            --green: #51f29b;
            --green-2: #19d978;
            --green-3: #0db967;
            --green-soft: rgba(81, 242, 155, .12);

            --line: rgba(14, 25, 19, .09);
            --line-dark: rgba(255,255,255,.08);

            --shadow:
                0 30px 80px rgba(3, 14, 8, .10),
                0 10px 30px rgba(3, 14, 8, .05);

            --radius-sm: 16px;
            --radius-md: 24px;
            --radius-lg: 34px;
            --radius-xl: 46px;

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
            font-family: 'DM Sans', sans-serif;
            background: var(--paper);
            color: var(--text);
            line-height: 1.5;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        body.menu-open {
            overflow: hidden;
        }

        button,
        input,
        textarea,
        select {
            font: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        img,
        svg {
            max-width: 100%;
            display: block;
        }

        .container {
            width: min(var(--container), calc(100% - 40px));
            margin: 0 auto;
        }

        .section {
            padding: 120px 0;
            position: relative;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 20px;
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.6px;
            color: #087847;
        }

        .eyebrow::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green-2);
            box-shadow: 0 0 0 6px rgba(25, 217, 120, .10);
        }

        .section-heading {
            max-width: 900px;
            font-family: 'Manrope', sans-serif;
            font-size: clamp(42px, 5.6vw, 76px);
            line-height: .98;
            letter-spacing: -4.2px;
            font-weight: 800;
        }

        .section-description {
            max-width: 650px;
            margin-top: 24px;
            font-size: 18px;
            line-height: 1.75;
            color: var(--muted);
        }

        /* =========================================================
           NAVIGATION
        ========================================================= */

        .nav-space {
            height: 92px;
        }

        .nav-shell {
            position: fixed;
            z-index: 1000;
            left: 0;
            right: 0;
            top: 0;
            padding: 15px 0;
            transition: .3s ease;
        }

        .nav-shell.scrolled {
            padding-top: 9px;
        }

        .navbar {
            position: relative;
            min-height: 66px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 26px;
            padding: 9px 10px 9px 18px;

            background:
                linear-gradient(
                    180deg,
                    rgba(255,255,255,.90),
                    rgba(250,252,250,.78)
                );

            border: 1px solid rgba(255,255,255,.85);
            border-radius: 20px;

            box-shadow:
                0 18px 50px rgba(9, 24, 15, .08),
                inset 0 0 0 1px rgba(15, 30, 21, .05);

            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            flex-shrink: 0;
        }

        .brand-symbol {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            overflow: hidden;

            background:
                radial-gradient(circle at 35% 25%, rgba(81,242,155,.35), transparent 34%),
                #0a0e0c;

            box-shadow:
                inset 0 0 0 1px rgba(255,255,255,.07),
                0 10px 25px rgba(6,15,10,.12);
        }

        .brand-symbol::before,
        .brand-symbol::after {
            content: "";
            position: absolute;
            width: 17px;
            height: 3px;
            border-radius: 5px;
            background: var(--green);
        }

        .brand-symbol::before {
            transform: rotate(55deg);
            left: 9px;
        }

        .brand-symbol::after {
            transform: rotate(-55deg);
            right: 9px;
        }

        .brand-text {
            font-family: 'Manrope', sans-serif;
            font-size: 21px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .brand-text small {
            display: block;
            margin-top: 4px;
            color: #8b948f;
            font-family: 'DM Sans', sans-serif;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-left: auto;
        }

        .nav-links a {
            position: relative;
            font-size: 14px;
            font-weight: 600;
            color: #4d5751;
            transition: .2s ease;
        }

        .nav-links a::after {
            content: "";
            position: absolute;
            left: 0;
            right: 100%;
            bottom: -7px;
            height: 2px;
            background: var(--green-2);
            border-radius: 5px;
            transition: .25s ease;
        }

        .nav-links a:hover {
            color: #0c100e;
        }

        .nav-links a:hover::after {
            right: 0;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-login {
            height: 46px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            font-size: 14px;
            font-weight: 700;
            color: #333b36;
            transition: .2s ease;
        }

        .nav-login:hover {
            background: #edf1ee;
        }

        .nav-cta {
            height: 48px;
            padding: 0 19px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            color: white;
            background: var(--black);

            border-radius: 13px;
            font-size: 14px;
            font-weight: 700;

            box-shadow:
                0 10px 24px rgba(8, 15, 11, .16),
                inset 0 0 0 1px rgba(255,255,255,.06);

            transition: .22s ease;
        }

        .nav-cta:hover {
            transform: translateY(-2px);
            background: #111712;
        }

        .nav-cta svg {
            width: 15px;
        }

        .mobile-menu-button {
            display: none;
            width: 46px;
            height: 46px;
            border: 0;
            border-radius: 13px;
            background: #edf1ee;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .mobile-menu-button span {
            position: relative;
            width: 20px;
            height: 2px;
            border-radius: 5px;
            background: var(--black);
        }

        .mobile-menu-button span::before,
        .mobile-menu-button span::after {
            content: "";
            position: absolute;
            left: 0;
            width: 20px;
            height: 2px;
            border-radius: 5px;
            background: var(--black);
        }

        .mobile-menu-button span::before {
            top: -6px;
        }

        .mobile-menu-button span::after {
            top: 6px;
        }

        .mobile-nav {
            display: none;
        }

        /* =========================================================
           HERO
        ========================================================= */

        .hero {
            position: relative;
            overflow: hidden;
            padding: 58px 0 90px;
        }

        .hero::before {
            content: "";
            position: absolute;
            width: 680px;
            height: 680px;
            top: -270px;
            right: -160px;
            border-radius: 50%;
            background:
                radial-gradient(circle, rgba(81,242,155,.15), rgba(81,242,155,0) 67%);
            pointer-events: none;
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 550px;
            height: 550px;
            left: -260px;
            bottom: -260px;
            border-radius: 50%;
            background:
                radial-gradient(circle, rgba(25,217,120,.08), transparent 68%);
            pointer-events: none;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(430px, .95fr);
            gap: 54px;
            align-items: center;
        }

        .hero-copy {
            position: relative;
            z-index: 3;
            padding-top: 24px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 38px;
            padding: 0 14px;
            margin-bottom: 24px;
            border-radius: 999px;

            border: 1px solid rgba(15, 148, 87, .13);
            background: rgba(255,255,255,.78);

            box-shadow:
                inset 0 0 0 1px rgba(255,255,255,.8),
                0 8px 24px rgba(7, 26, 15, .04);

            color: #267153;
            font-size: 12px;
            font-weight: 700;
        }

        .pulse {
            position: relative;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--green-2);
        }

        .pulse::after {
            content: "";
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            border: 1px solid rgba(25, 217, 120, .45);
            animation: pulseRing 2s infinite;
        }

        @keyframes pulseRing {
            0% {
                opacity: 1;
                transform: scale(.7);
            }

            70% {
                opacity: 0;
                transform: scale(1.6);
            }

            100% {
                opacity: 0;
            }
        }

        .hero h1 {
            max-width: 800px;
            font-family: 'Manrope', sans-serif;
            font-size: clamp(58px, 6.7vw, 92px);
            line-height: .91;
            letter-spacing: -6px;
            font-weight: 800;
        }

        .hero h1 .soft {
            color: #7b857f;
        }

        .hero h1 .green {
            position: relative;
            color: #0d995a;
            white-space: nowrap;
        }

        .hero h1 .green::after {
            content: "";
            position: absolute;
            height: 13px;
            left: 1%;
            right: 0;
            bottom: 5px;
            z-index: -1;
            background: rgba(81,242,155,.28);
            border-radius: 20px;
            transform: rotate(-1deg);
        }

        .hero-description {
            max-width: 680px;
            margin-top: 28px;
            font-size: 18px;
            line-height: 1.75;
            color: #626e67;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 33px;
        }

        .button-primary,
        .button-secondary {
            min-height: 58px;
            padding: 0 24px;
            border-radius: 16px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;

            font-size: 15px;
            font-weight: 700;

            transition: .23s ease;
        }

        .button-primary {
            background: var(--black);
            color: white;

            box-shadow:
                0 18px 38px rgba(7, 14, 10, .18),
                inset 0 0 0 1px rgba(255,255,255,.07);
        }

        .button-primary:hover {
            transform: translateY(-3px);
            box-shadow:
                0 24px 50px rgba(7, 14, 10, .22),
                inset 0 0 0 1px rgba(255,255,255,.08);
        }

        .button-primary .arrow {
            width: 30px;
            height: 30px;
            border-radius: 10px;
            background: rgba(255,255,255,.09);
            display: grid;
            place-items: center;
        }

        .button-primary .arrow svg {
            width: 14px;
        }

        .button-secondary {
            color: #26302a;
            border: 1px solid rgba(13, 25, 18, .10);
            background: rgba(255,255,255,.72);
            backdrop-filter: blur(10px);
        }

        .button-secondary:hover {
            background: white;
            transform: translateY(-2px);
        }

        .hero-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 22px;
            color: #8a948f;
            font-size: 12px;
        }

        .hero-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .hero-meta svg {
            width: 14px;
            color: #0cb968;
        }

        /* =========================================================
           HERO VISUAL
        ========================================================= */

        .hero-visual {
            position: relative;
            min-height: 680px;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1200px;
        }

        .visual-orbit {
            position: absolute;
            width: 550px;
            height: 550px;
            border-radius: 50%;

            background:
                radial-gradient(circle at center, rgba(81,242,155,.14), rgba(81,242,155,0) 55%);

            border: 1px dashed rgba(18, 135, 79, .12);
        }

        .visual-orbit::before,
        .visual-orbit::after {
            content: "";
            position: absolute;
            inset: 55px;
            border-radius: 50%;
            border: 1px solid rgba(11, 33, 20, .055);
        }

        .visual-orbit::after {
            inset: 120px;
        }

        .phone {
            position: relative;
            z-index: 4;
            width: 346px;
            height: 687px;
            padding: 10px;
            border-radius: 52px;

            background:
                linear-gradient(145deg, #242b27, #050706 45%, #141916);

            box-shadow:
                0 60px 110px rgba(5, 19, 11, .26),
                0 18px 40px rgba(6, 20, 12, .12),
                inset 0 0 0 1px rgba(255,255,255,.08);

            transform:
                rotateY(-7deg)
                rotateX(2deg)
                rotateZ(1.5deg);

            transition: transform .4s ease;
        }

        .hero-visual:hover .phone {
            transform:
                rotateY(-2deg)
                rotateX(0deg)
                rotateZ(.5deg)
                translateY(-5px);
        }

        .phone-side-button {
            position: absolute;
            width: 3px;
            border-radius: 3px;
            background: #252b28;
        }

        .phone-side-button.one {
            height: 50px;
            left: -3px;
            top: 120px;
        }

        .phone-side-button.two {
            height: 76px;
            left: -3px;
            top: 190px;
        }

        .phone-side-button.three {
            height: 82px;
            right: -3px;
            top: 155px;
        }

        .phone-screen {
            position: relative;
            height: 100%;
            overflow: hidden;
            border-radius: 44px;

            background:
                linear-gradient(rgba(235, 231, 223, .91), rgba(235, 231, 223, .91)),
                repeating-linear-gradient(
                    45deg,
                    rgba(255,255,255,.3) 0,
                    rgba(255,255,255,.3) 1px,
                    transparent 1px,
                    transparent 14px
                );
        }

        .dynamic-island {
            position: absolute;
            z-index: 10;
            top: 10px;
            left: 50%;
            width: 96px;
            height: 28px;
            transform: translateX(-50%);
            border-radius: 999px;
            background: #050706;
        }

        .phone-header {
            height: 88px;
            padding: 29px 14px 10px;
            display: flex;
            align-items: center;
            gap: 10px;

            background: rgba(248, 250, 249, .95);
            border-bottom: 1px solid rgba(15, 28, 20, .08);

            backdrop-filter: blur(12px);
        }

        .back-arrow {
            width: 18px;
            color: #555e59;
        }

        .chat-avatar {
            position: relative;
            width: 42px;
            height: 42px;
            flex-shrink: 0;

            display: grid;
            place-items: center;

            border-radius: 50%;
            background: #0b100d;
            color: var(--green);

            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            font-size: 14px;

            box-shadow: inset 0 0 0 1px rgba(255,255,255,.1);
        }

        .chat-avatar::after {
            content: "";
            position: absolute;
            right: 0;
            bottom: 1px;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--green-2);
            border: 2px solid white;
        }

        .chat-person {
            flex: 1;
            min-width: 0;
        }

        .chat-person strong {
            display: block;
            font-size: 13px;
            line-height: 1.25;
            white-space: nowrap;
        }

        .chat-person span {
            display: block;
            margin-top: 3px;
            color: #0aab62;
            font-size: 10px;
            font-weight: 700;
        }

        .chat-icons {
            display: flex;
            align-items: center;
            gap: 13px;
            color: #47514b;
        }

        .chat-icons svg {
            width: 17px;
        }

        .chat-body {
            height: calc(100% - 148px);
            overflow: hidden;
            padding: 21px 12px 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .day-label {
            align-self: center;
            padding: 5px 9px;
            border-radius: 6px;
            background: rgba(255,255,255,.68);
            box-shadow: 0 2px 7px rgba(0,0,0,.04);
            color: #68726c;
            font-size: 8px;
            font-weight: 700;
        }

        .chat-bubble {
            position: relative;
            max-width: 82%;
            padding: 9px 11px 7px;
            border-radius: 11px;
            font-size: 11px;
            line-height: 1.5;
            box-shadow: 0 2px 5px rgba(0,0,0,.04);
        }

        .chat-bubble.in {
            align-self: flex-start;
            background: white;
            border-top-left-radius: 3px;
        }

        .chat-bubble.out {
            align-self: flex-end;
            background: #d9fdd3;
            border-top-right-radius: 3px;
        }

        .chat-bubble .time {
            display: inline-flex;
            align-items: center;
            float: right;
            gap: 2px;
            margin-left: 9px;
            margin-top: 4px;
            color: #8b948f;
            font-size: 7px;
        }

        .checkmarks {
            color: #3398e0;
            font-size: 8px;
        }

        .typing-bubble {
            align-self: flex-end;
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 54px;
            min-height: 31px;
            padding: 9px 12px;
            background: #d9fdd3;
            border-radius: 11px 3px 11px 11px;
        }

        .typing-bubble i {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #688173;
            animation: typingDot 1.25s infinite ease-in-out;
        }

        .typing-bubble i:nth-child(2) {
            animation-delay: .14s;
        }

        .typing-bubble i:nth-child(3) {
            animation-delay: .28s;
        }

        @keyframes typingDot {
            0%, 60%, 100% {
                opacity: .45;
                transform: translateY(0);
            }

            30% {
                opacity: 1;
                transform: translateY(-3px);
            }
        }

        .phone-composer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 60px;
            padding: 8px 8px 10px;

            display: flex;
            align-items: center;
            gap: 7px;

            background: rgba(244, 246, 244, .94);
            border-top: 1px solid rgba(20, 31, 24, .06);
        }

        .composer-input {
            flex: 1;
            height: 42px;
            border-radius: 22px;
            background: white;
            padding: 0 13px;

            display: flex;
            align-items: center;
            gap: 9px;

            color: #9ca49f;
            font-size: 9px;

            box-shadow: inset 0 0 0 1px rgba(16,30,21,.05);
        }

        .composer-input svg {
            width: 16px;
            color: #808984;
        }

        .voice-button {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #1dae6f;
            color: white;
        }

        .voice-button svg {
            width: 16px;
        }

        .float-card {
            position: absolute;
            z-index: 7;

            padding: 14px 15px;
            border-radius: 17px;

            background: rgba(255,255,255,.89);
            border: 1px solid rgba(255,255,255,.9);

            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);

            box-shadow:
                0 22px 60px rgba(8, 31, 18, .13),
                inset 0 0 0 1px rgba(8, 25, 15, .05);

            animation: floating 5s ease-in-out infinite;
        }

        @keyframes floating {
            0%, 100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-7px);
            }
        }

        .float-card.status {
            width: 180px;
            right: -15px;
            top: 105px;
        }

        .float-card.lead {
            width: 195px;
            left: -50px;
            bottom: 110px;
            animation-delay: -2s;
        }

        .float-card.follow {
            width: 185px;
            right: -43px;
            bottom: 56px;
            animation-delay: -1s;
        }

        .float-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 800;
        }

        .float-icon {
            width: 28px;
            height: 28px;
            border-radius: 9px;

            display: grid;
            place-items: center;

            background: #e8faef;
            color: #0bae62;
        }

        .float-icon svg {
            width: 14px;
        }

        .float-card p {
            margin-top: 6px;
            padding-left: 36px;
            color: #78827c;
            font-size: 9px;
            line-height: 1.45;
        }

        /* =========================================================
           TRUST BAND
        ========================================================= */

        .trust-section {
            padding: 20px 0 85px;
        }

        .trust-shell {
            position: relative;
            overflow: hidden;
            padding: 22px 26px;
            border: 1px solid rgba(13, 30, 19, .08);
            border-radius: 24px;
            background: rgba(255,255,255,.67);
        }

        .trust-shell::before,
        .trust-shell::after {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            width: 100px;
            z-index: 2;
            pointer-events: none;
        }

        .trust-shell::before {
            left: 0;
            background: linear-gradient(90deg, var(--paper), transparent);
        }

        .trust-shell::after {
            right: 0;
            background: linear-gradient(-90deg, var(--paper), transparent);
        }

        .trust-label {
            margin-bottom: 20px;
            text-align: center;
            color: #818c85;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .trust-track {
            display: flex;
            width: max-content;
            gap: 14px;
            animation: marquee 28s linear infinite;
        }

        @keyframes marquee {
            from {
                transform: translateX(0);
            }

            to {
                transform: translateX(-50%);
            }
        }

        .industry {
            min-width: 170px;
            height: 58px;
            padding: 0 20px;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            border-radius: 16px;
            border: 1px solid rgba(13, 29, 19, .07);
            background: #fbfcfb;

            font-size: 12px;
            font-weight: 700;
            color: #515c55;
        }

        .industry svg {
            width: 17px;
            color: #0ab969;
        }

        /* =========================================================
           MANIFESTO
        ========================================================= */

        .manifesto {
            padding-top: 135px;
            padding-bottom: 120px;
        }

        .manifesto-grid {
            display: grid;
            grid-template-columns: .8fr 1.2fr;
            gap: 80px;
            align-items: start;
        }

        .sticky-copy {
            position: sticky;
            top: 130px;
        }

        .manifesto-big {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .manifesto-line {
            position: relative;
            min-height: 152px;
            display: flex;
            align-items: center;
            padding: 26px 30px;
            border-bottom: 1px solid var(--line);
            overflow: hidden;
        }

        .manifesto-line::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(81,242,155,.12), transparent 70%);
            opacity: 0;
            transition: .4s ease;
        }

        .manifesto-line:hover::before {
            opacity: 1;
        }

        .manifesto-number {
            width: 70px;
            flex-shrink: 0;
            color: #98a29c;
            font-size: 11px;
            font-weight: 800;
        }

        .manifesto-line h3 {
            position: relative;
            font-family: 'Manrope', sans-serif;
            font-size: clamp(28px, 3vw, 44px);
            line-height: 1.05;
            letter-spacing: -2px;
        }

        .manifesto-line h3 span {
            color: #8b958f;
        }

        /* =========================================================
           DARK PRODUCT SECTION
        ========================================================= */

        .product-section {
            position: relative;
            padding: 120px 0;
            overflow: hidden;
            background:
                radial-gradient(circle at 75% 5%, rgba(43, 216, 126, .12), transparent 26%),
                radial-gradient(circle at 20% 70%, rgba(43, 216, 126, .07), transparent 26%),
                var(--black);
            color: white;
        }

        .product-section::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);

            background-size: 58px 58px;
            mask-image: linear-gradient(to bottom, black, transparent 80%);
            pointer-events: none;
        }

        .product-header {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 60px;
            align-items: end;
        }

        .product-section .eyebrow {
            color: var(--green);
        }

        .product-section .section-heading {
            color: white;
        }

        .product-copy {
            max-width: 500px;
            justify-self: end;
            color: #a5b0aa;
            font-size: 17px;
            line-height: 1.75;
        }

        .workspace {
            position: relative;
            z-index: 2;
            margin-top: 58px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 30px;

            background:
                linear-gradient(180deg, #111815, #0b100e);

            box-shadow:
                0 70px 140px rgba(0,0,0,.38),
                inset 0 0 0 1px rgba(255,255,255,.025);
        }

        .workspace-top {
            height: 60px;
            padding: 0 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(255,255,255,.07);
            background: rgba(255,255,255,.015);
        }

        .traffic-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #29312d;
        }

        .workspace-url {
            width: 240px;
            height: 29px;
            margin-left: 12px;
            padding: 0 12px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            background: rgba(255,255,255,.035);
            color: #67736c;
            font-size: 9px;
        }

        .workspace-main {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr) 290px;
            min-height: 600px;
        }

        .inbox-list {
            border-right: 1px solid rgba(255,255,255,.07);
            background: rgba(255,255,255,.01);
        }

        .panel-head {
            height: 62px;
            padding: 0 17px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .panel-head strong {
            font-size: 12px;
        }

        .count-badge {
            min-width: 24px;
            height: 22px;
            padding: 0 7px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: rgba(81,242,155,.12);
            color: var(--green);
            font-size: 8px;
            font-weight: 800;
        }

        .filters {
            display: flex;
            gap: 5px;
            padding: 10px 10px 8px;
            overflow: hidden;
        }

        .filter {
            min-width: max-content;
            height: 27px;
            padding: 0 9px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: rgba(255,255,255,.035);
            color: #748079;
            font-size: 7px;
            font-weight: 700;
        }

        .filter.active {
            background: rgba(81,242,155,.12);
            color: var(--green);
        }

        .contact {
            position: relative;
            display: flex;
            gap: 10px;
            padding: 12px 13px;
            border-bottom: 1px solid rgba(255,255,255,.035);
            transition: .2s ease;
        }

        .contact.active {
            background: rgba(81,242,155,.075);
        }

        .contact-avatar {
            position: relative;
            width: 34px;
            height: 34px;
            flex-shrink: 0;

            display: grid;
            place-items: center;

            border-radius: 11px;
            background: #1a231e;

            color: #aab4ae;
            font-size: 9px;
            font-weight: 800;
        }

        .contact.active .contact-avatar {
            background: #1c5537;
            color: #a9ffd0;
        }

        .contact-data {
            min-width: 0;
            flex: 1;
        }

        .contact-line {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .contact-line strong {
            font-size: 9px;
            white-space: nowrap;
        }

        .contact-line span {
            color: #59655e;
            font-size: 7px;
        }

        .contact-data p {
            margin-top: 4px;
            color: #657169;
            font-size: 7px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .contact-tag {
            display: inline-flex;
            margin-top: 5px;
            padding: 3px 5px;
            border-radius: 5px;
            background: rgba(242, 180, 58, .09);
            color: #d5a544;
            font-size: 6px;
            font-weight: 800;
        }

        .chat-workspace {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .workspace-chat-head {
            height: 62px;
            padding: 0 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .workspace-chat-head .contact-avatar {
            width: 36px;
            height: 36px;
        }

        .workspace-person {
            flex: 1;
        }

        .workspace-person strong {
            display: block;
            font-size: 10px;
        }

        .workspace-person span {
            display: block;
            margin-top: 3px;
            color: #66736c;
            font-size: 7px;
        }

        .ai-toggle {
            height: 30px;
            padding: 0 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            border-radius: 9px;
            background: rgba(81,242,155,.08);
            color: var(--green);
            font-size: 7px;
            font-weight: 800;
        }

        .ai-toggle::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 0 4px rgba(81,242,155,.08);
        }

        .workspace-messages {
            flex: 1;
            padding: 28px 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;

            background:
                radial-gradient(circle at 50% 100%, rgba(81,242,155,.035), transparent 30%);
        }

        .workspace-message {
            max-width: 72%;
            padding: 10px 11px;
            border-radius: 10px;
            font-size: 8px;
            line-height: 1.55;
        }

        .workspace-message.left {
            align-self: flex-start;
            background: rgba(255,255,255,.06);
            color: #ced4d0;
            border-top-left-radius: 3px;
        }

        .workspace-message.right {
            align-self: flex-end;
            background: rgba(81,242,155,.12);
            color: #c7fce0;
            border-top-right-radius: 3px;
        }

        .workspace-message em {
            display: block;
            margin-top: 4px;
            color: #64736a;
            font-style: normal;
            font-size: 6px;
            text-align: right;
        }

        .workspace-compose {
            height: 64px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-top: 1px solid rgba(255,255,255,.06);
        }

        .workspace-input {
            height: 39px;
            flex: 1;
            padding: 0 12px;
            display: flex;
            align-items: center;
            border-radius: 11px;
            background: rgba(255,255,255,.045);
            color: #5f6b64;
            font-size: 7px;
        }

        .workspace-send {
            width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: var(--green-2);
            color: #082016;
        }

        .workspace-send svg {
            width: 13px;
        }

        .crm-panel {
            border-left: 1px solid rgba(255,255,255,.07);
        }

        .crm-body {
            padding: 16px;
        }

        .crm-customer {
            padding: 15px;
            border-radius: 14px;
            background: rgba(255,255,255,.035);
        }

        .crm-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .crm-name .contact-avatar {
            width: 40px;
            height: 40px;
        }

        .crm-name strong {
            display: block;
            font-size: 10px;
        }

        .crm-name span {
            display: block;
            margin-top: 3px;
            color: #66716b;
            font-size: 7px;
        }

        .crm-row {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,.05);
        }

        .crm-row label {
            display: block;
            color: #59665e;
            font-size: 6px;
            text-transform: uppercase;
            letter-spacing: .8px;
        }

        .crm-row strong {
            display: block;
            margin-top: 5px;
            color: #ced5d0;
            font-size: 8px;
        }

        .crm-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 7px;
        }

        .crm-tag {
            padding: 5px 7px;
            border-radius: 6px;
            font-size: 6px;
            font-weight: 800;
        }

        .crm-tag.hot {
            color: #ffbe62;
            background: rgba(255,190,98,.10);
        }

        .crm-tag.new {
            color: #6deaa9;
            background: rgba(81,242,155,.10);
        }

        .takeover {
            margin-top: 14px;
            padding: 13px;
            border-radius: 13px;
            background:
                linear-gradient(145deg, rgba(81,242,155,.08), rgba(81,242,155,.02));
            border: 1px solid rgba(81,242,155,.08);
        }

        .takeover strong {
            font-size: 8px;
        }

        .takeover p {
            margin-top: 5px;
            color: #617068;
            font-size: 6px;
            line-height: 1.5;
        }

        .takeover-button {
            margin-top: 10px;
            height: 30px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: var(--green-2);
            color: #082016;
            font-size: 7px;
            font-weight: 800;
        }

        /* =========================================================
           BENTO
        ========================================================= */

        .bento-section {
            padding: 130px 0;
        }

        .bento-header {
            display: flex;
            justify-content: space-between;
            gap: 70px;
            align-items: end;
            margin-bottom: 56px;
        }

        .bento-header .section-description {
            max-width: 430px;
            margin: 0;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            grid-template-rows: 380px 330px;
            gap: 18px;
        }

        .bento-card {
            position: relative;
            overflow: hidden;
            padding: 32px;
            border-radius: 28px;
            border: 1px solid rgba(12, 30, 19, .075);
            background: white;
            box-shadow: 0 20px 60px rgba(6, 24, 14, .045);
        }

        .bento-card.dark {
            color: white;
            background:
                radial-gradient(circle at 85% 15%, rgba(81,242,155,.13), transparent 26%),
                #0a0f0c;
            border-color: rgba(255,255,255,.06);
        }

        .bento-card.green {
            background:
                linear-gradient(145deg, #d8ffe8, #effff5);
        }

        .bento-card h3 {
            max-width: 480px;
            font-family: 'Manrope', sans-serif;
            font-size: 28px;
            line-height: 1.07;
            letter-spacing: -1.4px;
        }

        .bento-card p {
            max-width: 470px;
            margin-top: 12px;
            color: #6d7871;
            font-size: 14px;
            line-height: 1.65;
        }

        .bento-card.dark p {
            color: #8d9991;
        }

        .mini-label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 14px;
            color: #138d57;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .bento-card.dark .mini-label {
            color: var(--green);
        }

        .mini-label::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .automation-flow {
            position: absolute;
            left: 32px;
            right: 32px;
            bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .flow-node {
            flex: 1;
            min-height: 84px;
            padding: 12px;
            border-radius: 15px;
            background: rgba(255,255,255,.72);
            border: 1px solid rgba(11, 29, 18, .07);
        }

        .flow-node strong {
            display: block;
            font-size: 10px;
        }

        .flow-node span {
            display: block;
            margin-top: 5px;
            color: #768179;
            font-size: 8px;
            line-height: 1.45;
        }

        .flow-arrow {
            width: 22px;
            flex-shrink: 0;
            color: #65aa83;
        }

        .human-demo {
            position: absolute;
            left: 32px;
            right: 32px;
            bottom: 28px;

            display: grid;
            grid-template-columns: 1fr 90px 1fr;
            gap: 12px;
            align-items: center;
        }

        .human-side {
            padding: 14px;
            border-radius: 16px;
            background: rgba(255,255,255,.055);
            border: 1px solid rgba(255,255,255,.06);
        }

        .human-side span {
            display: block;
            color: #69766e;
            font-size: 7px;
        }

        .human-side strong {
            display: block;
            margin-top: 4px;
            font-size: 10px;
        }

        .switcher {
            height: 38px;
            padding: 4px;
            border-radius: 999px;
            background: rgba(255,255,255,.055);
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .switch-knob {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 4px 15px rgba(81,242,155,.25);
        }

        .memory-items {
            position: absolute;
            left: 32px;
            right: 32px;
            bottom: 26px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 9px;
        }

        .memory-item {
            min-height: 57px;
            padding: 11px;
            border-radius: 13px;
            background: rgba(255,255,255,.72);
            border: 1px solid rgba(10, 25, 16, .06);
        }

        .memory-item label {
            display: block;
            color: #789084;
            font-size: 7px;
        }

        .memory-item strong {
            display: block;
            margin-top: 5px;
            font-size: 9px;
        }

        .followup-demo {
            position: absolute;
            left: 32px;
            right: 32px;
            bottom: 28px;
        }

        .timeline {
            position: relative;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 9px;
        }

        .timeline::before {
            content: "";
            position: absolute;
            left: 10%;
            right: 10%;
            top: 17px;
            height: 1px;
            background: rgba(255,255,255,.10);
        }

        .time-step {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .time-dot {
            width: 34px;
            height: 34px;
            margin: 0 auto;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #162019;
            border: 1px solid rgba(255,255,255,.07);
            color: #647068;
            font-size: 8px;
            font-weight: 800;
        }

        .time-step.active .time-dot {
            background: var(--green);
            color: #061a10;
            border-color: transparent;
        }

        .time-step strong {
            display: block;
            margin-top: 9px;
            font-size: 8px;
        }

        .time-step span {
            display: block;
            margin-top: 3px;
            color: #5e6962;
            font-size: 6px;
        }

        /* =========================================================
           STEPS
        ========================================================= */

        .steps-section {
            padding: 130px 0;
            background: white;
        }

        .steps-intro {
            text-align: center;
        }

        .steps-intro .eyebrow {
            justify-content: center;
        }

        .steps-intro .section-heading {
            margin: 0 auto;
            max-width: 850px;
        }

        .steps-intro .section-description {
            margin-left: auto;
            margin-right: auto;
        }

        .steps-grid {
            margin-top: 70px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .step-card {
            position: relative;
            min-height: 420px;
            overflow: hidden;
            padding: 28px;

            border-radius: 28px;
            border: 1px solid rgba(12, 30, 19, .075);

            background:
                linear-gradient(180deg, #fafcfa, #f3f7f4);
        }

        .step-number {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;

            border-radius: 14px;
            background: var(--black);
            color: var(--green);

            font-family: 'Manrope', sans-serif;
            font-size: 12px;
            font-weight: 800;
        }

        .step-card h3 {
            margin-top: 28px;
            font-family: 'Manrope', sans-serif;
            font-size: 25px;
            line-height: 1.1;
            letter-spacing: -1.2px;
        }

        .step-card p {
            margin-top: 12px;
            color: #69746d;
            font-size: 14px;
            line-height: 1.65;
        }

        .step-visual {
            position: absolute;
            left: 28px;
            right: 28px;
            bottom: 25px;
            height: 140px;
        }

        .qr-shell {
            height: 100%;
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 18px;
            border-radius: 19px;
            background: white;
            border: 1px solid rgba(10, 28, 17, .07);
        }

        .qr-code {
            width: 86px;
            height: 86px;
            flex-shrink: 0;
            padding: 8px;
            border-radius: 13px;
            background:
                repeating-linear-gradient(
                    45deg,
                    #0a110d 0 4px,
                    white 4px 8px
                );
            box-shadow: inset 0 0 0 7px white;
        }

        .qr-copy strong {
            display: block;
            font-size: 11px;
        }

        .qr-copy span {
            display: block;
            margin-top: 5px;
            color: #7e8982;
            font-size: 8px;
            line-height: 1.45;
        }

        .knowledge-list {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .knowledge {
            height: 37px;
            padding: 0 11px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 11px;
            background: white;
            border: 1px solid rgba(10, 28, 17, .06);
            font-size: 8px;
            font-weight: 700;
        }

        .knowledge-check {
            width: 18px;
            height: 18px;
            display: grid;
            place-items: center;
            border-radius: 6px;
            background: #e7f9ee;
            color: #0ab868;
        }

        .knowledge-check svg {
            width: 10px;
        }

        .go-live {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
            border-radius: 19px;
            background: #0b100d;
            color: white;
        }

        .live-line {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .live-orb {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: rgba(81,242,155,.12);
        }

        .live-orb::after {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 18px var(--green);
        }

        .live-line strong {
            font-size: 11px;
        }

        .go-live p {
            margin-top: 12px;
            color: #76837b;
            font-size: 8px;
        }

        /* =========================================================
           USE CASES
        ========================================================= */

        .usecases-section {
            padding: 130px 0;
        }

        .usecases-shell {
            overflow: hidden;
            border-radius: 38px;
            padding: 65px;
            background:
                radial-gradient(circle at 100% 0%, rgba(81,242,155,.14), transparent 30%),
                var(--black);
            color: white;
        }

        .usecases-top {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 55px;
            align-items: end;
        }

        .usecases-shell .eyebrow {
            color: var(--green);
        }

        .usecases-description {
            max-width: 480px;
            justify-self: end;
            color: #96a29b;
            font-size: 16px;
            line-height: 1.7;
        }

        .usecase-grid {
            margin-top: 55px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .usecase {
            min-height: 220px;
            padding: 21px;
            border-radius: 20px;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.055);
            transition: .25s ease;
        }

        .usecase:hover {
            background: rgba(81,242,155,.06);
            transform: translateY(-4px);
        }

        .usecase-icon {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            background: rgba(81,242,155,.09);
            color: var(--green);
        }

        .usecase-icon svg {
            width: 19px;
        }

        .usecase h3 {
            margin-top: 35px;
            font-family: 'Manrope', sans-serif;
            font-size: 18px;
            letter-spacing: -.6px;
        }

        .usecase p {
            margin-top: 9px;
            color: #79867e;
            font-size: 11px;
            line-height: 1.65;
        }

        /* =========================================================
           STATS
        ========================================================= */

        .numbers {
            padding: 115px 0;
        }

        .numbers-grid {
            display: grid;
            grid-template-columns: 1.2fr repeat(3, .8fr);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .number-intro,
        .stat {
            padding: 42px 30px;
            border-right: 1px solid var(--line);
        }

        .stat:last-child {
            border-right: 0;
        }

        .number-intro {
            padding-left: 0;
        }

        .number-intro strong {
            display: block;
            max-width: 280px;
            font-family: 'Manrope', sans-serif;
            font-size: 24px;
            line-height: 1.15;
            letter-spacing: -1px;
        }

        .number-intro span {
            display: block;
            margin-top: 10px;
            color: #808b84;
            font-size: 12px;
        }

        .stat strong {
            display: block;
            font-family: 'Manrope', sans-serif;
            font-size: 46px;
            line-height: 1;
            letter-spacing: -2.5px;
        }

        .stat span {
            display: block;
            margin-top: 10px;
            color: #7e8982;
            font-size: 11px;
            line-height: 1.4;
        }

        /* =========================================================
           FAQ
        ========================================================= */

        .faq-section {
            padding: 110px 0 135px;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: .75fr 1.25fr;
            gap: 80px;
            align-items: start;
        }

        .faq-copy {
            position: sticky;
            top: 130px;
        }

        .faq-copy .section-heading {
            font-size: clamp(40px, 4vw, 58px);
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
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--text);

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;

            text-align: left;
            cursor: pointer;
        }

        .faq-question span:first-child {
            font-family: 'Manrope', sans-serif;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -.5px;
        }

        .faq-plus {
            position: relative;
            width: 32px;
            height: 32px;
            flex-shrink: 0;
            border-radius: 10px;
            background: #e9eeea;
        }

        .faq-plus::before,
        .faq-plus::after {
            content: "";
            position: absolute;
            width: 12px;
            height: 2px;
            left: 10px;
            top: 15px;
            border-radius: 2px;
            background: #49534d;
            transition: .25s ease;
        }

        .faq-plus::after {
            transform: rotate(90deg);
        }

        .faq-item.open .faq-plus::after {
            transform: rotate(0);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height .35s ease;
        }

        .faq-answer-inner {
            max-width: 700px;
            padding: 0 50px 26px 0;
            color: #68736c;
            font-size: 14px;
            line-height: 1.7;
        }

        /* =========================================================
           CTA
        ========================================================= */

        .final-cta {
            padding: 30px 0 70px;
        }

        .cta-shell {
            position: relative;
            overflow: hidden;
            min-height: 580px;
            padding: 75px 60px;

            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;

            border-radius: 42px;
            text-align: center;
            color: white;

            background:
                radial-gradient(circle at 50% 100%, rgba(81,242,155,.30), transparent 34%),
                radial-gradient(circle at 0% 0%, rgba(81,242,155,.11), transparent 25%),
                #080c0a;
        }

        .cta-shell::before {
            content: "";
            position: absolute;
            width: 720px;
            height: 720px;
            left: 50%;
            top: 74%;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            border: 1px solid rgba(81,242,155,.12);
            box-shadow:
                0 0 0 70px rgba(81,242,155,.025),
                0 0 0 150px rgba(81,242,155,.018);
        }

        .cta-content {
            position: relative;
            z-index: 2;
        }

        .cta-mini {
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

        .cta-mini::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 18px rgba(81,242,155,.7);
        }

        .cta-shell h2 {
            max-width: 920px;
            font-family: 'Manrope', sans-serif;
            font-size: clamp(50px, 7vw, 92px);
            line-height: .92;
            letter-spacing: -5.5px;
            font-weight: 800;
        }

        .cta-shell h2 span {
            color: var(--green);
        }

        .cta-shell p {
            max-width: 590px;
            margin: 25px auto 0;
            color: #95a199;
            font-size: 16px;
            line-height: 1.7;
        }

        .cta-buttons {
            margin-top: 32px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .cta-shell .button-primary {
            background: var(--green);
            color: #07150d;
            box-shadow: 0 18px 45px rgba(81,242,155,.17);
        }

        .cta-shell .button-primary .arrow {
            background: rgba(7, 20, 12, .10);
        }

        .cta-shell .button-secondary {
            border-color: rgba(255,255,255,.10);
            background: rgba(255,255,255,.05);
            color: white;
        }

        .cta-shell .button-secondary:hover {
            background: rgba(255,255,255,.09);
        }

        /* =========================================================
           FOOTER
        ========================================================= */

        footer {
            padding: 0 0 35px;
        }

        .footer-top {
            display: grid;
            grid-template-columns: 1.4fr .6fr .6fr .6fr;
            gap: 60px;
            padding: 55px 0 50px;
            border-bottom: 1px solid var(--line);
        }

        .footer-brand p {
            max-width: 340px;
            margin-top: 17px;
            color: #758079;
            font-size: 13px;
            line-height: 1.7;
        }

        .footer-column strong {
            display: block;
            margin-bottom: 16px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .footer-column a {
            display: block;
            width: fit-content;
            margin-top: 10px;
            color: #717c75;
            font-size: 12px;
            transition: .2s ease;
        }

        .footer-column a:hover {
            color: #0d995a;
        }

        .footer-bottom {
            padding-top: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            color: #8a948e;
            font-size: 11px;
        }

        .made-by {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .made-by strong {
            color: #49534d;
        }

        /* =========================================================
           REVEAL
        ========================================================= */

        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition:
                opacity .7s ease,
                transform .7s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 1080px) {
            .nav-links {
                display: none;
            }

            .mobile-menu-button {
                display: flex;
            }

            .hero-grid {
                grid-template-columns: 1fr;
            }

            .hero-copy {
                text-align: center;
            }

            .hero-description {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-actions {
                justify-content: center;
            }

            .hero-meta {
                justify-content: center;
            }

            .hero-visual {
                margin-top: 20px;
            }

            .manifesto-grid {
                grid-template-columns: 1fr;
                gap: 35px;
            }

            .sticky-copy,
            .faq-copy {
                position: static;
            }

            .product-header,
            .usecases-top {
                grid-template-columns: 1fr;
            }

            .product-copy,
            .usecases-description {
                justify-self: start;
            }

            .workspace-main {
                grid-template-columns: 200px 1fr;
            }

            .crm-panel {
                display: none;
            }

            .bento-grid {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(4, 360px);
            }

            .steps-grid {
                grid-template-columns: 1fr;
            }

            .step-card {
                min-height: 360px;
            }

            .usecase-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .numbers-grid {
                grid-template-columns: 1fr 1fr;
            }

            .number-intro,
            .stat {
                border-bottom: 1px solid var(--line);
            }

            .stat:nth-child(2) {
                border-right: 0;
            }

            .faq-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .footer-top {
                grid-template-columns: 1fr 1fr;
            }

            .mobile-nav {
                position: fixed;
                z-index: 998;
                left: 20px;
                right: 20px;
                top: 88px;

                display: block;
                padding: 13px;

                border-radius: 20px;
                background: rgba(250,252,250,.97);

                border: 1px solid rgba(255,255,255,.9);
                box-shadow: 0 25px 70px rgba(8, 24, 15, .15);

                backdrop-filter: blur(20px);

                opacity: 0;
                visibility: hidden;
                transform: translateY(-12px);
                transition: .25s ease;
            }

            body.menu-open .mobile-nav {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            .mobile-nav a {
                min-height: 49px;
                padding: 0 13px;
                display: flex;
                align-items: center;
                border-radius: 12px;
                font-size: 14px;
                font-weight: 700;
                color: #3e4942;
            }

            .mobile-nav a:hover {
                background: #eef3ef;
            }

            .mobile-nav-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 7px;
                margin-top: 7px;
                padding-top: 12px;
                border-top: 1px solid var(--line);
            }

            .mobile-nav-actions a {
                justify-content: center;
            }

            .mobile-nav-actions .primary {
                background: var(--black);
                color: white;
            }
        }

        @media (max-width: 720px) {
            .container {
                width: min(100% - 24px, var(--container));
            }

            .nav-space {
                height: 78px;
            }

            .nav-shell {
                padding-top: 9px;
            }

            .navbar {
                min-height: 58px;
                padding: 7px 7px 7px 12px;
                border-radius: 17px;
            }

            .brand-symbol {
                width: 36px;
                height: 36px;
                border-radius: 11px;
            }

            .brand-text {
                font-size: 19px;
            }

            .brand-text small {
                display: none;
            }

            .nav-actions {
                display: none;
            }

            .mobile-menu-button {
                width: 42px;
                height: 42px;
                border-radius: 11px;
            }

            .mobile-nav {
                left: 12px;
                right: 12px;
                top: 76px;
            }

            .hero {
                padding: 50px 0 45px;
            }

            .hero::before {
                width: 420px;
                height: 420px;
                top: -140px;
                right: -240px;
            }

            .hero-badge {
                min-height: 35px;
                font-size: 10px;
                margin-bottom: 21px;
            }

            .hero h1 {
                font-size: clamp(49px, 15vw, 68px);
                letter-spacing: -4px;
            }

            .hero h1 .green {
                white-space: normal;
            }

            .hero-description {
                max-width: 520px;
                margin-top: 23px;
                font-size: 15px;
                line-height: 1.67;
            }

            .hero-actions {
                flex-direction: column;
                width: 100%;
                margin-top: 28px;
            }

            .button-primary,
            .button-secondary {
                width: 100%;
                min-height: 57px;
            }

            .hero-meta {
                flex-wrap: wrap;
                gap: 8px 13px;
                font-size: 10px;
            }

            .hero-visual {
                min-height: 570px;
                margin-top: 38px;
            }

            .visual-orbit {
                width: 400px;
                height: 400px;
            }

            .phone {
                width: 284px;
                height: 564px;
                border-radius: 45px;
            }

            .phone-screen {
                border-radius: 37px;
            }

            .phone-header {
                height: 79px;
                padding-top: 25px;
            }

            .chat-body {
                height: calc(100% - 137px);
                padding-top: 17px;
            }

            .chat-bubble {
                font-size: 9.5px;
            }

            .float-card {
                padding: 11px;
                border-radius: 14px;
            }

            .float-card.status {
                width: 142px;
                right: -5px;
                top: 82px;
            }

            .float-card.lead {
                width: 154px;
                left: -5px;
                bottom: 85px;
            }

            .float-card.follow {
                display: none;
            }

            .float-icon {
                width: 24px;
                height: 24px;
            }

            .float-title {
                font-size: 9px;
            }

            .float-card p {
                padding-left: 32px;
                font-size: 7px;
            }

            .trust-section {
                padding-top: 0;
                padding-bottom: 55px;
            }

            .trust-shell {
                padding: 18px 0;
            }

            .industry {
                min-width: 145px;
                height: 51px;
                font-size: 10px;
            }

            .section {
                padding: 85px 0;
            }

            .section-heading {
                font-size: 43px;
                line-height: 1;
                letter-spacing: -2.8px;
            }

            .section-description {
                margin-top: 18px;
                font-size: 15px;
                line-height: 1.65;
            }

            .manifesto {
                padding-top: 85px;
                padding-bottom: 80px;
            }

            .manifesto-line {
                min-height: 122px;
                padding: 20px 5px;
            }

            .manifesto-number {
                width: 45px;
            }

            .manifesto-line h3 {
                font-size: 30px;
                letter-spacing: -1.4px;
            }

            .product-section {
                padding: 85px 0;
            }

            .workspace {
                margin-top: 38px;
                border-radius: 20px;
            }

            .workspace-top {
                height: 44px;
            }

            .workspace-url {
                width: 160px;
            }

            .workspace-main {
                grid-template-columns: 110px 1fr;
                min-height: 420px;
            }

            .panel-head,
            .workspace-chat-head {
                height: 49px;
                padding: 0 9px;
            }

            .filters {
                padding-left: 6px;
                padding-right: 6px;
            }

            .filter {
                height: 23px;
                padding: 0 6px;
                font-size: 5px;
            }

            .contact {
                padding: 9px 7px;
                gap: 6px;
            }

            .contact-avatar {
                display: none;
            }

            .contact-line strong {
                font-size: 7px;
            }

            .contact-data p {
                font-size: 5px;
            }

            .contact-tag {
                font-size: 5px;
            }

            .workspace-person strong {
                font-size: 8px;
            }

            .workspace-person span {
                font-size: 5px;
            }

            .ai-toggle {
                height: 25px;
                padding: 0 7px;
                font-size: 5px;
            }

            .workspace-messages {
                padding: 17px 10px;
            }

            .workspace-message {
                max-width: 88%;
                font-size: 6.5px;
            }

            .workspace-compose {
                height: 50px;
                padding: 7px;
            }

            .workspace-input {
                height: 33px;
            }

            .workspace-send {
                width: 33px;
                height: 33px;
            }

            .bento-section {
                padding: 85px 0;
            }

            .bento-header {
                display: block;
                margin-bottom: 38px;
            }

            .bento-header .section-description {
                margin-top: 18px;
            }

            .bento-grid {
                grid-template-rows: 390px 370px 380px 390px;
            }

            .bento-card {
                padding: 24px;
                border-radius: 23px;
            }

            .bento-card h3 {
                font-size: 24px;
            }

            .automation-flow {
                left: 24px;
                right: 24px;
                bottom: 24px;
                display: grid;
                grid-template-columns: 1fr;
            }

            .flow-arrow {
                display: none;
            }

            .flow-node {
                min-height: 57px;
            }

            .human-demo {
                left: 24px;
                right: 24px;
                grid-template-columns: 1fr;
            }

            .switcher {
                width: 70px;
                justify-self: center;
            }

            .memory-items {
                left: 24px;
                right: 24px;
            }

            .followup-demo {
                left: 24px;
                right: 24px;
            }

            .timeline {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px 8px;
            }

            .timeline::before {
                display: none;
            }

            .steps-section {
                padding: 85px 0;
            }

            .steps-grid {
                margin-top: 45px;
            }

            .step-card {
                min-height: 385px;
                border-radius: 23px;
                padding: 24px;
            }

            .step-visual {
                left: 24px;
                right: 24px;
            }

            .usecases-section {
                padding: 85px 0;
            }

            .usecases-shell {
                padding: 39px 22px;
                border-radius: 28px;
            }

            .usecase-grid {
                grid-template-columns: 1fr;
                margin-top: 38px;
            }

            .usecase {
                min-height: 180px;
            }

            .usecase h3 {
                margin-top: 27px;
            }

            .numbers {
                padding: 80px 0;
            }

            .numbers-grid {
                grid-template-columns: 1fr;
            }

            .number-intro,
            .stat {
                padding: 28px 5px;
                border-right: 0;
            }

            .stat {
                display: grid;
                grid-template-columns: 130px 1fr;
                align-items: center;
                gap: 20px;
            }

            .stat strong {
                font-size: 39px;
            }

            .stat span {
                margin-top: 0;
            }

            .faq-section {
                padding: 80px 0 100px;
            }

            .faq-question {
                min-height: 74px;
            }

            .faq-question span:first-child {
                font-size: 15px;
            }

            .faq-answer-inner {
                padding-right: 15px;
                font-size: 13px;
            }

            .final-cta {
                padding-bottom: 40px;
            }

            .cta-shell {
                min-height: 520px;
                padding: 55px 20px;
                border-radius: 30px;
            }

            .cta-shell h2 {
                font-size: 53px;
                letter-spacing: -3.5px;
            }

            .cta-shell p {
                font-size: 14px;
            }

            .cta-buttons {
                flex-direction: column;
                width: 100%;
            }

            .footer-top {
                grid-template-columns: 1fr;
                gap: 35px;
            }

            .footer-bottom {
                flex-direction: column;
                align-items: flex-start;
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
    </style>
</head>

<body>

<div class="nav-space"></div>

<header class="nav-shell" id="navbar">
    <div class="container">
        <nav class="navbar">

            <a href="/" class="brand">
                <span class="brand-symbol"></span>

                <span class="brand-text">
                    WAI
                    <small>WhatsApp Intelligence</small>
                </span>
            </a>

            <div class="nav-links">
                <a href="#urun">Ürün</a>
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

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"></path>
                        <path d="m13 6 6 6-6 6"></path>
                    </svg>
                </a>
            </div>

            <button
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Menüyü aç"
            >
                <span></span>
            </button>

        </nav>
    </div>
</header>

<div class="mobile-nav" id="mobileNav">
    <a href="#urun">Ürün</a>
    <a href="#ozellikler">Özellikler</a>
    <a href="#nasil-calisir">Nasıl Çalışır?</a>
    <a href="#sektorler">Sektörler</a>
    <a href="#sss">SSS</a>

    <div class="mobile-nav-actions">
        <a href="/admin/login">Giriş Yap</a>
        <a href="/admin/register" class="primary">Ücretsiz Başla</a>
    </div>
</div>

<main>

    {{-- HERO --}}
    <section class="hero">
        <div class="container">

            <div class="hero-grid">

                <div class="hero-copy">

                    <div class="hero-badge">
                        <span class="pulse"></span>
                        WhatsApp satışlarınız artık yapay zekâ ile çalışıyor
                    </div>

                    <h1>
                        Mesajı müşteriniz atar.
                        <span class="soft">Satışı</span>
                        <span class="green">WAI yönetir.</span>
                    </h1>

                    <p class="hero-description">
                        WAI; WhatsApp mesajlarınıza 7/24 cevap verir, işletmenizi öğrenir,
                        müşterilerinizi tanır, satış fırsatlarını takip eder ve gerektiğinde
                        görüşmeyi ekibinize devreder.
                    </p>

                    <div class="hero-actions">

                        <a href="/admin/register" class="button-primary">
                            WAI'yi Ücretsiz Deneyin

                            <span class="arrow">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14"></path>
                                    <path d="m13 6 6 6-6 6"></path>
                                </svg>
                            </span>
                        </a>

                        <a href="#urun" class="button-secondary">
                            Ürünü Keşfet
                        </a>

                    </div>

                    <div class="hero-meta">

                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m5 12 4 4L19 6"></path>
                            </svg>
                            Kredi kartı gerekmez
                        </span>

                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m5 12 4 4L19 6"></path>
                            </svg>
                            Dakikalar içinde kurulum
                        </span>

                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m5 12 4 4L19 6"></path>
                            </svg>
                            AI + insan kontrolü
                        </span>

                    </div>

                </div>

                <div class="hero-visual">

                    <div class="visual-orbit"></div>

                    <div class="phone">

                        <span class="phone-side-button one"></span>
                        <span class="phone-side-button two"></span>
                        <span class="phone-side-button three"></span>

                        <div class="phone-screen">

                            <div class="dynamic-island"></div>

                            <div class="phone-header">

                                <svg class="back-arrow"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <path d="m15 18-6-6 6-6"></path>
                                </svg>

                                <div class="chat-avatar">
                                    W
                                </div>

                                <div class="chat-person">
                                    <strong>WAI Satış Asistanı</strong>
                                    <span>çevrimiçi</span>
                                </div>

                                <div class="chat-icons">

                                    <svg viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="1.8"
                                         stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <path d="m22 8-6 4 6 4V8Z"></path>
                                        <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                                    </svg>

                                    <svg viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="1.8"
                                         stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"></path>
                                    </svg>

                                </div>

                            </div>

                            <div class="chat-body">

                                <div class="day-label">
                                    BUGÜN
                                </div>

                                <div class="chat-bubble in">
                                    Merhaba, zeytinyağlarınız hakkında bilgi almak istiyorum.
                                    <span class="time">14:21</span>
                                </div>

                                <div class="chat-bubble out">
                                    Merhaba 👋 Memnuniyetle yardımcı olayım.
                                    Hangi boy zeytinyağımızla ilgileniyorsunuz?
                                    <span class="time">
                                        14:21
                                        <span class="checkmarks">✓✓</span>
                                    </span>
                                </div>

                                <div class="chat-bubble in">
                                    5 litrelik almak istiyorum. Kargo ücretsiz mi?
                                    <span class="time">14:22</span>
                                </div>

                                <div class="chat-bubble out">
                                    Evet 🌿 5 litre ve üzeri zeytinyağı siparişlerinde
                                    kargo ücretsizdir. İsterseniz siparişinizi birlikte oluşturalım.
                                    <span class="time">
                                        14:22
                                        <span class="checkmarks">✓✓</span>
                                    </span>
                                </div>

                                <div class="chat-bubble in">
                                    Olur.
                                    <span class="time">14:23</span>
                                </div>

                                <div class="typing-bubble">
                                    <i></i>
                                    <i></i>
                                    <i></i>
                                </div>

                            </div>

                            <div class="phone-composer">

                                <div class="composer-input">

                                    <svg viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="1.8"
                                         stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                        <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                        <line x1="15" y1="9" x2="15.01" y2="9"></line>
                                    </svg>

                                    Mesaj

                                </div>

                                <div class="voice-button">

                                    <svg viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2"
                                         stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <rect x="9" y="2" width="6" height="12" rx="3"></rect>
                                        <path d="M5 10a7 7 0 0 0 14 0"></path>
                                        <line x1="12" y1="19" x2="12" y2="22"></line>
                                    </svg>

                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="float-card status">

                        <div class="float-title">

                            <div class="float-icon">
                                <svg viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <path d="M12 2v4"></path>
                                    <path d="m16.2 7.8 2.9-2.9"></path>
                                    <path d="M18 12h4"></path>
                                    <path d="m16.2 16.2 2.9 2.9"></path>
                                    <path d="M12 18v4"></path>
                                    <path d="m4.9 19.1 2.9-2.9"></path>
                                    <path d="M2 12h4"></path>
                                    <path d="m4.9 4.9 2.9 2.9"></path>
                                </svg>
                            </div>

                            AI aktif

                        </div>

                        <p>
                            WAI müşterinizle görüşüyor.
                        </p>

                    </div>

                    <div class="float-card lead">

                        <div class="float-title">

                            <div class="float-icon">
                                <svg viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <path d="M20 7h-9"></path>
                                    <path d="M14 17H5"></path>
                                    <circle cx="17" cy="17" r="3"></circle>
                                    <circle cx="7" cy="7" r="3"></circle>
                                </svg>
                            </div>

                            Sıcak müşteri

                        </div>

                        <p>
                            CRM etiketi otomatik güncellendi.
                        </p>

                    </div>

                    <div class="float-card follow">

                        <div class="float-title">

                            <div class="float-icon">
                                <svg viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M12 7v5l3 2"></path>
                                </svg>
                            </div>

                            Takip planlandı

                        </div>

                        <p>
                            Yanıt gelmezse otomatik hatırlatma.
                        </p>

                    </div>

                </div>

            </div>

        </div>
    </section>

    {{-- INDUSTRIES --}}
    <section class="trust-section">
        <div class="container">

            <div class="trust-shell">

                <div class="trust-label">
                    WAI farklı iş modellerine uyum sağlar
                </div>

                <div class="trust-track">

                    @for ($i = 0; $i < 2; $i++)

                        <div class="industry">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M3 9l9-6 9 6v11H3z"></path>
                                <path d="M9 20v-6h6v6"></path>
                            </svg>
                            E-Ticaret
                        </div>

                        <div class="industry">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M4 21V10l8-6 8 6v11"></path>
                                <path d="M9 21v-6h6v6"></path>
                            </svg>
                            Emlak
                        </div>

                        <div class="industry">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M3 12h18"></path>
                                <path d="M12 3v18"></path>
                            </svg>
                            Klinikler
                        </div>

                        <div class="industry">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M8 12h8"></path>
                            </svg>
                            Finans
                        </div>

                        <div class="industry">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M3 21h18"></path>
                                <path d="M5 21V8l7-5 7 5v13"></path>
                            </svg>
                            Otomotiv
                        </div>

                        <div class="industry">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M4 19h16"></path>
                                <path d="M6 16h12"></path>
                                <path d="M8 13h8"></path>
                            </svg>
                            Turizm
                        </div>

                        <div class="industry">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M4 4h16v16H4z"></path>
                                <path d="M8 8h8v8H8z"></path>
                            </svg>
                            Ajanslar
                        </div>

                    @endfor

                </div>

            </div>

        </div>
    </section>

    {{-- MANIFESTO --}}
    <section class="manifesto section" id="ozellikler">
        <div class="container">

            <div class="manifesto-grid">

                <div class="sticky-copy reveal">

                    <div class="eyebrow">
                        WAI mantığı
                    </div>

                    <h2 class="section-heading">
                        Bot değil.
                        Dijital satış çalışanı.
                    </h2>

                    <p class="section-description">
                        WAI yalnızca sorulara cevap vermek için değil,
                        müşteri görüşmesini baştan sona yönetebilmek için tasarlandı.
                    </p>

                </div>

                <div class="manifesto-big">

                    <div class="manifesto-line reveal">
                        <div class="manifesto-number">01</div>

                        <h3>
                            İşletmenizi
                            <span>öğrenir.</span>
                        </h3>
                    </div>

                    <div class="manifesto-line reveal">
                        <div class="manifesto-number">02</div>

                        <h3>
                            Müşterinizi
                            <span>hatırlar.</span>
                        </h3>
                    </div>

                    <div class="manifesto-line reveal">
                        <div class="manifesto-number">03</div>

                        <h3>
                            Satış fırsatını
                            <span>takip eder.</span>
                        </h3>
                    </div>

                    <div class="manifesto-line reveal">
                        <div class="manifesto-number">04</div>

                        <h3>
                            Gerektiğinde
                            <span>insana devreder.</span>
                        </h3>
                    </div>

                </div>

            </div>

        </div>
    </section>

    {{-- PRODUCT --}}
    <section class="product-section" id="urun">
        <div class="container">

            <div class="product-header reveal">

                <div>

                    <div class="eyebrow">
                        WAI Kontrol Merkezi
                    </div>

                    <h2 class="section-heading">
                        WhatsApp operasyonunuz.
                        Tek bir merkezde.
                    </h2>

                </div>

                <p class="product-copy">
                    Gelen mesajları görün, yapay zekânın görüşmelerini izleyin,
                    müşterileri etiketleyin, sıcak lead'leri ayırın ve istediğiniz
                    konuşmayı anında insan kontrolüne alın.
                </p>

            </div>

            <div class="workspace reveal">

                <div class="workspace-top">
                    <span class="traffic-dot"></span>
                    <span class="traffic-dot"></span>
                    <span class="traffic-dot"></span>

                    <div class="workspace-url">
                        wai.asilkansoft.com.tr/admin/gelen-kutusu
                    </div>
                </div>

                <div class="workspace-main">

                    <aside class="inbox-list">

                        <div class="panel-head">
                            <strong>Gelen Kutusu</strong>
                            <span class="count-badge">12</span>
                        </div>

                        <div class="filters">
                            <span class="filter active">Tümü</span>
                            <span class="filter">Okunmamış</span>
                            <span class="filter">İnsan</span>
                        </div>

                        <div class="contact active">

                            <div class="contact-avatar">
                                AY
                            </div>

                            <div class="contact-data">

                                <div class="contact-line">
                                    <strong>Ahmet Yılmaz</strong>
                                    <span>14:23</span>
                                </div>

                                <p>
                                    5 litrelik almak istiyorum.
                                </p>

                                <span class="contact-tag">
                                    SICAK MÜŞTERİ
                                </span>

                            </div>

                        </div>

                        <div class="contact">

                            <div class="contact-avatar">
                                EK
                            </div>

                            <div class="contact-data">

                                <div class="contact-line">
                                    <strong>Elif Kaya</strong>
                                    <span>13:51</span>
                                </div>

                                <p>
                                    Fiyat bilgisi alabilir miyim?
                                </p>

                                <span class="contact-tag">
                                    TEKLİF
                                </span>

                            </div>

                        </div>

                        <div class="contact">

                            <div class="contact-avatar">
                                MD
                            </div>

                            <div class="contact-data">

                                <div class="contact-line">
                                    <strong>Mehmet Demir</strong>
                                    <span>12:18</span>
                                </div>

                                <p>
                                    Kargo kaç günde gelir?
                                </p>

                            </div>

                        </div>

                        <div class="contact">

                            <div class="contact-avatar">
                                SA
                            </div>

                            <div class="contact-data">

                                <div class="contact-line">
                                    <strong>Selin Aydın</strong>
                                    <span>11:42</span>
                                </div>

                                <p>
                                    Sipariş vermek istiyorum.
                                </p>

                            </div>

                        </div>

                        <div class="contact">

                            <div class="contact-avatar">
                                BY
                            </div>

                            <div class="contact-data">

                                <div class="contact-line">
                                    <strong>Burak Yalçın</strong>
                                    <span>10:15</span>
                                </div>

                                <p>
                                    Ürünleriniz nelerdir?
                                </p>

                            </div>

                        </div>

                    </aside>

                    <div class="chat-workspace">

                        <div class="workspace-chat-head">

                            <div class="contact-avatar">
                                AY
                            </div>

                            <div class="workspace-person">
                                <strong>Ahmet Yılmaz</strong>
                                <span>+90 5•• ••• •• 21</span>
                            </div>

                            <div class="ai-toggle">
                                AI AKTİF
                            </div>

                        </div>

                        <div class="workspace-messages">

                            <div class="workspace-message left">
                                Merhaba, zeytinyağlarınız hakkında bilgi
                                almak istiyorum.
                                <em>14:21</em>
                            </div>

                            <div class="workspace-message right">
                                Merhaba 👋 Memnuniyetle yardımcı olayım.
                                Hangi boy zeytinyağımızla ilgileniyorsunuz?
                                <em>14:21 ✓✓</em>
                            </div>

                            <div class="workspace-message left">
                                5 litrelik almak istiyorum. Kargo ücretsiz mi?
                                <em>14:22</em>
                            </div>

                            <div class="workspace-message right">
                                Evet 🌿 5 litre ve üzeri siparişlerde
                                kargo ücretsizdir. İsterseniz siparişinizi
                                birlikte oluşturabiliriz.
                                <em>14:22 ✓✓</em>
                            </div>

                            <div class="workspace-message left">
                                Olur, sipariş vereyim.
                                <em>14:23</em>
                            </div>

                        </div>

                        <div class="workspace-compose">

                            <div class="workspace-input">
                                Mesaj yazın...
                            </div>

                            <div class="workspace-send">

                                <svg viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2">
                                    <path d="m22 2-7 20-4-9-9-4Z"></path>
                                    <path d="M22 2 11 13"></path>
                                </svg>

                            </div>

                        </div>

                    </div>

                    <aside class="crm-panel">

                        <div class="panel-head">
                            <strong>Müşteri</strong>
                        </div>

                        <div class="crm-body">

                            <div class="crm-customer">

                                <div class="crm-name">

                                    <div class="contact-avatar">
                                        AY
                                    </div>

                                    <div>
                                        <strong>Ahmet Yılmaz</strong>
                                        <span>Yeni müşteri</span>
                                    </div>

                                </div>

                                <div class="crm-row">
                                    <label>Durum</label>

                                    <div class="crm-tags">
                                        <span class="crm-tag hot">
                                            🔥 Sıcak Müşteri
                                        </span>

                                        <span class="crm-tag new">
                                            Yeni
                                        </span>
                                    </div>
                                </div>

                                <div class="crm-row">
                                    <label>İlgilendiği ürün</label>
                                    <strong>5 L Zeytinyağı</strong>
                                </div>

                                <div class="crm-row">
                                    <label>Son görüşme</label>
                                    <strong>Az önce</strong>
                                </div>

                            </div>

                            <div class="takeover">

                                <strong>İnsan kontrolü</strong>

                                <p>
                                    İsterseniz görüşmeyi AI'dan devralabilirsiniz.
                                </p>

                                <div class="takeover-button">
                                    Görüşmeyi Devral
                                </div>

                            </div>

                        </div>

                    </aside>

                </div>

            </div>

        </div>
    </section>

    {{-- BENTO --}}
    <section class="bento-section" id="ozellikler-detay">
        <div class="container">

            <div class="bento-header reveal">

                <div>

                    <div class="eyebrow">
                        Daha fazlası
                    </div>

                    <h2 class="section-heading">
                        Satışı konuşmanın ötesine taşıyın.
                    </h2>

                </div>

                <p class="section-description">
                    WAI, müşteriye yalnızca cevap vermek yerine satış
                    operasyonunuzun parçalarını birbirine bağlar.
                </p>

            </div>

            <div class="bento-grid">

                <article class="bento-card green reveal">

                    <div class="mini-label">
                        Otomasyon
                    </div>

                    <h3>
                        Mesaj geldiği anda satış akışı başlasın.
                    </h3>

                    <p>
                        Müşteriyi karşılayın, ihtiyacını anlayın ve doğru satış akışına yönlendirin.
                    </p>

                    <div class="automation-flow">

                        <div class="flow-node">
                            <strong>Yeni mesaj</strong>
                            <span>Müşteri WhatsApp'tan yazdı.</span>
                        </div>

                        <svg class="flow-arrow"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.7">
                            <path d="M5 12h14"></path>
                            <path d="m13 6 6 6-6 6"></path>
                        </svg>

                        <div class="flow-node">
                            <strong>AI görüşmesi</strong>
                            <span>WAI ihtiyacı anladı.</span>
                        </div>

                        <svg class="flow-arrow"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.7">
                            <path d="M5 12h14"></path>
                            <path d="m13 6 6 6-6 6"></path>
                        </svg>

                        <div class="flow-node">
                            <strong>Satış fırsatı</strong>
                            <span>CRM otomatik güncellendi.</span>
                        </div>

                    </div>

                </article>

                <article class="bento-card dark reveal">

                    <div class="mini-label">
                        İnsan devralma
                    </div>

                    <h3>
                        Yapay zekâ çalışsın. Kontrol hep sizde kalsın.
                    </h3>

                    <p>
                        İstediğiniz konuşmayı saniyeler içinde ekibinize devredin.
                    </p>

                    <div class="human-demo">

                        <div class="human-side">
                            <span>Şu anda</span>
                            <strong>WAI konuşuyor</strong>
                        </div>

                        <div class="switcher">
                            <div class="switch-knob"></div>
                        </div>

                        <div class="human-side">
                            <span>Devralınca</span>
                            <strong>Ekibiniz konuşur</strong>
                        </div>

                    </div>

                </article>

                <article class="bento-card reveal">

                    <div class="mini-label">
                        Konuşma hafızası
                    </div>

                    <h3>
                        Aynı bilgiyi müşteriye ikinci kez sormayın.
                    </h3>

                    <p>
                        WAI, müşterinin görüşmede verdiği önemli bilgileri hatırlar.
                    </p>

                    <div class="memory-items">

                        <div class="memory-item">
                            <label>Müşteri</label>
                            <strong>Ahmet Yılmaz</strong>
                        </div>

                        <div class="memory-item">
                            <label>İlgilendiği ürün</label>
                            <strong>5 L Zeytinyağı</strong>
                        </div>

                        <div class="memory-item">
                            <label>Satış aşaması</label>
                            <strong>Siparişe hazır</strong>
                        </div>

                        <div class="memory-item">
                            <label>Son konuşma</label>
                            <strong>14:23</strong>
                        </div>

                    </div>

                </article>

                <article class="bento-card dark reveal">

                    <div class="mini-label">
                        Akıllı takip
                    </div>

                    <h3>
                        Cevapsız kalan müşteri kaybolmasın.
                    </h3>

                    <p>
                        WAI, satış fırsatlarını takip ederek gerektiğinde müşteriye tekrar ulaşır.
                    </p>

                    <div class="followup-demo">

                        <div class="timeline">

                            <div class="time-step active">
                                <div class="time-dot">✓</div>
                                <strong>Mesaj</strong>
                                <span>Şimdi</span>
                            </div>

                            <div class="time-step">
                                <div class="time-dot">1h</div>
                                <strong>Bekle</strong>
                                <span>Yanıt kontrolü</span>
                            </div>

                            <div class="time-step">
                                <div class="time-dot">24h</div>
                                <strong>Takip</strong>
                                <span>Otomatik mesaj</span>
                            </div>

                            <div class="time-step">
                                <div class="time-dot">CRM</div>
                                <strong>Güncelle</strong>
                                <span>Satış durumu</span>
                            </div>

                        </div>

                    </div>

                </article>

            </div>

        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section class="steps-section" id="nasil-calisir">
        <div class="container">

            <div class="steps-intro reveal">

                <div class="eyebrow">
                    3 adım
                </div>

                <h2 class="section-heading">
                    WhatsApp'ınızı yapay zekâya hazırlamak düşündüğünüzden kolay.
                </h2>

                <p class="section-description">
                    Teknik ekip gerekmeden birkaç adımda WAI'yi kullanmaya başlayın.
                </p>

            </div>

            <div class="steps-grid">

                <article class="step-card reveal">

                    <div class="step-number">
                        01
                    </div>

                    <h3>
                        WhatsApp'ınızı bağlayın.
                    </h3>

                    <p>
                        QR kodu okutun ve işletme numaranızı WAI'ye bağlayın.
                    </p>

                    <div class="step-visual">

                        <div class="qr-shell">

                            <div class="qr-code"></div>

                            <div class="qr-copy">
                                <strong>QR kodu okutun</strong>
                                <span>
                                    WhatsApp bağlantınız birkaç saniye içinde hazırlanır.
                                </span>
                            </div>

                        </div>

                    </div>

                </article>

                <article class="step-card reveal">

                    <div class="step-number">
                        02
                    </div>

                    <h3>
                        İşletmenizi öğretin.
                    </h3>

                    <p>
                        Ürün, fiyat, hizmet ve şirket kurallarınızı WAI'ye tanımlayın.
                    </p>

                    <div class="step-visual">

                        <div class="knowledge-list">

                            <div class="knowledge">
                                Ürünler ve hizmetler

                                <span class="knowledge-check">
                                    <svg viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2.5">
                                        <path d="m5 12 4 4L19 6"></path>
                                    </svg>
                                </span>
                            </div>

                            <div class="knowledge">
                                Fiyat ve ödeme bilgileri

                                <span class="knowledge-check">
                                    <svg viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2.5">
                                        <path d="m5 12 4 4L19 6"></path>
                                    </svg>
                                </span>
                            </div>

                            <div class="knowledge">
                                Firma kuralları

                                <span class="knowledge-check">
                                    <svg viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2.5">
                                        <path d="m5 12 4 4L19 6"></path>
                                    </svg>
                                </span>
                            </div>

                        </div>

                    </div>

                </article>

                <article class="step-card reveal">

                    <div class="step-number">
                        03
                    </div>

                    <h3>
                        WAI çalışmaya başlasın.
                    </h3>

                    <p>
                        Artık müşterileriniz yazdığında yapay zekâ satış ekibiniz hazır.
                    </p>

                    <div class="step-visual">

                        <div class="go-live">

                            <div class="live-line">
                                <span class="live-orb"></span>
                                <strong>WAI yayında</strong>
                            </div>

                            <p>
                                Gelen WhatsApp mesajları otomatik olarak işleniyor.
                            </p>

                        </div>

                    </div>

                </article>

            </div>

        </div>
    </section>

    {{-- USE CASES --}}
    <section class="usecases-section" id="sektorler">
        <div class="container">

            <div class="usecases-shell">

                <div class="usecases-top reveal">

                    <div>

                        <div class="eyebrow">
                            Her sektöre uyarlanabilir
                        </div>

                        <h2 class="section-heading">
                            İşiniz farklı olabilir.
                            WAI'nin görevi aynı:
                            müşteriyi kaçırmamak.
                        </h2>

                    </div>

                    <p class="usecases-description">
                        İşletmenizin çalışma biçimini WAI'ye öğreterek sektörünüze özel
                        bir WhatsApp yapay zekâ çalışanı oluşturabilirsiniz.
                    </p>

                </div>

                <div class="usecase-grid">

                    <article class="usecase reveal">

                        <div class="usecase-icon">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M3 9l9-6 9 6v11H3z"></path>
                                <path d="M8 20v-7h8v7"></path>
                            </svg>
                        </div>

                        <h3>E-Ticaret</h3>

                        <p>
                            Ürün bilgisi, kargo, ödeme, sipariş ve müşteri takibi.
                        </p>

                    </article>

                    <article class="usecase reveal">

                        <div class="usecase-icon">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M3 21h18"></path>
                                <path d="M6 21V7l6-4 6 4v14"></path>
                                <path d="M9 11h6"></path>
                            </svg>
                        </div>

                        <h3>Emlak</h3>

                        <p>
                            Portföy soruları, müşteri ihtiyacı, randevu ve lead toplama.
                        </p>

                    </article>

                    <article class="usecase reveal">

                        <div class="usecase-icon">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 7v10"></path>
                                <path d="M7 12h10"></path>
                            </svg>
                        </div>

                        <h3>Klinikler</h3>

                        <p>
                            Hizmet bilgisi, ön görüşme, randevu talebi ve danışan yönlendirme.
                        </p>

                    </article>

                    <article class="usecase reveal">

                        <div class="usecase-icon">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                <path d="M7 15h3"></path>
                                <path d="M14 9h3"></path>
                            </svg>
                        </div>

                        <h3>Finans</h3>

                        <p>
                            Ön bilgi toplama, akış yönlendirme ve başvuru süreci takibi.
                        </p>

                    </article>

                    <article class="usecase reveal">

                        <div class="usecase-icon">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M3 17h18"></path>
                                <path d="m5 17 2-7h10l2 7"></path>
                                <circle cx="7" cy="18" r="2"></circle>
                                <circle cx="17" cy="18" r="2"></circle>
                            </svg>
                        </div>

                        <h3>Otomotiv</h3>

                        <p>
                            Araç bilgisi, servis talepleri, teklif ve müşteri yönlendirme.
                        </p>

                    </article>

                    <article class="usecase reveal">

                        <div class="usecase-icon">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M4 20h16"></path>
                                <path d="M6 16h12"></path>
                                <path d="M8 12h8"></path>
                                <path d="M10 8h4"></path>
                            </svg>
                        </div>

                        <h3>Turizm</h3>

                        <p>
                            Rezervasyon soruları, fiyat bilgisi ve müşteri iletişimi.
                        </p>

                    </article>

                    <article class="usecase reveal">

                        <div class="usecase-icon">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <rect x="4" y="4" width="16" height="16" rx="3"></rect>
                                <path d="M8 8h8v8H8z"></path>
                            </svg>
                        </div>

                        <h3>Ajanslar</h3>

                        <p>
                            Hizmet tanıtımı, müşteri ön eleme, satış ve destek otomasyonu.
                        </p>

                    </article>

                    <article class="usecase reveal">

                        <div class="usecase-icon">
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.8">
                                <path d="M4 18h16"></path>
                                <path d="M6 18V8"></path>
                                <path d="M18 18V8"></path>
                                <path d="M8 8h8"></path>
                                <path d="M10 4h4"></path>
                            </svg>
                        </div>

                        <h3>Hizmet Sektörü</h3>

                        <p>
                            Fiyat talepleri, keşif, randevu ve satış sonrası destek.
                        </p>

                    </article>

                </div>

            </div>

        </div>
    </section>

    {{-- NUMBERS --}}
    <section class="numbers">
        <div class="container">

            <div class="numbers-grid reveal">

                <div class="number-intro">
                    <strong>
                        WhatsApp satış operasyonunu sadeleştirin.
                    </strong>

                    <span>
                        Daha az manuel işlem. Daha hızlı müşteri iletişimi.
                    </span>
                </div>

                <div class="stat">
                    <strong>7/24</strong>
                    <span>
                        Müşterileriniz için sürekli erişilebilir yapay zekâ.
                    </span>
                </div>

                <div class="stat">
                    <strong>&lt;10 sn</strong>
                    <span>
                        Mesajlara saniyeler içinde otomatik yanıt.
                    </span>
                </div>

                <div class="stat">
                    <strong>1 Panel</strong>
                    <span>
                        AI, CRM, gelen kutusu ve insan devralma tek yerde.
                    </span>
                </div>

            </div>

        </div>
    </section>

    {{-- FAQ --}}
    <section class="faq-section" id="sss">
        <div class="container">

            <div class="faq-grid">

                <div class="faq-copy reveal">

                    <div class="eyebrow">
                        Merak edilenler
                    </div>

                    <h2 class="section-heading">
                        WAI hakkında kısa cevaplar.
                    </h2>

                    <p class="section-description">
                        Başlamadan önce en çok merak edilen konuları burada topladık.
                    </p>

                </div>

                <div class="faq-list reveal">

                    <div class="faq-item open">

                        <button class="faq-question">
                            <span>
                                WAI normal bir chatbot mu?
                            </span>

                            <span class="faq-plus"></span>
                        </button>

                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Hayır. WAI yalnızca hazır cevap gönderen bir chatbot mantığıyla çalışmaz.
                                İşletmenizin verdiği bilgiler, konuşma geçmişi ve belirlenen kurallar üzerinden
                                müşterilerinizle doğal bir satış görüşmesi yürütmek için tasarlanmıştır.
                            </div>
                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">
                            <span>
                                Mevcut WhatsApp numaramı kullanabilir miyim?
                            </span>

                            <span class="faq-plus"></span>
                        </button>

                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Uygun bağlantı yöntemiyle mevcut işletme WhatsApp numaranızı WAI sistemine
                                bağlayabilirsiniz. Bağlantı durumu panel üzerinden yönetilir.
                            </div>
                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">
                            <span>
                                Yapay zekânın verdiği cevapları kontrol edebilir miyim?
                            </span>

                            <span class="faq-plus"></span>
                        </button>

                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Evet. WAI'ye firma açıklamanızı, çalışma saatlerinizi, ürün ve hizmetlerinizi,
                                ödeme ve kargo bilgilerinizi, firma kurallarınızı ve özel konuşma talimatlarınızı
                                tanımlayabilirsiniz.
                            </div>
                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">
                            <span>
                                Bir görüşmeyi çalışanım devralabilir mi?
                            </span>

                            <span class="faq-plus"></span>
                        </button>

                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Evet. Gelen Kutusu üzerinden istediğiniz müşterinin görüşmesini AI'dan
                                devralabilir, manuel şekilde mesaj gönderebilir ve daha sonra kontrolü
                                tekrar yapay zekâya bırakabilirsiniz.
                            </div>
                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">
                            <span>
                                WAI müşteriyi daha sonra tekrar takip edebilir mi?
                            </span>

                            <span class="faq-plus"></span>
                        </button>

                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                WAI'de otomatik takip özellikleri bulunur. İşletmenizin belirlediği
                                senaryolara göre cevapsız kalan veya satış süreci yarım kalan müşteriler
                                tekrar takip edilebilir.
                            </div>
                        </div>

                    </div>

                    <div class="faq-item">

                        <button class="faq-question">
                            <span>
                                Teknik bilgiye ihtiyacım var mı?
                            </span>

                            <span class="faq-plus"></span>
                        </button>

                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Hayır. WAI, işletmelerin teknik ekip olmadan kullanabilmesi için
                                panel üzerinden yönetilebilecek şekilde tasarlanmıştır.
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="final-cta">
        <div class="container">

            <div class="cta-shell reveal">

                <div class="cta-content">

                    <div class="cta-mini">
                        WAI'yi deneyin
                    </div>

                    <h2>
                        WhatsApp'ta
                        <span>cevapsız müşteri</span>
                        bırakmayın.
                    </h2>

                    <p>
                        Yapay zekâ satış asistanınızı oluşturun.
                        WAI işletmenizi öğrensin, müşterilerinizle konuşmaya başlasın.
                    </p>

                    <div class="cta-buttons">

                        <a href="/admin/register" class="button-primary">
                            Ücretsiz Hesap Oluştur

                            <span class="arrow">
                                <svg viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <path d="M5 12h14"></path>
                                    <path d="m13 6 6 6-6 6"></path>
                                </svg>
                            </span>
                        </a>

                        <a href="/admin/login" class="button-secondary">
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
                    <span class="brand-symbol"></span>

                    <span class="brand-text">
                        WAI
                        <small>WhatsApp Intelligence</small>
                    </span>
                </a>

                <p>
                    İşletmeler için yapay zekâ destekli WhatsApp satış,
                    müşteri iletişimi ve otomasyon platformu.
                </p>

            </div>

            <div class="footer-column">
                <strong>Ürün</strong>

                <a href="#urun">Gelen Kutusu</a>
                <a href="#ozellikler">Yapay Zeka</a>
                <a href="#nasil-calisir">Nasıl Çalışır?</a>
            </div>

            <div class="footer-column">
                <strong>Hesap</strong>

                <a href="/admin/login">Giriş Yap</a>
                <a href="/admin/register">Ücretsiz Başla</a>
            </div>

            <div class="footer-column">
                <strong>WAI</strong>

                <a href="#sektorler">Sektörler</a>
                <a href="#sss">SSS</a>
            </div>

        </div>

        <div class="footer-bottom">

            <span>
                © {{ date('Y') }} WAI. Tüm hakları saklıdır.
            </span>

            <span class="made-by">
                Bir <strong>AsilkanSoft</strong> teknolojisidir.
            </span>

        </div>

    </div>
</footer>

<script>
    /*
    |--------------------------------------------------------------------------
    | WAI PUBLIC LANDING PAGE
    |--------------------------------------------------------------------------
    |
    | Bu script yalnızca landing page arayüz davranışlarını yönetir.
    | Laravel, Filament, webhook, queue veya WhatsApp işlemlerine müdahale etmez.
    |
    */

    document.addEventListener('DOMContentLoaded', () => {

        /*
        |--------------------------------------------------------------------------
        | Sticky Navbar
        |--------------------------------------------------------------------------
        */

        const navbar = document.getElementById('navbar');

        const updateNavbar = () => {
            if (window.scrollY > 20) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        };

        updateNavbar();

        window.addEventListener('scroll', updateNavbar, {
            passive: true
        });


        /*
        |--------------------------------------------------------------------------
        | Mobile Menu
        |--------------------------------------------------------------------------
        */

        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileLinks = document.querySelectorAll('#mobileNav a');

        mobileMenuButton?.addEventListener('click', () => {
            document.body.classList.toggle('menu-open');
        });

        mobileLinks.forEach(link => {
            link.addEventListener('click', () => {
                document.body.classList.remove('menu-open');
            });
        });


        /*
        |--------------------------------------------------------------------------
        | Scroll Reveal
        |--------------------------------------------------------------------------
        */

        const revealItems = document.querySelectorAll('.reveal');

        if ('IntersectionObserver' in window) {

            const revealObserver = new IntersectionObserver(
                entries => {

                    entries.forEach(entry => {

                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add('visible');
                        revealObserver.unobserve(entry.target);

                    });

                },
                {
                    threshold: 0.12,
                    rootMargin: '0px 0px -40px 0px'
                }
            );

            revealItems.forEach(item => {
                revealObserver.observe(item);
            });

        } else {

            revealItems.forEach(item => {
                item.classList.add('visible');
            });

        }


        /*
        |--------------------------------------------------------------------------
        | FAQ
        |--------------------------------------------------------------------------
        */

        const faqItems = document.querySelectorAll('.faq-item');

        const setFaqHeight = item => {

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

            setFaqHeight(item);

            button?.addEventListener('click', () => {

                const isOpen = item.classList.contains('open');

                faqItems.forEach(otherItem => {
                    otherItem.classList.remove('open');
                    setFaqHeight(otherItem);
                });

                if (!isOpen) {
                    item.classList.add('open');
                    setFaqHeight(item);
                }

            });

        });

        window.addEventListener('resize', () => {

            faqItems.forEach(item => {
                setFaqHeight(item);
            });

        });

    });
</script>

</body>
</html>
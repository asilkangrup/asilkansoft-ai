<x-filament-panels::page>

<style>
/* ==========================================================================
   WAI PREMIUM DESIGN SYSTEM
   KANAL MERKEZİ — FINAL
   ========================================================================== */

.wai-page {
    --green: #20df78;
    --green-2: #58efa1;
    --green-dark: #07964c;
    --green-deep: #06713a;

    --ink: #0b110d;
    --text: #38443d;
    --muted: #6f7b73;

    --line: #e5eae7;
    --line-dark: #d8dfdb;

    --soft: #f8faf9;
    --green-soft: #effcf5;

    --shadow: 0 18px 55px rgba(13,31,20,.055);
    --shadow-lg: 0 30px 85px rgba(13,31,20,.08);

    width: 100%;
    max-width: 1240px;

    margin: 0 auto;

    color: var(--text);
}

.wai-page *,
.wai-page *::before,
.wai-page *::after {
    box-sizing: border-box;
}


/* ==========================================================================
   TOP ACTIONS
   ========================================================================== */

.wai-top-actions {
    margin-bottom: 17px;

    display: flex;
    justify-content: flex-end;

    gap: 10px;
}

.wai-top-action {
    min-height: 46px;

    padding: 0 17px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 9px;

    border: 1px solid var(--line);
    border-radius: 13px;

    color: #445148 !important;
    background: #fff;

    text-decoration: none !important;

    font-size: 13px;
    font-weight: 800;

    box-shadow: 0 7px 20px rgba(13,31,20,.035);
}

.wai-top-action svg {
    width: 17px;
    height: 17px;
}

.wai-top-action.primary {
    border-color: transparent;

    color: #062d19 !important;

    background:
        linear-gradient(
            135deg,
            #61eea0,
            #29df7c
        );

    box-shadow:
        0 12px 30px rgba(32,223,120,.16);
}


/* ==========================================================================
   HERO
   ========================================================================== */

.wai-hero {
    position: relative;

    overflow: hidden;

    padding: 40px;

    border: 1px solid var(--line);
    border-radius: 30px;

    background:
        radial-gradient(
            circle at 100% 100%,
            rgba(32,223,120,.14),
            transparent 30%
        ),
        linear-gradient(
            145deg,
            #ffffff,
            #fbfdfc
        );

    box-shadow: var(--shadow);
}

.wai-hero::before {
    content: "";

    position: absolute;

    width: 470px;
    height: 470px;

    right: -135px;
    top: -170px;

    opacity: .48;

    background-image:
        repeating-radial-gradient(
            ellipse at center,
            rgba(32,223,120,.16) 0,
            rgba(32,223,120,.16) 1px,
            transparent 1px,
            transparent 16px
        );

    transform: rotate(-14deg);

    pointer-events: none;
}

.wai-hero-grid {
    position: relative;
    z-index: 2;

    display: grid;

    grid-template-columns:
        minmax(0,1.28fr)
        minmax(300px,.72fr);

    gap: 50px;

    align-items: center;
}

.wai-eyebrow {
    min-height: 34px;

    padding: 0 13px;

    display: inline-flex;
    align-items: center;

    gap: 8px;

    border: 1px solid #c8eed7;
    border-radius: 999px;

    color: var(--green-deep);
    background: var(--green-soft);

    font-size: 11px;
    font-weight: 900;

    letter-spacing: .9px;

    text-transform: uppercase;
}

.wai-eyebrow-dot {
    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: var(--green);

    box-shadow:
        0 0 0 4px rgba(32,223,120,.12);
}

.wai-hero-title {
    max-width: 720px;

    margin: 21px 0 0;

    color: var(--ink);

    font-size:
        clamp(
            42px,
            5vw,
            67px
        );

    line-height: .95;

    font-weight: 850;

    letter-spacing: -4px;
}

.wai-hero-title span {
    display: block;

    margin-top: 5px;

    color: var(--green-dark);
}

.wai-hero-copy {
    max-width: 700px;

    margin: 20px 0 0;

    color: var(--muted);

    font-size: 16px;

    line-height: 1.75;
}


/* ==========================================================================
   HERO FEATURES
   ========================================================================== */

.wai-hero-features {
    margin-top: 31px;

    display: grid;

    grid-template-columns:
        repeat(3,minmax(0,1fr));

    gap: 18px;
}

.wai-hero-feature {
    display: flex;
    align-items: center;

    gap: 11px;
}

.wai-feature-icon {
    width: 42px;
    height: 42px;

    display: grid;
    place-items: center;

    flex: 0 0 42px;

    border-radius: 12px;

    color: var(--green-dark);

    background:
        linear-gradient(
            145deg,
            #eafcf2,
            #f5fff9
        );
}

.wai-feature-icon svg {
    width: 20px;
    height: 20px;
}

.wai-feature-text strong {
    display: block;

    color: #18221c;

    font-size: 13px;
    font-weight: 850;
}

.wai-feature-text span {
    display: block;

    margin-top: 4px;

    color: #8b978f;

    font-size: 11px;
}


/* ==========================================================================
   PREMIUM BRAND ICON BASE
   ========================================================================== */

.wai-brand-icon {
    position: relative;

    display: grid;
    place-items: center;

    flex-shrink: 0;

    overflow: hidden;

    border-radius: 50%;

    background: #fff;

    box-shadow:
        0 8px 22px rgba(13,31,20,.06);
}

.wai-brand-icon svg {
    display: block;
}


/* WHATSAPP */

.wai-brand-icon.whatsapp {
    color: #fff;

    background:
        linear-gradient(
            145deg,
            #2bf27e,
            #12b95d
        );
}

.wai-brand-icon.whatsapp::after {
    content: "";

    position: absolute;

    inset: 1px;

    border:
        1px solid rgba(255,255,255,.25);

    border-radius: inherit;

    pointer-events: none;
}


/* INSTAGRAM */

.wai-brand-icon.instagram {
    color: #fff;

    background:
        radial-gradient(
            circle at 32% 95%,
            #ffd600 0 20%,
            #ff7a00 32%,
            #ff0169 56%,
            #d300c5 76%,
            #7638fa 100%
        );
}


/* FACEBOOK MESSENGER */

.wai-brand-icon.messenger {
    color: #fff;

    background:
        linear-gradient(
            145deg,
            #5ba8ff,
            #1877f2
        );
}


/* WEB */

.wai-brand-icon.web {
    color: #06984d;

    background:
        linear-gradient(
            145deg,
            #f5fff9,
            #e7fbef
        );

    border:
        1px solid #cdeed9;
}


/* ==========================================================================
   NETWORK CARD
   ========================================================================== */

.wai-network {
    position: relative;

    padding: 27px;

    overflow: hidden;

    border: 1px solid var(--line);
    border-radius: 24px;

    background:
        linear-gradient(
            145deg,
            #fff,
            #fafcfb
        );

    box-shadow:
        0 18px 55px rgba(14,34,21,.065);
}

.wai-network-top {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;
}

.wai-network-label {
    color: #445149;

    font-size: 11px;
    font-weight: 900;

    letter-spacing: .7px;

    text-transform: uppercase;
}

.wai-live-pill {
    min-height: 29px;

    padding: 0 10px;

    display: inline-flex;
    align-items: center;

    gap: 6px;

    border-radius: 999px;

    color: var(--green-deep);

    background: #e9faef;

    font-size: 10px;
    font-weight: 900;
}

.wai-live-pill i {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--green);
}

.wai-network-number {
    margin-top: 16px;

    display: flex;
    align-items: flex-end;

    gap: 8px;
}

.wai-network-number strong {
    color: var(--green);

    font-size: 54px;
    line-height: .9;

    font-weight: 850;

    letter-spacing: -3px;
}

.wai-network-number span {
    color: #0c130f;

    font-size: 43px;
    line-height: .9;

    font-weight: 850;

    letter-spacing: -3px;
}

.wai-network-subtitle {
    margin-top: 12px;

    color: #536058;

    font-size: 13px;
}

.wai-network-channels {
    margin-top: 23px;

    display: flex;

    gap: 11px;
}

.wai-network-brand {
    width: 46px;
    height: 46px;
}

.wai-network-brand svg {
    width: 23px;
    height: 23px;
}

.wai-network-brand.inactive {
    filter: grayscale(.35);

    opacity: .72;
}

.wai-network-progress {
    height: 8px;

    margin-top: 22px;

    overflow: hidden;

    border-radius: 999px;

    background: #ebefec;
}

.wai-network-progress span {
    display: block;

    height: 100%;

    border-radius: inherit;

    background:
        linear-gradient(
            90deg,
            var(--green),
            var(--green-2)
        );
}

.wai-network-footer {
    margin-top: 13px;

    color: #6e7a72;

    font-size: 11px;
}


/* ==========================================================================
   WHATSAPP PREMIUM CARD
   ========================================================================== */

.wai-connected {
    position: relative;

    margin-top: 22px;

    overflow: hidden;

    padding: 31px;

    border-radius: 28px;

    color: #fff;

    background:
        radial-gradient(
            circle at 82% 36%,
            rgba(31,244,126,.18),
            transparent 23%
        ),
        linear-gradient(
            130deg,
            #04120c 0%,
            #071b12 55%,
            #06130d 100%
        );

    box-shadow:
        0 32px 85px rgba(0,21,12,.17);
}

.wai-connected::before {
    content: "";

    position: absolute;

    width: 420px;
    height: 420px;

    right: -130px;
    top: -120px;

    border-radius: 50%;

    border:
        1px solid rgba(75,255,153,.08);

    box-shadow:
        0 0 0 45px rgba(75,255,153,.025),
        0 0 0 90px rgba(75,255,153,.015);

    pointer-events: none;
}

.wai-connected-label {
    position: relative;
    z-index: 2;

    display: inline-flex;
    align-items: center;

    gap: 7px;

    color: #d8eee1;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .8px;

    text-transform: uppercase;
}

.wai-connected-label i {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--green);
}

.wai-connected-grid {
    position: relative;
    z-index: 2;

    margin-top: 22px;

    display: grid;

    grid-template-columns:
        minmax(0,1.1fr)
        1px
        minmax(250px,.6fr)
        minmax(260px,.75fr);

    gap: 28px;

    align-items: center;
}

.wai-connected-separator {
    width: 1px;
    height: 210px;

    background:
        linear-gradient(
            to bottom,
            transparent,
            rgba(255,255,255,.15),
            transparent
        );
}

.wai-wa-head {
    display: flex;
    align-items: center;

    gap: 17px;
}

.wai-wa-logo-shell {
    width: 74px;
    height: 74px;

    display: grid;
    place-items: center;

    flex: 0 0 74px;

    border-radius: 21px;

    background: #fff;

    box-shadow:
        0 17px 40px rgba(0,0,0,.18);
}

.wai-wa-logo {
    width: 49px;
    height: 49px;
}

.wai-wa-logo svg {
    width: 28px;
    height: 28px;
}

.wai-wa-title-row {
    display: flex;
    align-items: center;

    gap: 10px;
}

.wai-wa-title {
    color: #f5fff8;

    font-size: 28px;
    font-weight: 850;

    letter-spacing: -.9px;
}

.wai-wa-active {
    min-height: 29px;

    padding: 0 11px;

    display: inline-flex;
    align-items: center;

    border:
        1px solid rgba(55,255,142,.15);

    border-radius: 999px;

    color: #36ee8a;

    background:
        rgba(32,223,120,.09);

    font-size: 10px;
    font-weight: 900;
}

.wai-wa-subtitle {
    margin-top: 6px;

    color: #b2c6b9;

    font-size: 13px;
}

.wai-wa-copy {
    max-width: 500px;

    margin-top: 22px;

    color: #c1d2c7;

    font-size: 14px;

    line-height: 1.7;
}

.wai-dark-tags {
    margin-top: 19px;

    display: flex;
    flex-wrap: wrap;

    gap: 8px;
}

.wai-dark-tag {
    min-height: 37px;

    padding: 0 12px;

    display: inline-flex;
    align-items: center;

    gap: 8px;

    border:
        1px solid rgba(92,255,157,.13);

    border-radius: 10px;

    color: #d9e9de;

    background:
        rgba(255,255,255,.025);

    font-size: 11px;
    font-weight: 750;
}


/* ==========================================================================
   DETAILS
   ========================================================================== */

.wai-wa-details {
    display: flex;
    flex-direction: column;

    gap: 22px;
}

.wai-detail-label {
    color: #9fb2a6;

    font-size: 11px;
}

.wai-detail-value {
    margin-top: 6px;

    color: #edfdf3;

    font-size: 13px;

    font-weight: 800;
}

.wai-detail-value.green {
    color: var(--green);
}


/* ==========================================================================
   PREMIUM ORBIT
   ========================================================================== */

.wai-orbit {
    position: relative;

    width: 205px;
    height: 175px;

    margin: 0 auto 14px;
}

.wai-orbit-ring {
    position: absolute;

    left: 50%;
    top: 50%;

    border:
        1px solid rgba(63,255,148,.26);

    border-radius: 50%;

    transform:
        translate(-50%,-50%)
        rotate(-8deg);
}

.wai-orbit-ring.one {
    width: 200px;
    height: 75px;
}

.wai-orbit-ring.two {
    width: 160px;
    height: 115px;

    transform:
        translate(-50%,-50%)
        rotate(28deg);
}

.wai-orbit-ring.three {
    width: 125px;
    height: 145px;

    opacity: .48;

    transform:
        translate(-50%,-50%)
        rotate(-25deg);
}

.wai-orbit-core {
    position: absolute;

    left: 50%;
    top: 50%;

    width: 94px;
    height: 94px;

    display: grid;
    place-items: center;

    transform: translate(-50%,-50%);

    border-radius: 50%;

    background:
        radial-gradient(
            circle at 35% 28%,
            #81ffc0 0,
            #22df78 27%,
            #0aa357 67%,
            #08703c 100%
        );

    box-shadow:
        inset 0 0 25px rgba(255,255,255,.25),
        0 0 0 9px rgba(38,255,137,.045),
        0 0 55px rgba(32,223,120,.37);
}

.wai-orbit-core svg {
    width: 49px;
    height: 49px;

    color: #fff;
}

.wai-wa-button {
    width: 100%;
    min-height: 59px;

    padding: 0 19px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    border-radius: 14px;

    color: #101511 !important;

    background: #fff;

    text-decoration: none !important;

    font-size: 13px;
    font-weight: 850;

    box-shadow:
        0 13px 34px rgba(0,0,0,.18);
}


/* ==========================================================================
   OTHER CHANNELS
   ========================================================================== */

.wai-channels-card {
    margin-top: 22px;

    padding: 29px;

    border: 1px solid var(--line);
    border-radius: 27px;

    background: #fff;

    box-shadow: var(--shadow);
}

.wai-channels-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 20px;
}

.wai-channels-kicker {
    display: flex;
    align-items: center;

    gap: 7px;

    color: var(--green-dark);

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .8px;

    text-transform: uppercase;
}

.wai-channels-kicker i {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--green);
}

.wai-channels-heading h2 {
    margin: 8px 0 0;

    color: var(--ink);

    font-size: 26px;
    font-weight: 850;

    letter-spacing: -.7px;
}

.wai-coming-button {
    min-height: 43px;

    padding: 0 14px;

    display: inline-flex;
    align-items: center;

    border: 1px solid var(--line);
    border-radius: 12px;

    color: #5f6c64;

    background: #fff;

    font-size: 11px;
    font-weight: 800;
}

.wai-other-grid {
    margin-top: 22px;

    display: grid;

    grid-template-columns:
        repeat(3,minmax(0,1fr));

    gap: 13px;
}

.wai-channel {
    position: relative;

    min-height: 340px;

    padding: 22px;

    display: flex;
    flex-direction: column;

    overflow: hidden;

    border: 1px solid var(--line);
    border-radius: 20px;

    background: #fff;
}

.wai-channel.instagram {
    background:
        radial-gradient(
            circle at 0% 100%,
            rgba(214,74,255,.09),
            transparent 36%
        ),
        #fff;
}

.wai-channel.facebook {
    background:
        radial-gradient(
            circle at 100% 100%,
            rgba(54,135,255,.09),
            transparent 36%
        ),
        #fff;
}

.wai-channel.web {
    background:
        radial-gradient(
            circle at 100% 100%,
            rgba(32,223,120,.09),
            transparent 36%
        ),
        #fff;
}

.wai-channel-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 12px;
}

.wai-platform-shell {
    width: 60px;
    height: 60px;

    display: grid;
    place-items: center;

    border: 1px solid var(--line);
    border-radius: 17px;

    background: #fff;

    box-shadow:
        0 10px 27px rgba(13,31,20,.05);
}

.wai-platform-logo {
    width: 43px;
    height: 43px;
}

.wai-platform-logo svg {
    width: 23px;
    height: 23px;
}

.wai-channel-pill {
    min-height: 30px;

    padding: 0 10px;

    display: inline-flex;
    align-items: center;

    border-radius: 999px;

    color: #6d7972;

    background: #f0f3f1;

    font-size: 10px;
    font-weight: 850;
}

.wai-channel h3 {
    margin: 21px 0 0;

    color: #111713;

    font-size: 20px;
    font-weight: 850;

    letter-spacing: -.4px;
}

.wai-channel p {
    margin: 9px 0 0;

    color: #69766e;

    font-size: 13px;

    line-height: 1.65;
}

.wai-light-tags {
    margin-top: 15px;

    display: flex;
    flex-wrap: wrap;

    gap: 7px;
}

.wai-light-tag {
    min-height: 29px;

    padding: 0 10px;

    display: inline-flex;
    align-items: center;

    border: 1px solid var(--line);
    border-radius: 999px;

    color: #5e6b63;

    background:
        rgba(255,255,255,.78);

    font-size: 10px;
    font-weight: 800;
}

.wai-channel-coming {
    min-height: 68px;

    margin-top: auto;
    padding: 0 14px;

    display: flex;
    align-items: center;

    gap: 11px;

    border: 1px solid var(--line);
    border-radius: 13px;

    background:
        rgba(255,255,255,.82);
}

.wai-coming-lock {
    width: 37px;
    height: 37px;

    display: grid;
    place-items: center;

    flex: 0 0 37px;

    border-radius: 10px;

    color: #59655d;

    background: #f5f7f6;
}

.wai-coming-lock svg {
    width: 17px;
    height: 17px;
}

.wai-channel-coming-text {
    flex: 1;
}

.wai-channel-coming-text strong {
    display: block;

    color: #3f4b43;

    font-size: 11px;
}

.wai-channel-coming-text span {
    display: block;

    margin-top: 4px;

    color: #8d9891;

    font-size: 10px;
}


/* ==========================================================================
   BENEFITS
   ========================================================================== */

.wai-benefits {
    margin-top: 17px;

    padding: 23px 25px;

    display: grid;

    grid-template-columns:
        repeat(4,minmax(0,1fr));

    border: 1px solid var(--line);
    border-radius: 22px;

    background: #fff;
}

.wai-benefit {
    padding: 0 18px;

    display: flex;
    align-items: center;

    gap: 12px;

    border-right:
        1px solid var(--line);
}

.wai-benefit:first-child {
    padding-left: 0;
}

.wai-benefit:last-child {
    padding-right: 0;

    border-right: 0;
}

.wai-benefit-icon {
    width: 43px;
    height: 43px;

    display: grid;
    place-items: center;

    flex: 0 0 43px;

    border-radius: 12px;

    color: var(--green-dark);

    background: var(--green-soft);
}

.wai-benefit strong {
    display: block;

    color: #263129;

    font-size: 12px;
}

.wai-benefit span {
    display: block;

    margin-top: 5px;

    color: #818d85;

    font-size: 10px;

    line-height: 1.45;
}


/* ==========================================================================
   BOTTOM
   ========================================================================== */

.wai-ai-banner {
    margin-top: 17px;

    padding: 29px 34px;

    display: grid;

    grid-template-columns:
        70px minmax(0,1fr) auto;

    gap: 24px;

    align-items: center;

    border: 1px solid var(--line);
    border-radius: 25px;

    background:
        radial-gradient(
            circle at 85% 50%,
            rgba(32,223,120,.10),
            transparent 30%
        ),
        linear-gradient(
            135deg,
            #f8fcfa,
            #f2fbf6
        );
}

.wai-ai-gem {
    width: 62px;
    height: 62px;

    display: grid;
    place-items: center;

    transform: rotate(45deg);

    border-radius: 17px;

    background:
        linear-gradient(
            135deg,
            #8affbb,
            #21c96d,
            #087e43
        );

    box-shadow:
        0 13px 31px rgba(32,223,120,.19);
}

.wai-ai-gem span {
    transform: rotate(-45deg);

    color: #fff;

    font-size: 20px;
    font-weight: 900;
}

.wai-ai-copy strong {
    display: block;

    color: #162019;

    font-size: 18px;
}

.wai-ai-copy p {
    margin: 8px 0 0;

    color: #67746c;

    font-size: 12px;

    line-height: 1.65;
}

.wai-ai-logos {
    display: flex;
    align-items: center;

    gap: 9px;
}

.wai-ai-logos .wai-brand-icon {
    width: 43px;
    height: 43px;
}

.wai-ai-logos svg {
    width: 21px;
    height: 21px;
}


/* ==========================================================================
   TABLET
   ========================================================================== */

@media (max-width: 1050px) {

    .wai-hero-grid {
        grid-template-columns: 1fr;
    }

    .wai-network {
        max-width: 480px;
    }

    .wai-connected-grid {
        grid-template-columns:
            1fr
            minmax(220px,.65fr);
    }

    .wai-connected-separator,
    .wai-wa-visual {
        display: none;
    }

    .wai-other-grid {
        grid-template-columns:
            1fr 1fr;
    }

    .wai-channel.web {
        grid-column: 1 / -1;
    }

    .wai-benefits {
        grid-template-columns:
            1fr 1fr;

        gap: 18px 0;
    }

    .wai-benefit:nth-child(2) {
        border-right: 0;
    }

    .wai-ai-banner {
        grid-template-columns:
            65px 1fr;
    }

    .wai-ai-logos {
        grid-column: 1 / -1;

        padding-left: 89px;
    }
}


/* ==========================================================================
   MOBILE — FINAL WAI STANDARD
   ========================================================================== */

@media (max-width: 700px) {

    .wai-top-actions {
        margin-bottom: 12px;
    }

    .wai-top-action {
        flex: 1;

        min-height: 48px;

        padding: 0 10px;

        font-size: 12px;
    }


    /* HERO */

    .wai-hero {
        padding: 23px 17px 19px;

        border-radius: 22px;
    }

    .wai-hero::before {
        width: 250px;
        height: 250px;

        right: -110px;
        top: -100px;
    }

    .wai-hero-grid {
        gap: 24px;
    }

    .wai-eyebrow {
        min-height: 31px;

        font-size: 10px;
    }

    .wai-hero-title {
        margin-top: 18px;

        font-size: 39px;
        line-height: .97;

        letter-spacing: -2.6px;
    }

    .wai-hero-copy {
        margin-top: 16px;

        font-size: 14px;

        line-height: 1.65;
    }

    .wai-hero-features {
        margin-top: 23px;

        grid-template-columns: 1fr;

        gap: 9px;
    }

    .wai-hero-feature {
        min-height: 58px;

        padding: 9px 11px;

        border: 1px solid #edf1ee;
        border-radius: 13px;

        background:
            rgba(255,255,255,.76);
    }

    .wai-feature-text strong {
        font-size: 12px;
    }

    .wai-feature-text span {
        font-size: 11px;
    }


    /* NETWORK */

    .wai-network {
        width: 100%;

        padding: 21px;

        border-radius: 19px;
    }

    .wai-network-number strong {
        font-size: 47px;
    }

    .wai-network-number span {
        font-size: 37px;
    }

    .wai-network-channels {
        justify-content: space-between;

        gap: 7px;
    }

    .wai-network-brand {
        width: 43px;
        height: 43px;
    }


    /* WHATSAPP */

    .wai-connected {
        margin-top: 14px;

        padding: 22px 17px 18px;

        border-radius: 22px;
    }

    .wai-connected-grid {
        margin-top: 19px;

        grid-template-columns: 1fr;

        gap: 22px;
    }

    .wai-wa-logo-shell {
        width: 62px;
        height: 62px;

        flex-basis: 62px;

        border-radius: 18px;
    }

    .wai-wa-logo {
        width: 43px;
        height: 43px;
    }

    .wai-wa-title-row {
        display: block;
    }

    .wai-wa-title {
        font-size: 24px;
    }

    .wai-wa-active {
        margin-top: 6px;
    }

    .wai-wa-subtitle {
        font-size: 12px;
    }

    .wai-wa-copy {
        font-size: 13px;
    }

    .wai-dark-tags {
        display: grid;

        grid-template-columns:
            1fr 1fr;

        gap: 7px;
    }

    .wai-dark-tag {
        min-height: 40px;

        font-size: 11px;
    }

    .wai-wa-details {
        padding: 16px;

        border:
            1px solid rgba(255,255,255,.08);

        border-radius: 15px;

        background:
            rgba(255,255,255,.025);
    }

    .wai-detail-label {
        font-size: 11px;
    }

    .wai-detail-value {
        font-size: 13px;
    }

    .wai-wa-button {
        min-height: 58px;

        font-size: 13px;
    }


    /* OTHER CHANNELS */

    .wai-channels-card {
        margin-top: 14px;

        padding: 20px 12px 12px;

        border-radius: 21px;
    }

    .wai-channels-heading {
        display: block;

        padding: 0 5px;
    }

    .wai-channels-heading h2 {
        font-size: 22px;
    }

    .wai-coming-button {
        width: 100%;

        margin-top: 13px;

        min-height: 45px;

        justify-content: center;

        font-size: 11px;
    }

    .wai-other-grid {
        margin-top: 16px;

        grid-template-columns: 1fr;

        gap: 10px;
    }

    .wai-channel,
    .wai-channel.web {
        min-height: auto;

        grid-column: auto;

        padding: 19px;

        border-radius: 17px;
    }

    .wai-platform-shell {
        width: 56px;
        height: 56px;
    }

    .wai-platform-logo {
        width: 41px;
        height: 41px;
    }

    .wai-channel h3 {
        font-size: 19px;
    }

    .wai-channel p {
        font-size: 13px;
    }

    .wai-channel-coming {
        min-height: 64px;

        margin-top: 20px;
    }


    /* BENEFITS */

    .wai-benefits {
        margin-top: 14px;

        padding: 12px;

        grid-template-columns: 1fr;

        gap: 8px;

        border-radius: 19px;
    }

    .wai-benefit,
    .wai-benefit:first-child,
    .wai-benefit:last-child {
        min-height: 68px;

        padding: 11px;

        border: 1px solid var(--line);
        border-radius: 13px;

        background: #fbfcfb;
    }

    .wai-benefit strong {
        font-size: 12px;
    }

    .wai-benefit span {
        font-size: 10px;
    }


    /* BOTTOM */

    .wai-ai-banner {
        margin-top: 14px;

        padding: 21px 17px;

        grid-template-columns:
            52px minmax(0,1fr);

        gap: 14px;

        border-radius: 20px;
    }

    .wai-ai-gem {
        width: 50px;
        height: 50px;
    }

    .wai-ai-copy strong {
        font-size: 16px;
    }

    .wai-ai-copy p {
        font-size: 11px;
    }

    .wai-ai-logos {
        grid-column: 1 / -1;

        width: 100%;

        padding-left: 0;

        justify-content: center;
    }

    .wai-ai-logos .wai-brand-icon {
        width: 41px;
        height: 41px;
    }
}

@media (max-width: 380px) {

    .wai-hero-title {
        font-size: 35px;
    }

    .wai-dark-tags {
        grid-template-columns: 1fr;
    }
}
</style>


<div class="wai-page">

    {{-- =========================================================
         TOP
    ========================================================== --}}

    <div class="wai-top-actions">

        <a href="javascript:void(0)" class="wai-top-action">

            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <circle cx="12" cy="12" r="9"/>
                <path d="M9.8 9a2.4 2.4 0 0 1 4.6 1c0 1.8-2.4 2-2.4 3.5"/>
                <path d="M12 17h.01"/>
            </svg>

            Yardım Merkezi

        </a>

        <a href="#waiYeniKanallar" class="wai-top-action primary">
            ＋ Kanal Ekle
        </a>

    </div>


    {{-- =========================================================
         HERO
    ========================================================== --}}

    <section class="wai-hero">

        <div class="wai-hero-grid">

            <div>

                <div class="wai-eyebrow">
                    <span class="wai-eyebrow-dot"></span>
                    WAI · Kanal Merkezi
                </div>

                <h1 class="wai-hero-title">
                    Müşteriniz nerede,
                    <span>WAI orada.</span>
                </h1>

                <p class="wai-hero-copy">
                    WhatsApp, Instagram, Facebook ve web sitenizden gelen
                    tüm müşteri görüşmelerini tek yapay zekâ ile yönetin.
                    İşletmenizi bir kez öğretin, WAI tüm kanallarınızda
                    aynı bilgi ve kurallarla çalışsın.
                </p>


                <div class="wai-hero-features">

                    <div class="wai-hero-feature">

                        <div class="wai-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M12 3 5 6v5c0 4.8 2.8 8.2 7 10 4.2-1.8 7-5.2 7-10V6l-7-3Z"/>
                                <path d="m9 12 2 2 4-4"/>
                            </svg>
                        </div>

                        <div class="wai-feature-text">
                            <strong>Tek AI Motoru</strong>
                            <span>Tüm kanallarda aynı zekâ</span>
                        </div>

                    </div>


                    <div class="wai-hero-feature">

                        <div class="wai-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="5" y="10" width="14" height="10" rx="2"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                            </svg>
                        </div>

                        <div class="wai-feature-text">
                            <strong>Güvenli Altyapı</strong>
                            <span>Verileriniz güvende</span>
                        </div>

                    </div>


                    <div class="wai-hero-feature">

                        <div class="wai-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 18 9 13l3 3 7-8"/>
                                <path d="M15 8h4v4"/>
                            </svg>
                        </div>

                        <div class="wai-feature-text">
                            <strong>Performans Analitiği</strong>
                            <span>Kanal bazlı detaylı raporlar</span>
                        </div>

                    </div>

                </div>

            </div>


            {{-- NETWORK --}}

            <div class="wai-network">

                <div class="wai-network-top">

                    <div class="wai-network-label">
                        WAI Network
                    </div>

                    <div class="wai-live-pill">
                        <i></i>
                        Canlı
                    </div>

                </div>


                <div class="wai-network-number">

                    <strong>
                        {{ $this->connectedChannelsCount }}
                    </strong>

                    <span>
                        / {{ $this->availableChannelsCount }}
                    </span>

                </div>

                <div class="wai-network-subtitle">
                    kanal aktif
                </div>


                <div class="wai-network-channels">

                    {{-- WHATSAPP --}}
                    <div class="
                        wai-brand-icon
                        whatsapp
                        wai-network-brand
                        {{ $this->whatsAppConnected ? '' : 'inactive' }}
                    ">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M20 11.5a8 8 0 0 1-11.9 7L4 19.8l1.2-3.9A8 8 0 1 1 20 11.5Z"/>
                            <path d="M8.6 8.3c.2-.4.4-.4.7-.4h.4c.2 0 .4.1.5.4l.7 1.6c.1.2.1.4-.1.6l-.5.6c-.2.2-.1.4 0 .6.6 1.1 1.4 1.9 2.5 2.4.3.2.5.1.7-.1l.7-.9c.2-.2.4-.3.7-.2l1.7.8"/>
                        </svg>
                    </div>

                    {{-- INSTAGRAM --}}
                    <div class="wai-brand-icon instagram wai-network-brand inactive">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="3.5" y="3.5" width="17" height="17" rx="5"/>
                            <circle cx="12" cy="12" r="4"/>
                            <circle cx="17.3" cy="6.7" r="1"/>
                        </svg>
                    </div>

                    {{-- MESSENGER --}}
                    <div class="wai-brand-icon messenger wai-network-brand inactive">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 3C6.9 3 3 6.7 3 11.5c0 2.7 1.2 5 3.3 6.6V21l3-1.6c.9.3 1.8.5 2.7.5 5.1 0 9-3.7 9-8.4S17.1 3 12 3Zm.9 11.4-2.3-2.5-4.5 2.5 5-5.3 2.4 2.5L18 9.1l-5.1 5.3Z"/>
                        </svg>
                    </div>

                    {{-- WEB --}}
                    <div class="wai-brand-icon web wai-network-brand inactive">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="8.5"/>
                            <path d="M3.5 12h17"/>
                            <path d="M12 3.5c2.1 2.3 3.2 5.1 3.2 8.5S14.1 18.2 12 20.5"/>
                            <path d="M12 3.5C9.9 5.8 8.8 8.6 8.8 12s1.1 6.2 3.2 8.5"/>
                        </svg>
                    </div>

                </div>


                @php
                    $channelPercent =
                        ($this->connectedChannelsCount / max(1, $this->availableChannelsCount)) * 100;
                @endphp

                <div class="wai-network-progress">
                    <span style="width: {{ max(4, $channelPercent) }}%"></span>
                </div>

                <div class="wai-network-footer">
                    Toplam {{ $this->availableChannelsCount }} kanal altyapısı
                </div>

            </div>

        </div>

    </section>


    {{-- =========================================================
         WHATSAPP
    ========================================================== --}}

    <section class="wai-connected">

        <div class="wai-connected-label">
            <i></i>
            Bağlı Kanal
        </div>


        <div class="wai-connected-grid">

            <div>

                <div class="wai-wa-head">

                    <div class="wai-wa-logo-shell">

                        <div class="wai-brand-icon whatsapp wai-wa-logo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M20 11.5a8 8 0 0 1-11.9 7L4 19.8l1.2-3.9A8 8 0 1 1 20 11.5Z"/>
                                <path d="M8.6 8.3c.2-.4.4-.4.7-.4h.4c.2 0 .4.1.5.4l.7 1.6c.1.2.1.4-.1.6l-.5.6c-.2.2-.1.4 0 .6.6 1.1 1.4 1.9 2.5 2.4.3.2.5.1.7-.1l.7-.9c.2-.2.4-.3.7-.2l1.7.8"/>
                            </svg>
                        </div>

                    </div>


                    <div>

                        <div class="wai-wa-title-row">

                            <div class="wai-wa-title">
                                WhatsApp
                            </div>

                            <div class="wai-wa-active">
                                {{ $this->whatsAppConnected ? 'Aktif' : 'Bağlı Değil' }}
                            </div>

                        </div>

                        <div class="wai-wa-subtitle">
                            Ana müşteri iletişim kanalınız
                        </div>

                    </div>

                </div>


                <div class="wai-wa-copy">
                    WAI, WhatsApp müşterilerinizi karşılayabilir,
                    soruları yanıtlayabilir ve gerektiğinde
                    görüşmeyi ekibinize devredebilir.
                </div>


                <div class="wai-dark-tags">

                    <div class="wai-dark-tag">
                        ✓ AI Yanıt
                    </div>

                    <div class="wai-dark-tag">
                        ✓ CRM
                    </div>

                    <div class="wai-dark-tag">
                        ✓ İnsan Devralma
                    </div>

                    <div class="wai-dark-tag">
                        ✓ Takip
                    </div>

                </div>

            </div>


            <div class="wai-connected-separator"></div>


            <div class="wai-wa-details">

                <div>

                    <div class="wai-detail-label">
                        Bağlantı Durumu
                    </div>

                    <div class="wai-detail-value green">
                        {{ $this->whatsAppConnected ? 'Bağlı' : 'Bağlı Değil' }}
                    </div>

                </div>


                <div>

                    <div class="wai-detail-label">
                        Telefon Numarası
                    </div>

                    <div class="wai-detail-value green">
                        {{ $bot?->whatsapp_number ?: 'Henüz bağlanmadı' }}
                    </div>

                </div>


                <div>

                    <div class="wai-detail-label">
                        Durum
                    </div>

                    <div class="wai-detail-value">
                        {{ $this->whatsAppConnected
                            ? 'WAI kullanıma hazır'
                            : 'Bağlantı bekleniyor'
                        }}
                    </div>

                </div>

            </div>


            <div class="wai-wa-visual">

                <div class="wai-orbit">

                    <div class="wai-orbit-ring one"></div>
                    <div class="wai-orbit-ring two"></div>
                    <div class="wai-orbit-ring three"></div>

                    <div class="wai-orbit-core">

                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                            <path d="M20 11.5a8 8 0 0 1-11.9 7L4 19.8l1.2-3.9A8 8 0 1 1 20 11.5Z"/>
                            <path d="M8.6 8.3c.2-.4.4-.4.7-.4h.4c.2 0 .4.1.5.4l.7 1.6c.1.2.1.4-.1.6l-.5.6c-.2.2-.1.4 0 .6.6 1.1 1.4 1.9 2.5 2.4.3.2.5.1.7-.1l.7-.9c.2-.2.4-.3.7-.2l1.7.8"/>
                        </svg>

                    </div>

                </div>


                <a
                    href="{{ $this->whatsAppManageUrl }}"
                    class="wai-wa-button"
                >

                    <span>
                        {{ $this->whatsAppConnected
                            ? 'WhatsApp\'ı Yönet'
                            : 'WhatsApp\'ı Bağla'
                        }}
                    </span>

                    <b>→</b>

                </a>

            </div>

        </div>

    </section>


    {{-- =========================================================
         OTHER CHANNELS
    ========================================================== --}}

    <section
        class="wai-channels-card"
        id="waiYeniKanallar"
    >

        <div class="wai-channels-heading">

            <div>

                <div class="wai-channels-kicker">
                    <i></i>
                    Yeni Kanal Ekle
                </div>

                <h2>
                    Diğer kanallarınızı bağlayın
                </h2>

            </div>

            <div class="wai-coming-button">
                Yakında Gelecek Kanallar →
            </div>

        </div>


        <div class="wai-other-grid">


            {{-- INSTAGRAM --}}

            <article class="wai-channel instagram">

                <div class="wai-channel-top">

                    <div class="wai-platform-shell">

                        <div class="wai-brand-icon instagram wai-platform-logo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="3.5" y="3.5" width="17" height="17" rx="5"/>
                                <circle cx="12" cy="12" r="4"/>
                                <circle cx="17.3" cy="6.7" r="1"/>
                            </svg>
                        </div>

                    </div>

                    <span class="wai-channel-pill">
                        Yakında
                    </span>

                </div>


                <h3>
                    Instagram
                </h3>

                <p>
                    Instagram DM görüşmelerinizi WAI Gelen Kutusu'nda
                    yönetin ve AI ile otomatik yanıtlayın.
                </p>


                <div class="wai-light-tags">

                    <span class="wai-light-tag">
                        DM
                    </span>

                    <span class="wai-light-tag">
                        AI Yanıt
                    </span>

                    <span class="wai-light-tag">
                        CRM
                    </span>

                </div>


                <div class="wai-channel-coming">

                    <div class="wai-coming-lock">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="5" y="10" width="14" height="10" rx="2"/>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        </svg>
                    </div>

                    <div class="wai-channel-coming-text">
                        <strong>Entegrasyon hazırlanıyor</strong>
                        <span>Yakında aktif olacak</span>
                    </div>

                    →

                </div>

            </article>


            {{-- FACEBOOK MESSENGER --}}

            <article class="wai-channel facebook">

                <div class="wai-channel-top">

                    <div class="wai-platform-shell">

                        <div class="wai-brand-icon messenger wai-platform-logo">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 3C6.9 3 3 6.7 3 11.5c0 2.7 1.2 5 3.3 6.6V21l3-1.6c.9.3 1.8.5 2.7.5 5.1 0 9-3.7 9-8.4S17.1 3 12 3Zm.9 11.4-2.3-2.5-4.5 2.5 5-5.3 2.4 2.5L18 9.1l-5.1 5.3Z"/>
                            </svg>
                        </div>

                    </div>

                    <span class="wai-channel-pill">
                        Yakında
                    </span>

                </div>


                <h3>
                    Facebook Messenger
                </h3>

                <p>
                    Facebook Sayfanıza gelen Messenger mesajlarını
                    WAI ile tek merkezden yönetin.
                </p>


                <div class="wai-light-tags">

                    <span class="wai-light-tag">
                        Messenger
                    </span>

                    <span class="wai-light-tag">
                        AI Yanıt
                    </span>

                    <span class="wai-light-tag">
                        CRM
                    </span>

                </div>


                <div class="wai-channel-coming">

                    <div class="wai-coming-lock">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="5" y="10" width="14" height="10" rx="2"/>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        </svg>
                    </div>

                    <div class="wai-channel-coming-text">
                        <strong>Entegrasyon hazırlanıyor</strong>
                        <span>Yakında aktif olacak</span>
                    </div>

                    →

                </div>

            </article>


            {{-- WEB CHAT --}}

            <article class="wai-channel web">

                <div class="wai-channel-top">

                    <div class="wai-platform-shell">

                        <div class="wai-brand-icon web wai-platform-logo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="8.5"/>
                                <path d="M3.5 12h17"/>
                                <path d="M12 3.5c2.1 2.3 3.2 5.1 3.2 8.5S14.1 18.2 12 20.5"/>
                                <path d="M12 3.5C9.9 5.8 8.8 8.6 8.8 12s1.1 6.2 3.2 8.5"/>
                            </svg>
                        </div>

                    </div>

                    <span class="wai-channel-pill">
                        Yakında
                    </span>

                </div>


                <h3>
                    Web Chat
                </h3>

                <p>
                    WAI'yi sitenize ekleyin.
                    Ziyaretçilerinizi gerçek zamanlı lead ve
                    satış görüşmesine dönüştürün.
                </p>


                <div class="wai-light-tags">

                    <span class="wai-light-tag">
                        Canlı Chat
                    </span>

                    <span class="wai-light-tag">
                        AI Yanıt
                    </span>

                    <span class="wai-light-tag">
                        Lead
                    </span>

                </div>


                <div class="wai-channel-coming">

                    <div class="wai-coming-lock">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="5" y="10" width="14" height="10" rx="2"/>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        </svg>
                    </div>

                    <div class="wai-channel-coming-text">
                        <strong>Web Chat hazırlanıyor</strong>
                        <span>Yakında aktif olacak</span>
                    </div>

                    →

                </div>

            </article>

        </div>

    </section>


    {{-- =========================================================
         BENEFITS
    ========================================================== --}}

    <section class="wai-benefits">

        <div class="wai-benefit">

            <div class="wai-benefit-icon">
                ✦
            </div>

            <div>
                <strong>Tüm Kanallar Tek Panelde</strong>
                <span>Gelen mesajları tek gelen kutusunda yönetin.</span>
            </div>

        </div>


        <div class="wai-benefit">

            <div class="wai-benefit-icon">
                24
            </div>

            <div>
                <strong>7/24 AI Desteği</strong>
                <span>WAI müşterilerinize anında yanıt verir.</span>
            </div>

        </div>


        <div class="wai-benefit">

            <div class="wai-benefit-icon">
                ↗
            </div>

            <div>
                <strong>Detaylı Raporlama</strong>
                <span>Kanal performansını anlık takip edin.</span>
            </div>

        </div>


        <div class="wai-benefit">

            <div class="wai-benefit-icon">
                ✓
            </div>

            <div>
                <strong>İnsan Devralma</strong>
                <span>Ekibiniz gerektiğinde görüşmeyi devralır.</span>
            </div>

        </div>

    </section>


    {{-- =========================================================
         BOTTOM
    ========================================================== --}}

    <section class="wai-ai-banner">

        <div class="wai-ai-gem">
            <span>W</span>
        </div>

        <div class="wai-ai-copy">

            <strong>
                İşletmenizi bir kez öğretin.
            </strong>

            <p>
                Her kanal için ayrı bir yapay zekâ kurmanız gerekmez.
                WAI, işletme bilgileriniz ve kurallarınızla bağlı
                tüm müşteri kanallarında aynı şekilde çalışır.
            </p>

        </div>


        <div class="wai-ai-logos">

            <div class="wai-brand-icon whatsapp">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M20 11.5a8 8 0 0 1-11.9 7L4 19.8l1.2-3.9A8 8 0 1 1 20 11.5Z"/>
                </svg>
            </div>

            <div class="wai-brand-icon instagram">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3.5" y="3.5" width="17" height="17" rx="5"/>
                    <circle cx="12" cy="12" r="4"/>
                </svg>
            </div>

            <div class="wai-brand-icon messenger">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 3C6.9 3 3 6.7 3 11.5c0 2.7 1.2 5 3.3 6.6V21l3-1.6c.9.3 1.8.5 2.7.5 5.1 0 9-3.7 9-8.4S17.1 3 12 3Z"/>
                </svg>
            </div>

            <div class="wai-brand-icon web">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="8.5"/>
                    <path d="M3.5 12h17"/>
                    <path d="M12 3.5c2.1 2.3 3.2 5.1 3.2 8.5S14.1 18.2 12 20.5"/>
                </svg>
            </div>

        </div>

    </section>

</div>

</x-filament-panels::page>
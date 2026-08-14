<x-filament-panels::page>

<style>
/* ==========================================================================
   WAI PREMIUM INBOX
   FINAL DESKTOP + TABLET + MOBILE
   ========================================================================== */

[x-cloak] {
    display: none !important;
}

.wai-inbox {
    --green: #20df78;
    --green-2: #58efa1;
    --green-dark: #07964c;
    --green-deep: #06713a;

    --ink: #0b110d;
    --text: #39453d;
    --muted: #6f7b73;
    --muted-2: #929c96;

    --white: #ffffff;
    --soft: #f7faf8;
    --soft-2: #f1f5f2;
    --green-soft: #effcf5;

    --line: #e4eae6;
    --line-dark: #d8dfdb;

    --shadow:
        0 22px 65px rgba(13,31,20,.065);

    width: 100%;
    max-width: 1500px;

    margin: 0 auto;
}

.wai-inbox *,
.wai-inbox *::before,
.wai-inbox *::after {
    box-sizing: border-box;
}


/* ==========================================================================
   PAGE HEADER
   ========================================================================== */

.wai-inbox-hero {
    position: relative;

    margin-bottom: 16px;
    padding: 25px 28px;

    overflow: hidden;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 24px;

    border: 1px solid var(--line);
    border-radius: 23px;

    background:
        radial-gradient(
            circle at 93% 40%,
            rgba(32,223,120,.10),
            transparent 28%
        ),
        linear-gradient(
            145deg,
            #ffffff,
            #fbfdfc
        );

    box-shadow:
        0 14px 45px rgba(13,31,20,.045);
}

.wai-inbox-hero::after {
    content: "";

    position: absolute;

    width: 270px;
    height: 270px;

    right: -145px;
    top: -120px;

    border-radius: 50%;

    border:
        1px solid rgba(32,223,120,.12);

    box-shadow:
        0 0 0 38px rgba(32,223,120,.025),
        0 0 0 76px rgba(32,223,120,.012);

    pointer-events: none;
}

.wai-inbox-hero-copy {
    position: relative;
    z-index: 2;
}

.wai-inbox-eyebrow {
    display: inline-flex;
    align-items: center;

    gap: 8px;

    color: var(--green-deep);

    font-size: 11px;
    font-weight: 900;

    letter-spacing: .8px;

    text-transform: uppercase;
}

.wai-inbox-eyebrow i {
    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: var(--green);

    box-shadow:
        0 0 0 4px rgba(32,223,120,.10);
}

.wai-inbox-hero h1 {
    margin: 7px 0 0;

    color: var(--ink);

    font-size: 30px;
    font-weight: 850;

    letter-spacing: -1px;
}

.wai-inbox-hero p {
    margin: 7px 0 0;

    color: var(--muted);

    font-size: 13px;

    line-height: 1.55;
}

.wai-inbox-status {
    position: relative;
    z-index: 2;

    min-height: 43px;

    padding: 0 14px;

    display: inline-flex;
    align-items: center;

    gap: 8px;

    border: 1px solid #caecd7;
    border-radius: 13px;

    color: var(--green-deep);

    background: var(--green-soft);

    font-size: 12px;
    font-weight: 850;

    white-space: nowrap;
}

.wai-inbox-status i {
    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: var(--green);
}


/* ==========================================================================
   APP SHELL
   ========================================================================== */

.wai-shell {
    display: grid;

    grid-template-columns:
        340px minmax(0,1fr) 310px;

    height: calc(100vh - 245px);
    min-height: 690px;
    max-height: 920px;

    overflow: hidden;

    border: 1px solid var(--line);
    border-radius: 25px;

    background: #fff;

    box-shadow: var(--shadow);
}


/* ==========================================================================
   LEFT SIDE
   ========================================================================== */

.wai-side {
    min-width: 0;
    min-height: 0;

    display: flex;
    flex-direction: column;

    overflow: hidden;

    border-right: 1px solid var(--line);

    background:
        linear-gradient(
            180deg,
            #ffffff,
            #fbfcfb
        );
}

.wai-side-head {
    padding: 18px;

    border-bottom: 1px solid var(--line);
}

.wai-side-head-top {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;
}

.wai-side-title {
    color: var(--ink);

    font-size: 18px;
    font-weight: 850;

    letter-spacing: -.45px;
}

.wai-side-count {
    min-height: 30px;

    padding: 0 9px;

    display: inline-flex;
    align-items: center;

    border-radius: 999px;

    color: var(--green-deep);

    background: var(--green-soft);

    font-size: 10px;
    font-weight: 900;
}


/* ==========================================================================
   SEARCH
   ========================================================================== */

.wai-search {
    position: relative;

    margin-top: 14px;
}

.wai-search svg {
    position: absolute;

    left: 13px;
    top: 50%;

    width: 18px;
    height: 18px;

    color: #849087;

    transform: translateY(-50%);

    pointer-events: none;
}

.wai-search input {
    width: 100%;
    height: 47px;

    padding: 0 14px 0 42px;

    outline: none;

    border: 1px solid var(--line-dark);
    border-radius: 13px;

    color: #263229;

    background: #f9fbfa;

    font-size: 13px;

    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        background .2s ease;
}

.wai-search input:focus {
    border-color: #90dfb0;

    background: #fff;

    box-shadow:
        0 0 0 4px rgba(32,223,120,.07);
}

.wai-search input::placeholder {
    color: #9aa49e;
}


/* ==========================================================================
   FILTERS
   ========================================================================== */

.wai-filters-wrap {
    padding: 12px 13px;

    border-bottom: 1px solid var(--line);

    background: #fff;
}

.wai-filters {
    display: flex;
    gap: 7px;

    overflow-x: auto;

    scrollbar-width: none;
}

.wai-filters::-webkit-scrollbar {
    display: none;
}

.wai-filter {
    min-height: 34px;

    padding: 0 11px;

    display: inline-flex;
    align-items: center;

    gap: 6px;

    flex: 0 0 auto;

    border: 1px solid var(--line);
    border-radius: 999px;

    color: #68756d;

    background: #fff;

    font-size: 11px;
    font-weight: 800;

    cursor: pointer;

    transition:
        color .2s ease,
        background .2s ease,
        border-color .2s ease;
}

.wai-filter:hover {
    border-color: #cfe6d7;
}

.wai-filter.active {
    border-color: #bce8cd;

    color: var(--green-deep);

    background: var(--green-soft);
}

.wai-filter svg {
    width: 14px;
    height: 14px;
}


/* ==========================================================================
   CHAT LIST
   ========================================================================== */

.wai-list {
    flex: 1;

    min-height: 0;

    overflow-y: auto;

    scrollbar-width: thin;
    scrollbar-color: #dce4de transparent;
}

.wai-chat {
    position: relative;

    width: 100%;

    padding: 14px 15px;

    border: 0;
    border-bottom: 1px solid #edf1ee;

    text-align: left;

    background: #fff;

    cursor: pointer;

    transition:
        background .18s ease;
}

.wai-chat:hover {
    background: #fafcfb;
}

.wai-chat.active {
    background:
        linear-gradient(
            90deg,
            #effcf5,
            #fbfefc
        );
}

.wai-chat.active::before {
    content: "";

    position: absolute;

    left: 0;
    top: 12px;
    bottom: 12px;

    width: 3px;

    border-radius: 0 3px 3px 0;

    background: var(--green);
}

.wai-chat-row {
    display: flex;
    align-items: flex-start;

    gap: 11px;
}

.wai-avatar {
    width: 47px;
    height: 47px;

    display: grid;
    place-items: center;

    flex: 0 0 47px;

    border: 1px solid #d0efdc;
    border-radius: 15px;

    color: var(--green-deep);

    background:
        linear-gradient(
            145deg,
            #effcf5,
            #e7faef
        );

    font-size: 15px;
    font-weight: 900;
}

.wai-chat-body {
    min-width: 0;
    flex: 1;
}

.wai-chat-head {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 8px;
}

.wai-name {
    min-width: 0;

    overflow: hidden;

    color: #1e2922;

    font-size: 13px;
    font-weight: 850;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.wai-time {
    flex: 0 0 auto;

    color: #9aa49e;

    font-size: 10px;
    font-weight: 650;
}

.wai-preview {
    margin-top: 5px;

    overflow: hidden;

    color: #768279;

    font-size: 11px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.wai-chat-meta {
    margin-top: 7px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 8px;
}

.wai-badge {
    min-height: 26px;

    padding: 0 8px;

    display: inline-flex;
    align-items: center;

    gap: 5px;

    border-radius: 999px;

    color: var(--green-deep);

    background: #eafaf0;

    font-size: 9px;
    font-weight: 850;
}

.wai-badge.human {
    color: #9a5c00;

    background: #fff6df;
}

.wai-badge svg {
    width: 12px;
    height: 12px;
}

.wai-unread {
    min-width: 21px;
    height: 21px;

    padding: 0 6px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 999px;

    color: #05321a;

    background: var(--green);

    font-size: 9px;
    font-weight: 900;
}

.wai-empty-list {
    padding: 38px 20px;

    text-align: center;

    color: #8e9992;

    font-size: 12px;
}


/* ==========================================================================
   MAIN CHAT
   ========================================================================== */

.wai-main {
    min-width: 0;
    min-height: 0;

    display: flex;
    flex-direction: column;

    background:
        radial-gradient(
            circle at 10% 0%,
            rgba(32,223,120,.035),
            transparent 28%
        ),
        #f5f7f5;
}


/* ==========================================================================
   CHAT HEADER
   ========================================================================== */

.wai-header {
    min-height: 76px;

    padding: 12px 16px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    border-bottom: 1px solid var(--line);

    background:
        rgba(255,255,255,.96);

    backdrop-filter: blur(12px);
}

.wai-header-left {
    display: flex;
    align-items: center;

    min-width: 0;

    gap: 11px;
}

.wai-mobile-back {
    display: none;

    width: 39px;
    height: 39px;

    border: 1px solid var(--line);
    border-radius: 11px;

    color: #526057;

    background: #fff;

    cursor: pointer;
}

.wai-header-avatar {
    width: 45px;
    height: 45px;

    display: grid;
    place-items: center;

    flex: 0 0 45px;

    border-radius: 14px;

    color: var(--green-deep);

    background: var(--green-soft);

    font-size: 14px;
    font-weight: 900;
}

.wai-header-info {
    min-width: 0;
}

.wai-header-name {
    overflow: hidden;

    color: var(--ink);

    font-size: 14px;
    font-weight: 850;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.wai-header-number {
    margin-top: 3px;

    color: #87928b;

    font-size: 11px;
}

.wai-actions {
    display: flex;
    align-items: center;

    gap: 7px;
}

.wai-control-badge {
    min-height: 31px;

    padding: 0 9px;

    display: inline-flex;
    align-items: center;

    gap: 6px;

    border-radius: 999px;

    font-size: 10px;
    font-weight: 850;
}

.wai-control-badge.ai {
    color: var(--green-deep);

    background: #eafaf0;
}

.wai-control-badge.human {
    color: #965b00;

    background: #fff5dc;
}

.wai-control-badge svg {
    width: 13px;
    height: 13px;
}

.wai-btn {
    min-height: 39px;

    padding: 0 12px;

    display: inline-flex;
    align-items: center;

    gap: 7px;

    border: 0;
    border-radius: 11px;

    color: #fff;

    font-size: 11px;
    font-weight: 850;

    cursor: pointer;
}

.wai-btn svg {
    width: 15px;
    height: 15px;
}

.wai-human {
    color: #422600;

    background:
        linear-gradient(
            135deg,
            #ffd877,
            #f4b83a
        );
}

.wai-ai {
    color: #05321a;

    background:
        linear-gradient(
            135deg,
            #6cf2a8,
            #2ddd7f
        );
}

.wai-crm-toggle {
    display: none;

    width: 39px;
    height: 39px;

    border: 1px solid var(--line);
    border-radius: 11px;

    color: #506057;

    background: #fff;

    cursor: pointer;
}


/* ==========================================================================
   MESSAGES
   ========================================================================== */

.wai-messages {
    flex: 1;

    min-height: 0;

    overflow-y: auto;

    padding: 20px 24px;

    scroll-behavior: smooth;

    scrollbar-width: thin;
    scrollbar-color: #d7dfd9 transparent;
}

.wai-date {
    margin: 14px 0;

    display: flex;
    justify-content: center;
}

.wai-date span {
    min-height: 29px;

    padding: 0 10px;

    display: inline-flex;
    align-items: center;

    border: 1px solid #e3e9e5;
    border-radius: 999px;

    color: #748078;

    background:
        rgba(255,255,255,.9);

    font-size: 10px;
    font-weight: 750;

    box-shadow:
        0 6px 16px rgba(13,31,20,.03);
}

.wai-row {
    display: flex;

    margin-bottom: 10px;
}

.wai-row.customer {
    justify-content: flex-start;
}

.wai-row.outgoing {
    justify-content: flex-end;
}

.wai-bubble {
    max-width: min(72%, 700px);

    padding: 10px 12px;

    border: 1px solid transparent;
    border-radius: 15px;

    font-size: 13px;

    box-shadow:
        0 7px 18px rgba(13,31,20,.045);
}

.wai-bubble.customer {
    border-color: #e4e9e6;

    border-top-left-radius: 5px;

    background: #fff;
}

.wai-bubble.ai {
    border-color: #bee9ce;

    border-top-right-radius: 5px;

    background:
        linear-gradient(
            145deg,
            #effcf5,
            #e6faee
        );
}

.wai-bubble.human {
    border-color: #d8e4ed;

    border-top-right-radius: 5px;

    background:
        linear-gradient(
            145deg,
            #f2f7fb,
            #e8f2f9
        );
}

.wai-sender {
    margin-bottom: 5px;

    display: flex;
    align-items: center;

    gap: 5px;

    color: #6f7c73;

    font-size: 9px;
    font-weight: 850;
}

.wai-sender svg {
    width: 12px;
    height: 12px;
}

.wai-text {
    color: #263129;

    white-space: pre-wrap;
    word-break: break-word;

    line-height: 1.55;
}

.wai-meta {
    margin-top: 5px;

    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 5px;

    color: #829087;

    font-size: 9px;
}

.wai-check {
    color: var(--green-dark);

    font-weight: 900;
}

.wai-media {
    display: block;

    max-width: 320px;
    max-height: 330px;

    border-radius: 11px;
}

.wai-audio {
    width: 290px;
    max-width: 100%;
}

.wai-doc {
    min-width: 250px;

    padding: 11px;

    display: flex;
    align-items: center;

    gap: 10px;

    border: 1px solid rgba(0,0,0,.06);
    border-radius: 11px;

    color: inherit;

    background: rgba(255,255,255,.55);

    text-decoration: none;
}

.wai-doc-icon {
    width: 38px;
    height: 38px;

    display: grid;
    place-items: center;

    flex: 0 0 38px;

    border-radius: 10px;

    color: var(--green-dark);

    background: var(--green-soft);
}

.wai-doc-icon svg {
    width: 19px;
    height: 19px;
}

.wai-no-message {
    height: 100%;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 30px;

    text-align: center;

    color: #7b877f;

    font-size: 13px;
}


/* ==========================================================================
   COMPOSER
   ========================================================================== */

.wai-composer {
    padding: 11px 14px 13px;

    border-top: 1px solid var(--line);

    background:
        rgba(255,255,255,.97);

    backdrop-filter: blur(12px);
}

.wai-composer-top {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;

    margin-bottom: 8px;
}

.wai-tools {
    position: relative;

    display: flex;
    align-items: center;

    gap: 4px;
}

.wai-icon {
    width: 38px;
    height: 38px;

    display: grid;
    place-items: center;

    padding: 0;

    border: 0;
    border-radius: 11px;

    color: #66746b;

    background: transparent;

    cursor: pointer;

    transition:
        color .2s ease,
        background .2s ease;
}

.wai-icon:hover {
    color: var(--green-dark);

    background: var(--green-soft);
}

.wai-icon.active {
    color: #b56e00;

    background: #fff4da;
}

.wai-icon svg {
    width: 20px;
    height: 20px;
}

.wai-upload {
    display: none;
}

.wai-recording {
    display: inline-flex;
    align-items: center;

    gap: 7px;

    color: #bc3b3b;

    font-size: 10px;
    font-weight: 850;
}

.wai-recording i {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #ef4444;

    animation: waiPulse 1s infinite;
}

@keyframes waiPulse {
    50% {
        opacity: .35;
    }
}


/* ==========================================================================
   EMOJI
   ========================================================================== */

.wai-emoji {
    position: absolute;

    left: 0;
    bottom: 45px;

    z-index: 50;

    width: 280px;

    padding: 9px;

    display: grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap: 4px;

    border: 1px solid var(--line);
    border-radius: 15px;

    background: #fff;

    box-shadow:
        0 20px 50px rgba(13,31,20,.15);
}

.wai-emoji button {
    width: 39px;
    height: 39px;

    display: grid;
    place-items: center;

    border: 0;
    border-radius: 9px;

    background: transparent;

    font-size: 20px;

    cursor: pointer;
}

.wai-emoji button:hover {
    background: var(--soft-2);
}


/* ==========================================================================
   MEDIA BOX
   ========================================================================== */

.wai-media-box {
    margin-bottom: 9px;
    padding: 9px;

    display: flex;
    align-items: center;

    gap: 8px;

    border: 1px solid #dce6df;
    border-radius: 13px;

    background: #f8fbf9;
}

.wai-media-box-icon {
    width: 38px;
    height: 38px;

    display: grid;
    place-items: center;

    flex: 0 0 38px;

    border-radius: 10px;

    color: var(--green-dark);

    background: var(--green-soft);
}

.wai-media-box-icon svg {
    width: 19px;
    height: 19px;
}

.wai-media-box input {
    min-width: 0;
    flex: 1;

    height: 38px;

    padding: 0 10px;

    outline: 0;

    border: 0;

    color: #344039;

    background: transparent;

    font-size: 12px;
}

.wai-media-send,
.wai-media-cancel {
    min-height: 37px;

    padding: 0 11px;

    border: 0;
    border-radius: 9px;

    font-size: 10px;
    font-weight: 850;

    cursor: pointer;
}

.wai-media-send {
    color: #05321a;

    background:
        linear-gradient(
            135deg,
            #65f0a3,
            #2ddd7f
        );
}

.wai-media-cancel {
    color: #5a675f;

    background: #e9eeeb;
}


/* ==========================================================================
   QUICK REPLIES
   ========================================================================== */

.wai-quick {
    margin-bottom: 8px;

    display: flex;
    align-items: center;

    gap: 6px;

    overflow-x: auto;

    scrollbar-width: none;
}

.wai-quick::-webkit-scrollbar {
    display: none;
}

.wai-quick-label {
    flex: 0 0 auto;

    color: #87928b;

    font-size: 10px;
    font-weight: 800;
}

.wai-quick button {
    min-height: 31px;

    padding: 0 10px;

    flex: 0 0 auto;

    border: 1px solid var(--line);
    border-radius: 999px;

    color: #657168;

    background: #fff;

    font-size: 10px;
    font-weight: 700;

    cursor: pointer;
}

.wai-quick button:hover {
    color: var(--green-deep);

    border-color: #c7ead5;

    background: var(--green-soft);
}


/* ==========================================================================
   INPUT
   ========================================================================== */

.wai-compose-row {
    display: flex;
    align-items: flex-end;

    gap: 8px;
}

.wai-compose-field {
    position: relative;

    min-width: 0;
    flex: 1;
}

.wai-textarea {
    width: 100%;

    min-height: 50px;
    max-height: 130px;

    padding: 14px;

    outline: none;

    resize: none;

    border: 1px solid var(--line-dark);
    border-radius: 14px;

    color: #263229;

    background: #fff;

    font-family: inherit;
    font-size: 13px;

    line-height: 1.45;

    transition:
        border-color .2s ease,
        box-shadow .2s ease;
}

.wai-textarea:focus {
    border-color: #91dfb0;

    box-shadow:
        0 0 0 4px rgba(32,223,120,.065);
}

.wai-textarea::placeholder {
    color: #a1aaa4;
}

.wai-send {
    width: 52px;
    height: 50px;

    display: grid;
    place-items: center;

    padding: 0;

    flex: 0 0 52px;

    border: 0;
    border-radius: 14px;

    color: #06321a;

    background:
        linear-gradient(
            135deg,
            #69f1a6,
            #2edf81
        );

    box-shadow:
        0 10px 24px rgba(32,223,120,.18);

    cursor: pointer;
}

.wai-send svg {
    width: 20px;
    height: 20px;
}

.wai-send:disabled {
    opacity: .6;
}

.wai-hint {
    margin-top: 6px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;

    color: #939d97;

    font-size: 9px;
}

.wai-typing {
    color: var(--green-dark);

    font-weight: 800;
}

.wai-human-note {
    margin-top: 5px;

    color: #7e8a82;

    font-size: 10px;
}


/* ==========================================================================
   CRM
   ========================================================================== */

.wai-crm {
    min-width: 0;
    min-height: 0;

    overflow-y: auto;

    border-left: 1px solid var(--line);

    background: #fff;

    scrollbar-width: thin;
    scrollbar-color: #dce4de transparent;
}

.wai-crm-profile {
    position: relative;

    padding: 24px 18px 20px;

    overflow: hidden;

    text-align: center;

    border-bottom: 1px solid var(--line);

    background:
        radial-gradient(
            circle at 50% 0%,
            rgba(32,223,120,.09),
            transparent 52%
        );
}

.wai-crm-avatar {
    width: 72px;
    height: 72px;

    margin: 0 auto 11px;

    display: grid;
    place-items: center;

    border: 1px solid #c7ebd5;
    border-radius: 21px;

    color: var(--green-deep);

    background:
        linear-gradient(
            145deg,
            #effcf5,
            #e5faee
        );

    font-size: 24px;
    font-weight: 900;

    box-shadow:
        0 12px 30px rgba(32,223,120,.08);
}

.wai-crm-name {
    color: var(--ink);

    font-size: 17px;
    font-weight: 850;
}

.wai-crm-number {
    margin-top: 4px;

    color: #78847c;

    font-size: 11px;
}

.wai-crm-state {
    margin-top: 12px;

    min-height: 30px;

    padding: 0 10px;

    display: inline-flex;
    align-items: center;

    gap: 6px;

    border-radius: 999px;

    font-size: 10px;
    font-weight: 850;
}

.wai-crm-state.ai {
    color: var(--green-deep);

    background: #eafaf0;
}

.wai-crm-state.human {
    color: #985c00;

    background: #fff5dc;
}

.wai-crm-state i {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: currentColor;
}


/* ==========================================================================
   CRM SECTIONS
   ========================================================================== */

.wai-section {
    padding: 17px;

    border-bottom: 1px solid var(--line);
}

.wai-title {
    margin-bottom: 11px;

    color: #6b786f;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .7px;

    text-transform: uppercase;
}

.wai-info {
    min-height: 37px;

    padding: 7px 0;

    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 10px;

    color: #718077;

    font-size: 11px;
}

.wai-info span:last-child {
    max-width: 58%;

    color: #344139;

    text-align: right;

    font-weight: 800;

    word-break: break-word;
}


/* ==========================================================================
   TAGS
   ========================================================================== */

.wai-tags {
    display: flex;
    flex-wrap: wrap;

    gap: 6px;
}

.wai-tag {
    min-height: 30px;

    padding: 0 8px;

    display: inline-flex;
    align-items: center;

    gap: 6px;

    border: 1px solid #dce7df;
    border-radius: 999px;

    color: #526159;

    background: #f6f9f7;

    font-size: 10px;
    font-weight: 800;
}

.wai-tag button {
    width: 18px;
    height: 18px;

    display: grid;
    place-items: center;

    padding: 0;

    border: 0;
    border-radius: 50%;

    color: #89958d;

    background: #e8edea;

    cursor: pointer;
}

.wai-presets {
    margin-top: 10px;

    display: flex;
    flex-wrap: wrap;

    gap: 6px;
}

.wai-preset {
    min-height: 31px;

    padding: 0 9px;

    border: 1px solid var(--line);
    border-radius: 999px;

    color: #66736a;

    background: #fff;

    font-size: 10px;
    font-weight: 750;

    cursor: pointer;
}

.wai-preset:hover {
    color: var(--green-deep);

    border-color: #c7ead5;

    background: var(--green-soft);
}

.wai-tag-form {
    margin-top: 10px;

    display: flex;

    gap: 6px;
}

.wai-tag-form input {
    min-width: 0;
    height: 39px;

    flex: 1;

    padding: 0 10px;

    outline: none;

    border: 1px solid var(--line-dark);
    border-radius: 10px;

    color: #35423a;

    font-size: 11px;
}

.wai-tag-form button {
    min-width: 54px;
    height: 39px;

    border: 0;
    border-radius: 10px;

    color: #06321a;

    background:
        linear-gradient(
            135deg,
            #68f1a5,
            #2ddd7f
        );

    font-size: 10px;
    font-weight: 850;

    cursor: pointer;
}

.wai-note {
    padding: 11px;

    border: 1px solid #e2e9e4;
    border-radius: 11px;

    color: #7a867e;

    background: #f8faf9;

    font-size: 10px;

    line-height: 1.55;
}


/* ==========================================================================
   MOBILE CRM OVERLAY
   ========================================================================== */

.wai-mobile-crm-overlay {
    display: none;
}


/* ==========================================================================
   TABLET
   ========================================================================== */

@media (max-width: 1180px) {

    .wai-shell {
        grid-template-columns:
            300px minmax(0,1fr);
    }

    .wai-crm {
        display: none;
    }

    .wai-crm-toggle {
        display: grid;
        place-items: center;
    }

    .wai-mobile-crm-overlay {
        position: fixed;

        inset: 0;

        z-index: 9999;

        display: block;

        background: rgba(5,16,10,.28);

        backdrop-filter: blur(4px);
    }

    .wai-mobile-crm-panel {
        position: absolute;

        top: 0;
        right: 0;

        width: min(360px, 90vw);
        height: 100%;

        overflow-y: auto;

        background: #fff;

        box-shadow:
            -25px 0 70px rgba(7,20,12,.18);
    }

    .wai-mobile-crm-close {
        position: sticky;

        top: 0;

        z-index: 2;

        width: 100%;

        padding: 13px;

        display: flex;
        justify-content: flex-end;

        background:
            rgba(255,255,255,.95);

        backdrop-filter: blur(10px);
    }

    .wai-mobile-crm-close button {
        width: 40px;
        height: 40px;

        display: grid;
        place-items: center;

        border: 1px solid var(--line);
        border-radius: 11px;

        color: #5b685f;

        background: #fff;

        cursor: pointer;
    }

    .wai-mobile-crm-close svg {
        width: 18px;
        height: 18px;
    }
}


/* ==========================================================================
   MOBILE
   ========================================================================== */

@media (max-width: 780px) {

    .wai-inbox-hero {
        padding: 18px;

        display: block;

        border-radius: 19px;
    }

    .wai-inbox-hero h1 {
        font-size: 24px;
    }

    .wai-inbox-hero p {
        font-size: 13px;
    }

    .wai-inbox-status {
        width: 100%;

        margin-top: 13px;

        justify-content: center;
    }


    .wai-shell {
        display: block;

        height: calc(100vh - 205px);
        min-height: 650px;

        border-radius: 20px;
    }

    .wai-side,
    .wai-main {
        width: 100%;
        height: 100%;
    }

    .wai-side {
        border-right: 0;
    }

    .wai-mobile-hide {
        display: none !important;
    }

    .wai-mobile-show {
        display: flex !important;
    }


    /* LIST */

    .wai-side-head {
        padding: 15px;
    }

    .wai-side-title {
        font-size: 18px;
    }

    .wai-chat {
        padding: 14px;
    }

    .wai-avatar {
        width: 49px;
        height: 49px;

        flex-basis: 49px;
    }

    .wai-name {
        font-size: 14px;
    }

    .wai-preview {
        font-size: 12px;
    }

    .wai-time {
        font-size: 10px;
    }


    /* HEADER */

    .wai-header {
        min-height: 70px;

        padding: 10px;
    }

    .wai-mobile-back {
        display: grid;
        place-items: center;
    }

    .wai-header-avatar {
        width: 41px;
        height: 41px;

        flex-basis: 41px;
    }

    .wai-header-name {
        font-size: 13px;
    }

    .wai-header-number {
        font-size: 10px;
    }

    .wai-control-badge {
        display: none;
    }

    .wai-btn {
        width: 39px;
        height: 39px;

        padding: 0;

        display: grid;
        place-items: center;
    }

    .wai-btn span {
        display: none;
    }

    .wai-crm-toggle {
        display: grid;
        place-items: center;
    }


    /* MESSAGES */

    .wai-messages {
        padding: 15px 11px;
    }

    .wai-bubble {
        max-width: 88%;

        padding: 10px 11px;

        font-size: 13px;
    }

    .wai-media {
        max-width: min(260px, 75vw);
    }

    .wai-doc {
        min-width: 0;
        max-width: 75vw;
    }


    /* COMPOSER */

    .wai-composer {
        padding: 9px;
    }

    .wai-composer-top {
        margin-bottom: 6px;
    }

    .wai-icon {
        width: 37px;
        height: 37px;
    }

    .wai-quick {
        margin-bottom: 7px;
    }

    .wai-quick button {
        font-size: 10px;
    }

    .wai-textarea {
        min-height: 50px;

        padding: 13px;

        font-size: 16px;
    }

    .wai-send {
        width: 50px;
        height: 50px;

        flex-basis: 50px;
    }

    .wai-hint {
        display: none;
    }

    .wai-human-note {
        font-size: 10px;
    }

    .wai-emoji {
        position: fixed;

        left: 10px;
        right: 10px;
        bottom: 90px;

        width: auto;

        grid-template-columns:
            repeat(6,1fr);
    }

    .wai-emoji button {
        width: 100%;
    }

    .wai-media-box {
        flex-wrap: wrap;
    }

    .wai-media-box input {
        flex-basis:
            calc(100% - 50px);
    }
}


/* ==========================================================================
   SMALL PHONE
   ========================================================================== */

@media (max-width: 390px) {

    .wai-header-number {
        max-width: 120px;

        overflow: hidden;

        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .wai-actions {
        gap: 4px;
    }

    .wai-btn,
    .wai-crm-toggle {
        width: 36px;
        height: 36px;
    }

    .wai-emoji {
        grid-template-columns:
            repeat(5,1fr);
    }
}
</style>


<div
    class="wai-inbox"

    x-data="{
        emojiOpen: false,
        recording: false,
        mediaRecorder: null,
        audioChunks: [],
        typing: false,
        mobileView: {{ $selectedConversationId ? "'chat'" : "'list'" }},
        crmOpen: false,

        quickReplies: [
            'Merhaba, size nasıl yardımcı olabilirim?',
            'Fiyat bilgisi için hemen yardımcı olabilirim.',
            'Bilgilerinizi aldım. Kısa süre içinde dönüş yapacağız.',
            'Siparişiniz için gerekli bilgileri paylaşabilir misiniz?'
        ],

        insertText(text) {
            this.$wire.messageText = this.$wire.messageText
                ? this.$wire.messageText + ' ' + text
                : text;

            this.emojiOpen = false;
        },

        sendOnEnter(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                this.$wire.sendMessage();
                this.typing = false;
            }
        },

        async toggleRecord() {
            if (this.recording) {
                this.mediaRecorder?.stop();
                return;
            }

            if (! navigator.mediaDevices?.getUserMedia) {
                alert('Tarayıcınız ses kaydını desteklemiyor.');
                return;
            }

            try {
                const stream =
                    await navigator.mediaDevices.getUserMedia({
                        audio: true
                    });

                this.audioChunks = [];

                this.mediaRecorder =
                    new MediaRecorder(stream);

                this.mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        this.audioChunks.push(event.data);
                    }
                };

                this.mediaRecorder.onstop = () => {
                    const blob =
                        new Blob(
                            this.audioChunks,
                            {
                                type: 'audio/webm'
                            }
                        );

                    const reader =
                        new FileReader();

                    reader.onloadend = () => {
                        this.$wire.sendRecordedAudio(
                            reader.result
                        );
                    };

                    reader.readAsDataURL(blob);

                    stream
                        .getTracks()
                        .forEach(
                            track => track.stop()
                        );

                    this.recording = false;
                };

                this.mediaRecorder.start();
                this.recording = true;

            } catch (error) {
                alert('Mikrofon erişimi alınamadı.');
            }
        }
    }"

    wire:poll.4s
>


    {{-- ============================================================
         PREMIUM PAGE HEADER
    ============================================================= --}}

    <section class="wai-inbox-hero">

        <div class="wai-inbox-hero-copy">

            <div class="wai-inbox-eyebrow">
                <i></i>
                WAI · Gelen Kutusu
            </div>

            <h1>
                Tüm müşteri görüşmeleriniz tek yerde.
            </h1>

            <p>
                WAI ve ekibiniz müşteri konuşmalarını aynı merkezden yönetsin.
            </p>

        </div>

        <div class="wai-inbox-status">
            <i></i>
            Canlı Mesaj Merkezi
        </div>

    </section>


    <div class="wai-shell">


        {{-- ============================================================
             LEFT — CONVERSATIONS
        ============================================================= --}}

        <aside
            class="wai-side"
            :class="mobileView === 'list' ? 'wai-mobile-show' : 'wai-mobile-hide'"
        >

            <div class="wai-side-head">

                <div class="wai-side-head-top">

                    <div class="wai-side-title">
                        Görüşmeler
                    </div>

                    <div class="wai-side-count">
                        {{ $this->conversations->count() }}
                    </div>

                </div>


                <div class="wai-search">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    >
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m20 20-4-4"/>
                    </svg>

                    <input
                        wire:model.live.debounce.400ms="search"
                        placeholder="İsim, telefon veya firma ara..."
                    >

                </div>

            </div>


            {{-- FILTERS --}}

            <div class="wai-filters-wrap">

                <div class="wai-filters">

                    <button
                        type="button"
                        class="wai-filter {{ $filter === 'all' && $filterTag === '' ? 'active' : '' }}"
                        wire:click="setFilter('all')"
                    >
                        Tümü
                    </button>


                    <button
                        type="button"
                        class="wai-filter {{ $filter === 'unread' ? 'active' : '' }}"
                        wire:click="setFilter('unread')"
                    >

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M5 5h14v12H8l-3 2V5Z"/>
                            <circle cx="18" cy="5" r="2.5" fill="currentColor" stroke="none"/>
                        </svg>

                        Okunmamış

                    </button>


                    <button
                        type="button"
                        class="wai-filter {{ $filter === 'human' ? 'active' : '' }}"
                        wire:click="setFilter('human')"
                    >

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <circle cx="12" cy="8" r="3"/>
                            <path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/>
                        </svg>

                        İnsan

                    </button>


                    <button
                        type="button"
                        class="wai-filter {{ $filter === 'ai' ? 'active' : '' }}"
                        wire:click="setFilter('ai')"
                    >

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <rect x="5" y="6" width="14" height="13" rx="3"/>
                            <circle cx="9" cy="12" r="1"/>
                            <circle cx="15" cy="12" r="1"/>
                            <path d="M9 16h6"/>
                            <path d="M12 3v3"/>
                        </svg>

                        AI

                    </button>


                    @foreach (
                        [
                            'Yeni Müşteri',
                            'Sıcak Müşteri',
                            'Teklif Bekliyor',
                            'Sipariş',
                            'VIP',
                            'Acil'
                        ] as $tag
                    )

                        <button
                            type="button"
                            class="wai-filter {{ $filterTag === $tag ? 'active' : '' }}"
                            wire:click="setTagFilter(@js($tag))"
                        >
                            {{ $tag }}
                        </button>

                    @endforeach

                </div>

            </div>


            {{-- CHAT LIST --}}

            <div class="wai-list">

                @forelse (
                    $this->conversations
                    as $conversation
                )

                    @php
                        $name =
                            $conversation->customer_name
                            ?: $conversation->whatsapp_number;

                        $initial =
                            mb_strtoupper(
                                mb_substr(
                                    $name,
                                    0,
                                    1
                                )
                            );

                        $lastAt =
                            $conversation->last_message_at;
                    @endphp


                    <button
                        type="button"

                        class="
                            wai-chat
                            {{ $selectedConversationId === $conversation->id ? 'active' : '' }}
                        "

                        wire:click="
                            selectConversation(
                                {{ $conversation->id }}
                            )
                        "

                        @click="mobileView = 'chat'"
                    >

                        <div class="wai-chat-row">

                            <div class="wai-avatar">
                                {{ $initial }}
                            </div>


                            <div class="wai-chat-body">

                                <div class="wai-chat-head">

                                    <span class="wai-name">
                                        {{ $name }}
                                    </span>

                                    <span class="wai-time">
                                        {{ $lastAt?->format('H:i') }}
                                    </span>

                                </div>


                                <div class="wai-preview">
                                    {{ $conversation->last_message_preview }}
                                </div>


                                <div class="wai-chat-meta">

                                    <span
                                        class="
                                            wai-badge
                                            {{ $conversation->human_takeover ? 'human' : '' }}
                                        "
                                    >

                                        @if ($conversation->human_takeover)

                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <circle cx="12" cy="8" r="3"/>
                                                <path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/>
                                            </svg>

                                            İnsan

                                        @else

                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <rect x="5" y="6" width="14" height="13" rx="3"/>
                                                <circle cx="9" cy="12" r="1"/>
                                                <circle cx="15" cy="12" r="1"/>
                                                <path d="M9 16h6"/>
                                            </svg>

                                            AI

                                        @endif

                                    </span>


                                    @if (
                                        (int) $conversation->unread_count > 0
                                    )

                                        <span class="wai-unread">
                                            {{ $conversation->unread_count }}
                                        </span>

                                    @endif

                                </div>

                            </div>

                        </div>

                    </button>

                @empty

                    <div class="wai-empty-list">
                        Konuşma bulunamadı.
                    </div>

                @endforelse

            </div>

        </aside>


        {{-- ============================================================
             CENTER — CHAT
        ============================================================= --}}

        <main
            class="wai-main"
            :class="mobileView === 'chat' ? 'wai-mobile-show' : 'wai-mobile-hide'"
        >

            @if ($this->selectedConversation)

                @php
                    $selectedName =
                        $this->selectedConversation->customer_name
                        ?: $this->selectedConversation->whatsapp_number;

                    $selectedInitial =
                        mb_strtoupper(
                            mb_substr(
                                $selectedName,
                                0,
                                1
                            )
                        );
                @endphp


                {{-- HEADER --}}

                <header class="wai-header">

                    <div class="wai-header-left">

                        <button
                            type="button"
                            class="wai-mobile-back"
                            @click="mobileView = 'list'"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            >
                                <path d="m15 18-6-6 6-6"/>
                            </svg>
                        </button>


                        <div class="wai-header-avatar">
                            {{ $selectedInitial }}
                        </div>


                        <div class="wai-header-info">

                            <div class="wai-header-name">
                                {{ $selectedName }}
                            </div>

                            <div class="wai-header-number">
                                {{ $this->selectedConversation->whatsapp_number }}
                            </div>

                        </div>

                    </div>


                    <div class="wai-actions">

                        @if (
                            $this->selectedConversation->human_takeover
                        )

                            <span class="wai-control-badge human">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <circle cx="12" cy="8" r="3"/>
                                    <path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/>
                                </svg>

                                İnsan Yönetiyor

                            </span>


                            <button
                                type="button"
                                class="wai-btn wai-ai"
                                wire:click="releaseToAi"
                                title="AI'ye geri ver"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <rect x="5" y="6" width="14" height="13" rx="3"/>
                                    <circle cx="9" cy="12" r="1"/>
                                    <circle cx="15" cy="12" r="1"/>
                                    <path d="M9 16h6"/>
                                </svg>

                                <span>
                                    AI'ye Geri Ver
                                </span>

                            </button>

                        @else

                            <span class="wai-control-badge ai">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <rect x="5" y="6" width="14" height="13" rx="3"/>
                                    <circle cx="9" cy="12" r="1"/>
                                    <circle cx="15" cy="12" r="1"/>
                                    <path d="M9 16h6"/>
                                </svg>

                                AI Aktif

                            </span>


                            <button
                                type="button"
                                class="wai-btn wai-human"
                                wire:click="takeOver"
                                title="Konuşmayı devral"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <circle cx="12" cy="8" r="3"/>
                                    <path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/>
                                </svg>

                                <span>
                                    İnsan Devral
                                </span>

                            </button>

                        @endif


                        <button
                            type="button"
                            class="wai-crm-toggle"
                            @click="crmOpen = true"
                            title="Müşteri bilgileri"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle cx="12" cy="8" r="3"/>
                                <path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/>
                                <path d="M18 5h3M19.5 3.5v3"/>
                            </svg>

                        </button>

                    </div>

                </header>


                {{-- MESSAGES --}}

                <section
                    class="wai-messages"

                    x-data

                    x-init="
                        $nextTick(
                            () => {
                                $el.scrollTop =
                                    $el.scrollHeight
                            }
                        )
                    "

                    wire:key="
                        messages-
                        {{ $selectedConversationId }}-
                        {{ $this->messages->count() }}
                    "
                >

                    @php
                        $lastDate = null;
                    @endphp


                    @forelse (
                        $this->messages
                        as $message
                    )

                        @php
                            $dateKey =
                                $message->created_at?->format(
                                    'Y-m-d'
                                );

                            $senderType =
                                $message->sender_type
                                ?: (
                                    $message->role === 'assistant'
                                        ? 'ai'
                                        : 'customer'
                                );

                            $isCustomer =
                                $senderType === 'customer';

                            $isHuman =
                                $senderType === 'human';

                            $type =
                                $message->message_type
                                ?: 'text';

                            $status =
                                $message->status
                                ?: 'sent';
                        @endphp


                        @if (
                            $dateKey !== $lastDate
                        )

                            <div class="wai-date">

                                <span>
                                    {{ $message->created_at?->format('d.m.Y') }}
                                </span>

                            </div>

                            @php
                                $lastDate = $dateKey;
                            @endphp

                        @endif


                        <div
                            class="
                                wai-row
                                {{ $isCustomer ? 'customer' : 'outgoing' }}
                            "
                        >

                            <div
                                class="
                                    wai-bubble

                                    {{
                                        $isCustomer
                                            ? 'customer'
                                            : (
                                                $isHuman
                                                    ? 'human'
                                                    : 'ai'
                                            )
                                    }}
                                "
                            >

                                <div class="wai-sender">

                                    @if ($isCustomer)

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <circle cx="12" cy="8" r="3"/>
                                            <path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/>
                                        </svg>

                                        Müşteri

                                    @elseif ($isHuman)

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <circle cx="12" cy="8" r="3"/>
                                            <path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/>
                                        </svg>

                                        {{
                                            $message->sentByUser?->name
                                            ?? 'Personel'
                                        }}

                                    @else

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <rect x="5" y="6" width="14" height="13" rx="3"/>
                                            <circle cx="9" cy="12" r="1"/>
                                            <circle cx="15" cy="12" r="1"/>
                                            <path d="M9 16h6"/>
                                        </svg>

                                        Yapay Zekâ

                                    @endif

                                </div>


                                {{-- IMAGE --}}

                                @if (
                                    $type === 'image'
                                    && $message->media_url
                                )

                                    <img
                                        class="wai-media"
                                        src="{{ $message->media_url }}"
                                        alt="Fotoğraf"
                                    >


                                {{-- VIDEO --}}

                                @elseif (
                                    $type === 'video'
                                    && $message->media_url
                                )

                                    <video
                                        class="wai-media"
                                        controls
                                        src="{{ $message->media_url }}"
                                    ></video>


                                {{-- AUDIO --}}

                                @elseif (
                                    $type === 'audio'
                                    && $message->media_url
                                )

                                    <audio
                                        class="wai-audio"
                                        controls
                                        src="{{ $message->media_url }}"
                                    ></audio>


                                {{-- DOCUMENT --}}

                                @elseif (
                                    $type === 'document'
                                    && $message->media_url
                                )

                                    <a
                                        class="wai-doc"
                                        href="{{ $message->media_url }}"
                                        target="_blank"
                                        rel="noopener"
                                    >

                                        <div class="wai-doc-icon">

                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path d="M7 3h7l4 4v14H7V3Z"/>
                                                <path d="M14 3v5h5"/>
                                            </svg>

                                        </div>

                                        <span>
                                            {{
                                                $message->media_filename
                                                ?: 'Belgeyi aç'
                                            }}
                                        </span>

                                    </a>

                                @endif


                                {{-- TEXT / CAPTION --}}

                                @if ($message->media_caption)

                                    <div
                                        class="wai-text"
                                        style="margin-top:7px"
                                    >
                                        {{ $message->media_caption }}
                                    </div>

                                @elseif (
                                    $type === 'text'
                                    || ! $message->media_url
                                )

                                    <div class="wai-text">
                                        {{ $message->message }}
                                    </div>

                                @endif


                                <div class="wai-meta">

                                    <span>
                                        {{ $message->created_at?->format('H:i') }}
                                    </span>

                                    @if (! $isCustomer)

                                        <span class="wai-check">

                                            @switch ($status)

                                                @case('error')
                                                    !
                                                    @break

                                                @default
                                                    ✓✓

                                            @endswitch

                                        </span>

                                    @endif

                                </div>

                            </div>

                        </div>

                    @empty

                        <div class="wai-no-message">
                            Bu konuşmada henüz mesaj bulunmuyor.
                        </div>

                    @endforelse

                </section>


                {{-- ============================================================
                     COMPOSER
                ============================================================= --}}

                <form
                    class="wai-composer"

                    wire:submit="sendMessage"

                    x-on:submit="typing = false"
                >


                    <div class="wai-composer-top">

                        <div class="wai-tools">


                            {{-- EMOJI --}}

                            <button
                                type="button"
                                class="wai-icon"
                                @click="emojiOpen = ! emojiOpen"
                                title="Emoji"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <circle cx="12" cy="12" r="9"/>
                                    <circle cx="9" cy="10" r="1"/>
                                    <circle cx="15" cy="10" r="1"/>
                                    <path d="M8 14c1 2 2.3 3 4 3s3-1 4-3"/>
                                </svg>

                            </button>


                            {{-- MEDIA --}}

                            <label
                                class="wai-icon"
                                title="Fotoğraf, video veya belge"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                >
                                    <path d="M9 12.5 14.5 7a3 3 0 0 1 4.2 4.2l-7 7a5 5 0 0 1-7.1-7.1l7.2-7.2"/>
                                </svg>


                                <input
                                    class="wai-upload"

                                    type="file"

                                    wire:model="mediaUpload"

                                    accept="
                                        image/*,
                                        video/*,
                                        .pdf,
                                        .doc,
                                        .docx,
                                        .xls,
                                        .xlsx,
                                        .txt
                                    "

                                    @change="$wire.mediaType = 'media'"
                                >

                            </label>


                            {{-- RECORD --}}

                            <button
                                type="button"

                                class="wai-icon"

                                :class="recording ? 'active' : ''"

                                @click="toggleRecord()"

                                title="Ses kaydı"
                            >

                                <svg
                                    x-show="! recording"

                                    viewBox="0 0 24 24"

                                    fill="none"

                                    stroke="currentColor"

                                    stroke-width="1.8"

                                    stroke-linecap="round"
                                >
                                    <rect x="9" y="3" width="6" height="11" rx="3"/>
                                    <path d="M6 11a6 6 0 0 0 12 0"/>
                                    <path d="M12 17v4"/>
                                </svg>


                                <svg
                                    x-show="recording"

                                    x-cloak

                                    viewBox="0 0 24 24"

                                    fill="none"

                                    stroke="currentColor"

                                    stroke-width="1.8"
                                >
                                    <rect x="7" y="7" width="10" height="10" rx="2"/>
                                </svg>

                            </button>


                            {{-- EMOJI PANEL --}}

                            <div
                                class="wai-emoji"

                                x-show="emojiOpen"

                                x-cloak

                                @click.outside="emojiOpen = false"
                            >

                                @foreach (
                                    [
                                        '😀',
                                        '😂',
                                        '😍',
                                        '👍',
                                        '🙏',
                                        '❤️',
                                        '🔥',
                                        '🎉',
                                        '📦',
                                        '💰',
                                        '😊',
                                        '👋',
                                        '👏',
                                        '🤝',
                                        '✅',
                                        '❌',
                                        '⭐',
                                        '🚀'
                                    ]
                                    as $emoji
                                )

                                    <button
                                        type="button"

                                        @click="
                                            insertText(
                                                @js($emoji)
                                            )
                                        "
                                    >
                                        {{ $emoji }}
                                    </button>

                                @endforeach

                            </div>

                        </div>


                        <div
                            class="wai-recording"
                            x-show="recording"
                            x-cloak
                        >
                            <i></i>
                            Ses kaydediliyor
                        </div>

                    </div>


                    {{-- MEDIA UPLOAD READY --}}

                    @if ($mediaUpload)

                        <div class="wai-media-box">

                            <div class="wai-media-box-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path d="M7 3h7l4 4v14H7V3Z"/>
                                    <path d="M14 3v5h5"/>
                                </svg>

                            </div>


                            <input
                                wire:model="mediaCaption"
                                placeholder="Açıklama ekleyin (isteğe bağlı)"
                            >


                            <button
                                type="button"
                                class="wai-media-send"
                                wire:click="sendMediaMessage"
                            >
                                Gönder
                            </button>


                            <button
                                type="button"
                                class="wai-media-cancel"
                                wire:click="clearMedia"
                            >
                                İptal
                            </button>

                        </div>

                    @endif


                    {{-- QUICK REPLIES --}}

                    <div class="wai-quick">

                        <span class="wai-quick-label">
                            Hızlı
                        </span>

                        <template
                            x-for="reply in quickReplies"
                            :key="reply"
                        >

                            <button
                                type="button"

                                @click="
                                    insertText(
                                        reply
                                    )
                                "

                                x-text="reply"
                            ></button>

                        </template>

                    </div>


                    {{-- TEXT + SEND --}}

                    <div class="wai-compose-row">

                        <div class="wai-compose-field">

                            <textarea
                                class="wai-textarea"

                                wire:model="messageText"

                                placeholder="Mesajınızı yazın..."

                                rows="2"

                                @input="typing = true"

                                @keydown="
                                    sendOnEnter(
                                        $event
                                    )
                                "
                            ></textarea>

                        </div>


                        <button
                            class="wai-send"

                            type="submit"

                            wire:loading.attr="disabled"

                            wire:target="sendMessage"

                            title="Gönder"
                        >

                            <span
                                wire:loading.remove
                                wire:target="sendMessage"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="m4 4 16 8-16 8 3-8-3-8Z"/>
                                    <path d="M7 12h13"/>
                                </svg>

                            </span>


                            <span
                                wire:loading
                                wire:target="sendMessage"
                            >
                                ...
                            </span>

                        </button>

                    </div>


                    <div class="wai-hint">

                        <span
                            x-show="typing"
                            class="wai-typing"
                        >
                            Yazıyor...
                        </span>

                        <span>
                            Enter: Gönder · Shift + Enter: Yeni satır
                        </span>

                    </div>


                    @if (
                        ! $this->selectedConversation->human_takeover
                    )

                        <div class="wai-human-note">
                            Mesaj gönderdiğinizde konuşma otomatik olarak İnsan Yönetiyor moduna geçer.
                        </div>

                    @endif

                </form>


            @else

                <div class="wai-no-message">
                    Bir konuşma seçin.
                </div>

            @endif

        </main>


        {{-- ============================================================
             DESKTOP CRM
        ============================================================= --}}

        @if ($this->selectedConversation)

            @php
                $selectedName =
                    $this->selectedConversation->customer_name
                    ?: $this->selectedConversation->whatsapp_number;

                $tags =
                    $this->selectedConversation->etiketler();

                $presets = [
                    'Yeni Müşteri',
                    'Sıcak Müşteri',
                    'Teklif Bekliyor',
                    'Sipariş',
                    'VIP',
                    'Acil'
                ];

                $crmInitial =
                    mb_strtoupper(
                        mb_substr(
                            $selectedName,
                            0,
                            1
                        )
                    );
            @endphp


            <aside class="wai-crm">


                <div class="wai-crm-profile">

                    <div class="wai-crm-avatar">
                        {{ $crmInitial }}
                    </div>

                    <div class="wai-crm-name">
                        {{ $selectedName }}
                    </div>

                    <div class="wai-crm-number">
                        {{ $this->selectedConversation->whatsapp_number }}
                    </div>


                    <div
                        class="
                            wai-crm-state
                            {{
                                $this->selectedConversation->human_takeover
                                    ? 'human'
                                    : 'ai'
                            }}
                        "
                    >
                        <i></i>

                        {{
                            $this->selectedConversation->human_takeover
                                ? 'İnsan Yönetiyor'
                                : 'AI Aktif'
                        }}

                    </div>

                </div>


                <div class="wai-section">

                    <div class="wai-title">
                        Müşteri Bilgileri
                    </div>


                    <div class="wai-info">

                        <span>
                            Telefon
                        </span>

                        <span>
                            {{ $this->selectedConversation->whatsapp_number }}
                        </span>

                    </div>


                    <div class="wai-info">

                        <span>
                            Firma
                        </span>

                        <span>
                            {{
                                $this->selectedConversation->aiBot?->company_name
                                ?? $this->selectedConversation->aiBot?->name
                                ?? '-'
                            }}
                        </span>

                    </div>


                    <div class="wai-info">

                        <span>
                            Son aktivite
                        </span>

                        <span>
                            {{ $this->selectedConversation->updated_at?->diffForHumans() }}
                        </span>

                    </div>


                    <div class="wai-info">

                        <span>
                            Okunmamış
                        </span>

                        <span>
                            {{ $this->selectedConversation->unread_count }}
                        </span>

                    </div>

                </div>


                {{-- TAGS --}}

                <div class="wai-section">

                    <div class="wai-title">
                        Etiketler
                    </div>


                    <div class="wai-tags">

                        @foreach (
                            $tags
                            as $tag
                        )

                            <span class="wai-tag">

                                {{ $tag }}

                                <button
                                    type="button"

                                    wire:click="
                                        removeTag(
                                            @js($tag)
                                        )
                                    "
                                >
                                    ×
                                </button>

                            </span>

                        @endforeach

                    </div>


                    <div class="wai-presets">

                        @foreach (
                            $presets
                            as $tag
                        )

                            @if (
                                ! in_array(
                                    $tag,
                                    $tags,
                                    true
                                )
                            )

                                <button
                                    type="button"

                                    class="wai-preset"

                                    wire:click="
                                        addTagFromPreset(
                                            @js($tag)
                                        )
                                    "
                                >
                                    + {{ $tag }}
                                </button>

                            @endif

                        @endforeach

                    </div>


                    <form
                        wire:submit="addTag"
                        class="wai-tag-form"
                    >

                        <input
                            wire:model="newTag"
                            maxlength="40"
                            placeholder="Özel etiket..."
                        >

                        <button type="submit">
                            Ekle
                        </button>

                    </form>

                </div>


                {{-- STATUS --}}

                <div class="wai-section">

                    <div class="wai-title">
                        Görüşme Durumu
                    </div>

                    <div class="wai-info">

                        <span>
                            Yönetim
                        </span>

                        <span>
                            {{
                                $this->selectedConversation->human_takeover
                                    ? 'İnsan'
                                    : 'Yapay Zekâ'
                            }}
                        </span>

                    </div>

                </div>


                {{-- NOTES --}}

                <div class="wai-section">

                    <div class="wai-title">
                        Notlar
                    </div>

                    <div class="wai-note">
                        Kalıcı müşteri notları, CRM modülünde bu alana bağlanacak.
                    </div>

                </div>

            </aside>


            {{-- ============================================================
                 TABLET / MOBILE CRM DRAWER
            ============================================================= --}}

            <div
                class="wai-mobile-crm-overlay"

                x-show="crmOpen"

                x-cloak

                @click.self="crmOpen = false"
            >

                <aside class="wai-mobile-crm-panel">

                    <div class="wai-mobile-crm-close">

                        <button
                            type="button"
                            @click="crmOpen = false"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="m6 6 12 12M18 6 6 18"/>
                            </svg>
                        </button>

                    </div>


                    <div class="wai-crm-profile">

                        <div class="wai-crm-avatar">
                            {{ $crmInitial }}
                        </div>

                        <div class="wai-crm-name">
                            {{ $selectedName }}
                        </div>

                        <div class="wai-crm-number">
                            {{ $this->selectedConversation->whatsapp_number }}
                        </div>


                        <div
                            class="
                                wai-crm-state
                                {{
                                    $this->selectedConversation->human_takeover
                                        ? 'human'
                                        : 'ai'
                                }}
                            "
                        >
                            <i></i>

                            {{
                                $this->selectedConversation->human_takeover
                                    ? 'İnsan Yönetiyor'
                                    : 'AI Aktif'
                            }}

                        </div>

                    </div>


                    <div class="wai-section">

                        <div class="wai-title">
                            Müşteri Bilgileri
                        </div>

                        <div class="wai-info">
                            <span>Telefon</span>
                            <span>{{ $this->selectedConversation->whatsapp_number }}</span>
                        </div>

                        <div class="wai-info">
                            <span>Firma</span>

                            <span>
                                {{
                                    $this->selectedConversation->aiBot?->company_name
                                    ?? $this->selectedConversation->aiBot?->name
                                    ?? '-'
                                }}
                            </span>
                        </div>

                        <div class="wai-info">
                            <span>Son aktivite</span>
                            <span>{{ $this->selectedConversation->updated_at?->diffForHumans() }}</span>
                        </div>

                        <div class="wai-info">
                            <span>Okunmamış</span>
                            <span>{{ $this->selectedConversation->unread_count }}</span>
                        </div>

                    </div>


                    <div class="wai-section">

                        <div class="wai-title">
                            Etiketler
                        </div>


                        <div class="wai-tags">

                            @foreach (
                                $tags
                                as $tag
                            )

                                <span class="wai-tag">

                                    {{ $tag }}

                                    <button
                                        type="button"

                                        wire:click="
                                            removeTag(
                                                @js($tag)
                                            )
                                        "
                                    >
                                        ×
                                    </button>

                                </span>

                            @endforeach

                        </div>


                        <div class="wai-presets">

                            @foreach (
                                $presets
                                as $tag
                            )

                                @if (
                                    ! in_array(
                                        $tag,
                                        $tags,
                                        true
                                    )
                                )

                                    <button
                                        type="button"
                                        class="wai-preset"

                                        wire:click="
                                            addTagFromPreset(
                                                @js($tag)
                                            )
                                        "
                                    >
                                        + {{ $tag }}
                                    </button>

                                @endif

                            @endforeach

                        </div>


                        <form
                            wire:submit="addTag"
                            class="wai-tag-form"
                        >

                            <input
                                wire:model="newTag"
                                maxlength="40"
                                placeholder="Özel etiket..."
                            >

                            <button type="submit">
                                Ekle
                            </button>

                        </form>

                    </div>


                    <div class="wai-section">

                        <div class="wai-title">
                            Görüşme Durumu
                        </div>

                        <div class="wai-info">

                            <span>
                                Yönetim
                            </span>

                            <span>
                                {{
                                    $this->selectedConversation->human_takeover
                                        ? 'İnsan'
                                        : 'Yapay Zekâ'
                                }}
                            </span>

                        </div>

                    </div>


                    <div class="wai-section">

                        <div class="wai-title">
                            Notlar
                        </div>

                        <div class="wai-note">
                            Kalıcı müşteri notları, CRM modülünde bu alana bağlanacak.
                        </div>

                    </div>

                </aside>

            </div>

        @endif

    </div>

</div>

</x-filament-panels::page>
<?php
/*
|--------------------------------------------------------------------------
| SmartHospital Network URL
|--------------------------------------------------------------------------
| Your PC IPv4 address on the Wi-Fi network.
| Change this only if your PC gets a different IP address.
*/
$serverIP = "192.168.1.199";
$smartHospitalURL = "http://" . $serverIP . "/smart_hospital/";
?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SmartHospital | Queue Management System</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f7fbfc;
    color: #17324d;
    line-height: 1.6;
}

a {
    text-decoration: none;
}

button,
input,
select {
    font-family: inherit;
}


/* =========================================================
   NAVBAR
========================================================= */

header {
    background: rgba(255,255,255,0.96);
    border-bottom: 1px solid #eaf0f2;
    position: sticky;
    top: 0;
    z-index: 1000;
    backdrop-filter: blur(10px);
}

.navbar {
    max-width: 1200px;
    height: 76px;
    margin: auto;
    padding: 0 30px;

    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;

    color: #087f8c;
    font-size: 21px;
    font-weight: 700;
}

.logo-icon {
    width: 39px;
    height: 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background: #087f8c;
    color: white;

    font-size: 21px;
    font-weight: 700;

    box-shadow: 0 6px 15px rgba(8,127,140,0.18);
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 24px;
}

.nav-links a {
    color: #647789;
    font-size: 14px;
    font-weight: 500;
    transition: 0.2s ease;
}

.nav-links a:hover {
    color: #087f8c;
}


/* =========================================================
   LANGUAGE SELECTOR
========================================================= */

.language-selector {
    position: relative;
}

.language-select {
    appearance: none;
    -webkit-appearance: none;

    min-width: 135px;

    padding: 10px 34px 10px 13px;

    border: 1px solid #dce7eb;
    border-radius: 8px;

    background: white;

    color: #40596c;

    font-size: 13px;
    font-weight: 600;

    cursor: pointer;
    outline: none;

    background-image:
        linear-gradient(45deg, transparent 50%, #087f8c 50%),
        linear-gradient(135deg, #087f8c 50%, transparent 50%);

    background-position:
        calc(100% - 15px) 15px,
        calc(100% - 10px) 15px;

    background-size: 5px 5px;
    background-repeat: no-repeat;

    transition: 0.2s ease;
}

.language-select:hover,
.language-select:focus {
    border-color: #087f8c;
}


/* =========================================================
   LOGIN
========================================================= */

.login-btn {
    padding: 10px 21px;

    border: 1px solid #087f8c;
    border-radius: 7px;

    color: #087f8c !important;
}

.login-btn:hover {
    background: #087f8c;
    color: white !important;
}


/* =========================================================
   HERO
========================================================= */

.hero {
    max-width: 1200px;
    min-height: 650px;

    margin: auto;
    padding: 70px 30px;

    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 65px;

    align-items: center;
}

.hero-text {
    max-width: 560px;
}

.small-title {
    display: inline-block;

    padding: 8px 14px;
    margin-bottom: 22px;

    background: #e7f6f7;
    color: #087f8c;

    border-radius: 30px;

    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
}

.hero-text h1 {
    color: #122d45;

    font-size: 52px;
    line-height: 1.1;

    margin-bottom: 23px;

    letter-spacing: -1.6px;
}

.hero-text h1 span {
    color: #087f8c;
}

.hero-text p {
    color: #687c8e;

    font-size: 17px;
    line-height: 1.8;

    margin-bottom: 31px;
}

.hero-buttons {
    display: flex;
    align-items: center;
    gap: 13px;
}

.primary-btn,
.secondary-btn {
    padding: 13px 23px;

    border-radius: 7px;

    font-size: 14px;
    font-weight: 600;

    transition: 0.2s ease;
}

.primary-btn {
    background: #087f8c;
    color: white;
}

.primary-btn:hover {
    background: #066b76;
    transform: translateY(-1px);
}

.secondary-btn {
    background: white;
    color: #4e6476;
    border: 1px solid #d9e4e8;
}

.secondary-btn:hover {
    border-color: #087f8c;
    color: #087f8c;
}


/* =========================================================
   HERO IMAGE
========================================================= */

.hero-image {
    position: relative;
    height: 480px;
}

.hero-image img {
    width: 100%;
    height: 100%;

    display: block;

    object-fit: cover;

    border-radius: 22px;
}

.image-overlay {
    position: absolute;
    inset: 0;

    border-radius: 22px;

    background:
        linear-gradient(
            to top,
            rgba(8,48,65,0.42),
            transparent 60%
        );
}


/* =========================================================
   QUEUE CARD
========================================================= */

.queue-card {
    position: absolute;

    bottom: 25px;
    left: -35px;

    width: 245px;

    padding: 20px;

    background: rgba(255,255,255,0.97);

    border-radius: 14px;

    box-shadow:
        0 15px 40px rgba(23,50,77,0.17);
}

.queue-top {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 16px;
}

.queue-top h3 {
    font-size: 14px;
}

.active {
    padding: 5px 8px;

    border-radius: 20px;

    background: #e7f7ef;
    color: #24915d;

    font-size: 10px;
    font-weight: 700;
}

.queue-number {
    padding: 15px;

    margin-bottom: 14px;

    text-align: center;

    border-radius: 10px;

    background: #eef9fa;
}

.queue-number small {
    display: block;

    color: #718394;

    font-size: 10px;

    margin-bottom: 3px;
}

.queue-number strong {
    color: #087f8c;
    font-size: 32px;
}

.queue-details {
    display: flex;
    justify-content: space-between;

    padding: 7px 0;

    font-size: 11px;
}

.queue-details span:first-child {
    color: #81909c;
}

.queue-details span:last-child {
    color: #17324d;
    font-weight: 600;
}


/* =========================================================
   COMMON SECTIONS
========================================================= */

.section {
    padding: 75px 30px;
}

.section-white {
    background: white;
}

.section-light {
    background: #f7fbfc;
}

.section-title {
    max-width: 620px;
    margin: auto;
    text-align: center;
}

.section-label {
    display: inline-block;

    margin-bottom: 10px;

    color: #087f8c;

    font-size: 11px;
    font-weight: 700;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.section-title h2 {
    color: #17324d;

    font-size: 31px;

    margin-bottom: 12px;
}

.section-title p {
    color: #758796;

    font-size: 14px;
    line-height: 1.7;
}


/* =========================================================
   HOW IT WORKS
========================================================= */

.steps-grid {
    max-width: 1000px;

    margin: 45px auto 0;

    display: grid;
    grid-template-columns: repeat(3,1fr);

    gap: 22px;
}

.step-card {
    position: relative;

    padding: 30px 27px;

    background: white;

    border: 1px solid #e8eff1;

    border-radius: 14px;

    transition: 0.25s ease;
}

.step-card:hover {
    transform: translateY(-5px);

    border-color: #cfe6e8;

    box-shadow:
        0 14px 30px rgba(23,50,77,0.07);
}

.step-number {
    width: 46px;
    height: 46px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 20px;

    border-radius: 12px;

    background: #e8f7f8;
    color: #087f8c;

    font-size: 13px;
    font-weight: 800;
}

.step-card h3 {
    color: #17324d;

    font-size: 17px;

    margin-bottom: 9px;
}

.step-card p {
    color: #778897;

    font-size: 13px;
    line-height: 1.7;
}


/* =========================================================
   SERVICES
========================================================= */

.services-grid {
    max-width: 1000px;

    margin: 45px auto 0;

    display: grid;
    grid-template-columns: repeat(3,1fr);

    gap: 22px;
}

.service-card {
    padding: 29px;

    background: white;

    border: 1px solid #e8eff1;

    border-radius: 14px;

    transition: 0.25s ease;
}

.service-card:hover {
    transform: translateY(-5px);

    box-shadow:
        0 14px 30px rgba(23,50,77,0.07);
}

.service-icon {
    width: 46px;
    height: 46px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 18px;

    border-radius: 11px;

    background: #e8f7f8;
    color: #087f8c;

    font-size: 18px;
    font-weight: 700;
}

.service-card h3 {
    font-size: 16px;
    margin-bottom: 8px;
}

.service-card p {
    color: #778897;

    font-size: 13px;
    line-height: 1.7;
}


/* =========================================================
   QUICK ACCESS
========================================================= */

.quick-access {
    padding: 75px 30px;

    background: white;

    border-top: 1px solid #edf2f4;
    border-bottom: 1px solid #edf2f4;
}

.quick-access-box {
    max-width: 1000px;

    margin: auto;

    padding: 42px;

    display: grid;
    grid-template-columns: 1fr 240px;

    align-items: center;

    gap: 55px;

    background:
        linear-gradient(
            135deg,
            #f1fbfc,
            #ffffff
        );

    border: 1px solid #dcecef;

    border-radius: 20px;

    box-shadow:
        0 15px 40px rgba(23,50,77,0.06);
}

.quick-label {
    display: inline-flex;
    align-items: center;
    gap: 7px;

    margin-bottom: 13px;

    color: #087f8c;

    font-size: 11px;
    font-weight: 800;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.quick-label::before {
    content: "";

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #087f8c;
}

.quick-access-content h2 {
    color: #17324d;

    font-size: 29px;
    line-height: 1.25;

    margin-bottom: 12px;
}

.quick-access-content > p {
    max-width: 570px;

    color: #718394;

    font-size: 14px;
    line-height: 1.75;

    margin-bottom: 21px;
}

.quick-benefits {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.benefit {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    padding: 8px 11px;

    border-radius: 7px;

    background: white;

    border: 1px solid #e2ecef;

    color: #526a7b;

    font-size: 11px;
    font-weight: 600;
}

.benefit span {
    color: #087f8c;
    font-weight: 800;
}

.qr-area {
    display: flex;
    flex-direction: column;
    align-items: center;

    text-align: center;
}

.qr-frame {
    width: 180px;
    height: 180px;

    padding: 10px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: white;

    border: 1px solid #dce8eb;

    border-radius: 14px;

    box-shadow:
        0 10px 25px rgba(23,50,77,0.08);
}

.qr-frame img {
    width: 100%;
    height: 100%;

    object-fit: contain;
}

.qr-title {
    margin-top: 14px;

    color: #17324d;

    font-size: 13px;
    font-weight: 700;
}

.qr-subtitle {
    color: #8494a0;

    font-size: 10px;

    margin-top: 3px;
}

.qr-url {
    margin-top: 7px;

    max-width: 220px;

    color: #087f8c;

    font-size: 9px;

    word-break: break-all;
}


/* =========================================================
   HELP
========================================================= */

.help-section {
    padding: 60px 30px 75px;

    background: #f7fbfc;
}

.help-box {
    max-width: 1000px;

    margin: auto;

    padding: 29px 32px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 30px;

    background: #0f968d;

    border-radius: 18px;

    box-shadow:
        0 14px 35px rgba(15,150,141,0.17);
}

.help-content {
    display: flex;
    align-items: center;

    gap: 17px;
}

.help-icon {
    width: 48px;
    height: 48px;

    flex-shrink: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background: rgba(255,255,255,0.17);

    color: white;

    font-size: 20px;
    font-weight: 700;
}

.help-text h3 {
    color: white;

    font-size: 17px;

    margin-bottom: 4px;
}

.help-text p {
    max-width: 500px;

    color: rgba(255,255,255,0.91);

    font-size: 13px;
    line-height: 1.6;
}

.help-actions {
    display: flex;

    gap: 9px;

    flex-shrink: 0;
}

.help-btn {
    min-width: 135px;

    padding: 11px 17px;

    display: flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    border-radius: 8px;

    font-size: 13px;
    font-weight: 600;

    transition: 0.2s ease;
}

.call-btn {
    background: white;
    color: #087f8c;
}

.call-btn:hover {
    background: #f2fafa;
    transform: translateY(-1px);
}

.chat-btn {
    color: white;

    border: 1px solid rgba(255,255,255,0.45);

    background: transparent;
}

.chat-btn:hover {
    background: rgba(255,255,255,0.12);
}


/* =========================================================
   AI BUTTON
========================================================= */

.ai-button {
    position: fixed;

    right: 25px;
    bottom: 25px;

    width: 58px;
    height: 58px;

    border: none;

    border-radius: 50%;

    background: #087f8c;

    color: white;

    font-size: 24px;

    cursor: pointer;

    box-shadow:
        0 10px 28px rgba(8,127,140,0.30);

    z-index: 2000;

    transition: 0.2s ease;
}

.ai-button:hover {
    background: #066b76;
    transform: scale(1.05);
}


/* =========================================================
   AI CHAT
========================================================= */

.ai-chat {
    position: fixed;

    right: 25px;
    bottom: 95px;

    width: 360px;
    max-width: calc(100vw - 30px);

    background: white;

    border: 1px solid #e0eaed;

    border-radius: 16px;

    overflow: hidden;

    box-shadow:
        0 18px 50px rgba(23,50,77,0.20);

    display: none;

    z-index: 1999;
}

.ai-chat.active {
    display: block;
}

.ai-header {
    padding: 15px 17px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    background: #102d44;

    color: white;
}

.ai-header-left {
    display: flex;
    align-items: center;

    gap: 10px;
}

.ai-icon {
    width: 35px;
    height: 35px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background: #087f8c;
}

.ai-header strong {
    display: block;
    font-size: 13px;
}

.ai-header span {
    font-size: 10px;
    opacity: 0.7;
}

.ai-close {
    border: none;

    background: transparent;

    color: white;

    font-size: 21px;

    cursor: pointer;
}

.ai-messages {
    height: 290px;

    padding: 15px;

    overflow-y: auto;

    background: #f7fafb;
}

.ai-message {
    max-width: 88%;

    padding: 10px 12px;

    margin-bottom: 10px;

    border-radius: 10px;

    font-size: 12px;
    line-height: 1.5;
}

.ai-message.bot {
    background: white;

    border: 1px solid #e5edef;

    color: #17324d;
}

.ai-message.user {
    margin-left: auto;

    background: #087f8c;

    color: white;
}

.ai-quick {
    padding: 10px 12px;

    display: flex;
    flex-wrap: wrap;

    gap: 6px;

    border-top: 1px solid #e8eef1;

    background: white;
}

.ai-quick button {
    padding: 6px 9px;

    border: 1px solid #dce7eb;

    border-radius: 15px;

    background: #f6fafb;

    color: #17324d;

    font-size: 10px;

    cursor: pointer;
}

.ai-quick button:hover {
    border-color: #087f8c;
    color: #087f8c;
}

.ai-input {
    padding: 10px;

    display: flex;

    gap: 7px;

    border-top: 1px solid #e8eef1;

    background: white;
}

.ai-input input {
    flex: 1;

    min-width: 0;

    padding: 10px;

    border: 1px solid #dce7eb;

    border-radius: 8px;

    outline: none;

    font-size: 12px;
}

.ai-input input:focus {
    border-color: #087f8c;
}

.ai-input button {
    width: 42px;

    border: none;

    border-radius: 8px;

    background: #087f8c;

    color: white;

    cursor: pointer;
}


/* =========================================================
   FOOTER
========================================================= */

footer {
    padding: 25px;

    background: #102d44;

    color: #dce7ed;

    text-align: center;

    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .nav-links {
        gap: 12px;
    }

    .language-select {
        min-width: 120px;
    }

}

@media (max-width: 900px) {

    .hero {
        grid-template-columns: 1fr;

        padding-top: 50px;

        text-align: center;
    }

    .hero-text {
        margin: auto;
    }

    .hero-buttons {
        justify-content: center;
    }

    .hero-image {
        width: 100%;
        max-width: 600px;

        height: 400px;

        margin: auto;
    }

    .queue-card {
        left: 20px;
    }

    .steps-grid,
    .services-grid {
        grid-template-columns: 1fr;

        max-width: 500px;
    }

    .quick-access-box {
        grid-template-columns: 1fr;

        text-align: center;

        gap: 35px;
    }

    .quick-access-content > p {
        margin-left: auto;
        margin-right: auto;
    }

    .quick-benefits {
        justify-content: center;
    }

    .help-box {
        flex-direction: column;

        align-items: stretch;
    }

    .help-content {
        align-items: flex-start;
    }

    .help-actions {
        width: 100%;
    }

    .help-btn {
        flex: 1;
    }

}


@media (max-width: 700px) {

    .nav-links a:not(.login-btn) {
        display: none;
    }

    .navbar {
        padding: 0 18px;
    }

    .language-select {
        min-width: 105px;
        font-size: 11px;
    }

}


@media (max-width: 600px) {

    .navbar {
        height: 68px;
        padding: 0 14px;
    }

    .logo {
        font-size: 17px;
    }

    .logo-icon {
        width: 35px;
        height: 35px;
    }

    .nav-links {
        gap: 7px;
    }

    .language-select {
        min-width: 100px;
        padding-left: 8px;
        padding-right: 25px;
    }

    .login-btn {
        padding: 8px 12px;
        font-size: 11px !important;
    }

    .hero {
        padding: 45px 18px;
    }

    .hero-text h1 {
        font-size: 39px;
    }

    .hero-text p {
        font-size: 15px;
    }

    .hero-buttons {
        flex-direction: column;
    }

    .primary-btn,
    .secondary-btn {
        width: 100%;
        text-align: center;
    }

    .hero-image {
        height: 350px;
    }

    .queue-card {
        left: 10px;
        width: 220px;
    }

    .section,
    .quick-access {
        padding-left: 18px;
        padding-right: 18px;
    }

    .section-title h2 {
        font-size: 27px;
    }

    .quick-access-box {
        padding: 28px 20px;
    }

    .quick-access-content h2 {
        font-size: 25px;
    }

    .quick-benefits {
        flex-direction: column;
        align-items: stretch;
    }

    .benefit {
        justify-content: center;
    }

    .help-section {
        padding: 40px 18px 55px;
    }

    .help-box {
        padding: 23px;
    }

    .help-actions {
        flex-direction: column;
    }

    .help-btn {
        width: 100%;
    }

    .ai-chat {
        right: 15px;
        bottom: 85px;
    }

    .ai-button {
        right: 15px;
        bottom: 15px;
    }

}

</style>

</head>

<body>

<!-- =========================================================
     NAVIGATION
========================================================= -->

<header>

<nav class="navbar">

<a href="index.php" class="logo">

<span class="logo-icon">+</span>

SmartHospital

</a>

<div class="nav-links">

<a href="#about" data-i18n="nav_about">
    About
</a>

<a href="#services" data-i18n="nav_services">
    Services
</a>

<a href="#how-it-works" data-i18n="nav_how">
    How It Works
</a>

<!-- LANGUAGE -->

<div class="language-selector">

<select
id="languageSelect"
class="language-select"
aria-label="Select language"

>

<option value="en">🇬🇧 English</option>
<option value="rw">🇷🇼 Kinyarwanda</option>
<option value="fr">🇫🇷 Français</option>
<option value="sw">🇹🇿 Kiswahili</option>
<option value="es">🇪🇸 Español</option>

</select>

</div>

<a
href="login.php"
class="login-btn"
data-i18n="login"

>

```
Login
```

</a>

</div>

</nav>

</header>

<!-- =========================================================
     HERO
========================================================= -->

<section class="hero" id="about">

<div class="hero-text">

<span
class="small-title"
data-i18n="hero_small"

>

```
SMART HOSPITAL QUEUE SYSTEM
```

</span>

<h1 data-i18n="hero_title">
    Healthcare made
    <span>simpler.</span>
</h1>

<p data-i18n="hero_description">

Join the hospital queue digitally, track your position,
and spend less time waiting. SmartHospital connects
patients and hospital services through a simple,
efficient queue management system.

</p>

<div class="hero-buttons">

<a
href="register.php"
class="primary-btn"
data-i18n="get_started"

>

```
Get Started
```

</a>

<a
href="#how-it-works"
class="secondary-btn"
data-i18n="learn_more"

>

```
Learn How It Works
```

</a>

</div>

</div>

<!-- HERO IMAGE -->

<div class="hero-image">

<img
src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=1000&q=85"
alt="Healthcare professional in a modern hospital"

>

<div class="image-overlay"></div>

<!-- QUEUE PREVIEW -->

<div class="queue-card">

<div class="queue-top">

<h3 data-i18n="queue_status">
    Queue Status
</h3>

<span
class="active"
data-i18n="active"

>

```
ACTIVE
```

</span>

</div>

<div class="queue-number">

<small data-i18n="queue_number">
    Your Queue Number
</small>

<strong>
    A-024
</strong>

</div>

<div class="queue-details">

<span data-i18n="department">
    Department
</span>

<span data-i18n="outpatient">
    Outpatient
</span>

</div>

<div class="queue-details">

<span data-i18n="now_serving">
    Now Serving
</span>

<span>
    A-021
</span>

</div>

<div class="queue-details">

<span data-i18n="people_ahead">
    People Ahead
</span>

<span>
    3
</span>

</div>

</div>

</div>

</section>

<!-- =========================================================
     HOW IT WORKS
========================================================= -->

<section
    class="section section-white"
    id="how-it-works"
>

<div class="section-title">

<span
class="section-label"
data-i18n="how_label"

>

```
How It Works
```

</span>

<h2 data-i18n="how_title">
    Simple. Fast. Organized.
</h2>

<p data-i18n="how_description">

SmartHospital makes it easier for patients to access
hospital services while helping staff manage queues
efficiently.

</p>

</div>

<div class="steps-grid">

<div class="step-card">

<div class="step-number">
    01
</div>

<h3 data-i18n="register_title">
    Register
</h3>

<p data-i18n="register_text">

Create your patient account and provide
the necessary information securely.

</p>

</div>

<div class="step-card">

<div class="step-number">
    02
</div>

<h3 data-i18n="join_title">
    Join a Queue
</h3>

<p data-i18n="join_text">

Select the hospital department you need
and receive your queue number.

</p>

</div>

<div class="step-card">

<div class="step-number">
    03
</div>

<h3 data-i18n="track_title">
    Track Your Turn
</h3>

<p data-i18n="track_text">

Monitor your position and know when
your turn is approaching.

</p>

</div>

</div>

</section>

<!-- =========================================================
     SERVICES
========================================================= -->

<section
    class="section section-light"
    id="services"
>

<div class="section-title">

<span
class="section-label"
data-i18n="services_label"

>

```
Hospital Services
```

</span>

<h2 data-i18n="services_title">
    One platform. Multiple services.
</h2>

<p data-i18n="services_description">

SmartHospital helps organize different hospital
queues through one simple platform.

</p>

</div>

<div class="services-grid">

<div class="service-card">

<div class="service-icon">
    +
</div>

<h3 data-i18n="outpatient">
    Outpatient
</h3>

<p data-i18n="outpatient_text">

Manage general consultation and outpatient
queues efficiently.

</p>

</div>

<div class="service-card">

<div class="service-icon">
    +
</div>

<h3 data-i18n="laboratory">
    Laboratory
</h3>

<p data-i18n="laboratory_text">

Organize patients waiting for laboratory
services and reduce unnecessary waiting.

</p>

</div>

<div class="service-card">

<div class="service-icon">
    +
</div>

<h3 data-i18n="pharmacy">
    Pharmacy
</h3>

<p data-i18n="pharmacy_text">

Help patients manage pharmacy waiting time
and improve service organization.

</p>

</div>

</div>

</section>

<!-- =========================================================
     QUICK ACCESS
========================================================= -->

<section class="quick-access">

<div class="quick-access-box">

<div class="quick-access-content">

<span
class="quick-label"
data-i18n="quick_label"

>

```
Quick Access
```

</span>

<h2 data-i18n="quick_title">

Access SmartHospital
from your phone.

</h2>

<p data-i18n="quick_description">

Scan the QR code with your smartphone camera
to open SmartHospital instantly. You can then
register, join a queue, and track your turn
directly from your phone.

</p>

<div class="quick-benefits">

<div class="benefit">
    <span>✓</span>
    <span data-i18n="easy_access">Easy access</span>
</div>

<div class="benefit">
    <span>✓</span>
    <span data-i18n="no_typing">No typing required</span>
</div>

<div class="benefit">
    <span>✓</span>
    <span data-i18n="mobile_friendly">Mobile friendly</span>
</div>

</div>

</div>

<div class="qr-area">

<div class="qr-frame">

<img
src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($smartHospitalURL); ?>"
alt="SmartHospital QR Code"

>

</div>

<div
    class="qr-title"
    data-i18n="scan_title"
>
    Scan to open SmartHospital
</div>

<div
    class="qr-subtitle"
    data-i18n="scan_subtitle"
>
    Use your phone camera
</div>

<div class="qr-url">

<?php echo htmlspecialchars($smartHospitalURL); ?>

</div>

</div>

</div>

</section>

<!-- =========================================================
     HELP
========================================================= -->

<section class="help-section">

<div class="help-box">

<div class="help-content">

<div class="help-icon">
    ?
</div>

<div class="help-text">

<h3 data-i18n="help_title">
    Need Help?
</h3>

<p data-i18n="help_description">

Our support team is available to assist you
with questions about registration, queues,
and hospital services.

</p>

</div>

</div>

<div class="help-actions">

<a
href="tel:+250783835525"
class="help-btn call-btn"

>

<span>☎</span>

<span data-i18n="call_desk">
    Call Desk
</span>

</a>

<a
href="chat.php"
class="help-btn chat-btn"

>

<span>●</span>

<span data-i18n="chat">
    Chat
</span>

</a>

</div>

</div>

</section>

<!-- =========================================================
     AI BUTTON
========================================================= -->

<button
class="ai-button"
id="aiButton"
title="SmartHospital AI Assistant"

>

```
🤖
```

</button>

<!-- =========================================================
     AI CHAT
========================================================= -->

<div
    class="ai-chat"
    id="aiChat"
>

<div class="ai-header">

<div class="ai-header-left">

<div class="ai-icon">
    🤖
</div>

<div>

<strong>
    SmartHospital AI
</strong>

<span data-i18n="ai_subtitle">
    Virtual Support Assistant
</span>

</div>

</div>

<button
class="ai-close"
id="aiClose"

>

```
×
```

</button>

</div>

<div
    class="ai-messages"
    id="aiMessages"
>

<div
    class="ai-message bot"
    id="welcomeMessage"
>

Hello 👋

<br><br>

Welcome to SmartHospital.

<br><br>

I can help you with registration,
joining a queue, checking your queue,
and understanding our hospital services.

</div>

</div>

<div class="ai-quick">

<button
onclick="askAI('How do I register?')"
data-i18n="ai_registration"

>

```
Registration
```

</button>

<button
onclick="askAI('How do I join a queue?')"
data-i18n="ai_queue"

>

```
Join a queue
```

</button>

<button
onclick="askAI('How can I check my queue position?')"
data-i18n="ai_position"

>

```
Queue position
```

</button>

<button
onclick="askAI('What services are available?')"
data-i18n="ai_services"

>

```
Services
```

</button>

</div>

<div class="ai-input">

<input
type="text"
id="aiInput"
placeholder="Ask SmartHospital..."
data-i18n-placeholder="ai_placeholder"
autocomplete="off"

>

<button id="aiSend">
    ➤
</button>

</div>

</div>

<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

<span data-i18n="footer">
    © 2026 SmartHospital. Smart healthcare queue management.
</span>

</footer>

<script>

/* =========================================================
   LANGUAGE TRANSLATIONS
========================================================= */

const translations = {

en: {

nav_about: "About",
nav_services: "Services",
nav_how: "How It Works",
login: "Login",

hero_small: "SMART HOSPITAL QUEUE SYSTEM",
hero_title: 'Healthcare made <span>simpler.</span>',
hero_description:
"Join the hospital queue digitally, track your position, and spend less time waiting. SmartHospital connects patients and hospital services through a simple, efficient queue management system.",

get_started: "Get Started",
learn_more: "Learn How It Works",

queue_status: "Queue Status",
active: "ACTIVE",
queue_number: "Your Queue Number",
department: "Department",
outpatient: "Outpatient",
now_serving: "Now Serving",
people_ahead: "People Ahead",

how_label: "How It Works",
how_title: "Simple. Fast. Organized.",
how_description:
"SmartHospital makes it easier for patients to access hospital services while helping staff manage queues efficiently.",

register_title: "Register",
register_text:
"Create your patient account and provide the necessary information securely.",

join_title: "Join a Queue",
join_text:
"Select the hospital department you need and receive your queue number.",

track_title: "Track Your Turn",
track_text:
"Monitor your position and know when your turn is approaching.",

services_label: "Hospital Services",
services_title: "One platform. Multiple services.",
services_description:
"SmartHospital helps organize different hospital queues through one simple platform.",

outpatient_text:
"Manage general consultation and outpatient queues efficiently.",

laboratory: "Laboratory",
laboratory_text:
"Organize patients waiting for laboratory services and reduce unnecessary waiting.",

pharmacy: "Pharmacy",
pharmacy_text:
"Help patients manage pharmacy waiting time and improve service organization.",

quick_label: "Quick Access",
quick_title: "Access SmartHospital from your phone.",
quick_description:
"Scan the QR code with your smartphone camera to open SmartHospital instantly. You can then register, join a queue, and track your turn directly from your phone.",

easy_access: "Easy access",
no_typing: "No typing required",
mobile_friendly: "Mobile friendly",

scan_title: "Scan to open SmartHospital",
scan_subtitle: "Use your phone camera",

help_title: "Need Help?",
help_description:
"Our support team is available to assist you with questions about registration, queues, and hospital services.",

call_desk: "Call Desk",
chat: "Chat",

ai_subtitle: "Virtual Support Assistant",
ai_registration: "Registration",
ai_queue: "Join a queue",
ai_position: "Queue position",
ai_services: "Services",
ai_placeholder: "Ask SmartHospital...",

footer:
"© 2026 SmartHospital. Smart healthcare queue management."

},

rw: {

nav_about: "Ibyerekeye",
nav_services: "Serivisi",
nav_how: "Uko Bikora",
login: "Kwinjira",

hero_small: "UBURYO BWUBWENGE BWO GUCUNGA UMURONGO W'IBITARO",
hero_title: 'Ubuvuzi bworoheje <span>kuri bose.</span>',
hero_description:
"Jya ku murongo w'ibitaro ukoresheje ikoranabuhanga, kurikirana umwanya wawe kandi ugabanye igihe umara utegereje. SmartHospital ihuza abarwayi na serivisi z'ibitaro mu buryo bworoshye kandi bwihuse.",

get_started: "Tangira",
learn_more: "Menya Uko Bikora",

queue_status: "Imiterere y'Umurongo",
active: "UKORA",
queue_number: "Numero Yawe y'Umurongo",
department: "Serivisi",
outpatient: "Kuvurwa Hanze",
now_serving: "Uri Kuvurwa",
people_ahead: "Abakubanjirije",

how_label: "Uko Bikora",
how_title: "Byoroshye. Byihuse. Biteguye.",
how_description:
"SmartHospital ifasha abarwayi kubona serivisi z'ibitaro mu buryo bworoshye kandi igafasha abakozi gucunga imirongo neza.",

register_title: "Iyandikishe",
register_text:
"Fungura konti y'umurwayi kandi utange amakuru akenewe mu buryo bwizewe.",

join_title: "Jya ku Murongo",
join_text:
"Hitamo serivisi y'ibitaro ukeneye maze uhabwe numero y'umurongo.",

track_title: "Kurikirana Umwanya Wawe",
track_text:
"Reba aho ugeze ku murongo kandi umenye igihe cyawe kiri hafi.",

services_label: "Serivisi z'Ibitaro",
services_title: "Urubuga rumwe. Serivisi nyinshi.",
services_description:
"SmartHospital ifasha gucunga imirongo ya serivisi zitandukanye z'ibitaro.",

outpatient_text:
"Gucunga imirongo y'abakeneye kuganira n'abaganga n'izindi serivisi zo kwa muganga.",

laboratory: "Laboratwari",
laboratory_text:
"Gutunganya umurongo w'abategereje serivisi za laboratwari no kugabanya gutegereza bidakenewe.",

pharmacy: "Farumasi",
pharmacy_text:
"Gufasha abarwayi gucunga igihe bategereza serivisi za farumasi.",

quick_label: "Kwinjira Vuba",
quick_title: "Fungura SmartHospital ukoresheje telefoni yawe.",
quick_description:
"Sikana QR code ukoresheje camera ya telefoni yawe kugira ngo ufungure SmartHospital vuba. Ushobora kwiyandikisha, kujya ku murongo no gukurikirana umwanya wawe ukoresheje telefoni.",

easy_access: "Kwinjira byoroshye",
no_typing: "Nta kwandika aderesi",
mobile_friendly: "Ikorera kuri telefoni",

scan_title: "Sikana ufungure SmartHospital",
scan_subtitle: "Koresha camera ya telefoni",

help_title: "Ukeneye Ubufasha?",
help_description:
"Abakozi bacu bashinzwe ubufasha barahari kugira ngo bagufashe ku bijyanye no kwiyandikisha, imirongo n'izindi serivisi z'ibitaro.",

call_desk: "Hamagara Ibiro",
chat: "Ganira",

ai_subtitle: "Umufasha w'Ikoranabuhanga",
ai_registration: "Kwiyandikisha",
ai_queue: "Jya ku murongo",
ai_position: "Umwanya ku murongo",
ai_services: "Serivisi",
ai_placeholder: "Baza SmartHospital...",

footer:
"© 2026 SmartHospital. Uburyo bw'ikoranabuhanga bwo gucunga imirongo y'ubuvuzi."

},

fr: {

nav_about: "À propos",
nav_services: "Services",
nav_how: "Comment ça marche",
login: "Connexion",

hero_small: "SYSTÈME INTELLIGENT DE GESTION DES FILES",
hero_title: 'Les soins deviennent <span>plus simples.</span>',
hero_description:
"Rejoignez la file d'attente de l'hôpital numériquement, suivez votre position et passez moins de temps à attendre. SmartHospital relie les patients aux services hospitaliers grâce à un système simple et efficace.",

get_started: "Commencer",
learn_more: "Comment ça marche",

queue_status: "État de la file",
active: "ACTIF",
queue_number: "Votre numéro",
department: "Service",
outpatient: "Consultation externe",
now_serving: "En cours",
people_ahead: "Personnes devant vous",

how_label: "Comment ça marche",
how_title: "Simple. Rapide. Organisé.",
how_description:
"SmartHospital facilite l'accès des patients aux services hospitaliers tout en aidant le personnel à gérer efficacement les files d'attente.",

register_title: "S'inscrire",
register_text:
"Créez votre compte patient et fournissez les informations nécessaires en toute sécurité.",

join_title: "Rejoindre une file",
join_text:
"Sélectionnez le service hospitalier dont vous avez besoin et recevez votre numéro.",

track_title: "Suivre votre tour",
track_text:
"Suivez votre position et sachez quand votre tour approche.",

services_label: "Services hospitaliers",
services_title: "Une plateforme. Plusieurs services.",
services_description:
"SmartHospital aide à organiser différentes files d'attente hospitalières sur une seule plateforme.",

outpatient_text:
"Gérez efficacement les files de consultation générale et de soins ambulatoires.",

laboratory: "Laboratoire",
laboratory_text:
"Organisez les patients en attente de services de laboratoire et réduisez l'attente inutile.",

pharmacy: "Pharmacie",
pharmacy_text:
"Aidez les patients à gérer leur temps d'attente à la pharmacie.",

quick_label: "Accès rapide",
quick_title: "Accédez à SmartHospital depuis votre téléphone.",
quick_description:
"Scannez le QR code avec l'appareil photo de votre smartphone pour ouvrir SmartHospital instantanément. Vous pouvez ensuite vous inscrire, rejoindre une file et suivre votre tour.",

easy_access: "Accès facile",
no_typing: "Aucune saisie nécessaire",
mobile_friendly: "Compatible mobile",

scan_title: "Scannez pour ouvrir SmartHospital",
scan_subtitle: "Utilisez l'appareil photo",

help_title: "Besoin d'aide ?",
help_description:
"Notre équipe d'assistance est disponible pour vous aider concernant l'inscription, les files d'attente et les services hospitaliers.",

call_desk: "Appeler l'accueil",
chat: "Discussion",

ai_subtitle: "Assistant virtuel",
ai_registration: "Inscription",
ai_queue: "Rejoindre une file",
ai_position: "Position",
ai_services: "Services",
ai_placeholder: "Demandez à SmartHospital...",

footer:
"© 2026 SmartHospital. Gestion intelligente des files d'attente hospitalières."

},

sw: {

nav_about: "Kuhusu",
nav_services: "Huduma",
nav_how: "Jinsi Inavyofanya Kazi",
login: "Ingia",

hero_small: "MFUMO SMART WA KUSIMAMIA FOLENI HOSPITALINI",
hero_title: 'Huduma za afya <span>rahisi zaidi.</span>',
hero_description:
"Jiunge na foleni ya hospitali kidijitali, fuatilia nafasi yako na upunguze muda wa kusubiri. SmartHospital inaunganisha wagonjwa na huduma za hospitali kupitia mfumo rahisi na wenye ufanisi.",

get_started: "Anza",
learn_more: "Jinsi Inavyofanya Kazi",

queue_status: "Hali ya Foleni",
active: "INAFANYA KAZI",
queue_number: "Namba Yako ya Foleni",
department: "Idara",
outpatient: "Huduma za Wagonjwa wa Nje",
now_serving: "Anayehudumiwa Sasa",
people_ahead: "Watu Walio Mbele Yako",

how_label: "Jinsi Inavyofanya Kazi",
how_title: "Rahisi. Haraka. Imepangwa.",
how_description:
"SmartHospital hurahisisha wagonjwa kupata huduma za hospitali na kusaidia wafanyakazi kusimamia foleni kwa ufanisi.",

register_title: "Jisajili",
register_text:
"Fungua akaunti yako ya mgonjwa na utoe taarifa zinazohitajika kwa usalama.",

join_title: "Jiunge na Foleni",
join_text:
"Chagua idara ya hospitali unayohitaji na upokee namba yako ya foleni.",

track_title: "Fuatilia Zamu Yako",
track_text:
"Fuatilia nafasi yako na ujue wakati zamu yako inakaribia.",

services_label: "Huduma za Hospitali",
services_title: "Mfumo mmoja. Huduma nyingi.",
services_description:
"SmartHospital husaidia kupanga foleni za huduma mbalimbali za hospitali kupitia mfumo mmoja.",

outpatient_text:
"Simamia foleni za mashauriano ya jumla na huduma za wagonjwa wa nje kwa ufanisi.",

laboratory: "Maabara",
laboratory_text:
"Panga wagonjwa wanaosubiri huduma za maabara na kupunguza muda wa kusubiri.",

pharmacy: "Famasia",
pharmacy_text:
"Wasaidie wagonjwa kusimamia muda wa kusubiri huduma za famasia.",

quick_label: "Ufikiaji wa Haraka",
quick_title: "Fungua SmartHospital kwenye simu yako.",
quick_description:
"Scan QR code kwa kamera ya simu yako ili kufungua SmartHospital mara moja. Unaweza kujisajili, kujiunga na foleni na kufuatilia zamu yako kupitia simu yako.",

easy_access: "Ufikiaji rahisi",
no_typing: "Hakuna kuandika",
mobile_friendly: "Inafaa kwa simu",

scan_title: "Scan kufungua SmartHospital",
scan_subtitle: "Tumia kamera ya simu",

help_title: "Unahitaji Msaada?",
help_description:
"Timu yetu ya usaidizi iko tayari kukusaidia kuhusu usajili, foleni na huduma za hospitali.",

call_desk: "Piga Simu",
chat: "Ongea",

ai_subtitle: "Msaidizi wa Mtandao",
ai_registration: "Usajili",
ai_queue: "Jiunge na foleni",
ai_position: "Nafasi kwenye foleni",
ai_services: "Huduma",
ai_placeholder: "Uliza SmartHospital...",

footer:
"© 2026 SmartHospital. Mfumo mahiri wa kusimamia foleni za hospitali."

},

es: {

nav_about: "Acerca de",
nav_services: "Servicios",
nav_how: "Cómo funciona",
login: "Iniciar sesión",

hero_small: "SISTEMA INTELIGENTE DE GESTIÓN DE COLAS HOSPITALARIAS",
hero_title: 'La atención médica es <span>más sencilla.</span>',
hero_description:
"Únase a la cola del hospital digitalmente, siga su posición y pase menos tiempo esperando. SmartHospital conecta a los pacientes con los servicios hospitalarios mediante un sistema sencillo y eficiente.",

get_started: "Comenzar",
learn_more: "Cómo funciona",

queue_status: "Estado de la cola",
active: "ACTIVO",
queue_number: "Su número de cola",
department: "Departamento",
outpatient: "Consulta externa",
now_serving: "Atendiendo ahora",
people_ahead: "Personas delante",

how_label: "Cómo funciona",
how_title: "Simple. Rápido. Organizado.",
how_description:
"SmartHospital facilita a los pacientes el acceso a los servicios hospitalarios y ayuda al personal a gestionar las colas de manera eficiente.",

register_title: "Registrarse",
register_text:
"Cree su cuenta de paciente y proporcione la información necesaria de forma segura.",

join_title: "Unirse a una cola",
join_text:
"Seleccione el departamento hospitalario que necesita y reciba su número de cola.",

track_title: "Siga su turno",
track_text:
"Controle su posición y sepa cuándo se acerca su turno.",

services_label: "Servicios hospitalarios",
services_title: "Una plataforma. Múltiples servicios.",
services_description:
"SmartHospital ayuda a organizar diferentes colas hospitalarias mediante una sola plataforma.",

outpatient_text:
"Gestione de forma eficiente las colas de consultas generales y pacientes ambulatorios.",

laboratory: "Laboratorio",
laboratory_text:
"Organice a los pacientes que esperan servicios de laboratorio y reduzca las esperas innecesarias.",

pharmacy: "Farmacia",
pharmacy_text:
"Ayude a los pacientes a gestionar el tiempo de espera en la farmacia.",

quick_label: "Acceso rápido",
quick_title: "Acceda a SmartHospital desde su teléfono.",
quick_description:
"Escanee el código QR con la cámara de su teléfono para abrir SmartHospital al instante. Después puede registrarse, unirse a una cola y seguir su turno desde su teléfono.",

easy_access: "Acceso fácil",
no_typing: "Sin escribir",
mobile_friendly: "Compatible con móviles",

scan_title: "Escanee para abrir SmartHospital",
scan_subtitle: "Use la cámara de su teléfono",

help_title: "¿Necesita ayuda?",
help_description:
"Nuestro equipo de soporte está disponible para ayudarle con preguntas sobre el registro, las colas y los servicios hospitalarios.",

call_desk: "Llamar",
chat: "Chat",

ai_subtitle: "Asistente virtual",
ai_registration: "Registro",
ai_queue: "Unirse a una cola",
ai_position: "Posición en la cola",
ai_services: "Servicios",
ai_placeholder: "Pregunte a SmartHospital...",

footer:
"© 2026 SmartHospital. Gestión inteligente de colas hospitalarias."

}

};


/* =========================================================
   LANGUAGE SELECTOR
========================================================= */

const languageSelect =
document.getElementById("languageSelect");


function changeLanguage(language) {

    const selected =
        translations[language];

    if (!selected) {
        return;
    }


    /* TEXT CONTENT */

    document
        .querySelectorAll("[data-i18n]")
        .forEach(function(element) {

            const key =
                element.getAttribute("data-i18n");

            if (selected[key]) {

                element.innerHTML =
                    selected[key];

            }

        });


    /* PLACEHOLDERS */

    document
        .querySelectorAll("[data-i18n-placeholder]")
        .forEach(function(element) {

            const key =
                element.getAttribute(
                    "data-i18n-placeholder"
                );

            if (selected[key]) {

                element.placeholder =
                    selected[key];

            }

        });


    /* HTML LANGUAGE */

    document.documentElement.lang =
        language;


    /* SAVE LANGUAGE */

    localStorage.setItem(
        "smartHospitalLanguage",
        language
    );


    /* UPDATE AI WELCOME */

    updateAIWelcome(language);

}


languageSelect.addEventListener(
    "change",
    function() {

        changeLanguage(
            this.value
        );

    }
);


/* =========================================================
   LOAD SAVED LANGUAGE
========================================================= */

const savedLanguage =
localStorage.getItem(
    "smartHospitalLanguage"
) || "en";


languageSelect.value =
savedLanguage;


changeLanguage(
    savedLanguage
);


/* =========================================================
   AI ELEMENTS
========================================================= */

const aiButton =
document.getElementById("aiButton");

const aiChat =
document.getElementById("aiChat");

const aiClose =
document.getElementById("aiClose");

const aiMessages =
document.getElementById("aiMessages");

const aiInput =
document.getElementById("aiInput");

const aiSend =
document.getElementById("aiSend");


/* =========================================================
   OPEN AI
========================================================= */

aiButton.addEventListener(
    "click",
    function() {

        aiChat.classList.toggle(
            "active"
        );

        if (
            aiChat.classList.contains(
                "active"
            )
        ) {

            aiInput.focus();

        }

    }
);


/* =========================================================
   CLOSE AI
========================================================= */

aiClose.addEventListener(
    "click",
    function() {

        aiChat.classList.remove(
            "active"
        );

    }
);


/* =========================================================
   ADD MESSAGE
========================================================= */

function addMessage(
    message,
    type
) {

    const div =
        document.createElement(
            "div"
        );

    div.className =
        "ai-message " + type;

    div.textContent =
        message;

    aiMessages.appendChild(
        div
    );

    aiMessages.scrollTop =
        aiMessages.scrollHeight;

}


/* =========================================================
   AI RESPONSES
========================================================= */

function getAIResponse(
    question
) {

    const q =
        question.toLowerCase();

    const language =
        localStorage.getItem(
            "smartHospitalLanguage"
        ) || "en";


    if (
        q.includes("hello") ||
        q.includes("hi") ||
        q.includes("hey") ||
        q.includes("muraho") ||
        q.includes("bonjour") ||
        q.includes("hola") ||
        q.includes("habari")
    ) {

        const greetings = {

            en:
            "Hello! 👋 Welcome to SmartHospital. How can I assist you today?",

            rw:
            "Muraho! 👋 Murakaza neza kuri SmartHospital. Nabafasha iki uyu munsi?",

            fr:
            "Bonjour ! 👋 Bienvenue sur SmartHospital. Comment puis-je vous aider aujourd'hui ?",

            sw:
            "Habari! 👋 Karibu SmartHospital. Ninaweza kukusaidia nini leo?",

            es:
            "¡Hola! 👋 Bienvenido a SmartHospital. ¿Cómo puedo ayudarle hoy?"

        };

        return greetings[language];

    }


    if (
        q.includes("register") ||
        q.includes("registration") ||
        q.includes("create account") ||
        q.includes("iyandikishe") ||
        q.includes("inscription") ||
        q.includes("registro")
    ) {

        const responses = {

            en:
            "To register, click the 'Get Started' button and complete your patient information.",

            rw:
            "Kugira ngo wiyandikishe, kanda kuri 'Tangira' hanyuma wuzuze amakuru yawe y'umurwayi.",

            fr:
            "Pour vous inscrire, cliquez sur « Commencer » et remplissez vos informations de patient.",

            sw:
            "Ili kujisajili, bofya 'Anza' na ujaze taarifa zako za mgonjwa.",

            es:
            "Para registrarse, haga clic en « Comenzar » y complete su información de paciente."

        };

        return responses[language];

    }


    if (
        q.includes("join") &&
        (
            q.includes("queue") ||
            q.includes("line")
        )
    ) {

        const responses = {

            en:
            "After logging into SmartHospital, open the queue section, select the department you need, and join the available queue.",

            rw:
            "Nyuma yo kwinjira muri SmartHospital, fungura igice cy'umurongo, hitamo serivisi ukeneye maze ujye ku murongo.",

            fr:
            "Après vous être connecté à SmartHospital, ouvrez la section des files, choisissez le service souhaité et rejoignez la file disponible.",

            sw:
            "Baada ya kuingia SmartHospital, fungua sehemu ya foleni, chagua idara unayohitaji na jiunge na foleni.",

            es:
            "Después de iniciar sesión en SmartHospital, abra la sección de colas, seleccione el departamento que necesita y únase a la cola."

        };

        return responses[language];

    }


    if (
        q.includes("position") ||
        q.includes("people ahead") ||
        q.includes("my turn") ||
        q.includes("umwanya")
    ) {

        const responses = {

            en:
            "You can monitor your queue position from your SmartHospital patient dashboard.",

            rw:
            "Ushobora gukurikirana umwanya wawe ku murongo ukoresheje dashboard y'umurwayi.",

            fr:
            "Vous pouvez suivre votre position dans la file depuis votre tableau de bord patient SmartHospital.",

            sw:
            "Unaweza kufuatilia nafasi yako kwenye foleni kupitia dashibodi yako ya mgonjwa.",

            es:
            "Puede controlar su posición en la cola desde su panel de paciente SmartHospital."

        };

        return responses[language];

    }


    if (
        q.includes("service") ||
        q.includes("department") ||
        q.includes("hospital") ||
        q.includes("serivisi")
    ) {

        const responses = {

            en:
            "SmartHospital currently provides queue management support for Outpatient, Laboratory, and Pharmacy services.",

            rw:
            "SmartHospital ifasha gucunga imirongo ya serivisi zo kwa muganga, Laboratwari na Farumasi.",

            fr:
            "SmartHospital prend actuellement en charge les files d'attente des consultations externes, du laboratoire et de la pharmacie.",

            sw:
            "SmartHospital husaidia kusimamia foleni za huduma za wagonjwa wa nje, maabara na famasia.",

            es:
            "SmartHospital gestiona actualmente las colas de consultas externas, laboratorio y farmacia."

        };

        return responses[language];

    }


    if (
        q.includes("qr") ||
        q.includes("scan") ||
        q.includes("phone")
    ) {

        const responses = {

            en:
            "You can scan the SmartHospital QR code using your smartphone camera to open the system quickly.",

            rw:
            "Ushobora gusikana QR code ya SmartHospital ukoresheje camera ya telefoni kugira ngo ufungure sisitemu vuba.",

            fr:
            "Vous pouvez scanner le QR code SmartHospital avec l'appareil photo de votre téléphone pour ouvrir rapidement le système.",

            sw:
            "Unaweza kuscan QR code ya SmartHospital kwa kutumia kamera ya simu yako ili kufungua mfumo haraka.",

            es:
            "Puede escanear el código QR de SmartHospital con la cámara de su teléfono para abrir rápidamente el sistema."

        };

        return responses[language];

    }


    if (
        q.includes("thank") ||
        q.includes("thanks") ||
        q.includes("murakoze") ||
        q.includes("merci")
    ) {

        const responses = {

            en:
            "You're very welcome! 😊 I'm happy to help.",

            rw:
            "Murakaza neza! 😊 Nishimiye kubafasha.",

            fr:
            "Je vous en prie ! 😊 Je suis heureux de vous aider.",

            sw:
            "Karibu sana! 😊 Nimefurahi kukusaidia.",

            es:
            "¡De nada! 😊 Me alegra poder ayudarle."

        };

        return responses[language];

    }


    const fallback = {

        en:
        "I can help you with registration, joining a queue, checking your queue position, QR access, hospital services, and login.",

        rw:
        "Nshobora kugufasha kwiyandikisha, kujya ku murongo, kureba umwanya wawe, gukoresha QR code, serivisi z'ibitaro no kwinjira.",

        fr:
        "Je peux vous aider avec l'inscription, les files d'attente, votre position, l'accès QR, les services hospitaliers et la connexion.",

        sw:
        "Ninaweza kukusaidia kuhusu usajili, kujiunga na foleni, kufuatilia nafasi yako, QR code, huduma za hospitali na kuingia.",

        es:
        "Puedo ayudarle con el registro, las colas, su posición, el acceso mediante QR, los servicios hospitalarios y el inicio de sesión."

    };

    return fallback[language];

}


/* =========================================================
   ASK AI
========================================================= */

function askAI(
    question
) {

    if (!question.trim()) {
        return;
    }

    addMessage(
        question,
        "user"
    );


    setTimeout(
        function() {

            const response =
                getAIResponse(
                    question
                );

            addMessage(
                response,
                "bot"
            );

        },
        450
    );

}


/* =========================================================
   SEND BUTTON
========================================================= */

aiSend.addEventListener(
    "click",
    function() {

        const question =
            aiInput.value.trim();

        if (!question) {
            return;
        }

        askAI(question);

        aiInput.value = "";

    }
);


/* =========================================================
   ENTER KEY
========================================================= */

aiInput.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Enter"
        ) {

            event.preventDefault();

            aiSend.click();

        }

    }
);


/* =========================================================
   UPDATE AI WELCOME MESSAGE
========================================================= */

function updateAIWelcome(
    language
) {

    const welcome =
        document.getElementById(
            "welcomeMessage"
        );

    const messages = {

        en:
        "Hello 👋\n\nWelcome to SmartHospital.\n\nI can help you with registration, joining a queue, checking your queue, and understanding our hospital services.",

        rw:
        "Muraho 👋\n\nMurakaza neza kuri SmartHospital.\n\nNshobora kugufasha kwiyandikisha, kujya ku murongo, gukurikirana umwanya wawe no kumenya serivisi z'ibitaro.",

        fr:
        "Bonjour 👋\n\nBienvenue sur SmartHospital.\n\nJe peux vous aider avec l'inscription, les files d'attente, le suivi de votre position et les services hospitaliers.",

        sw:
        "Habari 👋\n\nKaribu SmartHospital.\n\nNinaweza kukusaidia kuhusu usajili, kujiunga na foleni, kufuatilia nafasi yako na huduma za hospitali.",

        es:
        "Hola 👋\n\nBienvenido a SmartHospital.\n\nPuedo ayudarle con el registro, las colas, el seguimiento de su posición y los servicios hospitalarios."

    };


    welcome.innerText =
        messages[language];

}

</script>

</body>
</html>

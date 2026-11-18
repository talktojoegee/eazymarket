<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="{{hyperAsset('logos/hyper-favicon.png')}}"/>

    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

    @php
        $siteName = 'Hypercommerce';
        $siteUrl = 'https://hypercommerce.eshopweb.store';
        $ogImage = hyperAsset('assets/landing/images/og-image.png');
        $isInfinitieTechMarket = request()->query('market') === 'infinitietech';
        $buyUrl = $isInfinitieTechMarket
            ? 'https://infinitietech.com/cart?product=hypercommerce-multivendor-ecommerce-marketplace-flutter-apps-laravel-admin-panel-website&license=regular'
            : 'https://1.envato.market/2RXEBz';
        $extendedUrl = $isInfinitieTechMarket
            ? 'https://infinitietech.com/cart?product=hypercommerce-multivendor-ecommerce-marketplace-flutter-apps-laravel-admin-panel-website&license=extended'
            : 'https://1.envato.market/4aRbB9';
        $docsUrl = url('docs');
        $metaTitle = 'Hypercommerce — Multivendor eCommerce Marketplace | Flutter App + Next.js Web + Laravel Admin & Seller Panel';
        $metaDescription = 'Launch your own multivendor marketplace with Hypercommerce — Flutter customer app, Next.js web storefront, Laravel admin & seller panels, multi-currency markets, and full source code. One-time purchase.';
    @endphp

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}"/>
    <link rel="canonical" href="{{ $siteUrl }}"/>

    <meta property="og:type" content="website"/>
    <meta property="og:site_name" content="{{ $siteName }}"/>
    <meta property="og:title" content="{{ $metaTitle }}"/>
    <meta property="og:description" content="{{ $metaDescription }}"/>
    <meta property="og:url" content="{{ $siteUrl }}"/>
    <meta property="og:image" content="{{ $ogImage }}"/>
    <meta property="og:image:width" content="1200"/>
    <meta property="og:image:height" content="630"/>

    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="{{ $metaTitle }}"/>
    <meta name="twitter:description" content="{{ $metaDescription }}"/>
    <meta name="twitter:image" content="{{ $ogImage }}"/>

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Android, iOS, Web',
            'description' => $metaDescription,
            'image' => $ogImage,
            'url' => $siteUrl,
            'brand' => ['@type' => 'Brand', 'name' => 'Infinitie Technologies'],
            'author' => ['@type' => 'Organization', 'name' => 'Infinitie Technologies', 'url' => 'https://infinitietech.com/'],
            'offers' => [
                '@type' => 'Offer',
                'price' => 'REPLACE_PRICE',
                'priceCurrency' => 'USD',
                'url' => $buyUrl,
                'availability' => 'https://schema.org/InStock',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap"
        rel="stylesheet"/>

    <!-- Tailwind CDN (temporary - replace with output.css after running CLI) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- SwiperJS (screenshots slider) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        heading: ["Lexend Deca", "sans-serif"],
                        body: ["Source Sans 3", "sans-serif"],
                    },
                },
            },
        };
    </script>

    <!-- Meta Pixel Code -->
    <script>
        !function (f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function () {
                n.callMethod ?
                    n.callMethod.apply(n, arguments) : n.queue.push(arguments)
            };
            if (!f._fbq) f._fbq = n;
            n.push = n;
            n.loaded = !0;
            n.version = '2.0';
            n.queue = [];
            t = b.createElement(e);
            t.async = !0;
            t.src = v;
            s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s)
        }(window, document, 'script',
            'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '980868264018226');
        fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
                   src="https://www.facebook.com/tr?id=980868264018226&ev=PageView&noscript=1"/></noscript>
    <!-- End Meta Pixel Code -->

    <!-- Smooth Scrolling -->
    <style>
        html {
            scroll-behavior: smooth;
        }

        /* Mobile Menu Styles */
        .mobile-menu {
            display: none;
        }

        .mobile-menu.active {
            display: flex;
        }

        .store-badge {
            max-height: 40px;
            display: block;
        }

        @media (max-width: 767px) {
            body {
                padding-bottom: 76px;
            }
        }

        @media (max-width: 480px) {
            .store-badge {
                height: 45px;
            }
        }

        [data-animate] {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.7s ease-out, transform 0.7s ease-out;
            transition-delay: var(--d, 0s);
            will-change: opacity, transform;
        }

        [data-animate="left"] {
            transform: translateX(-32px);
        }

        [data-animate="right"] {
            transform: translateX(32px);
        }

        [data-animate="zoom"] {
            transform: scale(0.94);
        }

        [data-animate].in-view {
            opacity: 1;
            transform: none;
        }

        .hero-float {
            animation: heroFloat 6s ease-in-out infinite;
        }

        @keyframes heroFloat {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-12px);
            }
        }

        .hero-glow {
            animation: heroGlow 6s ease-in-out infinite;
        }

        @keyframes heroGlow {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.06);
                opacity: 0.85;
            }
        }

        .faq-item summary::-webkit-details-marker {
            display: none;
        }

        .faq-item[open] .faq-icon {
            transform: rotate(180deg);
        }

        .faq-item[open] .faq-body {
            animation: faqReveal 0.3s ease-out;
        }

        @keyframes faqReveal {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        .phone-frame {
            position: relative;
            border-radius: 14% / 6.5%;
            padding: 1.7%;
            background: linear-gradient(150deg, #6b6b70 0%, #2b2b2f 18%, #4a4a50 42%, #24242a 68%, #5c5c62 100%);
            box-shadow: 0 18px 20px -12px rgba(0, 0, 0, 0.45), 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .phone-frame::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.55), rgba(255, 255, 255, 0) 35%, rgba(255, 255, 255, 0.25));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        .phone-screen {
            position: relative;
            width: 100%;
            aspect-ratio: 1320 / 2868;
            overflow: hidden;
            border-radius: 12.5% / 5.8%;
            background: #000;
        }

        .phone-screen img {
            height: 100%;
            width: 100%;
            object-fit: contain;
            display: block;
        }

        .phone-btn {
            position: absolute;
            background: linear-gradient(180deg, #5a5a60, #313136);
            border-radius: 2px;
        }

        .phone-btn-action {
            left: -1.2%;
            top: 17%;
            width: 1.4%;
            height: 5%;
            border-radius: 2px 0 0 2px;
        }

        .phone-btn-vol-up {
            left: -1.2%;
            top: 26%;
            width: 1.4%;
            height: 8.5%;
            border-radius: 2px 0 0 2px;
        }

        .phone-btn-vol-down {
            left: -1.2%;
            top: 36.5%;
            width: 1.4%;
            height: 8.5%;
            border-radius: 2px 0 0 2px;
        }

        .phone-btn-power {
            right: -1.2%;
            top: 30%;
            width: 1.4%;
            height: 12%;
            border-radius: 0 2px 2px 0;
        }

        .phone-btn-camera {
            right: -1.2%;
            top: 46%;
            width: 1.4%;
            height: 6%;
            border-radius: 0 2px 2px 0;
        }

        #scroll-progress-track {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 50;
            height: 3px;
            width: 100%;
            background: transparent;
            pointer-events: none;
        }

        #scroll-progress {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
            border-radius: 0 999px 999px 0;
            transition: width 0.1s linear;
        }

        .story-stage {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 46vh;
            background: #fff;
            box-shadow: 0 12px 16px -8px rgba(255, 255, 255, 0.95);
        }

        .story-phone {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            width: 150px;
        }

        .story-phone .phone-frame {
            width: 100%;
        }

        .story-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            z-index: -1;
            height: 118%;
            width: 175%;
            transform: translate(-50%, -50%);
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(251, 191, 36, 0.22) 0%, rgba(251, 191, 36, 0.07) 45%, rgba(251, 191, 36, 0) 70%);
            pointer-events: none;
        }

        .story-atoms {
            display: none;
        }

        @media (min-width: 768px) {
            .story-atoms {
                display: block;
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                transform: translate(-50%, -50%);
                pointer-events: none;
                z-index: 0;
            }

            .story-blob {
                position: absolute;
                top: 50%;
                left: 50%;
                border-radius: 9999px;
                filter: blur(36px);
                transform: translate(-50%, -50%);
            }

            .story-blob-1 {
                width: 340px;
                height: 340px;
                background: rgba(251, 191, 36, 0.18);
                animation: storyBlob1 14s ease-in-out infinite;
            }

            .story-blob-2 {
                width: 260px;
                height: 260px;
                background: rgba(96, 165, 250, 0.12);
                animation: storyBlob2 18s ease-in-out infinite;
            }

            @keyframes storyBlob1 {
                0%, 100% {
                    transform: translate(-58%, -54%) scale(1);
                }
                50% {
                    transform: translate(-42%, -46%) scale(1.12);
                }
            }

            @keyframes storyBlob2 {
                0%, 100% {
                    transform: translate(-40%, -52%) scale(1);
                }
                50% {
                    transform: translate(-60%, -44%) scale(1.15);
                }
            }

            .story-orbit {
                position: absolute;
                top: 0;
                left: 0;
                transform-origin: 0 0;
                animation: storySpin linear infinite;
            }

            .story-atom {
                position: absolute;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 44px;
                height: 44px;
                margin: -22px;
                border-radius: 14px;
                background: #fff;
                border: 1px solid rgba(0, 0, 0, 0.06);
                box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.18);
                color: #f59e0b;
                animation: storyUnspin linear infinite;
                animation-duration: inherit;
            }

            .story-atom svg {
                width: 20px;
                height: 20px;
            }

            @keyframes storySpin {
                to {
                    transform: rotate(360deg);
                }
            }

            @keyframes storyUnspin {
                to {
                    transform: rotate(-360deg);
                }
            }

            .story-orbit-a {
                animation-duration: 26s;
            }

            .story-orbit-a .story-atom {
                left: 235px;
                top: -30px;
                color: #f59e0b;
            }

            .story-orbit-b {
                animation-duration: 34s;
                animation-direction: reverse;
            }

            .story-orbit-b .story-atom {
                left: 205px;
                top: 120px;
                color: #f59e0b;
            }

            .story-orbit-c {
                animation-duration: 30s;
            }

            .story-orbit-c .story-atom {
                left: -250px;
                top: 60px;
                color: #60a5fa;
            }

            .story-orbit-d {
                animation-duration: 38s;
                animation-direction: reverse;
            }

            .story-orbit-d .story-atom {
                left: -215px;
                top: -140px;
                color: #f59e0b;
            }

            .story-orbit-e {
                animation-duration: 32s;
            }

            .story-orbit-e .story-atom {
                left: 250px;
                top: 180px;
                color: #60a5fa;
            }

            .story-orbit-f {
                animation-duration: 28s;
                animation-direction: reverse;
            }

            .story-orbit-f .story-atom {
                left: -240px;
                top: 200px;
                color: #34d399;
            }

        }

        @media (prefers-reduced-motion: reduce) {
            .story-blob,
            .story-orbit,
            .story-atom {
                animation: none !important;
            }
        }

        .story-shot {
            position: absolute;
            inset: 0;
            height: 100%;
            width: 100%;
            object-fit: contain;
            opacity: 0;
            transform: scale(1.015);
            transition: opacity 0.45s ease, transform 0.45s ease;
        }

        .story-shot.is-active {
            opacity: 1;
            transform: none;
        }

        .story-dots {
            display: flex;
            gap: 0.4rem;
        }

        .story-dot {
            height: 5px;
            width: 5px;
            border-radius: 999px;
            background: #e5e7eb;
            transition: background-color 0.3s ease, width 0.3s ease;
        }

        .story-dot.is-active {
            width: 16px;
            background: #fbbf24;
        }

        .story-step {
            display: flex;
            align-items: center;
            min-height: 52vh;
            opacity: 0.4;
            transition: opacity 0.4s ease;
        }

        .story-step.is-active {
            opacity: 1;
        }

        @media (min-width: 768px) {
            .story-stage {
                height: 100vh;
                background: transparent;
                box-shadow: none;
            }

            .story-phone {
                width: 288px;
            }

            .story-step {
                min-height: 72vh;
            }

            .story-step:first-child {
                padding-top: 6vh;
            }

            .story-step:last-child {
                padding-bottom: 6vh;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .story-shot {
                transition: none;
                transform: none;
            }

            .story-step {
                opacity: 1;
            }
        }

        .gallery-tab.is-active {
            background-color: rgb(251 191 36 / 0.9);
            color: #000;
            font-weight: 600;
        }

        .gallery-scroll,
        .roadmap-scroll {
            scrollbar-width: thin;
            scrollbar-color: #e5e7eb transparent;
        }

        .gallery-scroll::-webkit-scrollbar,
        .roadmap-scroll::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }

        .gallery-scroll::-webkit-scrollbar-thumb,
        .roadmap-scroll::-webkit-scrollbar-thumb {
            background: #e5e7eb;
            border-radius: 999px;
        }

        .gallery-scroll {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
            touch-action: pan-x pan-y;
            scroll-padding: 0 1rem;
            cursor: grab;
        }

        .gallery-scroll.is-dragging {
            cursor: grabbing;
            scroll-behavior: auto;
        }

        .gallery-scroll.is-dragging,
        .gallery-scroll.is-snapless {
            scroll-snap-type: none;
        }

        .gallery-scroll.is-dragging img {
            pointer-events: none;
        }

        .gallery-panel::before,
        .gallery-panel::after {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            width: 40px;
            z-index: 6;
            pointer-events: none;
        }

        .gallery-panel::before {
            left: -36px;
            background: linear-gradient(to right, #fff 0%, rgba(255, 255, 255, 0) 100%);
        }

        .gallery-panel::after {
            right: -36px;
            background: linear-gradient(to left, #fff 0%, rgba(255, 255, 255, 0) 100%);
        }

        @media (min-width: 768px) {
            .gallery-panel::before,
            .gallery-panel::after {
                width: 72px;
            }
        }

        .gallery-nav {
            position: absolute;
            top: 50%;
            z-index: 10;
            height: 2.75rem;
            width: 2.75rem;
            transform: translateY(-50%);
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #374151;
            box-shadow: 0 6px 16px -4px rgba(0, 0, 0, 0.18);
            transition: opacity 0.2s ease, background-color 0.2s ease, color 0.2s ease;
        }

        .gallery-nav:hover {
            background: #fbbf24;
            color: #000;
        }

        .gallery-nav-prev {
            left: -0.5rem;
        }

        .gallery-nav-next {
            right: -0.5rem;
        }

        .gallery-nav[disabled],
        .gallery-nav.swiper-button-disabled {
            opacity: 0;
            pointer-events: none;
        }

        @media (min-width: 768px) {
            .features-cloud {
                -webkit-mask-image: linear-gradient(to right, transparent, #000 9%, #000 91%, transparent);
                mask-image: linear-gradient(to right, transparent, #000 9%, #000 91%, transparent);
            }
        }

        .gallery-swiper {
            padding: 8px 4px 16px;
        }

        .gallery-swiper .swiper-slide {
            height: auto;
        }

        .gallery-swiper .swiper-wrapper {
            align-items: center;
        }

        #sticky-cta {
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        @media (max-width: 767px) {
            .wa-widget,
            .buy-float {
                transition: transform 0.3s ease, background-color 0.2s ease;
            }

            body.cta-up .wa-widget {
                transform: translateY(-72px);
            }

            body.cta-up .buy-float {
                transform: translateY(-72px);
            }
        }

        .buy-float {
            animation: buyPulse 2.2s ease-in-out infinite;
        }

        @keyframes buyPulse {
            0%, 100% {
                box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.25), 0 0 0 0 rgba(245, 158, 11, 0.55);
            }
            50% {
                box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.25), 0 0 0 12px rgba(245, 158, 11, 0);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .buy-float {
                animation: none;
            }
        }

        #sticky-cta.sticky-cta-hidden {
            transform: translateY(120%);
            opacity: 0;
            pointer-events: none;
        }

        #scroll-top {
            transition: opacity 0.3s ease, transform 0.3s ease, background-color 0.2s ease, color 0.2s ease;
        }

        #scroll-top.scroll-top-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .card-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-lift:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.12);
        }

        .landing-builder-feature {
            transition: transform 0.3s ease, border-color 0.3s ease, background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .landing-builder-feature.is-active {
            transform: translateY(-2px);
            border-color: rgba(251, 191, 36, 0.75);
            background: rgba(251, 191, 36, 0.12);
            box-shadow: 0 14px 30px -18px rgba(251, 191, 36, 0.6);
        }

        .landing-builder-feature.is-active .landing-builder-feature-icon {
            color: #111827;
            background: linear-gradient(135deg, #fcd34d, #f59e0b);
            border-color: rgba(253, 230, 138, 0.8);
        }

        .landing-builder-preview {
            box-shadow: 0 32px 80px -34px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.06);
        }

        .landing-builder-workspace {
            position: relative;
            height: 356px;
            min-height: 356px;
            overflow: hidden;
            background: radial-gradient(circle at 72% 12%, rgba(245, 158, 11, 0.12), transparent 38%), #111827;
        }

        .landing-builder-scene {
            position: absolute;
            inset: 0;
            padding: 18px;
            visibility: hidden;
            opacity: 0;
            transform: translateY(8px) scale(0.99);
            transition: opacity 0.35s ease, transform 0.35s ease, visibility 0.35s;
        }

        [data-landing-builder-demo][data-step="0"] [data-landing-builder-scene="0"],
        [data-landing-builder-demo][data-step="1"] [data-landing-builder-scene="1"],
        [data-landing-builder-demo][data-step="2"] [data-landing-builder-scene="2"],
        [data-landing-builder-demo][data-step="3"] [data-landing-builder-scene="3"],
        [data-landing-builder-demo][data-step="4"] [data-landing-builder-scene="4"],
        [data-landing-builder-demo][data-step="5"] [data-landing-builder-scene="5"],
        [data-landing-builder-demo][data-step="6"] [data-landing-builder-scene="6"],
        [data-landing-builder-demo][data-step="7"] [data-landing-builder-scene="7"] {
            visibility: visible;
            opacity: 1;
            transform: none;
        }

        .lb-scene-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            height: 30px;
            margin-bottom: 12px;
        }

        .lb-scene-head strong {
            color: #f8fafc;
            font-family: "Lexend Deca", sans-serif;
            font-size: 12px;
            font-weight: 600;
        }

        .lb-scene-head span {
            padding: 4px 8px;
            color: #fbbf24;
            font-size: 8px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: rgba(251, 191, 36, 0.09);
            border: 1px solid rgba(251, 191, 36, 0.18);
            border-radius: 999px;
        }

        .lb-stage-grid {
            display: grid;
            grid-template-columns: 116px minmax(0, 1fr) 126px;
            gap: 10px;
            height: 278px;
        }

        .lb-stage-grid.lb-stage-wide {
            grid-template-columns: minmax(0, 1.15fr) minmax(150px, 0.85fr);
        }

        .lb-panel,
        .lb-live-panel {
            min-width: 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.045);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
        }

        .lb-panel {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .lb-panel-title,
        .lb-live-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 5px;
            color: rgba(255, 255, 255, 0.52);
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .lb-live-panel {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .lb-live-title em {
            padding: 3px 6px;
            color: #fbbf24;
            font-size: 7px;
            font-style: normal;
            background: rgba(251, 191, 36, 0.1);
            border-radius: 999px;
        }

        .lb-option,
        .lb-action,
        .lb-sort-row {
            display: flex;
            align-items: center;
            gap: 6px;
            min-height: 29px;
            padding: 6px 8px;
            color: rgba(255, 255, 255, 0.52);
            font-size: 8px;
            font-style: normal;
            background: rgba(255, 255, 255, 0.045);
            border: 1px solid rgba(255, 255, 255, 0.055);
            border-radius: 7px;
        }

        .lb-option.is-active,
        .lb-option.lb-selected {
            color: #fff;
            background: rgba(245, 158, 11, 0.2);
            border-color: rgba(251, 191, 36, 0.28);
        }

        .lb-option svg,
        .lb-action svg {
            width: 11px;
            height: 11px;
            flex: 0 0 auto;
        }

        .lb-panel-note {
            margin-top: auto;
            color: rgba(255, 255, 255, 0.28);
            font-size: 7px;
            line-height: 1.4;
        }

        .lb-action {
            justify-content: center;
            margin-top: auto;
            color: #111827;
            font-weight: 700;
            background: linear-gradient(135deg, #fcd34d, #f59e0b);
            border-color: transparent;
        }

        .lb-action.lb-action-ghost {
            color: #fde68a;
            background: rgba(251, 191, 36, 0.08);
            border: 1px dashed rgba(251, 191, 36, 0.3);
        }

        .lb-storefront {
            display: grid;
            flex: 1;
            grid-template-rows: 14px 1.25fr 0.45fr 0.8fr;
            gap: 7px;
            min-height: 0;
            padding: 10px;
            overflow: hidden;
            background: rgba(2, 6, 23, 0.56);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 8px;
        }

        .lb-store-nav {
            width: 45%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .lb-store-hero {
            background: linear-gradient(115deg, rgba(245, 158, 11, 0.8), rgba(251, 191, 36, 0.35) 48%, rgba(59, 130, 246, 0.32));
            border-radius: 7px;
        }

        .lb-store-categories {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.11) 18%, transparent 18% 25%, rgba(255, 255, 255, 0.11) 25% 43%, transparent 43% 50%, rgba(255, 255, 255, 0.11) 50% 68%, transparent 68% 75%, rgba(255, 255, 255, 0.11) 75%);
            border-radius: 6px;
        }

        .lb-store-products {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.08) 31%, transparent 31% 35%, rgba(255, 255, 255, 0.08) 35% 66%, transparent 66% 70%, rgba(255, 255, 255, 0.08) 70%);
            border-radius: 6px;
        }

        .lb-product-preview {
            display: grid;
            flex: 1;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            min-height: 0;
            padding: 10px;
            background: rgba(2, 6, 23, 0.56);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 8px;
        }

        .lb-product-card {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
            padding: 6px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
        }

        .lb-product-card i {
            flex: 1;
            min-height: 75px;
            background: linear-gradient(145deg, rgba(251, 191, 36, 0.58), rgba(245, 158, 11, 0.14));
            border-radius: 5px;
        }

        .lb-product-card:nth-child(2) i {
            background: linear-gradient(145deg, rgba(96, 165, 250, 0.46), rgba(59, 130, 246, 0.14));
        }

        .lb-product-card:nth-child(3) i {
            background: linear-gradient(145deg, rgba(244, 114, 182, 0.42), rgba(124, 58, 237, 0.15));
        }

        .lb-product-card small {
            overflow: hidden;
            color: rgba(255, 255, 255, 0.52);
            font-size: 7px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .lb-play-preview {
            display: grid;
            flex: 1;
            grid-template-columns: 1.25fr 0.75fr;
            grid-template-rows: repeat(2, 1fr);
            gap: 8px;
            min-height: 0;
            padding: 10px;
            background: rgba(2, 6, 23, 0.56);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 8px;
        }

        .lb-play-preview i {
            background: linear-gradient(145deg, rgba(96, 165, 250, 0.42), rgba(59, 130, 246, 0.12));
            border-radius: 6px;
        }

        .lb-play-preview i:first-child {
            grid-row: 1 / 3;
            background: linear-gradient(145deg, rgba(251, 191, 36, 0.68), rgba(245, 158, 11, 0.16));
        }

        .lb-sort-row {
            min-height: 34px;
        }

        .lb-sort-row b {
            color: rgba(255, 255, 255, 0.76);
            font-size: 8px;
        }

        .lb-sort-row small {
            color: rgba(255, 255, 255, 0.28);
            font-size: 9px;
        }

        .lb-sort-row em {
            margin-left: auto;
            color: #fbbf24;
            font-size: 7px;
            font-style: normal;
        }

        .lb-input {
            display: flex;
            align-items: center;
            min-height: 32px;
            padding: 7px 9px;
            overflow: hidden;
            color: rgba(255, 255, 255, 0.76);
            font-size: 8px;
            background: rgba(255, 255, 255, 0.055);
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 7px;
        }

        .lb-input span {
            display: block;
            width: 0;
            overflow: hidden;
            white-space: nowrap;
        }

        .lb-navbar-preview {
            display: flex;
            flex-wrap: wrap;
            align-content: flex-start;
            gap: 6px;
            min-height: 100px;
            padding: 10px;
            background: rgba(2, 6, 23, 0.56);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 8px;
        }

        .lb-navbar-preview i {
            align-self: flex-start;
            padding: 5px 8px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 7px;
            font-style: normal;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 999px;
        }

        .lb-navbar-preview i:first-child {
            color: #111827;
            background: #fbbf24;
        }

        .lb-created,
        .lb-preview-created {
            opacity: 0;
            transform: translateY(6px);
        }

        .lb-media-canvas {
            position: relative;
            display: flex;
            flex: 1;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 0;
            overflow: hidden;
            color: #fff;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.8), rgba(30, 64, 175, 0.58) 56%, rgba(236, 72, 153, 0.46));
            border-radius: 8px;
        }

        .lb-media-canvas::after {
            position: absolute;
            inset: 0;
            content: "";
            background: radial-gradient(circle at center, rgba(255, 255, 255, 0.24), transparent 52%);
            transform: scale(0.65);
        }

        .lb-media-play {
            z-index: 1;
            display: flex;
            width: 46px;
            height: 46px;
            align-items: center;
            justify-content: center;
            background: rgba(2, 6, 23, 0.48);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 999px;
        }

        .lb-media-play svg {
            width: 18px;
            height: 18px;
        }

        .lb-media-canvas small {
            z-index: 1;
            font-size: 9px;
            font-weight: 700;
        }

        .landing-builder-cursor {
            position: absolute;
            z-index: 20;
            top: calc(100% - 28px);
            left: calc(100% - 28px);
            display: block;
            width: 24px;
            height: 24px;
            color: #fff;
            opacity: 0;
            filter: drop-shadow(0 4px 5px rgba(0, 0, 0, 0.65));
            pointer-events: none;
            transform: translate(-2px, -2px);
            will-change: top, left, opacity;
        }

        .landing-builder-cursor svg {
            width: 22px;
            height: 22px;
        }

        .landing-builder-cursor-ring {
            position: absolute;
            top: 3px;
            left: 2px;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(253, 230, 138, 0.9);
            border-radius: 999px;
            animation: landingBuilderCursorPulse 1.15s ease-in-out infinite;
        }

        @keyframes landingBuilderCursorPulse {
            0%, 45%, 100% {
                opacity: 0;
                transform: scale(0.45);
            }
            62% {
                opacity: 0.9;
                transform: scale(1.35);
            }
        }

        .landing-builder-caption {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            visibility: hidden;
            opacity: 0;
            transform: translateY(5px);
            transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;
        }

        .landing-builder-caption.is-active {
            visibility: visible;
            opacity: 1;
            transform: none;
        }

        .landing-builder-progress {
            position: relative;
            width: 18px;
            height: 4px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.13);
            border-radius: 999px;
        }

        .landing-builder-progress.is-active {
            width: 34px;
        }

        .landing-builder-progress.is-active::after {
            position: absolute;
            inset: 0;
            content: "";
            background: linear-gradient(90deg, #fcd34d, #f59e0b);
            transform-origin: left;
            animation: landingBuilderProgress 4.8s linear;
        }

        @keyframes landingBuilderProgress {
            from {
                transform: scaleX(0);
            }
            to {
                transform: scaleX(1);
            }
        }

        [data-landing-builder-demo][data-step="0"] .lb-app-option {
            animation: lbAppOption 4.8s ease;
        }

        [data-landing-builder-demo][data-step="0"] .lb-web-option {
            animation: lbWebOption 4.8s ease;
        }

        [data-landing-builder-demo][data-step="0"] .lb-device-storefront {
            animation: lbDeviceStorefront 4.8s ease;
        }

        @keyframes lbAppOption {
            0%, 44% {
                color: #fff;
                background: rgba(245, 158, 11, 0.2);
            }
            58%, 100% {
                color: rgba(255, 255, 255, 0.52);
                background: rgba(255, 255, 255, 0.045);
            }
        }

        @keyframes lbWebOption {
            0%, 44% {
                color: rgba(255, 255, 255, 0.52);
                background: rgba(255, 255, 255, 0.045);
            }
            58%, 100% {
                color: #fff;
                background: rgba(245, 158, 11, 0.2);
            }
        }

        @keyframes lbDeviceStorefront {
            0%, 44% {
                width: 76%;
                margin-inline: auto;
                border-radius: 12px;
            }
            58%, 100% {
                width: 100%;
                margin-inline: auto;
                border-radius: 8px;
            }
        }

        [data-landing-builder-demo][data-step="1"] .lb-product-grid-option,
        [data-landing-builder-demo][data-step="3"] .lb-scroll-option,
        [data-landing-builder-demo][data-step="3"] .lb-lift-option,
        [data-landing-builder-demo][data-step="6"] .lb-fashion-option,
        [data-landing-builder-demo][data-step="6"] .lb-market-option,
        [data-landing-builder-demo][data-step="7"] .lb-fade-option {
            animation: lbOptionSelect 4.8s ease forwards;
        }

        @keyframes lbOptionSelect {
            0%, 48% {
                color: rgba(255, 255, 255, 0.52);
                background: rgba(255, 255, 255, 0.045);
            }
            62%, 100% {
                color: #fff;
                background: rgba(245, 158, 11, 0.22);
                border-color: rgba(251, 191, 36, 0.3);
            }
        }

        [data-landing-builder-demo][data-step="2"] .lb-created-row {
            animation: lbPlayCreateSort 4.8s ease forwards;
        }

        [data-landing-builder-demo][data-step="2"] .lb-shift-row {
            animation: lbPlayShift 4.8s ease forwards;
        }

        [data-landing-builder-demo][data-step="2"] .lb-preview-created {
            animation: lbCreatedReveal 4.8s ease forwards;
        }

        @keyframes lbPlayCreateSort {
            0%, 28% {
                opacity: 0;
                transform: translateY(6px);
            }
            40%, 62% {
                opacity: 1;
                transform: translateY(0);
            }
            78%, 100% {
                opacity: 1;
                color: #fff;
                background: rgba(245, 158, 11, 0.2);
                transform: translateY(-30px);
            }
        }

        @keyframes lbPlayShift {
            0%, 62% {
                transform: translateY(0);
            }
            78%, 100% {
                transform: translateY(30px);
            }
        }

        @keyframes lbCreatedReveal {
            0%, 36% {
                opacity: 0;
                transform: translateY(6px);
            }
            50%, 100% {
                opacity: 1;
                transform: none;
            }
        }

        [data-landing-builder-demo][data-step="3"] .lb-play-preview {
            animation: lbLayoutChange 4.8s ease;
        }

        [data-landing-builder-demo][data-step="3"] .lb-play-preview i:first-child {
            animation: lbLayoutLead 4.8s ease;
        }

        @keyframes lbLayoutChange {
            0%, 48% {
                grid-template-columns: 1.25fr 0.75fr;
                grid-template-rows: repeat(2, 1fr);
            }
            62%, 100% {
                grid-template-columns: repeat(4, 1fr);
                grid-template-rows: 1fr;
            }
        }

        @keyframes lbLayoutLead {
            0%, 48% {
                grid-row: 1 / 3;
            }
            62%, 100% {
                grid-row: 1;
            }
        }

        [data-landing-builder-demo][data-step="4"] .lb-input span {
            animation: lbTypeNavbar 4.8s steps(12, end) forwards;
        }

        [data-landing-builder-demo][data-step="4"] .lb-created,
        [data-landing-builder-demo][data-step="4"] .lb-preview-created {
            animation: lbCreatedReveal 4.8s ease forwards;
        }

        @keyframes lbTypeNavbar {
            0%, 20% {
                width: 0;
            }
            45%, 100% {
                width: 80px;
            }
        }

        [data-landing-builder-demo][data-step="5"] .lb-navbar-summer {
            animation: lbNavbarMoveUp 4.8s ease forwards;
        }

        [data-landing-builder-demo][data-step="5"] .lb-navbar-home {
            animation: lbNavbarMoveDown 4.8s ease forwards;
        }

        @keyframes lbNavbarMoveUp {
            0%, 34% {
                transform: translateY(0);
            }
            68%, 100% {
                color: #fff;
                background: rgba(245, 158, 11, 0.2);
                transform: translateY(-41px);
            }
        }

        @keyframes lbNavbarMoveDown {
            0%, 34% {
                transform: translateY(0);
            }
            68%, 100% {
                transform: translateY(41px);
            }
        }

        [data-landing-builder-demo][data-step="7"] .lb-media-canvas::after {
            animation: lbMediaMotion 2.2s ease-in-out 2 alternate;
        }

        @keyframes lbMediaMotion {
            from {
                opacity: 0.25;
                transform: scale(0.65);
            }
            to {
                opacity: 1;
                transform: scale(1.18);
            }
        }

        @media (max-width: 639px) {
            .landing-builder-workspace {
                height: 328px;
                min-height: 328px;
            }

            .landing-builder-scene {
                padding: 12px;
            }

            .lb-scene-head {
                height: 24px;
                margin-bottom: 8px;
            }

            .lb-scene-head strong {
                font-size: 9px;
            }

            .lb-scene-head span {
                font-size: 6px;
            }

            .lb-stage-grid {
                grid-template-columns: 76px minmax(0, 1fr) 86px;
                gap: 6px;
                height: 272px;
            }

            .lb-stage-grid.lb-stage-wide {
                grid-template-columns: minmax(0, 1.05fr) minmax(105px, 0.95fr);
            }

            .lb-panel,
            .lb-live-panel {
                padding: 7px;
                border-radius: 8px;
            }

            .lb-option,
            .lb-action,
            .lb-sort-row {
                gap: 4px;
                min-height: 25px;
                padding: 5px;
                font-size: 6px;
            }

            .lb-panel-title,
            .lb-live-title {
                font-size: 6px;
            }

            .lb-panel-note {
                display: none;
            }

            .lb-product-preview {
                gap: 4px;
                padding: 6px;
            }

            .lb-product-card {
                gap: 3px;
                padding: 3px;
            }

            .lb-product-card i {
                min-height: 55px;
            }

            .lb-product-card small {
                font-size: 5px;
            }

            .lb-sort-row {
                min-height: 30px;
            }

            .lb-navbar-preview i {
                padding: 4px 5px;
                font-size: 5px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            [data-animate] {
                opacity: 1;
                transform: none;
                transition: none;
            }

            .hero-float,
            .hero-glow {
                animation: none;
            }

            .landing-builder-feature,
            .landing-builder-scene,
            .landing-builder-caption {
                transition: none;
            }

            .landing-builder-cursor {
                display: none;
            }

            .landing-builder-progress.is-active::after,
            [data-landing-builder-demo] * {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>

<body class="bg-white text-black font-body">
<div id="scroll-progress-track" aria-hidden="true">
    <div id="scroll-progress"></div>
</div>

<!-- NAVBAR -->
<header class="absolute top-0 left-0 w-full z-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-5 flex items-center justify-between">
        <!-- Logo -->
        <div class="flex items-center gap-2 font-heading font-semibold text-lg">
            <img src="{{hyperAsset('logos/hyper-logo.png')}}" alt="Logo" class="h-10 sm:h-16"/>
        </div>

        <!-- Nav Links -->
        <nav class="hidden md:flex items-center gap-1 bg-gray-50 backdrop-blur-sm rounded-full px-2 py-1.5 shadow-sm">
            <a href="#modules"
               class="nav-pill px-5 py-2 rounded-full text-sm font-semibold text-black bg-amber-400/90 transition"
               title="View our modules">Modules</a>
            <a href="#features"
               class="nav-pill px-5 py-2 rounded-full text-sm font-medium text-gray-700 hover:bg-amber-100 transition"
               title="Explore features">Features</a>
            <a href="#customization"
               class="nav-pill px-5 py-2 rounded-full text-sm font-medium text-gray-700 hover:bg-amber-100 transition"
               title="Learn about customization">Customisation</a>
            <a href="#faq"
               class="nav-pill px-5 py-2 rounded-full text-sm font-medium text-gray-700 hover:bg-amber-100 transition"
               title="Frequently asked questions">FAQs</a>
            <a href="{{url('docs')}}" target="_blank"
               class="nav-pill px-5 py-2 rounded-full text-sm font-medium text-gray-700 hover:bg-amber-100 transition"
               title="Check resources and technology">Resources</a>
        </nav>

        <!-- CTA -->
        <a target="_blank"
           href="{{ $buyUrl }}"
           class="hidden md:inline-block bg-amber-400 hover:bg-amber-500 text-black font-semibold px-7 py-2.5 rounded-xl text-sm lg:text-base shadow-md transition"
           title="Purchase Hypercommerce on CodeCanyon">
            Buy Now
        </a>

        <!-- Mobile Menu Button -->
        <button id="mobile-menu-btn" class="md:hidden text-black p-2" aria-label="Toggle menu">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu"
         class="mobile-menu md:hidden absolute top-full left-0 w-full bg-white/95 backdrop-blur-sm flex-col py-4 shadow-lg">
        <nav class="flex flex-col gap-4 px-6">
            <a href="#modules" class="text-gray-700 hover:text-black py-2 border-b border-gray-200"
               title="View our modules">Modules</a>
            <a href="#features" class="text-gray-700 hover:text-black py-2 border-b border-gray-200"
               title="Explore features">Features</a>
            <a href="#customization" class="text-gray-700 hover:text-black py-2 border-b border-gray-200"
               title="Learn about customization">Customisation</a>
            <a href="#faq" class="text-gray-700 hover:text-black py-2 border-b border-gray-200"
               title="Frequently asked questions">FAQs</a>
            <a href="{{url('docs')}}" target="_blank"
               class="text-gray-700 hover:text-black py-2 border-b border-gray-200"
               title="Check resources and technology">Resources</a>
            <a target="_blank"
               href="{{ $buyUrl }}"
               class="bg-amber-400 text-black font-semibold px-6 py-2 rounded-xl text-center mt-2"
               title="Purchase Hypercommerce on CodeCanyon">
                Buy Now
            </a>
        </nav>
    </div>
</header>

<!-- HERO SECTION -->
<section class="relative min-h-screen flex items-center overflow-hidden
           bg-cover bg-center
           bg-[url('{{ hyperAsset('assets/landing/images/hero-bg.png') }}')]
           md:bg-[url('{{ hyperAsset('assets/landing/images/hero-bg.png') }}')]">
    <div class="relative z-10 max-w-7xl mx-auto w-full px-4 sm:px-6 pt-28 pb-16 md:pt-32 md:pb-20">
        <div class="grid md:grid-cols-12 items-center gap-10 lg:gap-12">

            <div class="text-center md:text-left md:col-span-6">
                <h1 data-animate
                    class="font-heading text-3xl sm:text-4xl lg:text-[2rem] xl:text-[2.3rem] font-bold text-black leading-tight sm:leading-snug">
                    Build, Scale &amp; Grow Your Own
                    Multivendor Marketplace
                </h1>

                <p data-animate style="--d: .15s"
                   class="mt-4 sm:mt-6 text-gray-500 text-sm sm:text-base font-body leading-relaxed max-w-xl mx-auto md:mx-0">
                    Launch a powerful online marketplace where multiple sellers
                    can register, manage products, accept orders, and grow their
                    business&mdash;all from one centralized platform.
                </p>

                <div data-animate style="--d: .3s"
                     class="mt-7 sm:mt-9 flex flex-wrap items-center justify-center md:justify-start gap-4">
                    <a href="#modules" title="Get a free demo"
                       class="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-500 text-black font-semibold px-7 py-3 rounded-lg shadow-md transition text-sm sm:text-base">
                        Get Free Demo
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                    <a target="_blank"
                       href="{{ $buyUrl }}"
                       title="Purchase Hypercommerce on CodeCanyon"
                       class="inline-flex items-center gap-2 bg-white/70 text-amber-500 font-semibold px-7 py-3 rounded-lg border border-amber-400 hover:bg-amber-50 transition text-sm sm:text-base">
                        Buy Now
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                </div>

                <div data-animate style="--d: .45s"
                     class="mt-8 sm:mt-10 flex flex-wrap items-center justify-center md:justify-start gap-3 sm:gap-4">
                    <div
                        class="w-12 h-12 sm:w-14 sm:h-14 bg-white rounded-xl border border-gray-100 shadow-sm flex items-center justify-center p-2.5">
                        <img src="{{hyperAsset('assets/landing/images/flutter-image.png')}}" alt="Flutter"
                             class="max-w-full max-h-full object-contain"/>
                    </div>
                    <div
                        class="w-12 h-12 sm:w-14 sm:h-14 bg-white rounded-xl border border-gray-100 shadow-sm flex items-center justify-center p-2.5">
                        <img src="{{hyperAsset('assets/landing/images/laravel-image.png')}}" alt="Laravel"
                             class="max-w-full max-h-full object-contain"/>
                    </div>
                    <div
                        class="w-12 h-12 sm:w-14 sm:h-14 bg-white rounded-xl border border-gray-100 shadow-sm flex items-center justify-center p-2.5">
                        <img src="{{hyperAsset('assets/landing/images/firebase-image.png')}}" alt="Firebase"
                             class="max-w-full max-h-full object-contain"/>
                    </div>
                    <div
                        class="w-12 h-12 sm:w-14 sm:h-14 bg-white rounded-xl border border-gray-100 shadow-sm flex items-center justify-center p-2.5">
                        <img src="{{hyperAsset('assets/landing/images/bootstrap-image.png')}}" alt="Bootstrap"
                             class="max-w-full max-h-full object-contain"/>
                    </div>
                </div>
            </div>

            <div data-animate="right" style="--d: .2s"
                 class="relative flex items-center justify-center md:justify-end md:col-span-6">
                <div
                    class="hero-glow absolute inset-0 bg-contain bg-center bg-no-repeat bg-[url('{{ hyperAsset('assets/landing/images/hero-phones-bg.png') }}')]"></div>
                <img src="{{ hyperAsset('assets/landing/images/hero-phones.png') }}"
                     alt="Hypercommerce mobile apps"
                     class="hero-float relative w-full max-w-md md:max-w-none object-contain drop-shadow-2xl"/>
            </div>
        </div>
    </div>
</section>

{{-- TRUST STATS BAR --}}
<section class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
        <p data-animate class="text-center font-body text-xs sm:text-sm uppercase tracking-wider text-gray-400 mb-6">
            Built by Infinitie Technologies
        </p>
        @php
            $trustStats = [
                ['value' => '8+', 'label' => 'Years in business'],
                ['value' => '4000+', 'label' => 'Projects delivered'],
                ['value' => '180+', 'label' => 'Countries served'],
                ['value' => '4.8', 'label' => 'Average client rating'],
            ];
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8">
            @foreach ($trustStats as $stat)
                <div data-animate style="--d: {{ $loop->index * 0.1 }}s" class="text-center">
                    <p data-count class="font-heading text-2xl sm:text-3xl md:text-4xl font-black text-gray-900">
                        {{ $stat['value'] }}
                    </p>
                    <p class="mt-1 font-body text-xs sm:text-sm text-gray-500">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- INTRODUCTION SECTION --}}
<section id="introduction" class="pt-28 pb-16 bg-white text-black">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex flex-col md:flex-row items-stretch gap-12 lg:gap-16">

            <div data-animate="left" class="w-full md:w-[40%] flex-shrink-0">
                <img src="{{ hyperAsset('assets/landing/images/hypercommerce-intro.png') }}"
                     alt="Hypercommerce Intro Banner"
                     class="w-full h-auto rounded-2xl object-cover shadow-sm block">
            </div>

            <div data-animate="right" style="--d: .15s" class="w-full md:w-[60%] flex flex-col justify-between py-1">

                <div>
                    <h3 class="font-heading text-3xl md:text-[40px] font-black text-[#0f172a] leading-tight mb-5">
                        What is Hypercommerce?
                    </h3>

                    <div
                        class="font-body space-y-4 text-[#475569] text-base md:text-[19px] leading-[1.6] font-normal">
                        <p>
                            Hypercommerce is a complete multi-vendor eCommerce platform for launching your own online
                            marketplace — at any scale, in any category, in any country. Sell fashion, electronics,
                            beauty, gadgets, home & lifestyle, digital goods, services — or open it up to sellers across
                            every category you can think of. Onboard vendors, list unlimited products and start taking
                            orders, all from one place.
                        </p>
                        <p>
                            Go global from day one with multi-country, multi-currency markets — set the currencies, tax
                            rules and shipping zones for each region, and let customers shop and pay in their own
                            currency. Safe, PCI-compliant payment gateway checkout with support for major processors
                            keeps every transaction protected end-to-end, while automated Shiprocket integration handles
                            fulfilment.
                        </p>
                        <p>
                            An advanced drag-and-drop home builder puts your storefront in your hands — build any layout
                            you want with reusable sections, banners, product rails, categories, promo blocks and custom
                            widgets. Create any design you want without touching code: theme colours, typography,
                            spacing, mobile layouts and full page templates are all editable from the panel.
                        </p>
                        <p>
                            Sellers get more power too — multiple stores per seller means one vendor account can run
                            several branded storefronts side by side, each with its own catalogue, currency and market.
                            A native Flutter customer app, a Next.js web storefront, a powerful admin panel and a
                            dedicated seller portal give you full control over products, orders, vendors, payments,
                            inventory and promotions. A seller app is on the roadmap as a free update.
                        </p>
                    </div>
                </div>

                <div class="mt-6 md:mt-3">
                    <a href="https://wa.me/919974692496?text={{ rawurlencode('Hi Hypercommerce team, I’d like to know more about the Hypercommerce multi-vendor platform. Can you share the details?') }}"
                       class="inline-flex items-center justify-center " target="_blank" rel="noopener noreferrer"
                       title="Get More Details">
                        <img src="{{hyperAsset('assets/landing/images/get-more-details-btn.png')}}"
                             alt="Get More details" class="store-badge">
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- OUR MODULES SECTION -->
<section id="modules" class="py-12 sm:py-16 md:py-20 bg-white text-black">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <!-- Section Header -->
        <div data-animate class="text-center max-w-3xl mx-auto">
                <span
                    class="inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">
                    The platform
                </span>

            <h2 class="mt-4 sm:mt-6 text-2xl sm:text-3xl md:text-4xl font-heading font-bold leading-tight">
                One Ecosystem, Every Role Covered
            </h2>

            <p class="mt-3 sm:mt-4 text-sm sm:text-base text-gray-600 font-body">
                A customer app, web storefront, admin panel and seller portal that work together out of the box — try
                each live demo below.
            </p>
        </div>

        <!-- Cards Grid -->
        <div class="mt-8 sm:mt-10 md:mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 md:gap-8">
            <!-- Customer App -->
            <div data-animate
                 class="card-lift relative border rounded-xl p-4 sm:p-6 bg-white shadow-sm flex flex-col">
                <h3 class="font-heading font-semibold text-base sm:text-lg">
                    Customer App
                </h3>
                <p class="mt-2 xs:text-xs text-gray-600 font-body leading-relaxed">
                    An intuitive shopping experience with smart search, secure payments, and real-time order tracking.
                </p>

                <div class="mt-4 sm:mt-6 h-40 sm:h-48 w-full overflow-hidden rounded-lg">
                    <img src="{{hyperAsset('assets/landing/images/customer-app.svg')}}" alt="Customer App"
                         class="h-full w-full object-cover object-top"/>
                </div>

                <div class="mt-auto flex justify-between items-center border-t pt-3 text-sm font-medium text-blue-600">
                    <a href="https://play.google.com/store/apps/details?id=com.hyperCommerce.customer"
                       class="flex-1 text-center hover:underline py-2 flex justify-center" target="_blank"
                       title="Download Customer Android App">
                        <img src="{{hyperAsset('assets/landing/images/playstore.png')}}" alt="Get it on Google Play"
                             class="store-badge">
                    </a>
                    <div class="w-px h-4 bg-gray-300"></div>
                    <a href="https://testflight.apple.com/join/r2D5XbGt"
                       class="flex-1 text-center hover:underline py-2 flex justify-center" target="_blank"
                       title="Download Customer iOS App (TestFlight)">
                        <img src="{{hyperAsset('assets/landing/images/appstore.png')}}" alt="Download on the App Store"
                             class="store-badge">
                    </a>
                </div>
            </div>

            <!-- Customer Web -->
            <div data-animate style="--d: .15s"
                 class="card-lift relative border rounded-xl p-4 sm:p-6 bg-white shadow-sm flex flex-col">
                <h3 class="font-heading font-semibold text-base sm:text-lg">
                    Customer Web
                </h3>
                <p class="mt-2 xs:text-xs text-gray-600 font-body leading-relaxed">
                    The same storefront in any browser — browse, cart and checkout, with no app to install.
                </p>

                <div
                    class="mt-4 sm:mt-6 h-40 sm:h-48 w-full overflow-hidden rounded-lg border border-1 border-gray-200">
                    <img src="{{hyperAsset('assets/landing/images/gallery/web-home.png')}}" alt="Customer Web"
                         class="h-full w-full object-cover object-top"/>
                </div>

                <div class="mt-auto border-t pt-3 text-center">
                    <a href="https://hypercommerce-web.eshopweb.store"
                       class="text-sm font-medium text-blue-600 hover:underline py-2 inline-block" target="_blank"
                       title="View Customer Web Demo">
                        <img src="{{hyperAsset('assets/landing/images/checkdemo.png')}}" alt="View Customer Web Demo"
                             class="store-badge">
                    </a>
                </div>
            </div>

            <!-- Admin Panel -->
            <div data-animate style="--d: .30s"
                 class="card-lift relative border rounded-xl p-4 sm:p-6 bg-white shadow-sm flex flex-col">
                <h3 class="font-heading font-semibold text-base sm:text-lg">
                    Admin Panel
                </h3>
                <p class="mt-2 xs:text-xs text-gray-600 font-body leading-relaxed">
                    Control your entire marketplace with advanced management tools and real-time business insights.
                </p>

                <div class="mt-4 sm:mt-6 h-40 sm:h-48 w-full overflow-hidden rounded-lg">
                    <img src="{{hyperAsset('assets/landing/images/admin-panel.svg')}}" alt="Admin Panel"
                         class="h-full w-full object-cover object-top"/>
                </div>

                <div class="mt-auto border-t pt-3 text-center">
                    <a href="https://hypercommerce.eshopweb.store/admin"
                       class="text-sm font-medium text-blue-600 hover:underline py-2 inline-block" target="_blank"
                       title="View Admin Panel Demo">
                        <img src="{{hyperAsset('assets/landing/images/checkdemo.png')}}" alt="View Admin Panel Demo"
                             class="store-badge">
                    </a>
                </div>
            </div>

            <!-- Seller Panel -->
            <div data-animate style="--d: .45s"
                 class="card-lift relative border rounded-xl p-4 sm:p-6 bg-white shadow-sm flex flex-col">
                <h3 class="font-heading font-semibold text-base sm:text-lg">
                    Seller Panel
                </h3>
                <p class="mt-2 xs:text-xs text-gray-600 font-body leading-relaxed">
                    Manage products, inventory, orders, and earnings from one powerful dashboard.
                </p>

                <div class="mt-4 sm:mt-6 h-40 sm:h-48 w-full overflow-hidden rounded-lg">
                    <img src="{{hyperAsset('assets/landing/images/seller-panel.svg')}}" alt="Seller Panel"
                         class="h-full w-full object-cover object-top"/>
                </div>

                <div class="mt-auto border-t pt-3 text-center">
                    <a href="https://hypercommerce.eshopweb.store/seller"
                       class="text-sm font-medium text-blue-600 hover:underline py-2 inline-block" target="_blank"
                       title="View Seller Panel Demo">
                        <img src="{{hyperAsset('assets/landing/images/checkdemo.png')}}" alt="View Seller Panel Demo"
                             class="store-badge">
                    </a>
                </div>
            </div>

            <!-- Seller App -->
            <div data-animate
                 class="card-lift relative border rounded-xl p-4 sm:p-6 bg-white shadow-sm flex flex-col">
                <h3 class="font-heading font-semibold text-base sm:text-lg">
                    Seller App
                </h3>
                <p class="mt-2 xs:text-xs text-gray-600 font-body leading-relaxed">
                    Run your store from your phone — the seller panel, mirrored one-to-one on mobile.
                </p>

                <div class="mt-4 sm:mt-6 h-40 sm:h-48 w-full overflow-hidden rounded-lg">
                    <img src="{{hyperAsset('assets/landing/images/seller-mockup.png')}}" alt="Seller App"
                         class="h-full w-full object-cover object-top"/>
                </div>

                <div class="mt-auto flex justify-between items-center border-t pt-3 text-sm font-medium text-blue-600">
                    <a href="https://play.google.com/store/apps/details?id=com.hyperCommmerce.seller"
                       class="flex-1 text-center hover:underline py-2 flex justify-center" target="_blank"
                       title="Download Customer Android App">
                        <img src="{{hyperAsset('assets/landing/images/playstore.png')}}" alt="Get it on Google Play"
                             class="store-badge">
                    </a>
                    <div class="w-px h-4 bg-gray-300"></div>
                    <a href="https://testflight.apple.com/join/qXCRGNqq"
                       class="flex-1 text-center hover:underline py-2 flex justify-center" target="_blank"
                       title="Download Customer iOS App (TestFlight)">
                        <img src="{{hyperAsset('assets/landing/images/appstore.png')}}" alt="Download on the App Store"
                             class="store-badge">
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section id="features" class="py-12 sm:py-16 md:py-20"
         style="background: radial-gradient(ellipse 90% 70% at 50% 40%, #ffffff 0%, #FFF6E6 100%);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <!-- Section Header -->
        <div data-animate class="text-center max-w-3xl mx-auto">
           <span
               class="inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">
                    Features
                </span>
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-heading font-bold text-black mt-4 sm:mt-6">
                Everything Your Marketplace Needs
            </h2>
            <p class="mt-3 sm:mt-4 text-sm sm:text-base text-gray-600 font-body">
                Powerful tools for sellers, admins and customers — built in from day one, ready to scale as you grow.
            </p>
        </div>

        @php
            $features = [
                ['Multi-Vendor & Multi-Store Support', 'M3 21h18M5 21V8l7-4 7 4v13M9 21v-5h6v5M9 11h.01M15 11h.01'],
                ['Connect Stores Globally', 'M4 9.5 5 4h14l1 5.5M4 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 4 0M5 11v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8'],
                ['Sell Products Across Countries', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18'],
                ['Earn Commission Category-Wise', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 7v10M9.5 9a2.5 2.5 0 0 1 2.5-2h.7a2 2 0 0 1 0 4h-1.4a2 2 0 0 0 0 4h.7a2.5 2.5 0 0 0 2.5-2'],
                ['Manage Multiple Shipping Zones', 'M3 6h11v9H3zM14 9h3.5L21 12.5V15h-7M7.5 18a1.5 1.5 0 1 0 0-.01M16.5 18a1.5 1.5 0 1 0 0-.01'],
                ['Multiple Shipping Methods', 'M3 7h13v10H3zM16 10h3l2 2.5V17h-5M7 20a2 2 0 1 0 0-.01M17 20a2 2 0 1 0 0-.01'],
                ['Email Notifications & Alerts', 'M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM3 7l9 6 9-6'],
                ['System Wallet', 'M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v2M3 7v10a2 2 0 0 0 2 2h13a1 1 0 0 0 1-1v-3M16 12h4a1 1 0 0 1 1 1v0a1 1 0 0 1-1 1h-4a2 2 0 0 1 0-2'],
                ['Multi-Currency Support', 'M9 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10ZM14 8a5 5 0 1 1-4.9 6'],
                ['Multiple Product Variants', 'm12 3 9 5-9 5-9-5 9-5ZM3 13l9 5 9-5M3 17l9 5 9-5'],
                ['Home Page Builder', 'M4 4h16a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1ZM3 9h18M9 9v11'],
                ['Marketplace Analytics', 'M4 20V11M9 20V4M14 20v-6M19 20v-9M3 20h18'],
                ['Firebase Integration', 'M12 3s5 3.5 5 9a5 5 0 0 1-10 0c0-2.2 1-3.5 1-3.5s1.8 2 4 2c.2-3-.2-5.3 0-7.5Z'],
                ['Add Product Add-ons', 'M4 4h16a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1ZM12 8v8M8 12h8'],
                ['Smart Data Management', 'M12 3c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3ZM4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3'],
                ['Real-Time Notifications', 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M9.5 21a2.5 2.5 0 0 0 5 0'],
                ['Configure Any SMS Gateway', 'M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2zM8 9h8M8 13h5'],
                ['Refer & Earn', 'M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-2.6-3.5-6.8-3M15.4 5.5l-6.8 3'],
                ['Smart Search With Suggestions', 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14ZM20 20l-3.5-3.5M11 8v6M8 11h6'],
                ['One-Click Checkout / Buy Now', 'M3 4h2l2.4 12h10.2L20 8H6M9 20a1 1 0 1 0 0-.01M17 20a1 1 0 1 0 0-.01'],
                ['Secure Payment Gateways', 'M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6zM9 12l2 2 4-4'],
                ['Seamless Multi-Store Checkout', 'M6 8h12l-1 12H7L6 8ZM9 8a3 3 0 0 1 6 0M9 12h6'],
                ['Unlimited Categories & Products', 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z'],
            ];
            $pattern = [5, 4, 5, 4, 5];
            $rows = [];
            $i = 0;
            foreach ($pattern as $n) {
                $rows[] = array_slice($features, $i, $n);
                $i += $n;
                if ($i >= count($features)) break;
            }
            if ($i < count($features)) $rows[] = array_slice($features, $i);
        @endphp

        <div class="features-cloud mt-10 sm:mt-14 space-y-4 sm:space-y-6">
            @foreach ($rows as $ri => $row)
                <div data-animate style="--d: {{ $ri * 0.08 }}s"
                     class="flex flex-wrap justify-center gap-3 sm:gap-4 md:gap-5">
                    @foreach ($row as $f)
                        <div
                            class="feature-chip card-lift flex w-[150px] sm:w-[160px] flex-col items-center gap-3 rounded-2xl border border-gray-100 bg-white px-3 py-5 text-center shadow-sm">
                            <span
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-300 to-amber-400 text-black">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="{{ $f[1] }}"/>
                                </svg>
                            </span>
                            <span class="font-body text-xs sm:text-[13px] font-semibold leading-snug text-gray-700">
                                {{ $f[0] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div data-animate class="mt-8 text-center">
            <p class="font-body text-xs sm:text-sm text-gray-500">&amp; many more</p>
        </div>
    </div>
</section>

@php
    $homeBuilderFeatures = [
        [
            'title' => 'App & Web layouts',
            'copy' => 'Build independent storefront experiences for every screen.',
            'icon' => 'M5 4h14v11H5zM9 20h6M12 15v5M8 7h8',
        ],
        [
            'title' => 'Product sections',
            'copy' => 'Show featured, latest, category and curated product rails.',
            'icon' => 'M6 8h12l-1 12H7L6 8ZM9 8a3 3 0 0 1 6 0M9 12h6',
        ],
        [
            'title' => 'Play / Image layouts',
            'copy' => 'Mix visual stories, promotional imagery and linked content.',
            'icon' => 'M4 5h16v14H4zM4 14l4-4 4 4 3-3 5 5M9 8h.01',
        ],
        [
            'title' => 'Styles & configuration',
            'copy' => 'Control layout, spacing, hover effects and appearance.',
            'icon' => 'M4 7h10M18 7h2M14 5v4M4 17h2M10 17h10M8 15v4M4 12h5M13 12h7M11 10v4',
        ],
        [
            'title' => 'Create navbar views',
            'copy' => 'Create custom navigation experiences with visual content.',
            'icon' => 'M4 6h16M4 12h10M4 18h8M18 14v6M15 17h6',
        ],
        [
            'title' => 'App / Web navbar sorting',
            'copy' => 'Set a different navigation order for app and web.',
            'icon' => 'M8 6h12M8 12h9M8 18h6M4 4v16M2 7l2-3 2 3M2 17l2 3 2-3',
        ],
        [
            'title' => 'Market & category targeting',
            'copy' => 'Personalise layouts for each market and category.',
            'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3 12h18M12 3a15 15 0 0 1 0 18',
        ],
        [
            'title' => 'Media & animations',
            'copy' => 'Use images, video and Lottie with polished motion.',
            'icon' => 'M4 5h12v14H4zM16 9l4-2v10l-4-2M8 9l4 3-4 3z',
        ],
    ];
@endphp

<section id="home-builder" data-landing-builder-root
         class="relative overflow-hidden bg-[#0f172a] py-14 text-white sm:py-20 md:py-24">
    <div class="pointer-events-none absolute -left-32 top-10 h-80 w-80 rounded-full bg-amber-400/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -right-32 bottom-0 h-96 w-96 rounded-full bg-blue-500/10 blur-3xl"></div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6">
        <div data-animate class="mx-auto max-w-3xl text-center">
            <span
                class="inline-flex items-center gap-2 rounded-full border border-amber-300/40 bg-amber-400/10 px-4 py-1.5 font-body text-xs font-semibold text-amber-300 sm:text-sm">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-300 shadow-[0_0_0_4px_rgba(252,211,77,0.12)]"></span>
                Visual Home Builder
            </span>
            <h2 class="mt-5 font-heading text-2xl font-bold leading-tight text-white sm:text-3xl md:text-4xl">
                Build every storefront without touching code
            </h2>
            <p class="mx-auto mt-4 max-w-2xl font-body text-sm leading-relaxed text-slate-300 sm:text-base">
                Create, configure and reorder the complete app and web experience from one visual workspace, with every
                change reflected in a live preview.
            </p>
        </div>

        <div class="mt-10 grid items-center gap-10 lg:mt-14 lg:grid-cols-[0.82fr_1.18fr] lg:gap-12">
            <div data-animate="left" class="order-2 lg:order-1">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    @foreach ($homeBuilderFeatures as $feature)
                        <div data-landing-builder-feature="{{ $loop->index }}"
                             class="landing-builder-feature {{ $loop->first ? 'is-active' : '' }} flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.045] p-3.5 sm:p-4">
                            <span
                                class="landing-builder-feature-icon flex h-10 w-10 flex-none items-center justify-center rounded-xl border border-white/10 bg-white/[0.06] text-amber-300 transition sm:h-11 sm:w-11">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
                                     aria-hidden="true">
                                    <path d="{{ $feature['icon'] }}"/>
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <strong
                                    class="block font-heading text-xs font-semibold text-white sm:text-sm">{{ $feature['title'] }}</strong>
                                <small
                                    class="mt-1 block font-body text-[11px] leading-relaxed text-slate-400 sm:text-xs">{{ $feature['copy'] }}</small>
                            </span>
                        </div>
                    @endforeach
                </div>

                <a href="https://hypercommerce.eshopweb.store/admin" target="_blank" rel="noopener noreferrer"
                   class="mt-6 inline-flex items-center gap-2 rounded-xl bg-amber-400 px-5 py-3 font-body text-sm font-bold text-slate-950 shadow-[0_12px_28px_-14px_rgba(251,191,36,0.75)] transition hover:bg-amber-300"
                   title="View the Hypercommerce admin panel demo">
                    Explore the admin demo
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"
                         aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>

            <div data-animate="right" style="--d: .15s" class="order-1 min-w-0 lg:order-2">
                <div data-landing-builder-demo data-step="0"
                     class="landing-builder-preview overflow-hidden rounded-2xl border border-white/10 bg-slate-950/80">
                    <div class="flex h-11 items-center gap-2 border-b border-white/10 bg-white/[0.045] px-3 sm:px-4">
                        <span class="flex gap-1.5" aria-hidden="true">
                            <i class="h-2 w-2 rounded-full bg-red-400/70"></i>
                            <i class="h-2 w-2 rounded-full bg-amber-300/70"></i>
                            <i class="h-2 w-2 rounded-full bg-emerald-400/70"></i>
                        </span>
                        <strong class="ml-1 font-heading text-[10px] font-semibold text-slate-200 sm:text-xs">Hypercommerce
                            Home Builder</strong>
                        <span
                            class="ml-auto inline-flex items-center gap-1.5 font-body text-[9px] font-semibold text-emerald-300 sm:text-[10px]">
                            <i class="h-1.5 w-1.5 rounded-full bg-emerald-400 shadow-[0_0_0_3px_rgba(52,211,153,0.12)]"></i>
                            Live preview
                        </span>
                    </div>

                    <div data-landing-builder-workspace class="landing-builder-workspace" aria-hidden="true">
                        <div data-landing-builder-scene="0" class="landing-builder-scene">
                            <div class="lb-scene-head"><strong>App &amp; Web
                                    layouts</strong><span>Independent canvases</span></div>
                            <div class="lb-stage-grid">
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Layout</b>
                                    <i class="lb-option lb-app-option is-active" data-landing-builder-target>
                                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <rect x="7" y="2" width="10" height="20" rx="2"/>
                                            <path d="M11 18h2"/>
                                        </svg>
                                        App layout
                                    </i>
                                    <i class="lb-option lb-web-option" data-landing-builder-target>
                                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <rect x="3" y="4" width="18" height="13" rx="2"/>
                                            <path d="M8 21h8M12 17v4"/>
                                        </svg>
                                        Web layout
                                    </i>
                                    <small class="lb-panel-note">Each layout keeps its own sections, order and
                                        configuration.</small>
                                </div>
                                <div class="lb-live-panel">
                                    <b class="lb-live-title">Live storefront <em>App</em></b>
                                    <div class="lb-storefront lb-device-storefront">
                                        <i class="lb-store-nav"></i><i class="lb-store-hero"></i><i
                                            class="lb-store-categories"></i><i class="lb-store-products"></i>
                                    </div>
                                </div>
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Canvas</b>
                                    <i class="lb-option is-active">App</i>
                                    <i class="lb-option">Web</i>
                                    <span class="lb-action">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path
                                                d="M5 4h12l2 2v14H5zM8 4v6h8V4M8 16h8"/></svg>
                                        Save layout
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div data-landing-builder-scene="1" class="landing-builder-scene">
                            <div class="lb-scene-head"><strong>Product sections</strong><span>Content &amp; cards</span>
                            </div>
                            <div class="lb-stage-grid">
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Sections</b>
                                    <i class="lb-option">⋮⋮ Hero</i>
                                    <i class="lb-option">⋮⋮ Categories</i>
                                    <i class="lb-option is-active" data-landing-builder-target>⋮⋮ Products</i>
                                    <i class="lb-option">⋮⋮ Brands</i>
                                </div>
                                <div class="lb-live-panel">
                                    <b class="lb-live-title">Live storefront <em>Products</em></b>
                                    <div class="lb-product-preview">
                                        <span class="lb-product-card"><i></i><small>Summer shirt</small></span>
                                        <span class="lb-product-card"><i></i><small>Classic bag</small></span>
                                        <span class="lb-product-card"><i></i><small>New sneaker</small></span>
                                    </div>
                                </div>
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Product source</b>
                                    <i class="lb-option is-active" data-landing-builder-target>Featured</i>
                                    <i class="lb-option">Newest</i>
                                    <b class="lb-panel-title">Card style</b>
                                    <i class="lb-option lb-product-grid-option" data-landing-builder-target>Grid · 3
                                        cards</i>
                                </div>
                            </div>
                        </div>

                        <div data-landing-builder-scene="2" class="landing-builder-scene">
                            <div class="lb-scene-head"><strong>Play / Image
                                    layouts</strong><span>Create &amp; reorder</span></div>
                            <div class="lb-stage-grid">
                                <div class="lb-panel">
                                    <i class="lb-option">⋮⋮ Hero</i>
                                    <i class="lb-option lb-shift-row">⋮⋮ Categories</i>
                                    <i class="lb-option lb-created-row" data-landing-builder-target
                                       data-landing-builder-order="3" data-landing-builder-drag-y="-30">⋮⋮ Play /
                                        Image</i>
                                    <i class="lb-option">⋮⋮ Products</i>
                                    <span class="lb-action lb-action-ghost" data-landing-builder-target
                                          data-landing-builder-order="1">＋ Add section</span>
                                </div>
                                <div class="lb-live-panel">
                                    <b class="lb-live-title">Live storefront <em>Visual</em></b>
                                    <div class="lb-play-preview"><i class="lb-preview-created"></i><i></i><i></i></div>
                                </div>
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Choose section</b>
                                    <i class="lb-option">Products</i>
                                    <i class="lb-option">Categories</i>
                                    <i class="lb-option lb-selected" data-landing-builder-target
                                       data-landing-builder-order="2">Play / Image</i>
                                    <i class="lb-option">Brands</i>
                                </div>
                            </div>
                        </div>

                        <div data-landing-builder-scene="3" class="landing-builder-scene">
                            <div class="lb-scene-head"><strong>Styles &amp; configuration</strong><span>Edit Play / Image</span>
                            </div>
                            <div class="lb-stage-grid lb-stage-wide">
                                <div class="lb-live-panel">
                                    <b class="lb-live-title">Live storefront <em>Design</em></b>
                                    <div class="lb-play-preview"><i></i><i></i><i></i><i></i></div>
                                </div>
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Layout style</b>
                                    <i class="lb-option is-active">Grid</i>
                                    <i class="lb-option lb-scroll-option" data-landing-builder-target>Horizontal
                                        scroll</i>
                                    <b class="lb-panel-title">Hover effect</b>
                                    <i class="lb-option">Zoom</i>
                                    <i class="lb-option lb-lift-option" data-landing-builder-target>Lift</i>
                                    <span class="lb-action lb-action-ghost"
                                          data-landing-builder-target>＋ Add media</span>
                                </div>
                            </div>
                        </div>

                        <div data-landing-builder-scene="4" class="landing-builder-scene">
                            <div class="lb-scene-head"><strong>Create navbar
                                    views</strong><span>Visual navigation</span></div>
                            <div class="lb-stage-grid">
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Navbar views</b>
                                    <i class="lb-option is-active">All</i>
                                    <i class="lb-option">Fashion</i>
                                    <i class="lb-option">Grocery</i>
                                    <i class="lb-option lb-created">Summer Deals</i>
                                </div>
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Create navbar view</b>
                                    <div class="lb-input" data-landing-builder-target><span>Summer Deals</span></div>
                                    <span class="lb-action" data-landing-builder-target>＋ Create view</span>
                                    <b class="lb-panel-title">Content</b>
                                    <i class="lb-option">Title</i>
                                    <i class="lb-option" data-landing-builder-target>Image</i>
                                    <i class="lb-option">Lottie</i>
                                </div>
                                <div class="lb-live-panel">
                                    <b class="lb-live-title">Live navbar</b>
                                    <div class="lb-navbar-preview"><i>All</i><i>Fashion</i><i
                                            class="lb-preview-created">Summer</i></div>
                                </div>
                            </div>
                        </div>

                        <div data-landing-builder-scene="5" class="landing-builder-scene">
                            <div class="lb-scene-head"><strong>App / Web navbar
                                    sorting</strong><span>Drag &amp; drop</span></div>
                            <div class="lb-stage-grid lb-stage-wide">
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Navbar order <span class="lb-option is-active"
                                                                                 data-landing-builder-target>App</span></b>
                                    <i class="lb-sort-row lb-navbar-home"><small>⋮⋮</small><b>Home</b><em>01</em></i>
                                    <i class="lb-sort-row lb-navbar-summer" data-landing-builder-target
                                       data-landing-builder-drag-y="-41"><small>⋮⋮</small><b>Summer Deals</b><em>02</em></i>
                                    <i class="lb-sort-row"><small>⋮⋮</small><b>Fashion</b><em>03</em></i>
                                    <i class="lb-sort-row"><small>⋮⋮</small><b>Grocery</b><em>04</em></i>
                                    <span class="lb-action" data-landing-builder-target>Save sorting</span>
                                </div>
                                <div class="lb-live-panel">
                                    <b class="lb-live-title">Live navbar <em>App</em></b>
                                    <div class="lb-navbar-preview"><i>Home</i><i>Summer</i><i>Fashion</i><i>Grocery</i>
                                    </div>
                                    <small class="lb-panel-note">App and web navigation keep independent
                                        ordering.</small>
                                </div>
                            </div>
                        </div>

                        <div data-landing-builder-scene="6" class="landing-builder-scene">
                            <div class="lb-scene-head"><strong>Market &amp; category targeting</strong><span>Personalised storefronts</span>
                            </div>
                            <div class="lb-stage-grid lb-stage-wide">
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Scope</b>
                                    <i class="lb-option is-active">Global</i>
                                    <i class="lb-option" data-landing-builder-target>By category</i>
                                    <b class="lb-panel-title">Category</b>
                                    <i class="lb-option lb-fashion-option" data-landing-builder-target>Fashion</i>
                                    <b class="lb-panel-title">Market</b>
                                    <i class="lb-option lb-market-option" data-landing-builder-target>United States</i>
                                </div>
                                <div class="lb-live-panel">
                                    <b class="lb-live-title">Fashion storefront <em>United States</em></b>
                                    <div class="lb-storefront">
                                        <i class="lb-store-nav"></i><i class="lb-store-hero"></i><i
                                            class="lb-store-categories"></i><i class="lb-store-products"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div data-landing-builder-scene="7" class="landing-builder-scene">
                            <div class="lb-scene-head"><strong>Media &amp; animations</strong><span>Images, video &amp; Lottie</span>
                            </div>
                            <div class="lb-stage-grid lb-stage-wide">
                                <div class="lb-live-panel">
                                    <b class="lb-live-title">Live storefront <em>Motion</em></b>
                                    <div class="lb-media-canvas">
                                        <span class="lb-media-play">
                                            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        </span>
                                        <small>Summer collection</small>
                                    </div>
                                </div>
                                <div class="lb-panel">
                                    <b class="lb-panel-title">Media type</b>
                                    <i class="lb-option is-active">Image</i>
                                    <i class="lb-option" data-landing-builder-target>Video</i>
                                    <i class="lb-option" data-landing-builder-target>Lottie</i>
                                    <b class="lb-panel-title">Animation</b>
                                    <i class="lb-option">None</i>
                                    <i class="lb-option lb-fade-option" data-landing-builder-target>Fade in</i>
                                    <span class="lb-action lb-action-ghost">Upload media</span>
                                </div>
                            </div>
                        </div>

                        <span data-landing-builder-cursor class="landing-builder-cursor">
                            <svg fill="currentColor" stroke="#0f172a" stroke-width="1.1" viewBox="0 0 24 24">
                                <path d="M4.2 2.8 19 12.1l-6.1 1.2 3.7 6.3-3.1 1.8-3.7-6.3-3.9 4.8z"/>
                            </svg>
                            <i class="landing-builder-cursor-ring"></i>
                        </span>
                    </div>

                    <div
                        class="flex min-h-[72px] items-center gap-4 border-t border-white/10 bg-white/[0.035] px-4 py-3 sm:px-5">
                        <div class="relative h-10 min-w-0 flex-1">
                            @foreach ($homeBuilderFeatures as $feature)
                                <span data-landing-builder-caption="{{ $loop->index }}"
                                      class="landing-builder-caption {{ $loop->first ? 'is-active' : '' }}">
                                    <strong
                                        class="truncate font-heading text-[11px] font-semibold text-white sm:text-sm">{{ $feature['title'] }}</strong>
                                    <small
                                        class="truncate font-body text-[9px] text-slate-400 sm:text-xs">{{ $feature['copy'] }}</small>
                                </span>
                            @endforeach
                        </div>
                        <span class="flex items-center gap-1.5" aria-hidden="true">
                            @foreach ($homeBuilderFeatures as $feature)
                                <i data-landing-builder-progress="{{ $loop->index }}"
                                   class="landing-builder-progress {{ $loop->first ? 'is-active' : '' }}"></i>
                            @endforeach
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HOW Hypercommerce WORKS SECTION -->
<section class="bg-gray-50 py-12 sm:py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <!-- Title and Description -->
        <div data-animate class="text-center mb-8 sm:mb-10 md:mb-12">
            <h2 class="font-heading text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 sm:mb-4">
                Built to Scale Your Marketplace Business
            </h2>
            <p class="text-gray-600 font-body text-sm sm:text-base md:text-lg max-w-3xl mx-auto">
                Manage vendors, products, inventory, payments, shipping, and global operations from one powerful
                platform designed for modern eCommerce.
            </p>
        </div>

        <!-- Image -->
        <div data-animate="zoom" style="--d: .15s" class="flex justify-center">
            <!-- Mobile image -->
            <img src="{{ hyperAsset('assets/landing/images/marketplace-mobile.png') }}"
                 alt="How Hypercommerce Works" class="rounded-lg md:hidden"/>

            <!-- Desktop image -->
            <img src="{{ hyperAsset('assets/landing/images/marketplace.png') }}" alt="How Hypercommerce Works"
                 class="hidden w-full rounded-lg md:block"/>
        </div>
    </div>
</section>

{{-- APP FLOW SCROLLYTELLING --}}
@php
    $flowSteps = [
        [
            'src' => 'assets/landing/images/gallery/app-home.png',
            'label' => 'Discover',
            'title' => 'A storefront built for their market',
            'desc' => 'Customers land on a home feed of categories, banners and deals drawn from every seller in their market — detected automatically from their location.',
            'chips' => ['Market detected by IP', 'Multi-seller feed', 'Banners & campaigns'],
        ],
        [
            'src' => 'assets/landing/images/gallery/app-pl.png',
            'label' => 'Browse',
            'title' => 'Filter across the whole catalog',
            'desc' => 'Shoppers search, filter and sort across every seller at once, with category rails and live stock so nothing sold out ever reaches the cart.',
            'chips' => ['Search & filters', 'Live stock per location', 'Category rails'],
        ],
        [
            'src' => 'assets/landing/images/gallery/app-pdp.png',
            'label' => 'Choose',
            'title' => 'Variants, priced in their currency',
            'desc' => 'Sellers set a price once in the base currency. Every shopper sees it converted into their own market currency automatically, with variants, ratings and delivery estimates.',
            'chips' => ['Auto FX conversion', 'Variants & options', 'Ratings & reviews'],
        ],
        [
            'src' => 'assets/landing/images/gallery/app-cart.png',
            'label' => 'Checkout',
            'title' => 'Every number, itemised',
            'desc' => 'Shipping is resolved from the delivery address and market, then broken out line by line — items, delivery, platform fee and total savings before they pay.',
            'chips' => ['Address-based rates', 'Itemised bill', 'Savings surfaced'],
        ],
        [
            'src' => 'assets/landing/images/gallery/app-od.png',
            'label' => 'Track',
            'title' => 'Followed to the doorstep',
            'desc' => 'Orders move through accepted, preparing, collected and delivered, with courier and tracking details surfaced at every step.',
            'chips' => ['Status timeline', 'Courier & tracking ID', 'Returns built in'],
        ],
    ];

    $flowSteps = array_values(array_filter($flowSteps, fn ($s) => is_file(public_path($s['src']))));
@endphp

@if (count($flowSteps) > 1)
    <section id="how-it-works" class="relative bg-white py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div data-animate class="text-center mb-8 sm:mb-12 md:mb-16">
                <span
                    class="font-body inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">
                    The customer journey
                </span>
                <h2 class="font-heading mt-4 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                    From First Tap to Delivered
                </h2>
                <p class="mt-3 sm:mt-4 text-gray-600 font-body text-sm sm:text-base max-w-2xl mx-auto">
                    Scroll to walk through the flow your customers actually experience.
                </p>
            </div>

            <div class="story mx-auto grid max-w-5xl md:grid-cols-[minmax(0,0.85fr)_minmax(0,1fr)] md:gap-10 lg:gap-14">
                <div class="story-stage">
                    <div class="story-atoms" aria-hidden="true">
                        <span class="story-blob story-blob-1"></span>
                        <span class="story-blob story-blob-2"></span>
                        @php
                            $atoms = [
                                ['orbit' => 'a', 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3M17 13l2 2M9 20a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z'],
                                ['orbit' => 'b', 'icon' => 'M12 2l2.4 7.4H22l-6 4.4 2.3 7.2L12 16.6 5.7 21l2.3-7.2-6-4.4h7.6z'],
                                ['orbit' => 'c', 'icon' => 'M12 21s-7-4.5-7-10a7 7 0 1114 0c0 5.5-7 10-7 10zm0-7a3 3 0 100-6 3 3 0 000 6z'],
                                ['orbit' => 'd', 'icon' => 'M21 16V8a2 2 0 00-1-1.7l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.7l7 4a2 2 0 002 0l7-4A2 2 0 0021 16zM3.3 7L12 12l8.7-5M12 22V12'],
                                ['orbit' => 'e', 'icon' => 'M9 14l6-6M9.5 9.5h.01M14.5 14.5h.01M4 4h16v16H4z'],
                                ['orbit' => 'f', 'icon' => 'M20 6L9 17l-5-5'],
                            ];
                        @endphp
                        @foreach ($atoms as $atom)
                            <span class="story-orbit story-orbit-{{ $atom['orbit'] }}">
                                <span class="story-atom">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <path d="{{ $atom['icon'] }}"/>
                                    </svg>
                                </span>
                            </span>
                        @endforeach
                    </div>
                    <div class="story-phone">
                        <div class="story-glow" aria-hidden="true"></div>
                        <div class="phone-frame">
                            <span class="phone-btn phone-btn-action"></span>
                            <span class="phone-btn phone-btn-vol-up"></span>
                            <span class="phone-btn phone-btn-vol-down"></span>
                            <span class="phone-btn phone-btn-power"></span>
                            <span class="phone-btn phone-btn-camera"></span>
                            <div class="phone-screen">
                                @foreach ($flowSteps as $step)
                                    <img class="story-shot {{ $loop->first ? 'is-active' : '' }}"
                                         data-story-shot="{{ $loop->index }}"
                                         src="{{ hyperAsset($step['src']) }}" alt="{{ $step['title'] }}"
                                         loading="lazy"/>
                                @endforeach
                            </div>
                        </div>
                        <div class="story-dots" aria-hidden="true">
                            @foreach ($flowSteps as $step)
                                <span class="story-dot {{ $loop->first ? 'is-active' : '' }}"
                                      data-story-dot="{{ $loop->index }}"></span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <ol class="story-steps">
                    @foreach ($flowSteps as $step)
                        <li class="story-step {{ $loop->first ? 'is-active' : '' }}"
                            data-story-step="{{ $loop->index }}">
                            <div>
                                <span
                                    class="font-body inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-500">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 font-heading text-[11px] text-amber-600">
                                        {{ $loop->iteration }}
                                    </span>
                                    {{ $step['label'] }}
                                </span>
                                <h3 class="font-heading mt-3 text-xl sm:text-2xl font-bold text-gray-900 leading-snug">
                                    {{ $step['title'] }}
                                </h3>
                                <p class="font-body mt-3 text-sm sm:text-base leading-relaxed text-gray-500">
                                    {{ $step['desc'] }}
                                </p>
                                @if (!empty($step['chips']))
                                    <ul class="mt-5 flex flex-wrap gap-2">
                                        @foreach ($step['chips'] as $chip)
                                            <li class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 font-body text-xs font-medium text-gray-600">
                                                <svg class="h-3 w-3 text-amber-500" fill="none" stroke="currentColor"
                                                     stroke-width="3" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M5 13l4 4L19 7"/>
                                                </svg>
                                                {{ $chip }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>
@endif

{{-- SCREENSHOTS GALLERY --}}
<section id="screenshots" class="bg-white py-12 sm:py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div data-animate class="text-center mb-8 sm:mb-12">
            <span
                class="font-body inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">
                Screenshots
            </span>
            <h2 class="font-heading mt-4 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                See Every Screen in Action
            </h2>
            <p class="mt-3 sm:mt-4 text-gray-600 font-body text-sm sm:text-base">
                A closer look at the customer app, web storefront, admin panel and seller panel.
            </p>
        </div>

        @php
            $galleries = [
                'customer' => [
                    'label' => 'Customer App',
                    'phone' => true,
                    'aspect' => 'aspect-[9/19.5]',
                    'shots' => [
                        ['src' => 'assets/landing/images/gallery/app-home.png', 'alt' => 'Customer app home screen'],
                        ['src' => 'assets/landing/images/gallery/app-pl.png', 'alt' => 'Product listing screen'],
                        ['src' => 'assets/landing/images/gallery/app-pdp.png', 'alt' => 'Product detail screen'],
                        ['src' => 'assets/landing/images/gallery/app-cart.png', 'alt' => 'Cart and checkout screen'],
                        ['src' => 'assets/landing/images/gallery/app-od.png', 'alt' => 'Order tracking screen'],
                        ['src' => 'assets/landing/images/gallery/app-account.png', 'alt' => 'Account screen'],
                    ],
                ],
                'web' => [
                    'label' => 'Customer Web',
                    'aspect' => 'aspect-[20/9]',
                    'shots' => [
                        ['src' => 'assets/landing/images/gallery/web-home.png', 'alt' => 'Web storefront home'],
                        ['src' => 'assets/landing/images/gallery/web-pl.png', 'alt' => 'Product listing on the web'],
                        ['src' => 'assets/landing/images/gallery/web-pdp.png', 'alt' => 'Product detail on the web'],
                        ['src' => 'assets/landing/images/gallery/web-cart.png', 'alt' => 'Cart on the web'],
                        ['src' => 'assets/landing/images/gallery/web-checkout.png', 'alt' => 'Checkout on the web'],
                        ['src' => 'assets/landing/images/gallery/web-account.png', 'alt' => 'Account and orders on the web'],
                    ],
                ],
                'admin' => [
                    'label' => 'Admin Panel',
                    'aspect' => 'aspect-[20/9]',
                    'shots' => [
                        ['src' => 'assets/landing/images/gallery/admin-1.png', 'alt' => 'Admin dashboard'],
                        ['src' => 'assets/landing/images/gallery/admin-2.png', 'alt' => 'Product moderation'],
                        ['src' => 'assets/landing/images/gallery/admin-3.png', 'alt' => 'Orders management'],
                        ['src' => 'assets/landing/images/gallery/admin-4.png', 'alt' => 'Markets and currency'],
                        ['src' => 'assets/landing/images/gallery/admin-5.png', 'alt' => 'Settlements and wallets'],
                    ],
                ],
                'seller' => [
                    'label' => 'Seller Panel',
                    'aspect' => 'aspect-[20/9]',
                    'shots' => [
                        ['src' => 'assets/landing/images/gallery/seller-1.png', 'alt' => 'Seller dashboard'],
                        ['src' => 'assets/landing/images/gallery/seller-2.png', 'alt' => 'Add product screen'],
                        ['src' => 'assets/landing/images/gallery/seller-3.png', 'alt' => 'Inventory by location'],
                        ['src' => 'assets/landing/images/gallery/seller-4.png', 'alt' => 'Order fulfilment'],
                        ['src' => 'assets/landing/images/gallery/seller-5.png', 'alt' => 'Earnings and withdrawals'],
                    ],
                ],
            ];

            $galleries = collect($galleries)
                ->map(fn ($gallery) => [...$gallery, 'shots' => array_values(array_filter(
                    $gallery['shots'],
                    fn ($shot) => is_file(public_path($shot['src']))
                ))])
                ->filter(fn ($gallery) => count($gallery['shots']) > 0)
                ->all();
        @endphp

        @if (count($galleries))

            @if (count($galleries) > 1)
                <div data-animate style="--d: .15s" class="flex justify-center mb-8">
                    <div class="inline-flex flex-wrap justify-center gap-1 bg-gray-50 rounded-full p-1.5">
                        @foreach ($galleries as $key => $gallery)
                            <button type="button" data-gallery-tab="{{ $key }}"
                                    class="gallery-tab px-5 py-2 rounded-full text-sm font-medium text-gray-700 hover:bg-amber-100 transition {{ $loop->first ? 'is-active' : '' }}">
                                {{ $gallery['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @foreach ($galleries as $key => $gallery)
                @php $isPhone = !empty($gallery['phone']); @endphp
                <div data-gallery-panel="{{ $key }}" class="gallery-panel relative {{ $loop->first ? '' : 'hidden' }}">
                    <div class="swiper gallery-swiper" data-swiper="{{ $key }}">
                        <div class="swiper-wrapper">
                            @foreach ($gallery['shots'] as $shot)
                                <div
                                    class="swiper-slide {{ $isPhone ? 'w-[190px] sm:w-[225px]' : 'w-[86vw] sm:w-[540px] lg:w-[620px]' }}">
                                    @if ($isPhone)
                                        <figure class="w-full py-2">
                                            <div class="phone-frame">
                                                <span class="phone-btn phone-btn-action"></span>
                                                <span class="phone-btn phone-btn-vol-up"></span>
                                                <span class="phone-btn phone-btn-vol-down"></span>
                                                <span class="phone-btn phone-btn-power"></span>
                                                <span class="phone-btn phone-btn-camera"></span>
                                                <div class="phone-screen">
                                                    <img src="{{ hyperAsset($shot['src']) }}" alt="{{ $shot['alt'] }}"
                                                         loading="lazy"/>
                                                </div>
                                            </div>
                                        </figure>
                                    @else
                                        <figure
                                            class="w-full {{ $gallery['aspect'] }} overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 shadow-sm">
                                            <img src="{{ hyperAsset($shot['src']) }}" alt="{{ $shot['alt'] }}"
                                                 loading="lazy" class="h-full w-full object-cover object-top"/>
                                        </figure>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <button type="button" data-swiper-prev aria-label="Previous screenshots"
                            class="gallery-nav gallery-nav-prev hidden md:flex">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button type="button" data-swiper-next aria-label="More screenshots"
                            class="gallery-nav gallery-nav-next hidden md:flex">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            @endforeach

            <p data-animate class="mt-4 text-center font-body text-xs text-gray-400">Swipe or drag to explore</p>
        @endif
    </div>
</section>

{{-- Services Section --}}
<section id="customization" class="py-12 sm:py-16 md:py-20 bg-white text-black">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <!-- Section Header -->
        <div data-animate class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
                <span
                    class="font-body inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">
                    End-to-End Marketplace Solutions
                </span>

            <h2
                class="font-heading mt-4 text-2xl sm:text-3xl md:text-[40px] font-black text-slate-900 leading-tight">
                Everything You Need to Launch & Grow
            </h2>

            <p
                class="font-body mt-4 text-sm sm:text-base text-slate-500 font-normal max-w-2xl mx-auto leading-relaxed">
                From initial setup to advanced custom development,  we help you build a marketplace that's ready to
                scale and tailored to your business goals.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
            <!-- Card 1: Reskinning (Uses your full hyperAsset image as the wrapper background) -->
            <div data-animate="left"
                 class="bg-cover rounded-2xl bg-no-repeat bg-center p-8 md:p-10 flex flex-col justify-between aspect-square w-full"
                 style="background-image: url('{{ hyperAsset('assets/landing/images/reskinning.png') }}');">

                <div class="max-w-[85%] sm:max-w-[70%] md:max-w-[80%]">
                    <h3 class="text-2xl font-heading font-extrabold text-slate-900 mb-4">Reskinning</h3>
                    <p class="font-body text-sm md:text-[15px] text-slate-600 leading-relaxed mb-8">
                        Launch your own branded marketplace without building everything from scratch. Customize the look
                        and feel while keeping a proven, reliable platform.
                    </p>

                    <!-- Features List -->
                    <ul class="space-y-3.5">
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-700">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>Your Brand & Logo</span>
                        </li>
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-700">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>Custom Colors & Theme</span>
                        </li>
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-700">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>White-Label Application</span>
                        </li>
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-700">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>App Store Publishing Support</span>
                        </li>
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-700">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>Faster Time to Market</span>
                        </li>
                    </ul>
                </div>

                <!-- Action Button Layer -->
                <div class="mt-8">
                    <a href="https://wa.me/919974692496?text={{ rawurlencode('Hi Hypercommerce team, I’m interested in reskinning / white-labeling Hypercommerce with my own brand. Can you share the details and pricing?') }}"
                       class="inline-flex items-center justify-center"
                       target="_blank" rel="noopener noreferrer" title="Inquire about reskinning">
                        <img class="w-11/12 max-w-full"
                             src="{{ hyperAsset('assets/landing/images/inquire-button.png') }}" alt="Inquire Now">
                    </a>
                </div>
            </div>

            <!-- Card 2: Customization (Uses your full hyperAsset image as the wrapper background) -->
            <div data-animate="right"
                 style="--d: .15s; background-image: url('{{ hyperAsset('assets/landing/images/customization.png') }}');"
                 class="bg-cover rounded-2xl bg-no-repeat bg-center p-8 md:p-10 flex flex-col justify-between aspect-square w-full">

                <div class="max-w-[85%] sm:max-w-[70%] md:max-w-[80%]">
                    <h3 class="text-2xl font-heading font-extrabold text-white mb-4">Customization</h3>
                    <p class="font-body text-sm md:text-[15px] text-slate-300 leading-relaxed mb-8">
                        Need features unique to your business? Our development team can build custom modules,
                        integrations, and workflows to match your exact requirements.
                    </p>

                    <!-- Features List -->
                    <ul class="space-y-3.5">
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-200">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>Custom Features</span>
                        </li>
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-200">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>Third-Party Integrations</span>
                        </li>
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-200">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>API Development</span>
                        </li>
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-200">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>Workflow Automation</span>
                        </li>
                        <li class="flex items-center space-x-3 text-sm font-semibold text-slate-200">
                                <span
                                    class="flex-shrink-0 w-5 h-5 bg-[#FFB616] text-white rounded-full flex items-center justify-center text-xs">&checkmark;</span>
                            <span>Ongoing Technical Support</span>
                        </li>
                    </ul>
                </div>

                <!-- Action Button Layer -->
                <div class="mt-8">
                    <a href="https://wa.me/919974692496?text={{ rawurlencode('Hi Hypercommerce team, I need custom development on Hypercommerce (custom features / integrations / API). Can we discuss my requirements?') }}"
                       class="inline-flex items-center justify-center"
                       target="_blank" rel="noopener noreferrer" title="Inquire about customization">
                        <img class="w-11/12 max-w-full"
                             src="{{ hyperAsset('assets/landing/images/inquire-button.png') }}" alt="Inquire Now">
                    </a>
                </div>
            </div>
        </div>
    </div>

</section>

<!-- WHAT'S INCLUDED SECTION -->
<section id="included" class="bg-gray-50 py-12 sm:py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div data-animate class="text-center mb-8 sm:mb-12">
            <span
                class="font-body inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">
                What you get
            </span>
            <h2 class="font-heading mt-4 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                Everything Included in the Package
            </h2>
            <p class="mt-3 sm:mt-4 text-gray-600 font-body text-sm sm:text-base max-w-2xl mx-auto">
                One purchase, one download, everything you need to go live.
            </p>
        </div>

        @php
            $included = [
                ['title' => 'Flutter Customer App', 'desc' => 'Complete, unencrypted source code for Android and iOS.'],
                ['title' => 'Next.js Web Storefront', 'desc' => 'Full source. Runs server-rendered on a VPS, or as a static build on shared hosting.'],
                ['title' => 'Laravel Admin Panel', 'desc' => 'Full backend source powering moderation, markets and settlements.'],
                ['title' => 'Laravel Seller Panel', 'desc' => 'Full source for the vendor dashboard and fulfilment tools.'],
                ['title' => 'REST API + Postman Collection', 'desc' => 'Documented endpoints, ready to test. Included with the Extended license.'],
                ['title' => 'Setup Documentation', 'desc' => 'Step-by-step install guides for cPanel, Hostinger, VPS and Firebase.'],
                ['title' => 'Free Lifetime Updates', 'desc' => 'Every future release, at no extra cost, forever.'],
                ['title' => '6 Months of Support', 'desc' => 'Extended to 1 year of priority support with the Extended license.'],
                ['title' => 'Seller App', 'desc' => 'Released — available as a free update.'],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            @foreach ($included as $item)
                <div data-animate style="--d: {{ ($loop->index % 4) * 0.1 }}s"
                     class="card-lift relative bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                    @if (!empty($item['soon']))
                        <span
                            class="absolute top-4 right-4 rounded-full bg-amber-100 px-2.5 py-0.5 font-body text-[10px] font-semibold uppercase tracking-wide text-amber-600">
                            Soon
                        </span>
                    @endif
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full {{ !empty($item['soon']) ? 'bg-gray-100 text-gray-400' : 'bg-[#FFB616] text-white' }}">
                        @if (!empty($item['soon']))
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @else
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                    </span>
                    <h3 class="mt-4 font-heading text-base font-bold text-gray-900 leading-snug pr-12">
                        {{ $item['title'] }}
                    </h3>
                    <p class="mt-2 font-body text-sm text-gray-500 leading-relaxed">{{ $item['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- COMPARISON VS ALTERNATIVES -->
<section id="comparison" class="bg-white py-12 sm:py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div data-animate class="text-center mb-8 sm:mb-12">
            <span
                class="font-body inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">
                Why Hypercommerce
            </span>
            <h2 class="font-heading mt-4 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                Launch in Days, Not Months
            </h2>
            <p class="mt-3 sm:mt-4 text-gray-600 font-body text-sm sm:text-base max-w-2xl mx-auto">
                How a ready-made marketplace compares to building one yourself or hiring an agency.
            </p>
        </div>

        @php
            $comparisonRows = [
                ['criteria' => 'Upfront cost', 'hyper' => 'One-time license fee', 'scratch' => '$15,000+', 'agency' => '$20,000+'],
                ['criteria' => 'Time to launch', 'hyper' => 'A few days', 'scratch' => '6–12 months', 'agency' => '6-8 months'],
                ['criteria' => 'Full source code ownership', 'hyper' => true, 'scratch' => true, 'agency' => 'Varies by contract'],
                ['criteria' => 'Free lifetime updates', 'hyper' => true, 'scratch' => false, 'agency' => false],
                ['criteria' => 'Proven and production-tested', 'hyper' => true, 'scratch' => false, 'agency' => 'Varies'],
                ['criteria' => 'Support included', 'hyper' => true, 'scratch' => false, 'agency' => 'Paid retainer'],
                ['criteria' => 'Mobile apps included', 'hyper' => true, 'scratch' => 'Built separately', 'agency' => 'Costs extra'],
            ];
        @endphp

        <div data-animate style="--d: .15s" class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-200 min-w-[640px]">
                <thead class="bg-gray-50">
                <tr class="border-b-2 border-gray-300">
                    <th class="text-left py-3 md:py-4 px-3 md:px-6 font-semibold text-sm md:text-base text-gray-900 border-r border-gray-200 min-w-[200px]">
                        &nbsp;
                    </th>
                    <th class="text-center py-3 md:py-4 px-3 md:px-6 bg-yellow-50 border-r border-gray-200 min-w-[160px]">
                        <span class="font-semibold text-sm md:text-base text-gray-900">Hypercommerce</span>
                    </th>
                    <th class="text-center py-3 md:py-4 px-3 md:px-6 font-semibold text-sm md:text-base text-gray-900 border-r border-gray-200 min-w-[150px]">
                        Build from Scratch
                    </th>
                    <th class="text-center py-3 md:py-4 px-3 md:px-6 font-semibold text-sm md:text-base text-gray-900 min-w-[150px]">
                        Hire an Agency
                    </th>
                </tr>
                </thead>
                <tbody>
                @foreach ($comparisonRows as $row)
                    <tr class="border-b border-gray-200">
                        <td class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                            {{ $row['criteria'] }}
                        </td>
                        @foreach (['hyper', 'scratch', 'agency'] as $col)
                            <td class="py-3 md:py-4 px-3 md:px-6 text-center text-sm md:text-base text-gray-600 {{ $col === 'hyper' ? 'bg-yellow-50' : '' }} {{ $col !== 'agency' ? 'border-r border-gray-200' : '' }}">
                                @if ($row[$col] === true)
                                    <span
                                        class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                                @elseif ($row[$col] === false)
                                    <span
                                        class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                                @else
                                    {{ $row[$col] }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- STEPS AFTER PURCHASING SECTION -->
<section class="bg-white py-12 sm:py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <!-- Title and Description -->
        <div data-animate class="text-center mb-8 sm:mb-12 md:mb-16">
            <h2 class="font-heading text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 sm:mb-4">
                Steps After Purchasing
            </h2>
            <p class="text-gray-600 font-body text-sm sm:text-base md:text-lg">
                Launch your own multivendor marketplace in just 4 simple steps.
            </p>
        </div>

        <!-- Steps Grid -->
        <div data-animate="zoom" style="--d: .15s" class="max-w-7xl mx-auto px-4 sm:px-6 flex justify-center">
            <!-- Mobile -->
            <img src="{{ hyperAsset('assets/landing/images/steps-mobile.svg') }}" class="sm:hidden" alt="Steps"/>

            <!-- Desktop -->
            <img src="{{ hyperAsset('assets/landing/images/steps-desktop.svg') }}" class="hidden w-full h-full sm:block"
                 alt="Steps"/>
        </div>
    </div>
</section>

<!-- NEED HELP SECTION -->
{{-- <section id="customization" class="relative py-12 sm:py-16 md:py-20 text-white" style="
    background-image: url('{{hyperAsset('assets/landing/images/black-bg.png')}}');
    background-size: cover;
    background-position: center;
  ">
    <!-- Dark Overlay -->

    <!-- Content -->
    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 text-center">
        <h2 class="font-heading text-2xl sm:text-3xl md:text-4xl font-bold mb-4 sm:mb-6 leading-tight">
            Need Help with Setup or Customization?
        </h2>
        <p
            class="text-gray-300 font-body text-sm sm:text-base md:text-lg mb-6 sm:mb-8 max-w-3xl mx-auto leading-relaxed">
            Our professional team helps you with everything needed to go live,
            including system setup, branding, feature configuration, and app store
            submission, so you can focus on growing your business.
        </p>
        <a href="https://wa.me/919974692496" target="_blank" rel="noopener noreferrer"
            class="inline-flex items-center gap-2 px-6 sm:px-8 py-2.5 sm:py-3 bg-blue-600 hover:bg-blue-700 transition rounded-lg font-semibold text-white text-sm sm:text-base"
            title="Contact our expert team for setup and customization help">
            TALK TO OUR EXPERT
            <span>→</span>
        </a>
    </div>
</section> --}}

<!-- PRICING / LICENSE COMPARISON SECTION -->
<section class="bg-white py-12 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <!-- Title and Description -->
        <div data-animate class="text-center mb-8 md:mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 md:mb-4 leading-tight">
                Everything You Need to Launch & Grow
            </h2>
            <p class="text-gray-600 text-base md:text-lg">
                From initial setup to advanced custom development,  we help you build a marketplace that's ready to
                scale and tailored to your business goals.
            </p>
        </div>

        <!-- Comparison Table Wrapper -->
        <div data-animate style="--d: .15s" class="overflow-x-auto mb-8 md:mb-12">
            <table class="w-full border-collapse border border-gray-200 min-w-[640px]">
                <!-- Table Header -->
                <thead class="bg-gray-50">
                <tr class="border-b-2 border-gray-300">
                    <th
                        class="text-left py-3 md:py-4 px-3 md:px-6 font-semibold text-sm md:text-base text-gray-900 border-r border-gray-200 min-w-[200px]">
                        Features
                    </th>
                    <th
                        class="text-center py-3 md:py-4 px-3 md:px-6 font-semibold text-sm md:text-base text-gray-900 border-r border-gray-200 min-w-[140px]">
                        Regular License
                    </th>
                    <th class="text-center py-3 md:py-4 px-3 md:px-6 relative bg-yellow-50 min-w-[140px]">
                        <!-- Blue Ribbon -->
                        <div class="absolute top-0 right-0 w-20 h-20 overflow-hidden pointer-events-none">
                            <div
                                class="absolute top-4 -right-5 w-24 bg-yellow-600 text-white text-center py-1 text-[8px] font-bold tracking-wide transform rotate-45 shadow-md">
                                Recommended
                            </div>
                        </div>
                        <span class="font-semibold text-sm md:text-base text-gray-900">
                                    Extended License
                                </span>
                    </th>
                </tr>
                </thead>

                <!-- Table Body -->
                <tbody>
                <!-- Row 1 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        Lifetime License Validity
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 2 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        Permitted for 1 Domain
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 3 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        6 Months of Technical Support
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 4 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        All Premium Features
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 5 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        Free Admin Panel Installation (One Time)
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 6 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        Remote Support (AnyDesk)
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 7 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        For Personal Project
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 8 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        Lifetime Free Updates
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 9 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        Postman Collection for Rest API Documentation
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 10 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        For Commercial Projects
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 11 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        1 Year Priority Support
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 12 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        Free Website Setup
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>

                <!-- Row 13 -->
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        1 Logo Design
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-yellow-500 text-white text-lg md:text-xl">✓</span>
                    </td>
                </tr>
                <tr class="border-b border-gray-200">
                    <td
                        class="py-3 md:py-4 px-3 md:px-6 text-sm md:text-base text-gray-700 border-r border-gray-200">
                        App Setup
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center border-r border-gray-200">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                    </td>
                    <td class="py-3 md:py-4 px-3 md:px-6 text-center bg-yellow-50">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-100 text-red-600 text-lg md:text-xl">✕</span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Bottom CTA Section -->
        <div data-animate
             class="bg-yellow-100 rounded-xl p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6">
            <div class="text-center md:text-left">
                <h3 class="text-lg md:text-xl font-semibold text-gray-900">
                    Launch Your Marketplace with a Single Commercial License
                </h3>
            </div>
            <a href="{{$extendedUrl}}"
               class="px-6 md:px-8 py-3 bg-yellow-500 hover:bg-yellow-600 transition-colors rounded-lg font-semibold text-white whitespace-nowrap text-sm md:text-base"
               target="_blank" title="Purchase Extended License for commercial projects">
                Get Extended License Now
            </a>
        </div>
    </div>
</section>

<!-- ROADMAP SECTION -->
{{--<section id="roadmap" class="bg-white py-12 sm:py-16 md:py-20">--}}
{{--    <div class="max-w-5xl mx-auto px-4 sm:px-6">--}}
{{--        <div data-animate class="text-center mb-8 sm:mb-12">--}}
{{--            <span--}}
{{--                class="font-body inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">--}}
{{--                Roadmap--}}
{{--            </span>--}}
{{--            <h2 class="font-heading mt-4 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 leading-tight">--}}
{{--                Actively Built, Always Growing--}}
{{--            </h2>--}}
{{--            <p class="mt-3 sm:mt-4 text-gray-600 font-body text-sm sm:text-base max-w-2xl mx-auto">--}}
{{--                Every feature below ships to existing customers as a free update.--}}
{{--            </p>--}}
{{--        </div>--}}

{{--        @php--}}
{{--            $roadmap = [--}}
{{--                ['title' => 'Local Delivery', 'desc' => 'Fulfil orders straight from a nearby store location.', 'status' => 'In development'],--}}
{{--                ['title' => 'Local Pickup', 'desc' => 'Let customers collect their orders in person from a store.', 'status' => 'In development'],--}}
{{--                ['title' => 'Seller Mobile App', 'desc' => 'Let vendors manage products and orders from their phone.', 'status' => 'Planned'],--}}
{{--                ['title' => 'Customer Website', 'desc' => 'A full storefront on the web, alongside the mobile apps.', 'status' => 'Planned'],--}}
{{--                ['title' => 'Subscriptions', 'desc' => 'Recurring orders and subscription products for repeat revenue.', 'status' => 'Planned'],--}}
{{--                ['title' => 'Ads Module', 'desc' => 'Let sellers promote their products and open a new revenue stream.', 'status' => 'Planned'],--}}
{{--                ['title' => 'Automated Return Pickups', 'desc' => 'Schedule and track return collections without manual steps.', 'status' => 'Planned'],--}}
{{--            ];--}}
{{--        @endphp--}}

{{--        <div data-animate style="--d: .15s" class="relative">--}}
{{--            <div class="roadmap-scroll relative h-[420px] sm:h-[460px] overflow-y-auto pr-2 sm:pr-3">--}}
{{--                <div class="absolute left-[15px] top-2 bottom-2 w-px bg-gray-200 hidden sm:block"></div>--}}
{{--                <div class="space-y-4 sm:space-y-6">--}}
{{--                    @foreach ($roadmap as $item)--}}
{{--                        @php $inDev = $item['status'] === 'In development'; @endphp--}}
{{--                        <div class="relative flex gap-4 sm:gap-6">--}}
{{--                        <span--}}
{{--                            class="relative z-10 mt-1 hidden sm:flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-4 border-white {{ $inDev ? 'bg-[#FFB616]' : 'bg-gray-200' }}">--}}
{{--                            <span class="h-2 w-2 rounded-full {{ $inDev ? 'bg-white' : 'bg-gray-400' }}"></span>--}}
{{--                        </span>--}}
{{--                            <div--}}
{{--                                class="flex-1 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 {{ $inDev ? 'ring-1 ring-amber-200' : '' }}">--}}
{{--                                <div class="flex flex-wrap items-center gap-3">--}}
{{--                                    <h3 class="font-heading text-base sm:text-lg font-bold text-gray-900">{{ $item['title'] }}</h3>--}}
{{--                                    <span--}}
{{--                                        class="rounded-full px-2.5 py-0.5 font-body text-[10px] font-semibold uppercase tracking-wide {{ $inDev ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-500' }}">--}}
{{--                                    {{ $item['status'] }}--}}
{{--                                </span>--}}
{{--                                </div>--}}
{{--                                <p class="mt-2 font-body text-sm text-gray-500 leading-relaxed">{{ $item['desc'] }}</p>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    @endforeach--}}
{{--                </div>--}}
{{--            </div>--}}
{{--            <div--}}
{{--                class="pointer-events-none absolute bottom-0 left-0 h-16 w-full bg-gradient-to-t from-white to-transparent"></div>--}}
{{--        </div>--}}
{{--    </div>--}}
{{--</section>--}}

<!-- TESTIMONIALS SECTION -->
{{--<section id="testimonials" class="bg-gray-50 py-12 sm:py-16 md:py-20">--}}
{{--    <div class="max-w-7xl mx-auto px-4 sm:px-6">--}}
{{--        <div data-animate class="text-center mb-8 sm:mb-12">--}}
{{--            <span--}}
{{--                class="font-body inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">--}}
{{--                Testimonials--}}
{{--            </span>--}}
{{--            <h2 class="font-heading mt-4 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 leading-tight">--}}
{{--                Trusted by Businesses Worldwide--}}
{{--            </h2>--}}
{{--            <p class="mt-3 sm:mt-4 text-gray-600 font-body text-sm sm:text-base max-w-2xl mx-auto">--}}
{{--                What clients say about working with Infinitie Technologies.--}}
{{--            </p>--}}
{{--        </div>--}}

{{--        @php--}}
{{--            $testimonials = [--}}
{{--                ['quote' => 'REPLACE: Paste a real client quote here before this section goes live.', 'name' => 'REPLACE: Client Name', 'role' => 'REPLACE: Role, Company'],--}}
{{--                ['quote' => 'REPLACE: Paste a real client quote here before this section goes live.', 'name' => 'REPLACE: Client Name', 'role' => 'REPLACE: Role, Company'],--}}
{{--                ['quote' => 'REPLACE: Paste a real client quote here before this section goes live.', 'name' => 'REPLACE: Client Name', 'role' => 'REPLACE: Role, Company'],--}}
{{--            ];--}}
{{--        @endphp--}}

{{--        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">--}}
{{--            @foreach ($testimonials as $t)--}}
{{--                <figure data-animate style="--d: {{ $loop->index * 0.15 }}s"--}}
{{--                        class="card-lift flex flex-col rounded-2xl border border-gray-200 bg-white p-6 sm:p-8">--}}
{{--                    <div class="flex gap-0.5 text-amber-400" aria-label="5 out of 5 stars">--}}
{{--                        @for ($i = 0; $i < 5; $i++)--}}
{{--                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">--}}
{{--                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 00-.363 1.118l1.286 3.957c.3.922-.755 1.688-1.539 1.118l-3.366-2.446a1 1 0 00-1.176 0l-3.366 2.446c-.784.57-1.838-.196-1.539-1.118l1.286-3.957a1 1 0 00-.363-1.118L2.063 9.385c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.951-.69l1.285-3.958z"/>--}}
{{--                            </svg>--}}
{{--                        @endfor--}}
{{--                    </div>--}}
{{--                    <blockquote class="mt-4 flex-1 font-body text-sm sm:text-base leading-relaxed text-gray-600">--}}
{{--                        &ldquo;{{ $t['quote'] }}&rdquo;--}}
{{--                    </blockquote>--}}
{{--                    <figcaption class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-4">--}}
{{--                        <span--}}
{{--                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-100 font-heading text-sm font-bold text-amber-600">--}}
{{--                            {{ strtoupper(substr($t['name'], 0, 1)) }}--}}
{{--                        </span>--}}
{{--                        <div>--}}
{{--                            <p class="font-heading text-sm font-bold text-gray-900">{{ $t['name'] }}</p>--}}
{{--                            <p class="font-body text-xs text-gray-500">{{ $t['role'] }}</p>--}}
{{--                        </div>--}}
{{--                    </figcaption>--}}
{{--                </figure>--}}
{{--            @endforeach--}}
{{--        </div>--}}
{{--    </div>--}}
{{--</section>--}}

<!-- TECHNOLOGY STACK SECTION -->
<section id="technology" class="bg-gray-50 py-12 sm:py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <!-- Title and Description -->
        <div data-animate class="text-center mb-8 sm:mb-10 md:mb-12">
            <h2
                class="font-heading text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 sm:mb-4 leading-tight">
                Built with Modern & Reliable<br class="hidden sm:block"/>Technology
            </h2>
            <p class="text-gray-600 font-body text-sm sm:text-base md:text-lg max-w-4xl mx-auto">
                Hypercommerce is built with modern, trusted technologies for fast
                performance, strong security, and easy scalability.
            </p>
        </div>

        <!-- Technology Icons Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 place-items-center gap-4 sm:gap-6 align-center">
            <!-- Flutter -->
            <a href="https://flutter.dev/" target="_blank" rel="noopener noreferrer"
               title="Learn more about Flutter" data-animate
               class="bg-white border border-gray-200 rounded-lg sm:rounded-xl p-4 sm:p-6 md:p-8 flex items-center justify-center hover:shadow-lg transition-shadow">
                <img src="{{hyperAsset('assets/landing/images/flutter-image.png')}}" alt="Flutter"
                     class="w-full h-10 sm:h-12 md:h-16 object-contain"/>
            </a>

            <!-- Laravel -->
            <a href="https://laravel.com/" target="_blank" rel="noopener noreferrer"
               title="Learn more about Laravel" data-animate style="--d: .1s"
               class="bg-white border border-gray-200 rounded-lg sm:rounded-xl p-4 sm:p-6 md:p-8 flex items-center justify-center hover:shadow-lg transition-shadow">
                <img src="{{hyperAsset('assets/landing/images/laravel-image.png')}}" alt="Laravel"
                     class="w-full h-10 sm:h-12 md:h-16 object-contain"/>
            </a>

            <!-- Firebase -->
            <a href="https://firebase.google.com/" target="_blank" rel="noopener noreferrer"
               title="Learn more about Firebase" data-animate style="--d: .2s"
               class="bg-white border border-gray-200 rounded-lg sm:rounded-xl p-4 sm:p-6 md:p-8 flex items-center justify-center hover:shadow-lg transition-shadow">
                <img src="{{hyperAsset('assets/landing/images/firebase-image.png')}}" alt="Firebase"
                     class="w-full h-10 sm:h-12 md:h-16 object-contain"/>
            </a>

            <!-- Bootstrap -->
            <a href="https://getbootstrap.com/" target="_blank" rel="noopener noreferrer"
               title="Learn more about Bootstrap" data-animate style="--d: .3s"
               class="bg-white border border-gray-200 rounded-lg sm:rounded-xl p-4 sm:p-6 md:p-8 flex items-center justify-center hover:shadow-lg transition-shadow">
                <img src="{{hyperAsset('assets/landing/images/bootstrap-image.png')}}" alt="Bootstrap"
                     class="w-full h-10 sm:h-12 md:h-16 object-contain"/>
            </a>

        </div>
    </div>
</section>

<!-- FAQ SECTION -->
<section id="faq" class="bg-white py-12 sm:py-16 md:py-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <div data-animate class="text-center mb-8 sm:mb-12">
            <span
                class="font-body inline-block px-4 py-1 text-xs sm:text-sm border border-yellow-400 text-yellow-400 rounded-full font-medium">
                FAQs
            </span>
            <h2 class="font-heading mt-4 text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                Frequently Asked Questions
            </h2>
            <p class="mt-3 sm:mt-4 text-gray-600 font-body text-sm sm:text-base">
                Everything you need to know before you buy. Still unsure? Our team is one message away.
            </p>
        </div>

        <div data-animate style="--d: .15s" class="divide-y divide-gray-200 border-t border-b border-gray-200">
            @php
                $faqs = [
                    [
                        'q' => 'Is the full source code included?',
                        'a' => 'Yes. You get the complete Flutter customer app source and the full Laravel backend powering the admin and seller panels — unencrypted and fully customizable.',
                    ],
                    [
                        'q' => 'Is this a one-time payment or a subscription?',
                        'a' => 'A one-time purchase on CodeCanyon. Your license is valid for life and updates are free forever.',
                    ],
                    [
                        'q' => 'Can I publish the apps under my own brand?',
                        'a' => 'Yes, the platform is fully white-label. Use your own logo, colors and name, and publish to the Google Play Store and Apple App Store as your own product.',
                    ],
                    [
                        'q' => "What's the difference between the Regular and Extended license?",
                        'a' => 'A Regular license covers personal projects where end users are not charged. An Extended license covers commercial projects and adds free admin panel installation, free website setup, a logo design, the Postman API collection and 1 year of priority support.',
                    ],
                    [
                        'q' => 'What are the server requirements?',
                        'a' => 'PHP 8.4+, MySQL 8.0 (or MariaDB 10.6), Nginx 1.20+ or Apache 2.4, Composer 2.2+, the GD or Imagick extension, and 2 GB RAM recommended. Full requirements, including the Flutter and mobile OS versions, are listed in the documentation.',
                    ],
                    [
                        'q' => 'Do you help with installation?',
                        'a' => 'The Extended license includes a free one-time admin panel installation with remote support over AnyDesk. Paid setup assistance is available for Regular license buyers.',
                    ],
                    [
                        'q' => 'How do sellers get paid?',
                        'a' => 'When an order is delivered, the seller\'s earning is recorded as unsettled. The admin settles it into the seller\'s wallet, and sellers then request a withdrawal that the admin approves.',
                    ],
                    [
                        'q' => 'Do you offer customization services?',
                        'a' => 'Yes. Our team builds custom features, third-party integrations, API development and complete reskins so the platform matches your exact business requirements.',
                    ],
                    [
                        'q' => 'What support do I get?',
                        'a' => '6 months of Envato support is included, extended to 1 year of priority support with the Extended license. You also get the full documentation site and direct assistance from our team.',
                    ],
                ];
            @endphp

            @foreach ($faqs as $faq)
                <details class="faq-item group">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 py-5 text-left font-heading text-base sm:text-lg font-semibold text-gray-900 hover:text-amber-500 transition-colors">
                        {{ $faq['q'] }}
                        <span
                            class="faq-icon flex-shrink-0 flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-amber-500 transition-transform duration-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="faq-body">
                        <p class="pb-5 pr-10 font-body text-sm sm:text-base leading-relaxed text-gray-600">
                            {{ $faq['a'] }}
                            @if ($loop->index === 4)
                                <a href="{{ $docsUrl ?? url('docs') }}" target="_blank"
                                   rel="noopener noreferrer"
                                   class="font-semibold text-amber-500 hover:underline whitespace-nowrap"
                                   title="View full system requirements">View full requirements &rarr;</a>
                            @endif
                        </p>
                    </div>
                </details>
            @endforeach
        </div>

        <div data-animate style="--d: .3s" class="mt-8 sm:mt-10 text-center">
            <p class="font-body text-sm sm:text-base text-gray-600">
                Have a question that isn't answered here?
                <a href="https://wa.me/919974692496?text={{ rawurlencode('Hi Hypercommerce team, I have a question about the platform that isn\'t covered in your FAQ.') }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 font-semibold text-amber-500 hover:underline align-middle"
                   title="Chat with our team on WhatsApp">
                    <svg class="h-4 w-4 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M20.52 3.48A11.82 11.82 0 0012.02 0C5.4 0 .02 5.38.02 12c0 2.11.55 4.17 1.6 6L0 24l6.18-1.6a11.93 11.93 0 005.84 1.49h.01c6.62 0 12-5.38 12-12a11.86 11.86 0 00-3.5-8.41zM12.03 21.3a9.29 9.29 0 01-4.74-1.3l-.34-.2-3.67.95.98-3.58-.22-.37a9.29 9.29 0 01-1.43-4.98c0-5.14 4.19-9.33 9.33-9.33a9.27 9.27 0 016.6 2.73 9.28 9.28 0 012.73 6.6c0 5.14-4.19 9.33-9.34 9.33zm5.12-6.98c-.28-.14-1.65-.81-1.9-.9-.25-.09-.43-.14-.61.14-.18.28-.7.9-.86 1.08-.16.18-.32.21-.6.07-.28-.14-1.18-.43-2.25-1.38-.83-.74-1.39-1.65-1.55-1.93-.16-.28-.02-.43.12-.57.12-.12.28-.32.42-.48.14-.16.18-.28.28-.46.09-.18.05-.35-.02-.49-.07-.14-.61-1.47-.83-2.01-.22-.53-.44-.46-.61-.47h-.52c-.18 0-.46.07-.7.35-.25.28-.92.9-.92 2.2 0 1.3.95 2.55 1.08 2.73.14.18 1.87 2.85 4.53 4 2.66 1.15 2.66.77 3.14.72.48-.05 1.55-.63 1.77-1.24.22-.61.22-1.13.16-1.24-.07-.11-.25-.18-.53-.32z"/>
                    </svg>
                    Talk to our team &rarr;
                </a>
            </p>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="bg-gray-900 text-white py-8 sm:py-10 md:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <!-- Footer Content Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-8 mb-6 sm:mb-8">
            <!-- Logo & Company Name -->
            <div class="lg:col-span-1">
                <div class="flex items-center gap-3 mb-4">
                    <img src="{{hyperAsset('assets/landing/images/footer-logo.png')}}" alt="Logo" class="h-10"/>
                </div>
            </div>

            <!-- Company Column -->
            <div>
                <h4 class="font-heading font-semibold text-base mb-4">Company</h4>
                <ul class="space-y-2">
                    <li>
                        <a href="https://infinitietech.com/about-us" target="_blank"
                           class="text-gray-400 hover:text-white text-sm transition-colors"
                           title="Learn more about Infinitie Technologies">About us</a>
                    </li>
                    <li>
                        <a href="https://infinitietech.com/contact-us" target="_blank"
                           class="text-gray-400 hover:text-white text-sm transition-colors"
                           title="Contact us for your project needs">Hire us</a>
                    </li>
                </ul>
            </div>

            <!-- Services Column -->
            <div>
                <h4 class="font-heading font-semibold text-base mb-4">Services</h4>
                <ul class="space-y-2">
                    <li>
                        <a href="https://infinitietech.com/services/app-development" target="_blank"
                           class="text-gray-400 hover:text-white text-sm transition-colors"
                           title="Explore our app development services">App Development</a>
                    </li>
                    <li>
                        <a href="https://infinitietech.com/services/web-development" target="_blank"
                           class="text-gray-400 hover:text-white text-sm transition-colors"
                           title="Explore our web development services">Web Development</a>
                    </li>
                    <li>
                        <a href="https://infinitietech.com/services/ui-ux-service" target="_blank"
                           class="text-gray-400 hover:text-white text-sm transition-colors"
                           title="Explore our UI/UX design services">UI/UX Design</a>
                    </li>
                    <li>
                        <a href="https://infinitietech.com/services/digital-marketing" target="_blank"
                           class="text-gray-400 hover:text-white text-sm transition-colors"
                           title="Explore our digital marketing services">Digital Marketing</a>
                    </li>
                    <li>
                        <a href="https://infinitietech.com/services/custom-solutions" target="_blank"
                           class="text-gray-400 hover:text-white text-sm transition-colors"
                           title="Explore our customization and custom solution services">Customization</a>
                    </li>
                </ul>
            </div>

            <!-- Contact Us Column -->
            <div>
                <h4 class="font-heading font-semibold text-base mb-4">
                    Contact Us
                </h4>
                <ul class="space-y-3">
                    <li class="flex items-start gap-2 text-gray-400 text-sm">
                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>#A230, A231, Pantheon, Hill Garden Road, Above Shubham K Mart, Uma Nagar, Bhuj,<br/>Kutch Gujarat
                                India - 370001</span>
                    </li>
                    <li class="flex items-center gap-2 text-gray-400 text-sm">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <a href="tel:+919974692496" class="hover:text-white transition-colors"
                           title="Call us at +919974692496">+919974692496</a>
                    </li>
                    <li class="flex items-center gap-2 text-gray-400 text-sm">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <a href="mailto:info@infinitietech.com" class="hover:text-white transition-colors"
                           title="Email us at info@infinitietech.com">info@infinitietech.com</a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div
            class="border-t border-gray-800 pt-6 sm:pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-gray-400 text-xs sm:text-sm text-center md:text-left">
                Copyright © {{date('Y')}}
                <a href="https://infinitietech.com/" target="_blank" class="text-white hover:underline"
                   title="Visit Infinitie Technologies">Infinitie Technologies</a>
            </p>

            <!-- Social Media Icons -->
            <div class="flex items-center gap-2 sm:gap-3">
                <a href="https://in.linkedin.com/company/infinitie-technologies" target="_blank"
                   class="w-10 h-10 bg-white text-gray-900 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                   title="Follow us on LinkedIn">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                    </svg>
                </a>
                <a href="https://www.instagram.com/infinitietech/" target="_blank"
                   class="w-10 h-10 bg-white text-gray-900 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                   title="Follow us on Instagram">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                </a>
                <a href="https://wa.me/919974692496?text={{ rawurlencode('Hi Hypercommerce team, I’m interested in the Hypercommerce multi-vendor platform. Can you help me?') }}"
                   target="_blank" rel="noopener noreferrer"
                   class="w-10 h-10 bg-white text-gray-900 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                   title="Contact us on WhatsApp">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M20.52 3.48A11.82 11.82 0 0012.02 0C5.4 0 .02 5.38.02 12c0 2.11.55 4.17 1.6 6L0 24l6.18-1.6a11.93 11.93 0 005.84 1.49h.01c6.62 0 12-5.38 12-12a11.86 11.86 0 00-3.5-8.41zM12.03 21.3a9.29 9.29 0 01-4.74-1.3l-.34-.2-3.67.95.98-3.58-.22-.37a9.29 9.29 0 01-1.43-4.98c0-5.14 4.19-9.33 9.33-9.33a9.27 9.27 0 016.6 2.73 9.28 9.28 0 012.73 6.6c0 5.14-4.19 9.33-9.34 9.33zm5.12-6.98c-.28-.14-1.65-.81-1.9-.9-.25-.09-.43-.14-.61.14-.18.28-.7.9-.86 1.08-.16.18-.32.21-.6.07-.28-.14-1.18-.43-2.25-1.38-.83-.74-1.39-1.65-1.55-1.93-.16-.28-.02-.43.12-.57.12-.12.28-.32.42-.48.14-.16.18-.28.28-.46.09-.18.05-.35-.02-.49-.07-.14-.61-1.47-.83-2.01-.22-.53-.44-.46-.61-.47h-.52c-.18 0-.46.07-.7.35-.25.28-.92.9-.92 2.2 0 1.3.95 2.55 1.08 2.73.14.18 1.87 2.85 4.53 4 2.66 1.15 2.66.77 3.14.72.48-.05 1.55-.63 1.77-1.24.22-.61.22-1.13.16-1.24-.07-.11-.25-.18-.53-.32z"/>
                    </svg>
                </a>
                <a href="https://www.youtube.com/@infinitietech" target="_blank"
                   class="w-10 h-10 bg-white text-gray-900 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                   title="Subscribe to our YouTube channel">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- FLOATING BUY NOW (desktop) -->
<a id="buy-float" href="{{ $buyUrl }}" target="_blank" rel="noopener noreferrer"
   title="Buy Hypercommerce on CodeCanyon" aria-label="Buy Now on CodeCanyon"
   class="buy-float flex fixed bottom-5 left-5 z-40 items-center gap-2 rounded-full bg-amber-400 px-5 py-3 font-semibold text-black shadow-lg transition hover:bg-amber-500 hover:shadow-xl">
    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
         aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3M17 13l2 2M9 20a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
    </svg>
    <span class="text-sm">Buy Now</span>
</a>

<!-- FLOATING WHATSAPP BUTTON -->
<a id="wa-float"
   href="https://wa.me/919974692496?text={{ rawurlencode('Hi Hypercommerce team, I’m interested in the Hypercommerce platform and would like to know more.') }}"
   target="_blank" rel="noopener noreferrer" title="Chat with us on WhatsApp" aria-label="Chat on WhatsApp"
   class="wa-widget group fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-full bg-[#25D366] px-4 py-3 text-white shadow-lg transition hover:bg-[#1ebe57] hover:shadow-xl">
    <svg class="h-6 w-6 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path
            d="M20.52 3.48A11.82 11.82 0 0012.02 0C5.4 0 .02 5.38.02 12c0 2.11.55 4.17 1.6 6L0 24l6.18-1.6a11.93 11.93 0 005.84 1.49h.01c6.62 0 12-5.38 12-12a11.86 11.86 0 00-3.5-8.41zM12.03 21.3a9.29 9.29 0 01-4.74-1.3l-.34-.2-3.67.95.98-3.58-.22-.37a9.29 9.29 0 01-1.43-4.98c0-5.14 4.19-9.33 9.33-9.33a9.27 9.27 0 016.6 2.73 9.28 9.28 0 012.73 6.6c0 5.14-4.19 9.33-9.34 9.33zm5.12-6.98c-.28-.14-1.65-.81-1.9-.9-.25-.09-.43-.14-.61.14-.18.28-.7.9-.86 1.08-.16.18-.32.21-.6.07-.28-.14-1.18-.43-2.25-1.38-.83-.74-1.39-1.65-1.55-1.93-.16-.28-.02-.43.12-.57.12-.12.28-.32.42-.48.14-.16.18-.28.28-.46.09-.18.05-.35-.02-.49-.07-.14-.61-1.47-.83-2.01-.22-.53-.44-.46-.61-.47h-.52c-.18 0-.46.07-.7.35-.25.28-.92.9-.92 2.2 0 1.3.95 2.55 1.08 2.73.14.18 1.87 2.85 4.53 4 2.66 1.15 2.66.77 3.14.72.48-.05 1.55-.63 1.77-1.24.22-.61.22-1.13.16-1.24-.07-.11-.25-.18-.53-.32z"/>
    </svg>
    <span class="hidden text-sm font-semibold sm:inline">Chat with us</span>
</a>

<!-- SCROLL TO TOP -->
<button id="scroll-top" type="button" aria-label="Scroll to top"
        class="scroll-top-hidden wa-widget fixed bottom-[84px] right-5 z-40 flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 shadow-lg transition hover:bg-amber-400 hover:text-black hover:border-amber-400">
    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M5 12l7-7 7 7"/>
    </svg>
</button>

<!-- STICKY MOBILE CTA -->
<div id="sticky-cta"
     class="fixed bottom-0 left-0 z-40 w-full border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur-sm shadow-[0_-4px_16px_-4px_rgba(0,0,0,0.1)] md:hidden">
    <div class="flex items-center gap-3">
        <a href="#modules" title="Get a free demo"
           class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-amber-400 px-4 py-2.5 font-semibold text-black text-sm shadow-md transition hover:bg-amber-500">
            Get Free Demo
        </a>
        <a href="{{ $buyUrl }}" target="_blank" rel="noopener noreferrer" title="Purchase on CodeCanyon"
           class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg border border-amber-400 bg-white px-4 py-2.5 font-semibold text-amber-500 text-sm transition hover:bg-amber-50">
            Buy Now
        </a>
    </div>
</div>

<!-- Mobile Menu Script -->
<script>
    const mobileMenuBtn = document.getElementById("mobile-menu-btn");
    const mobileMenu = document.getElementById("mobile-menu");

    mobileMenuBtn.addEventListener("click", () => {
        mobileMenu.classList.toggle("active");
    });

    // Close menu when clicking on a link
    const mobileMenuLinks = mobileMenu.querySelectorAll("a");
    mobileMenuLinks.forEach((link) => {
        link.addEventListener("click", () => {
            mobileMenu.classList.remove("active");
        });
    });

    const animated = document.querySelectorAll("[data-animate]");
    if ("IntersectionObserver" in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("in-view");
                    observer.unobserve(entry.target);
                }
            });
        }, {threshold: 0.15, rootMargin: "0px 0px -40px 0px"});
        animated.forEach((el) => observer.observe(el));
    } else {
        animated.forEach((el) => el.classList.add("in-view"));
    }

    const countEls = [...document.querySelectorAll("[data-count]")];
    if (countEls.length) {
        const reducedCount = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        const parseStat = (el) => {
            const raw = el.textContent.trim();
            if (raw.includes("REPLACE")) return null;
            const m = raw.match(/^(\D*?)([\d,]+(?:\.\d+)?)(.*)$/s);
            if (!m) return null;
            const num = parseFloat(m[2].replace(/,/g, ""));
            if (!isFinite(num)) return null;
            const decimals = (m[2].split(".")[1] || "").length;
            const grouped = m[2].includes(",");
            return {prefix: m[1], num, decimals, grouped, suffix: m[3]};
        };

        const fmt = (v, s) => {
            const n = s.decimals ? v.toFixed(s.decimals) : String(Math.round(v));
            return s.prefix + (s.grouped ? Number(n).toLocaleString("en-US") : n) + s.suffix;
        };

        const runCount = (el) => {
            const s = parseStat(el);
            if (!s || el.dataset.counted) return;
            el.dataset.counted = "1";
            if (reducedCount) return;
            el.textContent = fmt(0, s);
            const dur = 1400;
            let t0 = null;
            const tick = (now) => {
                if (t0 === null) t0 = now;
                const p = Math.min(1, (now - t0) / dur);
                const eased = 1 - Math.pow(1 - p, 3);
                el.textContent = fmt(s.num * eased, s);
                if (p < 1) requestAnimationFrame(tick);
                else el.textContent = fmt(s.num, s);
            };
            requestAnimationFrame(tick);
        };

        if ("IntersectionObserver" in window) {
            const countObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        runCount(entry.target);
                        countObserver.unobserve(entry.target);
                    }
                });
            }, {threshold: 0.6});
            countEls.forEach((el) => countObserver.observe(el));
        } else {
            countEls.forEach(runCount);
        }

        window.__runCount = runCount;
    }

    const progressBar = document.getElementById("scroll-progress");
    if (progressBar) {
        const updateProgress = () => {
            const max = document.documentElement.scrollHeight - window.innerHeight;
            const pct = max > 0 ? (window.scrollY / max) * 100 : 0;
            progressBar.style.width = Math.min(100, Math.max(0, pct)) + "%";
        };
        let progressTicking = false;
        const onProgressScroll = () => {
            if (progressTicking) return;
            progressTicking = true;
            requestAnimationFrame(() => {
                updateProgress();
                progressTicking = false;
            });
        };
        window.addEventListener("scroll", onProgressScroll, {passive: true});
        window.addEventListener("resize", onProgressScroll);
        updateProgress();
        window.__updateProgress = updateProgress;
    }

    const landingBuilderRoot = document.querySelector("[data-landing-builder-root]");
    const landingBuilderDemo = landingBuilderRoot?.querySelector("[data-landing-builder-demo]");
    if (landingBuilderRoot && landingBuilderDemo) {
        const builderWorkspace = landingBuilderDemo.querySelector("[data-landing-builder-workspace]");
        const builderCursor = landingBuilderDemo.querySelector("[data-landing-builder-cursor]");
        const builderFeatures = landingBuilderRoot.querySelectorAll("[data-landing-builder-feature]");
        const builderCaptions = landingBuilderDemo.querySelectorAll("[data-landing-builder-caption]");
        const builderProgress = landingBuilderDemo.querySelectorAll("[data-landing-builder-progress]");
        const builderReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        let builderStep = 0;
        let builderVisible = false;
        let builderTimer = null;
        let builderCursorAnimation = null;
        let builderResizeFrame = null;

        const animateBuilderCursor = (step) => {
            if (builderReducedMotion || !builderVisible || !builderWorkspace || !builderCursor?.animate) return;

            const scene = landingBuilderDemo.querySelector(`[data-landing-builder-scene="${step}"]`);
            if (!scene) return;

            const targets = [...scene.querySelectorAll("[data-landing-builder-target]")]
                .sort((first, second) => {
                    return Number(first.dataset.landingBuilderOrder || 0) - Number(second.dataset.landingBuilderOrder || 0);
                });
            if (!targets.length) return;

            builderCursorAnimation?.cancel();

            const workspaceRect = builderWorkspace.getBoundingClientRect();
            const startLeft = workspaceRect.width - 26;
            const startTop = workspaceRect.height - 26;
            const segment = 0.78 / targets.length;
            const keyframes = [{left: `${startLeft}px`, top: `${startTop}px`, opacity: 0, offset: 0}];

            targets.forEach((target, index) => {
                const targetRect = target.getBoundingClientRect();
                const left = targetRect.left - workspaceRect.left + targetRect.width / 2;
                const top = targetRect.top - workspaceRect.top + targetRect.height / 2;
                const arrival = 0.08 + index * segment + 0.25 * segment;
                const hold = arrival + 0.25 * segment;
                const departure = 0.08 + (index + 1) * segment;
                const dragY = Number(target.dataset.landingBuilderDragY || 0);

                keyframes.push({left: `${left}px`, top: `${top}px`, opacity: 1, offset: arrival});
                keyframes.push({left: `${left}px`, top: `${top}px`, opacity: 1, offset: hold});
                keyframes.push({left: `${left}px`, top: `${top + dragY}px`, opacity: 1, offset: departure});
            });

            keyframes.push({left: `${startLeft}px`, top: `${startTop}px`, opacity: 0, offset: 1});
            builderCursorAnimation = builderCursor.animate(keyframes, {
                duration: 4550,
                easing: "ease-in-out",
                fill: "forwards",
            });
        };

        const showBuilderStep = (step, restart = false) => {
            builderStep = step;
            if (restart) {
                landingBuilderDemo.removeAttribute("data-step");
                void landingBuilderDemo.offsetWidth;
            }
            landingBuilderDemo.dataset.step = String(step);

            builderFeatures.forEach((feature, index) => {
                feature.classList.toggle("is-active", index === step);
            });
            builderCaptions.forEach((caption, index) => {
                caption.classList.toggle("is-active", index === step);
            });
            builderProgress.forEach((progress) => progress.classList.remove("is-active"));

            requestAnimationFrame(() => {
                builderProgress[step]?.classList.add("is-active");
                animateBuilderCursor(step);
            });
        };

        const stopBuilder = () => {
            if (builderTimer) window.clearInterval(builderTimer);
            builderTimer = null;
            builderCursorAnimation?.cancel();
            builderCursorAnimation = null;
        };

        const startBuilder = () => {
            if (builderReducedMotion || builderTimer) return;
            builderTimer = window.setInterval(() => {
                showBuilderStep((builderStep + 1) % builderCaptions.length);
            }, 4800);
        };

        showBuilderStep(0);

        if (!builderReducedMotion && "IntersectionObserver" in window) {
            const builderObserver = new IntersectionObserver((entries) => {
                builderVisible = entries[0]?.isIntersecting || false;
                if (builderVisible) {
                    showBuilderStep(builderStep, true);
                    startBuilder();
                } else {
                    stopBuilder();
                }
            }, {threshold: 0.2});
            builderObserver.observe(landingBuilderRoot);
        } else if (!builderReducedMotion) {
            builderVisible = true;
            showBuilderStep(0, true);
            startBuilder();
        }

        window.addEventListener("resize", () => {
            if (!builderVisible) return;
            if (builderResizeFrame) cancelAnimationFrame(builderResizeFrame);
            builderResizeFrame = requestAnimationFrame(() => showBuilderStep(builderStep, true));
        });
    }

    const storySteps = [...document.querySelectorAll("[data-story-step]")];
    if (storySteps.length) {
        const shots = document.querySelectorAll("[data-story-shot]");
        const dots = document.querySelectorAll("[data-story-dot]");
        let current = -1;

        const activate = (i) => {
            if (i === current) return;
            current = i;
            shots.forEach((s) => s.classList.toggle("is-active", +s.dataset.storyShot === i));
            dots.forEach((d) => d.classList.toggle("is-active", +d.dataset.storyDot === i));
            storySteps.forEach((s) => s.classList.toggle("is-active", +s.dataset.storyStep === i));
        };

        const pickStep = () => {
            const line = window.innerWidth < 768
                ? window.innerHeight * 0.76
                : window.innerHeight / 2;
            let best = 0, bestDist = Infinity;
            storySteps.forEach((step, i) => {
                const r = step.getBoundingClientRect();
                const dist = Math.abs(r.top + r.height / 2 - line);
                if (dist < bestDist) {
                    bestDist = dist;
                    best = i;
                }
            });
            activate(best);
        };

        let ticking = false;
        const onScroll = () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => {
                pickStep();
                ticking = false;
            });
        };

        window.addEventListener("scroll", onScroll, {passive: true});
        window.addEventListener("resize", onScroll);
        pickStep();

        window.__storyPick = pickStep;
    }

    const gallerySwipers = {};
    if (window.Swiper) {
        document.querySelectorAll("[data-gallery-panel]").forEach((panel) => {
            const key = panel.dataset.galleryPanel;
            const el = panel.querySelector(".gallery-swiper");
            if (!el) return;
            gallerySwipers[key] = new Swiper(el, {
                slidesPerView: "auto",
                spaceBetween: 20,
                grabCursor: true,
                freeMode: {enabled: true, momentum: true, momentumRatio: 0.8},
                mousewheel: {forceToAxis: true},
                navigation: {
                    prevEl: panel.querySelector("[data-swiper-prev]"),
                    nextEl: panel.querySelector("[data-swiper-next]"),
                },
                breakpoints: {768: {spaceBetween: 24}},
            });
        });
    }

    const galleryTabs = document.querySelectorAll("[data-gallery-tab]");
    galleryTabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            const key = tab.dataset.galleryTab;
            galleryTabs.forEach((t) => t.classList.toggle("is-active", t === tab));
            document.querySelectorAll("[data-gallery-panel]").forEach((panel) => {
                panel.classList.toggle("hidden", panel.dataset.galleryPanel !== key);
            });
            if (gallerySwipers[key]) gallerySwipers[key].update();
        });
    });

    document.querySelectorAll(".copy-btn").forEach((btn) => {
        btn.addEventListener("click", async () => {
            try {
                await navigator.clipboard.writeText(btn.dataset.copy);
                btn.classList.add("text-amber-500");
                setTimeout(() => btn.classList.remove("text-amber-500"), 1200);
            } catch (e) {
                //
            }
        });
    });

    const stickyCta = document.getElementById("sticky-cta");
    const heroSection = document.querySelector("section");
    if (stickyCta && heroSection) {
        const setCta = (visible) => {
            stickyCta.classList.toggle("sticky-cta-hidden", !visible);
            document.body.classList.toggle("cta-up", visible);
        };

        if ("IntersectionObserver" in window) {
            setCta(false);
            new IntersectionObserver((entries) => {
                entries.forEach((entry) => setCta(!entry.isIntersecting));
            }, {threshold: 0.4}).observe(heroSection);
        } else {
            setCta(true);
        }

        window.__setCta = setCta;
    }

    const scrollTopBtn = document.getElementById("scroll-top");
    if (scrollTopBtn) {
        const toggleScrollTop = () => {
            scrollTopBtn.classList.toggle("scroll-top-hidden", window.scrollY < 400);
        };
        window.addEventListener("scroll", toggleScrollTop, {passive: true});
        toggleScrollTop();
        scrollTopBtn.addEventListener("click", () => {
            const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            window.scrollTo({top: 0, behavior: reduced ? "auto" : "smooth"});
        });
    }
</script>
</body>

</html>

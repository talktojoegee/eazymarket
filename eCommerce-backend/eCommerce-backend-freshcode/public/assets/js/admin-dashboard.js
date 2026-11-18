document.addEventListener("DOMContentLoaded", function () {
    var homeBuilderDemoTimer = null;
    var homeBuilderCursorAnimation = null;
    var homeBuilderShowcase = document.querySelector("[data-home-builder-showcase]");
    if (homeBuilderShowcase) {
        var showcaseStorageKey = "hypercommerce.dashboard.home-builder-showcase.dismissed";
        var showcaseDismissed = false;

        try {
            showcaseDismissed = window.localStorage.getItem(showcaseStorageKey) === "1";
        } catch (error) {
            showcaseDismissed = false;
        }

        if (showcaseDismissed) {
            homeBuilderShowcase.remove();
        } else {
            homeBuilderShowcase.hidden = false;
            var showcaseDismissButton = homeBuilderShowcase.querySelector("[data-home-builder-showcase-dismiss]");
            if (showcaseDismissButton) {
                showcaseDismissButton.addEventListener("click", function () {
                    try {
                        window.localStorage.setItem(showcaseStorageKey, "1");
                    } catch (error) {
                        // The showcase still closes for this visit when storage is unavailable.
                    }
                    if (homeBuilderDemoTimer) window.clearInterval(homeBuilderDemoTimer);
                    if (homeBuilderCursorAnimation) homeBuilderCursorAnimation.cancel();
                    homeBuilderShowcase.remove();
                });
            }
        }
    }

    var homeBuilderDemo = document.querySelector("[data-home-builder-demo]");
    if (homeBuilderDemo) {
        var demoCaptions = homeBuilderDemo.querySelectorAll("[data-home-builder-demo-caption]");
        var demoProgress = homeBuilderDemo.querySelectorAll(".dashboard-home-builder__demo-progress i");
        var demoFeatures = document.querySelectorAll("[data-home-builder-feature]");
        var demoWorkspace = homeBuilderDemo.querySelector(".dashboard-home-builder__demo-workspace");
        var demoCursor = homeBuilderDemo.querySelector(".dashboard-home-builder__demo-cursor");
        var demoStep = 0;
        var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        var animateDemoCursor = function (step) {
            if (reduceMotion || !demoWorkspace || !demoCursor || !demoCursor.animate) return;

            var scene = homeBuilderDemo.querySelector('[data-demo-scene="' + step + '"]');
            if (!scene) return;

            var targets = Array.prototype.slice.call(scene.querySelectorAll("[data-demo-cursor-target]"));
            targets.sort(function (first, second) {
                return Number(first.getAttribute("data-demo-cursor-order") || 0) - Number(second.getAttribute("data-demo-cursor-order") || 0);
            });
            if (!targets.length) return;

            if (homeBuilderCursorAnimation) homeBuilderCursorAnimation.cancel();

            var workspaceRect = demoWorkspace.getBoundingClientRect();
            var startLeft = workspaceRect.width - 24;
            var startTop = workspaceRect.height - 22;
            var segment = .78 / targets.length;
            var keyframes = [{ left: startLeft + "px", top: startTop + "px", opacity: 0, offset: 0 }];

            targets.forEach(function (target, index) {
                var targetRect = target.getBoundingClientRect();
                var left = targetRect.left - workspaceRect.left + (targetRect.width / 2);
                var top = targetRect.top - workspaceRect.top + (targetRect.height / 2);
                var arrival = .08 + (index * segment) + (.25 * segment);
                var hold = arrival + (.25 * segment);
                var departure = .08 + ((index + 1) * segment);
                var dragY = Number(target.getAttribute("data-demo-cursor-drag-y") || 0);

                keyframes.push({ left: left + "px", top: top + "px", opacity: 1, offset: arrival });
                keyframes.push({ left: left + "px", top: top + "px", opacity: 1, offset: hold });
                keyframes.push({ left: left + "px", top: (top + dragY) + "px", opacity: 1, offset: departure });
            });

            keyframes.push({ left: startLeft + "px", top: startTop + "px", opacity: 0, offset: 1 });
            homeBuilderCursorAnimation = demoCursor.animate(keyframes, {
                duration: 4550,
                easing: "ease-in-out",
                fill: "forwards"
            });
        };

        var showDemoStep = function (step) {
            homeBuilderDemo.setAttribute("data-step", String(step));
            demoCaptions.forEach(function (caption, index) {
                caption.classList.toggle("active", index === step);
            });
            demoProgress.forEach(function (item, index) {
                item.classList.toggle("active", index === step);
            });
            demoFeatures.forEach(function (feature, index) {
                feature.classList.toggle("is-active", index === step);
            });
            window.requestAnimationFrame(function () {
                animateDemoCursor(step);
            });
        };

        showDemoStep(demoStep);

        if (!reduceMotion && demoCaptions.length > 1) {
            homeBuilderDemoTimer = window.setInterval(function () {
                demoStep = (demoStep + 1) % demoCaptions.length;
                showDemoStep(demoStep);
            }, 4800);

            window.addEventListener("resize", function () {
                window.requestAnimationFrame(function () {
                    animateDemoCursor(demoStep);
                });
            });
        }
    }

    if (!window.ApexCharts) return;

    var charts = {};

    var tblr = function (name, fallback) {
        var v = getComputedStyle(document.body).getPropertyValue(name);
        return (v && v.trim()) || fallback;
    };
    var PRIMARY = tblr("--tblr-primary", "#206bc4");
    var GREEN = tblr("--tblr-green", "#2fb344");
    var RED = tblr("--tblr-red", "#d63939");
    var INFO = tblr("--tblr-info", "#4299e1");

    // ---- Section 1: revenue vs orders (area + line combo) ----
    function renderRevenueOrders(payload) {
        var el = document.getElementById("chart-revenue-orders");
        if (!el) return;
        var daily = (payload && payload.daily) || [];
        var sym = (payload && payload.currency_symbol) || "";
        var money = function (v) { return sym + Math.round(v).toLocaleString(); };
        var opts = {
            chart: { type: "line", height: 280, fontFamily: "inherit", toolbar: { show: false }, animations: { enabled: false } },
            series: [
                { name: "Revenue", type: "area", data: daily.map(function (d) { return Math.round(d.revenue || 0); }) },
                { name: "Orders", type: "line", data: daily.map(function (d) { return d.orders || 0; }) }
            ],
            xaxis: { categories: daily.map(function (d) { return d.date; }), labels: { rotate: -45, hideOverlappingLabels: true } },
            yaxis: [
                { title: { text: "Revenue" }, labels: { formatter: money } },
                { opposite: true, title: { text: "Orders" }, labels: { formatter: function (v) { return Math.round(v); } } }
            ],
            colors: [PRIMARY, GREEN],
            stroke: { width: [2, 2], curve: "smooth" },
            fill: { type: ["gradient", "solid"], opacity: [0.15, 1] },
            dataLabels: { enabled: false },
            legend: { position: "top" },
            grid: { strokeDashArray: 4 },
            tooltip: { theme: "dark", y: { formatter: function (v, opts) { return opts.seriesIndex === 0 ? money(v) : Math.round(v); } } }
        };
        if (charts.revenueOrders) { charts.revenueOrders.updateOptions(opts); }
        else { charts.revenueOrders = new ApexCharts(el, opts); charts.revenueOrders.render(); }
    }
    renderRevenueOrders(dashboardData.revenueVsOrders);

    // ---- Section 1: net commission sparkline ----
    (function () {
        var el = document.getElementById("chart-commission");
        var daily = dashboardData.commissionDaily || [];
        if (!el || !daily.length) return;
        new ApexCharts(el, {
            chart: { type: "area", height: 40, sparkline: { enabled: true }, animations: { enabled: false } },
            series: [{ name: "Commission", data: daily.map(function (d) { return Math.round(d.commission || 0); }) }],
            stroke: { width: 2, curve: "smooth" },
            fill: { opacity: 0.15 },
            colors: [PRIMARY],
            tooltip: { theme: "dark", x: { show: false } }
        }).render();
    })();

    // ---- Section 1: revenue by category ----
    (function () {
        var el = document.getElementById("revenue-category-chart");
        var empty = document.getElementById("revenue-category-empty");
        var rows = (dashboardData.revenueByCategory && dashboardData.revenueByCategory.categories) || [];
        if (!el) return;
        if (!rows.length) { if (empty) empty.classList.remove("d-none"); return; }
        new ApexCharts(el, {
            chart: { type: "treemap", height: 260, fontFamily: "inherit", toolbar: { show: false }, animations: { enabled: false } },
            series: [{
                data: rows.map(function (r) {
                    return { x: r.name, y: r.revenue_raw };
                })
            }],
            colors: [PRIMARY, GREEN, INFO, "#845ef7", "#f59f00", "#f783ac", "#66d9e8", RED],
            plotOptions: {
                treemap: { distributed: true, enableShades: false }
            },
            dataLabels: {
                enabled: true,
                style: { fontSize: "11px", fontWeight: 400 },
                formatter: function (text, opts) {
                    var row = rows[opts.dataPointIndex];
                    return [text, (row ? row.share : 0) + "%"];
                },
                offsetY: -1
            },
            legend: { show: false },
            stroke: { width: 2, colors: ["transparent"] },
            fill: { opacity: 0.82 },
            tooltip: {
                theme: "dark",
                y: {
                    formatter: function (v, opts) {
                        var row = rows[opts.dataPointIndex];
                        return (row ? row.revenue : v.toLocaleString()) + " (" + (row ? row.share : 0) + "%)";
                    }
                }
            }
        }).render();
    })();

    // ---- Section 2: order funnel (horizontal bar) ----
    (function () {
        var el = document.getElementById("chart-order-funnel");
        var funnel = (dashboardData.orderFunnel && dashboardData.orderFunnel.funnel) || [];
        if (!el || !funnel.length) return;
        new ApexCharts(el, {
            chart: { type: "bar", height: 230, fontFamily: "inherit", toolbar: { show: false }, animations: { enabled: false } },
            series: [{ name: "Orders", data: funnel.map(function (f) { return f.count; }) }],
            xaxis: { categories: funnel.map(function (f) { return f.stage; }) },
            plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: "60%", distributed: true } },
            colors: [PRIMARY, INFO, GREEN, RED],
            dataLabels: { enabled: true },
            legend: { show: false },
            grid: { strokeDashArray: 4 }
        }).render();
    })();

    // ---- AJAX helpers for period dropdowns ----
    function fetchData(type, days) {
        var url = dashboardDataUrl + "?type=" + encodeURIComponent(type) + "&days=" + days;
        return fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } }).then(function (r) { return r.json(); });
    }

    function bindPeriod(selector, handler) {
        var toggle = document.querySelector(selector);
        if (!toggle) return;
        var dropdown = toggle.closest(".dropdown") || document;
        var items = dropdown.querySelectorAll(".dropdown-item");
        items.forEach(function (item) {
            item.addEventListener("click", function (e) {
                e.preventDefault();
                handler(this.getAttribute("data-period"));
                toggle.textContent = this.textContent;
                items.forEach(function (i) { i.classList.remove("active"); });
                this.classList.add("active");
            });
        });
    }

    bindPeriod(".revenue-orders-period", function (days) {
        fetchData("revenue_vs_orders", days).then(function (data) {
            renderRevenueOrders(data);
            var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
            set("aov-total", data.formatted_aov);
            set("revenue-total", data.formatted_total_revenue);
        });
    });

    bindPeriod(".customer-insights-period", function (days) {
        fetchData("customer_insights", days).then(function (data) {
            var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
            var width = function (id, v) { var el = document.getElementById(id); if (el) el.style.width = v + "%"; };
            set("ci-repeat-rate", data.repeat_rate + "%");
            set("ci-avg-basket", data.avg_basket_size);
            set("ci-churn-risk", data.churn_risk);
            set("ci-new", data.new_customers);
            set("ci-returning", data.returning_customers);
            set("ci-total", data.total_customers);
            set("ci-new-rev", data.new_revenue);
            set("ci-returning-rev", data.returning_revenue);
            set("ci-new-rev-share", data.new_revenue_share);
            set("ci-returning-rev-share", data.returning_revenue_share);
            width("ci-new-rev-bar", data.new_revenue_share);
            width("ci-returning-rev-bar", data.returning_revenue_share);
        });
    });
});

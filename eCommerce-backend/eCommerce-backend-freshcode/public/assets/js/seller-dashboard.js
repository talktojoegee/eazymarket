document.addEventListener("DOMContentLoaded", function () {
    if (!window.ApexCharts || typeof sellerDashboardData === "undefined") return;

    var tblr = function (name, fallback) {
        var v = getComputedStyle(document.body).getPropertyValue(name);
        return (v && v.trim()) || fallback;
    };
    var PRIMARY = tblr("--tblr-primary", "#206bc4");

    var salesChart = null;

    function renderSales(summary) {
        var el = document.getElementById("seller-sales-chart");
        if (!el) return;
        var daily = (summary && summary.daily) || [];
        var sym = (summary && summary.currency_symbol) || "";
        var money = function (v) { return sym + Math.round(v).toLocaleString(); };
        var opts = {
            chart: { type: "area", height: 260, fontFamily: "inherit", toolbar: { show: false }, animations: { enabled: false } },
            series: [{ name: "Revenue", data: daily.map(function (d) { return Math.round(d.revenue || 0); }) }],
            xaxis: { categories: daily.map(function (d) { return d.date; }), labels: { rotate: -45, hideOverlappingLabels: true } },
            yaxis: { labels: { formatter: money } },
            colors: [PRIMARY],
            stroke: { width: 2, curve: "smooth" },
            fill: { type: "gradient", opacity: 0.15 },
            dataLabels: { enabled: false },
            grid: { strokeDashArray: 4 },
            tooltip: { theme: "dark", y: { formatter: money } }
        };
        if (salesChart) { salesChart.updateOptions(opts); }
        else { salesChart = new ApexCharts(el, opts); salesChart.render(); }
    }
    renderSales(sellerDashboardData.salesSummary);

    function fetchData(type, days) {
        var url = sellerDashboardDataUrl + "?type=" + encodeURIComponent(type) + "&days=" + days;
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

    var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };

    bindPeriod(".sales-summary-period", function (days) {
        fetchData("sales_summary", days).then(function (d) {
            renderSales(d);
            set("ss-revenue", d.revenue);
            set("ss-aov", d.aov);
            set("ss-units", d.units);
            set("ss-orders", d.orders);
        });
    });

    bindPeriod(".customer-insights-period", function (days) {
        fetchData("customer_insights", days).then(function (d) {
            set("sci-repeat-rate", d.repeat_rate + "%");
            set("sci-total", d.total_customers);
            set("sci-new", d.new_customers);
            set("sci-returning", d.returning_customers);
        });
    });
});

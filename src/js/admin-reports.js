import Chart from 'chart.js/auto';

function initReports() {
    const dataElement = document.getElementById('reports-data');
    if (!dataElement) {
        console.warn('Reports data element not found - reports may not be active');
        return;
    }

function stabilizeChartSizing(chart, container) {
    if (!chart) return;
    const doResize = () => { try { chart.resize(); } catch(_) {} };
    try { requestAnimationFrame(() => { doResize(); setTimeout(doResize, 50); setTimeout(doResize, 250); setTimeout(doResize, 800); }); } catch(_) { setTimeout(doResize, 100); }
    try {
        if (window.ResizeObserver && container) {
            const ro = new ResizeObserver(() => { doResize(); });
            ro.observe(container);
            // Store to chart instance for potential cleanup later (optional)
            chart.__ro = ro;
        }
        window.addEventListener('resize', doResize, { passive: true });
        chart.__onDestroy = () => { try { window.removeEventListener('resize', doResize); } catch(_) {} try { chart.__ro && chart.__ro.disconnect && chart.__ro.disconnect(); } catch(_) {} };
    } catch(_) {}
}

    let chartData;
    try {
        chartData = JSON.parse(dataElement.textContent || '{}');
    } catch (e) {
        console.error('Failed to parse reports data JSON', e);
        return;
    }

    // 1. Sales Performance Chart
    const salesCanvas = document.getElementById('salesChart');
    if (salesCanvas && Array.isArray(chartData.labels) && chartData.labels.length > 0) {
        const salesChart = new Chart(salesCanvas, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Daily Revenue',
                        data: chartData.revenue,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        yAxisID: 'yRevenue',
                        tension: 0.1,
                        fill: true,
                    },
                    {
                        label: 'Daily Orders',
                        data: chartData.orders,
                        borderColor: 'rgba(153, 102, 255, 1)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        yAxisID: 'yOrders',
                        tension: 0.1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yRevenue: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue ($)',
                        },
                    },
                    yOrders: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders',
                        },
                        grid: {
                            drawOnChartArea: false, // only draw grid for revenue axis
                        },
                    },
                },
            },
        });
        try {
            stabilizeChartSizing(salesChart, salesCanvas.closest('.h-300') || salesCanvas.parentElement || document.body);
            requestAnimationFrame(() => { try { salesChart.update(); } catch(_) {} });
            setTimeout(() => { try { salesChart.update(); } catch(_) {} }, 350);
        } catch(_) {}
    }

    // 2. Payment Method Chart
    const paymentCanvas = document.getElementById('paymentMethodChart');
    if (paymentCanvas && Array.isArray(chartData.paymentLabels) && chartData.paymentLabels.length > 0) {
        const paymentChart = new Chart(paymentCanvas, {
            type: 'doughnut',
            data: {
                labels: chartData.paymentLabels,
                datasets: [{
                    label: 'Payment Methods',
                    data: chartData.paymentCounts,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                    ],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
            },
        });
        try {
            stabilizeChartSizing(paymentChart, paymentCanvas.closest('.h-300') || paymentCanvas.parentElement || document.body);
            requestAnimationFrame(() => { try { paymentChart.update(); } catch(_) {} });
            setTimeout(() => { try { paymentChart.update(); } catch(_) {} }, 350);
        } catch(_) {}
    }

    // 3. Print Button Handler
    const printButton = document.querySelector('.js-print-button');
    if (printButton) {
        printButton.addEventListener('click', () => {
            window.print();
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReports);
} else {
    initReports();
}

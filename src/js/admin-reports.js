import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const dataElement = document.getElementById('reports-data');
    if (!dataElement) {
        console.error('Reports data element not found!');
        return;
    }

    const chartData = JSON.parse(dataElement.textContent);

    // 1. Sales Performance Chart
    const salesCanvas = document.getElementById('salesChart');
    if (salesCanvas && chartData.labels.length > 0) {
        new Chart(salesCanvas, {
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
    }

    // 2. Payment Method Chart
    const paymentCanvas = document.getElementById('paymentMethodChart');
    if (paymentCanvas && chartData.paymentLabels.length > 0) {
        new Chart(paymentCanvas, {
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
    }

    // 3. Print Button Handler
    const printButton = document.querySelector('.js-print-button');
    if (printButton) {
        printButton.addEventListener('click', () => {
            window.print();
        });
    }
});
Chart.register(...registerables);

document.addEventListener('DOMContentLoaded', function() {
    // Check if we are on the reports page and data is available
    if (document.getElementById('salesChart') && window.chartData) {
        // Sales Performance Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: window.chartData.labels,
                datasets: [
                    {
                        label: 'Orders',
                        data: window.chartData.orders,
                        backgroundColor: 'rgba(135, 172, 58, 0.2)',
                        borderColor: 'rgba(135, 172, 58, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Revenue ($)',
                        data: window.chartData.revenue,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        title: { display: true, text: 'Orders' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: { display: true, text: 'Revenue ($)' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });

        // Payment Method Distribution Chart
        const paymentCtx = document.getElementById('paymentMethodChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: window.chartData.paymentLabels,
                datasets: [{
                    data: window.chartData.paymentCounts,
                    backgroundColor: [
                        'rgba(135, 172, 58, 0.8)',   // WhimsicalFrog Green
                        'rgba(75, 85, 99, 0.8)',     // Gray
                        'rgba(16, 185, 129, 0.8)',   // Emerald
                        'rgba(245, 158, 11, 0.8)',   // Amber
                        'rgba(239, 68, 68, 0.8)',    // Red
                        'rgba(99, 102, 241, 0.8)'    // Indigo
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});

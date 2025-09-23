import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const dataElement = document.getElementById('reports-data');
    if (!dataElement) {
        console.warn('Reports data element not found - reports may not be active');
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

import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    LineController,
    BarController,
    DoughnutController,
    PieController,
    Title,
    Tooltip,
    Legend,
    Filler,
    ChartConfiguration,
    ChartData,
    ChartEvent,
    ActiveElement,
    ChartOptions
} from 'chart.js';

// Register Chart.js components for tree-shaking
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    LineController,
    BarController,
    DoughnutController,
    PieController,
    Title,
    Tooltip,
    Legend,
    Filler
);

export const getCommonOptions = (onClick?: (evt: ChartEvent, els: ActiveElement[], chart: ChartJS) => void): ChartOptions<'line' | 'bar'> => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
        x: { grid: { display: false } }
    },
    onClick
});

export const getDonutOptions = (onClick?: (evt: ChartEvent, els: ActiveElement[], chart: ChartJS) => void): ChartOptions<'doughnut' | 'pie'> => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
            labels: { usePointStyle: true, boxWidth: 6, font: { size: 10 } }
        }
    },
    cutout: '70%',
    onClick
});

export const createSalesChartConfig = (data: ChartData<'line'>, options: ChartOptions<'line'>): ChartConfiguration<'line'> => ({
    type: 'line',
    data: {
        ...data,
        datasets: data.datasets.map(ds => ({
            ...ds,
            tension: 0.4,
            fill: true,
            backgroundColor: ds.backgroundColor || 'rgba(59, 130, 246, 0.1)',
            borderColor: ds.borderColor || '#3b82f6',
        }))
    },
    options
});

export const createDonutChartConfig = (type: 'doughnut' | 'pie', labels: string[], values: number[], colors: string[], options: ChartOptions<'doughnut' | 'pie'>): ChartConfiguration<'doughnut' | 'pie'> => ({
    type,
    data: {
        labels,
        datasets: [{
            data: values,
            backgroundColor: colors,
            borderWidth: 0
        }]
    },
    options
});

export const createBarChartConfig = (labels: string[], values: number[], options: ChartOptions<'bar'>): ChartConfiguration<'bar'> => ({
    type: 'bar',
    data: {
        labels,
        datasets: [{
            data: values,
            backgroundColor: 'rgba(99, 102, 241, 0.8)',
            borderRadius: 4
        }]
    },
    options: {
        ...options,
        indexAxis: 'y' as const
    }
});

function initTopicAccuracyChart(canvas, config) {
    if (!canvas || !config) return;

    const data = config.data || [];
    const labels = config.labels || [];

    if (data.length === 0) return;

    const bgColors = data.map(function (val) {
        if (val >= 85) return '#198754';
        if (val >= 70) return '#0d6efd';
        if (val >= 50) return '#fd7e14';
        return '#dc3545';
    });

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Accuracy (%)',
                data: data,
                backgroundColor: bgColors,
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                x: {
                    min: 0,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Accuracy (%)'
                    }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function initAccuracyTrendChart(canvas, config) {
    if (!canvas || !config) return;

    const data = config.data || [];
    const labels = config.labels || [];
    const passThreshold = config.passThreshold || 50;

    if (data.length < 2) return;

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Accuracy',
                    data: data,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#0d6efd',
                },
                {
                    label: 'Pass Threshold',
                    data: Array(data.length).fill(passThreshold),
                    borderColor: '#dc3545',
                    borderDash: [5, 5],
                    pointRadius: 0,
                    fill: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Accuracy (%)'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        afterLabel: function (context) {
                            if (context.datasetIndex === 0) {
                                return 'Quiz: ' + (config.quizTitles ? config.quizTitles[context.dataIndex] : '');
                            }
                            return '';
                        }
                    }
                }
            }
        }
    });
}

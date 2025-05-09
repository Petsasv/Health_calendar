// Health data visualization using Chart.js
class HealthCharts {
    constructor() {
        // Load Chart.js if not already loaded
        if (typeof Chart === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            document.head.appendChild(script);
        }
    }
    
    // Create population health chart
    createPopulationHealthChart(data, elementId) {
        const ctx = document.getElementById(elementId).getContext('2d');
        
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(data.common_conditions),
                datasets: [{
                    label: 'Prevalence (%)',
                    data: Object.values(data.common_conditions),
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Common Health Conditions in Your Area',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Percentage of Population',
                            font: {
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Create risk assessment chart
    createRiskAssessmentChart(data, elementId) {
        const ctx = document.getElementById(elementId).getContext('2d');
        const riskLevels = { 'low': 1, 'medium': 2, 'high': 3 };
        
        return new Chart(ctx, {
            type: 'radar',
            data: {
                labels: Object.keys(data.specific_conditions),
                datasets: [{
                    label: 'Your Risk Level',
                    data: Object.values(data.specific_conditions).map(risk => {
                        return riskLevels[risk.toLowerCase()] || 0;
                    }),
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(255, 99, 132, 1)'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Your Health Risk Assessment',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 3,
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                return ['', 'Low', 'Medium', 'High'][value];
                            },
                            font: {
                                weight: 'bold'
                            }
                        },
                        pointLabels: {
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Create community insights chart
    createCommunityInsightsChart(data, elementId) {
        const ctx = document.getElementById(elementId).getContext('2d');
        
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: Object.keys(data.checkup_compliance.by_age),
                datasets: [{
                    label: 'Checkup Compliance Rate',
                    data: Object.values(data.checkup_compliance.by_age),
                    fill: false,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Checkup Compliance by Age Group',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Compliance Rate (%)',
                            font: {
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Age Group',
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Create preventive care success chart
    createPreventiveSuccessChart(data, elementId) {
        const ctx = document.getElementById(elementId).getContext('2d');
        
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data.preventive_success),
                datasets: [{
                    data: Object.values(data.preventive_success),
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Preventive Care Success Rates',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }
} 
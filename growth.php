<?php
// Example realistic revenue growth data for a TNVS company
// Simulated monthly revenue growth (e.g., fluctuations due to seasons, promotions, or market changes)
$growth = [100000, 120000, 115000, 130000, 150000, 140000, 160000, 155000, 170000, 180000, 175000, 190000]; // Monthly revenue growth in pesos
?>

<!-- Include Chart.js from a CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

<div class="bg-white p-6 rounded-lg w-full">
    <h2 class="text-xl font-bold mb-6 text-gray-800">REVENUE GROWTH</h2>

    <!-- Growth Graph -->
    <canvas id="growthChart" width="400" height="400"></canvas> <!-- Ensure the canvas has a size -->

    <script>
        window.onload = function () { // Ensure the script runs after the page is loaded
            // Get the data from PHP
            const growth = <?php echo json_encode($growth); ?>;

            // Create the growth chart using Chart.js
            const ctx = document.getElementById('growthChart').getContext('2d');
            
            const growthChart = new Chart(ctx, {
                type: 'line', // Line chart to show revenue fluctuations
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], // Months
                    datasets: [{
                        label: 'Monthly Revenue Growth (₱)',
                        data: growth,
                        fill: false,
                        borderColor: '#4311A5', // Line color
                        borderWidth: 2,
                        tension: 0.4, // To make the line smooth with curves
                        pointBackgroundColor: '#4311A5', // Points on the line
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString(); // Format the Y-axis labels with currency
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(tooltipItem) {
                                    return '' + tooltipItem.raw.toLocaleString(); // Format tooltip values with currency
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</div>
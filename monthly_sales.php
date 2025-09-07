<?php
// This is the PHP code for your monthly sales graph (monthly_sales.php)

// Example sales data (You can fetch this data from a database or other source)
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$sales = [120000, 135000, 150000, 140000, 160000, 145000, 155000, 165000, 170000, 180000, 190000, 200000];
?>

<div class="bg-white p-6 rounded-lg w-full">
    <h2 class="text-xl font-bold mb-4 text-gray-800">MONTHLY RIDE EARNINGS</h2>

    <!-- Sales Graph -->
    <canvas id="salesChart"></canvas>

    <script>
        // Get the data from PHP
        const months = <?php echo json_encode($months); ?>;
        const sales = <?php echo json_encode($sales); ?>;

        // Create the sales chart using Chart.js
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                    label: 'Monthly Sales (₱)',
                    data: sales,
                    backgroundColor: '#c6aafdff',
                    borderColor: '#4311A5',
                    borderWidth: 1
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
                                return '₱' + tooltipItem.raw.toLocaleString(); // Format tooltip values with currency
                            }
                        }
                    }
                }
            }
        });
    </script>
</div>
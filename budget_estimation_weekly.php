<?php
session_start(); // Start the session

include 'session_manager.php'; // Include the session manager

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php"); // Redirect to login page if not logged in
    exit();
}

// Optionally, check for double login and alert if necessary
if (is_user_logged_in($_SESSION['users_username'])) {
    // Optionally, log them out or handle the session as needed
}

// Your page content here...

?>

<html>

<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />

<body>

    <?php include('sidebar.php'); ?>

    <div class="overflow-y-auto h-full px-6">
        <!-- Breadcrumb -->
    <div class="bg-white p-4">
        <nav class="text-[#4311A5] font-poppins text-2xl">
            <ol class="list-reset flex">
            <li>
                <a class="text-black hover:text-[#4311A5]" href="dashboard.php">Dashboard</a>
            </li>
            <li><span class="mx-2">&gt;</span></li>
            <li>
                <a class="text-black hover:text-[#4311A5]" href="budget_request.php">Budget</a>
            </li>
            <li><span class="mx-2">&gt;</span></li>
            <li>
                <a class="text-black hover:text-[#4311A5]" href="budget_allocation.php">Budget Estimation Weekly</a>
            </li>
            </ol>
        </nav>
        </div>
    <!-- Main content area -->
    <div class="flex-1 p-6">
        <h1 class="font-bold font-[Poppins] text-[#0e1847] text-3xl mb-6 text-center">Budget Estimation</h1>

        <div class="flex items-center justify-start gap-6 mb-8 border-b border-gray-300 text-m font-medium">
            <a href="budget_estimation_weekly.php"
                class="pb-3 transition-all <?= basename($_SERVER['PHP_SELF']) === 'budget_estimation_weekly.php' 
                ? 'border-b-4 border-yellow-400 text-yellow-600 font-semibold' 
                : 'text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300' ?>">
                WEEKLY
            </a>

            <a href="budget_estimation.php"
                class="pb-3 transition-all <?= basename($_SERVER['PHP_SELF']) === 'budget_estimation.php' 
                ? 'border-b-4 border-yellow-400 text-yellow-600 font-semibold' 
                : 'text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300' ?>">
                MONTHLY
            </a>

            <a href="budget_estimation_quarterly.php"
                class="pb-3 transition-all <?= basename($_SERVER['PHP_SELF']) === 'budget_estimation_quarterly.php' 
                ? 'border-b-4 border-yellow-400 text-yellow-600 font-semibold' 
                : 'text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300' ?>">
                QUARTERLY
            </a>

            <a href="budget_estimation_yearly.php"
                class="pb-3 transition-all <?= basename($_SERVER['PHP_SELF']) === 'budget_estimation_yearly.php' 
                ? 'border-b-4 border-yellow-400 text-yellow-600 font-semibold' 
                : 'text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300' ?>">
                YEARLY
            </a>
        </div>

        <div class="grid grid-cols-1  gap-6">

            <!-- Card 4 -->
            <div class="bg-white p-4 rounded-lg shadow-lg border border-gray-300">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Budget vs Actual</h2>

                <!-- Budget vs Actual Table -->
                <table class=" min-w-full text-sm text-left float-right">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2 px-4 text-gray-600">Category</th>
                            <th class="py-2 px-4 text-gray-600">Budgeted</th>
                            <th class="py-2 px-4 text-gray-600">Actual</th>
                            <th class="py-2 px-4 text-gray-600">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b">
                            <td class="py-2 px-4 text-gray-700">Equipments</td>
                            <td class="py-2 px-4 text-gray-700">₱6,976.74</td>
                            <td class="py-2 px-4 text-gray-700">₱6,511.63</td>
                            <td class="py-2 px-4 text-green-500">₱465.12</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2 px-4 text-gray-700">Maintenance</td>
                            <td class="py-2 px-4 text-gray-700">₱2,325.58</td>
                            <td class="py-2 px-4 text-gray-700">₱2,906.98</td>
                            <td class="py-2 px-4 text-red-500">-581.40</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2 px-4 text-gray-700">Salaries</td>
                            <td class="py-2 px-4 text-gray-700">₱23,255.81</td>
                            <td class="py-2 px-4 text-gray-700">22,790.70</td>
                            <td class="py-2 px-4 text-green-500">₱465.12</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2 px-4 text-gray-700">Bonuses</td>
                            <td class="py-2 px-4 text-gray-700">4,651.16</td>
                            <td class="py-2 px-4 text-gray-700">₱4,186.05</td>
                            <td class="py-2 px-4 text-green-500">₱465.12</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2 px-4 text-gray-700">Facility Cost</td>
                            <td class="py-2 px-4 text-gray-700">₱3,488.37</td>
                            <td class="py-2 px-4 text-gray-700">₱3,488.37</td>
                            <td class="py-2 px-4 text-gray-500">₱0</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2 px-4 text-gray-700">Training</td>
                            <td class="py-2 px-4 text-gray-700">₱1,860.47</td>
                            <td class="py-2 px-4 text-gray-700">₱1,744.19</td>
                            <td class="py-2 px-4 text-green-500">₱116.28</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2 px-4 text-gray-700">Wellness</td>
                            <td class="py-2 px-4 text-gray-700">₱697.67</td>
                            <td class="py-2 px-4 text-gray-700">₱813.95</td>
                            <td class="py-2 px-4 text-red-500">-₱116.28</td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2 px-4 text-gray-700">Tax</td>
                            <td class="py-2 px-4 text-gray-700">₱2,790.70</td>
                            <td class="py-2 px-4 text-gray-700">₱2,790.70</td>
                            <td class="py-2 px-4 text-gray-500">₱0</td>
                        </tr>


                    </tbody>
                </table>
            </div>
            <!-- Card 5 -->
            <div class="bg-white p-4 rounded-lg shadow-lg border border-gray-300">
                <h2 class="text-xl font-bold mb-4 text-gray-800">REMAINING BUDGET</h2>

                <!-- Remaining Budget for Equipments/Assets -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Equipments/Assets</span>
                        <span class="text-gray-700">₱465.12</span> <!-- Remaining Budget -->
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: 93%;"></div> <!-- Used 93% -->
                    </div>
                </div>

                <!-- Remaining Budget for Maintenance/Repair -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Maintenance/Repair</span>
                        <span class="text-gray-700">₱581.40</span> <!-- Remaining Budget -->
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-red-500 h-2 rounded-full" style="width: 100%;"></div> <!-- Maximum width of 100% -->
                    </div>
                </div>

                <!-- Remaining Budget for Salaries -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Salaries</span>
                        <span class="text-gray-700">₱465.12</span> <!-- Remaining Budget -->
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: 98%;"></div> <!-- Used 98% -->
                    </div>
                </div>

                <!-- Remaining Budget for Bonuses -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Bonuses</span>
                        <span class="text-gray-700">₱465.12</span> <!-- Remaining Budget -->
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: 90%;"></div> <!-- Used 90% -->
                    </div>
                </div>

                <!-- Remaining Budget for Facility Cost -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Facility Cost</span>
                        <span class="text-gray-700">₱0</span> <!-- Remaining Budget -->
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-gray-300 h-2 rounded-full" style="width: 100%;"></div> <!-- No remaining budget -->
                    </div>
                </div>

                <!-- Remaining Budget for Training Cost -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Training Cost</span>
                        <span class="text-gray-700">₱116.28</span> <!-- Remaining Budget -->
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: 94%"></div> <!-- Used 94% -->
                    </div>
                </div>

                <!-- Remaining Budget for Wellness Program Cost -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Wellness Program Cost</span>
                        <span class="text-gray-700">₱116.28</span> <!-- Remaining Budget -->
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-red-500 h-2 rounded-full" style="width: 100%;"></div> <!-- Maximum width of 100% -->
                    </div>
                </div>

                <!-- Remaining Budget for Tax Payment -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Tax Payment</span>
                        <span class="text-gray-700">₱0</span> <!-- Remaining Budget -->
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-gray-300 h-2 rounded-full" style="width: 100%;"></div> <!-- No remaining budget -->
                    </div>
                </div>
            </div>


            <!-- Card 6 -->
            <div class="bg-white p-4 rounded-lg shadow-lg border border-gray-300">
                <h2 class="text-xl font-bold mb-4 text-gray-800">ESTIMATED BUDGET</h2>

                <!-- Forecast for Equipments -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Equipments</span>
                        <span class="text-gray-700">₱6,976.74</span> <!-- Budgeted amount -->
                    </div>
                    <div class="text-sm text-gray-600">
                        <span>Variance: </span>
                        <span class="text-green-500">+₱465.11</span> <!-- Predicted increase based on variance -->
                    </div>
                </div>

                <!-- Forecast for Maintenance -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Maintenance</span>
                        <span class="text-red-600">₱2,906.97</span> <!-- Budgeted amount -->
                    </div>
                    <div class="text-sm text-gray-600">
                        <span>Variance: </span>
                        <span class="text-red-500">-581.39</span> <!-- Predicted increase due to variance -->
                    </div>
                </div>

                <!-- Forecast for Salaries -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Salaries</span>
                        <span class="text-gray-700">₱23,255.81</span> <!-- Budgeted amount -->
                    </div>
                    <div class="text-sm text-gray-600">
                        <span>Variance: </span>
                        <span class="text-green-500">+₱465.11</span> <!-- Predicted savings based on variance -->
                    </div>
                </div>

                <!-- Forecast for Bonuses -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Bonuses</span>
                        <span class="text-gray-700">₱4,651.16</span> <!-- Budgeted amount -->
                    </div>
                    <div class="text-sm text-gray-600">
                        <span>Variance: </span>
                        <span class="text-green-500">+₱465.11</span> <!-- Predicted savings based on variance -->
                    </div>
                </div>

                <!-- Forecast for Facility Cost -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Facility Cost</span>
                        <span class="text-gray-700">₱3,488.37</span> <!-- Budgeted amount -->
                    </div>
                    <div class="text-sm text-gray-600">
                        <span>Variance: </span>
                        <span class="text-gray-600">₱0</span> <!-- No change forecasted -->
                    </div>
                </div>

                <!-- Forecast for Training -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Training</span>
                        <span class="text-gray-700">₱1,860.46</span> <!-- Budgeted amount -->
                    </div>
                    <div class="text-sm text-gray-600">
                        <span>Variance: </span>
                        <span class="text-green-500">+₱116.27</span> <!-- Slight increase forecasted -->
                    </div>
                </div>

                <!-- Forecast for Wellness Program -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Wellness Program</span>
                        <span class="text-red-600">₱813.95</span> <!-- Budgeted amount -->
                    </div>
                    <div class="text-sm text-gray-600">
                        <span>Variance: </span>
                        <span class="text-red-500">-₱116.27</span> <!-- Forecasted increase based on variance -->
                    </div>
                </div>

                <!-- Forecast for Tax -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-700">Tax Payment</span>
                        <span class="text-gray-700">₱2,790.69</span> <!-- Budgeted amount -->
                    </div>
                    <div class="text-sm text-gray-600">
                        <span>Variance: </span>
                        <span class="text-gray-600">₱0</span> <!-- No change forecasted -->
                    </div>
                </div>

            </div>


        </div>

    </div>

    <!-- Modal for Adding Employee -->
    </div>
</body>

</html>
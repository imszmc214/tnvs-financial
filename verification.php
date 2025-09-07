<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification</title>
    <link rel="icon" href="logo.png" type="img">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styling for the OTP inputs */
        .otp-box {
            width: 3rem;
            height: 3rem;
            text-align: center;
            font-size: 1.25rem;
            font-weight: bold;
            border-radius: 0.375rem;
            border: 2px solid #ddd;
        }

        .otp-box:focus {
            outline: none;
            border-color: #4A90E2;
        }
    </style>
</head>
<body class="bg-gray-100">

    <!-- Authentication Container -->
    <div class="flex justify-center items-center min-h-screen">
        <div class="w-full max-w-sm p-6 bg-white rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold text-center text-blue-600 mb-6">Verification</h2>

            <!-- Instructions -->
            <div class="mb-4 text-center text-sm text-gray-600">
                <p>Enter your pincode.</p>
            </div>

            <!-- OTP Code Input Form -->
            <form action="verify.php" method="POST">
                <div class="flex justify-between mb-6">
                    <!-- OTP Inputs -->
                    <input type="text" name="otp1" maxlength="1" class="otp-box" required autofocus>
                    <input type="text" name="otp2" maxlength="1" class="otp-box" required>
                    <input type="text" name="otp3" maxlength="1" class="otp-box" required>
                    <input type="text" name="otp4" maxlength="1" class="otp-box" required>
                    <input type="text" name="otp5" maxlength="1" class="otp-box" required>
                    <input type="text" name="otp6" maxlength="1" class="otp-box" required>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:bg-blue-700">
                    Verify Code
                </button>
            </form>
        </div>
    </div>

    <script>
        // Auto-focus next OTP input
        document.querySelectorAll('.otp-box').forEach((element, index, otpArray) => {
            element.addEventListener('input', function() {
                if (this.value.length == 1 && otpArray[index + 1]) {
                    otpArray[index + 1].focus();
                }
            });
        });
    </script>

</body>
</html>

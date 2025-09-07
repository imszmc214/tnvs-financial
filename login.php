<?php
session_start(); // Start session at the top
include 'session_manager.php'; // Include session manager

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $servername = 'localhost';
    $usernameDB = 'financial';
    $passwordDB = 'UbrdRDvrHRAyHiA]';
    $dbname = 'financial_db';
    
    $conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username = $_POST["username"];
    $password = $_POST["password"];

    $sql = "SELECT * FROM userss WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Check if the user is already logged in
            if (is_user_logged_in($username)) {
                echo '<script>alert("User is already logged in from another session!"); window.history.back();</script>';
            } else {
                // Set session variables upon successful login
                $_SESSION['users_username'] = $username;
                $_SESSION['logged_in'] = true;
                $_SESSION['user_pin'] = $user['pin']; // Store PIN in session
                $_SESSION['user_role'] = $user['role']; // Store role in session
                $_SESSION["user_id"] = $user["id"]; // Store user ID in session 

                // Store given name and surname for display
                $_SESSION['givenname'] = $user['gname'];
                $_SESSION['surname']   = $user['surname'];

                // Mark this user as logged in
                log_user_in($username, $conn);

                header("Location: verification.php"); // Redirect to dashboard.php
                exit();
            }
        } else {
            echo '<script>alert("Invalid username or password!"); window.history.back();</script>';
        }
    } else {
        echo '<script>alert("Invalid username or password!"); window.history.back();</script>';
    }

    echo "Username: $username<br>";
    echo "Password: $password<br>";

    if ($result->num_rows > 0) {
        echo "User found.<br>";
    } else {
        echo "No user found.<br>";
    }


    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);


    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ViaHale Financials</title>
  <link rel="icon" href="logo.png" type="image/png" />
  <script src="https://kit.fontawesome.com/yourkitid.js" crossorigin="anonymous"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Quicksand:wght@300;400;600&display=swap" rel="stylesheet">
     <style>
    .font-poppins {
      font-family: 'Poppins', sans-serif;
    }
    .font-quicksand {
      font-family: 'Quicksand', sans-serif;
    }
    .font-bricolage {
      font-family: 'Bricolage Grotesque', sans-serif;
    }
  </style>
</head>
<body class="min-h-screen bg-white flex items-center justify-center px-4 font-poppins">
<!-- Page wrapper -->
<div class="min-h-screen flex flex-col items-center justify-center px-4 foont-poppins">
  
  <!-- Header text OUTSIDE the purple box -->
  <div class="mb-6 text-center">
    <h1 class="text-4xl font-poppins text-purple-900">Welcome back, Admin!</h1>
    <p class="text-sm text-gray-600 mt-2">Please enter your credentials to access the dashboard.</p>
  </div>

  <!-- Purple login box -->
  <div class="w-[1000px] max-w-lg bg-purple-800 text-white rounded-xl shadow-lg shadow-gray-500 p-8 space-y-6" style="background: linear-gradient(135deg, #9A66FF, #6532C9, #4311A5);">
      <div class="text-left">
            <h2 class="text-2xl font-bold mb-2">LogIn</h2>
        </div>

    <form action="login.php" method="post" class="space-y-4 ">
  <!-- Username Group -->
  <div class="space-y-1">
    <label for="username" class=" space-y-1 block text-white font-medium">Username</label>
    <input type="text" id="username" name="username" placeholder="Username"
      class="w-full px-4 py-2 rounded-xl bg-purple-300 bg-opacity-30 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" />
  </div>

  <!-- Password Group -->
  <div class="space-y-1">
    <label for="password" class="block text-white font-medium">Password</label>
    <input type="password" id="password" name="password" placeholder="Password"
      class="w-full px-4 py-2 rounded-xl bg-purple-300 bg-opacity-30 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" />
  </div>

      <button type="submit" class="w-full py-2 bg-white text-purple-800 font-semibold rounded-xl hover:bg-purple-100 transition">Login</button>
    </form>

    <div class="flex justify-between text-sm text-yellow-200 space-x-2">
      <a href="#" class=" underline hover:text-white">Forgot password?</a>
     
      <a href="register.php" class=" underline hover:text-white">Sign up</a>
    </div>
  </div>
</div>

  <script>
    const togglePassword = document.getElementById("togglePassword");
    const password = document.getElementById("password");

    togglePassword.addEventListener("click", function () {
      const type = password.getAttribute("type") === "password" ? "text" : "password";
      password.setAttribute("type", type);
      this.classList.toggle("fa-eye");
      this.classList.toggle("fa-eye-slash");
    });
  </script>
</body>
</html>
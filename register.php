<?php
$registrationError = "";

$username = $email = $givenname = $initial = $surname = $address = $age = $contact = "";

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
    $email = $_POST["email"];
    $givenname = $_POST["givenname"];
    $initial = $_POST["initial"];
    $surname = $_POST["surname"];
    $address = $_POST["address"];
    $age = $_POST["age"];
    $contact = $_POST["contact"];
    $password = $_POST["password"];
    $cpassword = isset($_POST["cpassword"]) ? $_POST["cpassword"] : "";
    $pin = $_POST["pin"];
    $role = $_POST["role"];

    if ($password !== $cpassword) {
        $registrationError = "Passwords don't match!";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO userss (username, email, gname, minitial, surname, address, age, contact, password, pin, role) 
                VALUES ('$username', '$email', '$givenname', '$initial', '$surname', '$address', '$age', '$contact', '$hashedPassword', '$pin', '$role')";

        if ($conn->query($sql) === TRUE) {
            echo '<script>alert("Registration successful!"); window.location.href = "login.php";</script>';
            exit();
        } else {
            $registrationError = "Error: " . $conn->error;
        }
    }

    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register</title>
  <link rel="icon" href="logo.png" type="img">
  <script src="https://kit.fontawesome.com/yourkitid.js" crossorigin="anonymous">
 <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Quicksand:wght@300;400;600&display=swap" rel="stylesheet">
  
  </script>
  <script>
    function toggleVisibility(id, iconId) {
      const input = document.getElementById(id);
      const icon = document.getElementById(iconId);
      const type = input.type === "password" ? "text" : "password";
      input.type = type;
      icon.classList.toggle("fa-eye");
      icon.classList.toggle("fa-eye-slash");
    }
  </script>
    <script>
    function generatePassword() {
      const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
      let password = "";
      for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      document.getElementById("password").value = password;
      document.getElementById("cpassword").value = password;
    }
  </script>

  <script src="https://cdn.tailwindcss.com"></script>
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
<body class="min-h-screen  flex items-center justify-center px-4 font-poppins">
    <div class="w-full max-w-6xl flex flex-col md:flex-row gap-8 items-stretch font-poppins">
    
    <!-- Left Side: About Us -->
    <div class="flex-1 flex flex-col justify-center px-6">
      <h1 class="text-5xl font-bold text-purple-800 mb-6">About Us</h1>
      <p class="text-sm text-black">ViaHale stands for Vehicle Integrated Access for High Quality Assistance,<br>Logistics, and Experience a proudly Filipino-built transport service <br>dedicated to making every journey safe, smooth, and accessible, for everyone.</p>
    </div>

    <!-- Right Side: Registration Form -->
    <div class="w-full max-w-md overflow-y-auto max-h-[90vh] p-6 rounded-xl shadow-lg" style="background: linear-gradient(135deg, #9A66FF, #6532C9, #4311A5);">
    <h2 class="text-2xl font-bold text-white mb-4">Register</h2>
    <p class="text-purple-100 mb-6">Please fill out the form to create your account.</p>
    <form action="register.php" method="POST" class="space-y-4 text-left">
      <!-- Username -->
      <div>
        <label for="username" class="text-white font-semibold">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter username"
          class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
      </div>

      <!-- Email -->
      <div>
        <label for="email" class="text-white font-semibold">Email</label>
        <input type="text" id="email" name="email" placeholder="Enter email address"
          class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
      </div>

      <!-- Given Name & Surname -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="givenname" class="text-white font-semibold">Given Name</label>
          <input type="text" id="givenname" name="givenname" placeholder="Enter given name"
            class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        </div>
        <div>
          <label for="surname" class="text-white font-semibold">Surname</label>
          <input type="text" id="surname" name="surname" placeholder="Enter surname"
            class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        </div>
      </div>

      <!-- Address -->
      <div>
        <label for="address" class="text-white font-semibold">Address</label>
        <input type="text" id="address" name="address" placeholder="Enter address"
          class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
      </div>

      <!-- Age & Contact -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="age" class="text-white font-semibold">Age</label>
          <input type="number" id="age" name="age" placeholder="Enter your age"
            class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        </div>
        <div>
          <label for="contact" class="text-white font-semibold">Contact Number</label>
          <input type="text" id="contact" name="contact" placeholder="Enter contact number"
            class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        </div>
      </div>

      <!-- PIN -->
      <div>
        <label for="pin" class="text-white font-semibold">6-digit PIN</label>
        <input type="text" id="pin" name="pin" maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit PIN"
          class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
      </div>

      <!-- Role -->
      <div>
        <label for="role" class="text-white font-semibold">Role</label>
        <select id="role" name="role"
          class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white focus:outline-none focus:ring-2 focus:ring-purple-400" required>
          <option style="color: #7e22ce;" value="admin">Admin</option>
          <option style="color: #7e22ce;" value="budget manager">Budget Manager</option>
          <option style="color: #7e22ce;" value="disburse officer">Disburse Officer</option>
          <option style="color: #7e22ce;" value="collector">Collector</option>
          <option style="color: #7e22ce;"  value="auditor">Auditor</option>
        </select>
      </div>

      <!-- Generate Password -->
      <div>
        <button type="button" onclick="generatePassword()" class="w-full py-2 bg-white text-purple-800 font-semibold rounded-lg hover:bg-purple-100 transition">
          Generate Password
        </button>
      </div>

      <!-- Password Fields -->
      <div class="relative">
        <label for="password" class="text-white font-semibold">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter Password"
          class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        <i id="eyeicon" class="fa-solid fa-eye-slash absolute right-4 top-10 transform -translate-y-1/2 text-purple-300 cursor-pointer hover:text-white"
           onclick="toggleVisibility('password','eyeicon')"></i>
      </div>

      <div class="relative">
        <label for="cpassword" class="text-white font-semibold">Confirm Password</label>
        <input type="password" id="cpassword" name="cpassword" placeholder="Confirm Password"
          class="w-full mt-1 px-4 py-2 rounded-lg bg-purple-200 bg-opacity-20 text-white placeholder-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-400" required />
        <i id="cpass_eyeicon" class="fa-solid fa-eye-slash absolute right-4 top-10 transform -translate-y-1/2 text-purple-300 cursor-pointer hover:text-white"
           onclick="toggleVisibility('cpassword','cpass_eyeicon')"></i>
      </div>

      <!-- Buttons -->
      <div class="flex gap-4 pt-4">
        <button type="submit" name="register" class="w-full py-2 bg-white text-purple-800 font-semibold rounded-lg hover:bg-purple-100 transition">
          Register
        </button>
        <a href="login.php" class="w-full">
          <button type="button" class="w-full py-2 bg-red-500 text-white font-semibold rounded-lg hover:bg-red-600 transition">
            Cancel
          </button>
        </a>
      </div>
    </form>
  </div>

  
    <script>
        let eyeicon = document.getElementById("eyeicon");
        let cpassEyeicon = document.getElementById("cpass_eyeicon");
        let password = document.getElementById("password");
        let cpassword = document.getElementById("cpassword");

        eyeicon.onclick = function() {
            if (password.type === "password") {
                password.type = "text";
                eyeicon.src = "open-eye2.jpg";
            } else {
                password.type = "password";
                eyeicon.src = "close-eye2.jpg";
            }
        };

        cpassEyeicon.onclick = function() {
            if (cpassword.type === "password") {
                cpassword.type = "text";
                cpassEyeicon.src = "open-eye2.jpg";
            } else {
                cpassword.type = "password";
                cpassEyeicon.src = "close-eye2.jpg";
            }
        };

        document.getElementById('generatePassword').addEventListener('click', function() {
            let generatedPassword = Math.random().toString(36).slice(-9);
            const randomCapitalLetter = String.fromCharCode(65 + Math.floor(Math.random() * 26));
            generatedPassword = randomCapitalLetter + generatedPassword;

            password.value = generatedPassword;
            cpassword.value = generatedPassword;

            password.setAttribute('type', 'text');
            cpassword.setAttribute('type', 'text');

            setTimeout(() => {
                password.setAttribute('type', 'password');
                cpassword.setAttribute('type', 'password');
            }, 1);
        });
    </script>
</body>

</html>
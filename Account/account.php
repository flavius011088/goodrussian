<?php
/**
 * Account Controller
 * Handles user authentication: Login, Register, ForgotPassword, Logout
 * GoodRussian Application
 */

require_once __DIR__ . '/../config/db.php';
session_start();

// Determine action from URL path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$action = pathinfo($path, PATHINFO_FILENAME);

// Route to appropriate handler
switch ($action) {
    case 'Login':
        handleLogin();
        break;
    case 'Register':
        handleRegister();
        break;
    case 'ForgotPassword':
        handleForgotPassword();
        break;
    case 'Logout':
        handleLogout();
        break;
    default:
        http_response_code(404);
        echo 'Action not found';
        exit;
}

/**
 * Handle user login
 * POST only, validates email and password
 */
function handleLogin()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/Account/Login.html');
    }

    $email = trim($_POST['Email'] ?? '');
    $password = $_POST['Password'] ?? '';
    $rememberMe = isset($_POST['RememberMe']);

    // Validate input
    if (empty($email) || empty($password)) {
        redirect('/Account/Login.html?error=empty');
    }

    // Find user by email
    $user = findUserByEmail($email);
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        redirect('/Account/Login.html?error=invalid');
    }

    // Check if user is active
    if (!$user['is_active']) {
        redirect('/Account/Login.html?error=inactive');
    }

    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);

    // Update last login timestamp
    updateLastLogin($user['id']);

    // Redirect to profile
    redirect('/Profile/EntranceTesting');
}

/**
 * Handle user registration
 * POST only, validates all profile fields
 */
function handleRegister()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/Account/Register.html');
    }

    // Collect user input
    $email = trim($_POST['Email'] ?? '');
    $password = $_POST['Password'] ?? '';
    $passwordConfirm = $_POST['PasswordConfirm'] ?? '';
    $firstName = trim($_POST['FirstName'] ?? '');
    $lastName = trim($_POST['LastName'] ?? '');
    $languageLevel = $_POST['LanguageLevel'] ?? '';
    $dateOfBirth = $_POST['DateOfBirth'] ?? null;
    $country = trim($_POST['Country'] ?? '');
    $profession = trim($_POST['Profession'] ?? '');

    // Validate required fields
    if (empty($email) || empty($password) || empty($passwordConfirm)) {
        redirect('/Account/Register.html?error=required');
    }

    // Validate password match
    if ($password !== $passwordConfirm) {
        redirect('/Account/Register.html?error=mismatch');
    }

    // Validate password strength (minimum 6 characters)
    if (strlen($password) < 6) {
        redirect('/Account/Register.html?error=weak');
    }

    // Check if email already exists
    if (findUserByEmail($email)) {
        redirect('/Account/Register.html?error=exists');
    }

    // Create new user
    try {
        $userId = createUser([
            'email' => $email,
            'password' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'language_level' => $languageLevel,
            'date_of_birth' => $dateOfBirth ?: null,
            'country' => $country,
            'profession' => $profession,
        ]);

        // Create session
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);

        // Redirect to profile
        redirect('/Profile/EntranceTesting');
    } catch (Exception $e) {
        error_log('Registration error: ' . $e->getMessage());
        redirect('/Account/Register.html?error=system');
    }
}

/**
 * Handle password reset request
 * POST only, sends reset link via email (placeholder)
 */
function handleForgotPassword()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/Account/ForgotPassword.html');
    }

    $email = trim($_POST['Email'] ?? '');

    if (empty($email)) {
        redirect('/Account/ForgotPassword.html?error=empty');
    }

    $user = findUserByEmail($email);
    
    if ($user && $user['is_active']) {
        $token = bin2hex(random_bytes(24));
        saveResetToken($user['id'], $token);
        
        // TODO: Send reset email
        // sendResetEmail($email, $token);
        
        error_log("Password reset requested for: $email, token: $token");
    }

    // Always show success to prevent email enumeration
    redirect('/Account/ForgotPassword.html?sent=1');
}

/**
 * Handle user logout
 * Destroys session and redirects to login
 */
function handleLogout()
{
    session_destroy();
    redirect('/Account/Login.html?logged_out=1');
}

/**
 * Find user by email
 * 
 * @param string $email
 * @return array|false User data or false if not found
 */
function findUserByEmail($email)
{
    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create new user
 * 
 * @param array $data User data
 * @return string|false Last inserted ID or false on error
 */
function createUser($data)
{
    try {
        $db = db_connect();
        $stmt = $db->prepare('
            INSERT INTO users
                (email, password_hash, first_name, last_name, language_level, date_of_birth, country, profession)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['first_name'],
            $data['last_name'],
            $data['language_level'] ?: null,
            $data['date_of_birth'],
            $data['country'],
            $data['profession'],
        ]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log('User creation error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Save password reset token
 * 
 * @param int $userId
 * @param string $token
 */
function saveResetToken($userId, $token)
{
    try {
        $db = db_connect();
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
        $stmt = $db->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
        $stmt->execute([$token, $expires, $userId]);
    } catch (PDOException $e) {
        error_log('Reset token save error: ' . $e->getMessage());
    }
}

/**
 * Update last login timestamp
 * 
 * @param int $userId
 */
function updateLastLogin($userId)
{
    try {
        $db = db_connect();
        $stmt = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log('Last login update error: ' . $e->getMessage());
    }
}

/**
 * Redirect to URL
 * 
 * @param string $url
 */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

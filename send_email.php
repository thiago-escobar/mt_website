<?php
// Configuration
$admin_email = "thiagoescobar@matosteixeira.com.br"; // Change this to your email
$max_file_size = 5 * 1024 * 1024; // 5MB in bytes
$upload_dir = "uploads/";
$log_file = "logs/email_log.txt";

// Session configuration for CSRF protection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create logs directory if it doesn't exist
if (!is_dir("logs")) {
    mkdir("logs", 0755, true);
}

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Function to validate email - prevent header injection
function validate_email($email) {
    // First check if it's a valid email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Prevent email header injection - check for suspicious characters
    if (preg_match('/[\r\n]/', $email)) {
        return false;
    }
    
    return true;
}

// Function to sanitize email headers
function sanitize_email_header($header) {
    // Remove any line breaks or tabs that could be used for header injection
    $header = preg_replace('/[\r\n\t]/', '', $header);
    return $header;
}

// Function to check rate limiting (simple IP-based)
function check_rate_limit() {
    $ip = sanitize_input($_SERVER['REMOTE_ADDR']);
    $rate_limit_file = "logs/rate_limit_" . md5($ip) . ".txt";
    
    if (file_exists($rate_limit_file)) {
        $last_submission_time = (int)file_get_contents($rate_limit_file);
        $time_since_last = time() - $last_submission_time;
        
        // Allow only one submission per 60 seconds per IP
        if ($time_since_last < 60) {
            return false;
        }
    }
    
    // Update last submission time
    file_put_contents($rate_limit_file, time());
    
    // Clean up old rate limit files (older than 1 hour)
    $rate_limit_files = glob("logs/rate_limit_*.txt");
    foreach ($rate_limit_files as $file) {
        if (time() - filemtime($file) > 3600) {
            unlink($file);
        }
    }
    
    return true;
}

// Function to validate CSRF token
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to log email activity
function log_email($form_type, $email, $success, $error_msg = '') {
    global $log_file;
    $log_message = date('Y-m-d H:i:s') . " | ";
    $log_message .= "Type: $form_type | ";
    $log_message .= "Email: $email | ";
    $log_message .= "IP: " . sanitize_input($_SERVER['REMOTE_ADDR']) . " | ";
    $log_message .= "Status: " . ($success ? "SUCCESS" : "FAILED") . " | ";
    
    if (!empty($error_msg)) {
        $log_message .= "Error: $error_msg | ";
    }
    
    $log_message .= "\n";
    
    error_log($log_message, 3, $log_file);
}

// Store CSRF token from client in session for validation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["csrf_token"])) {
    $_SESSION['csrf_token'] = $_POST["csrf_token"];
}

// Handle Contato form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["form_type"]) && $_POST["form_type"] == "contato") {
    // Check rate limiting
    if (!check_rate_limit()) {
        http_response_code(429);
        echo json_encode(["success" => false, "message" => "Muitas requisições. Tente novamente em alguns segundos."]);
        exit;
    }
    
    // Validate CSRF token
    if (!isset($_POST["csrf_token"]) || !validate_csrf_token($_POST["csrf_token"])) {
        log_email('contato', $_POST["email"] ?? 'unknown', false, 'Invalid CSRF token');
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Requisição inválida"]);
        exit;
    }
    
    // Check honeypot
    if (!empty($_POST["website"] ?? '')) {
        // Honeypot field filled, likely a bot
        log_email('contato', $_POST["email"] ?? 'unknown', false, 'Honeypot triggered');
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Erro ao processar formulário"]);
        exit;
    }
    
    $nome = sanitize_input($_POST["nome"] ?? "");
    $email = sanitize_input($_POST["email"] ?? "");
    $mensagem = sanitize_input($_POST["mensagem"] ?? "");
    
    // Validation
    $errors = [];
    
    if (empty($nome)) {
        $errors[] = "Nome é obrigatório";
    }
    
    if (empty($email) || !validate_email($email)) {
        $errors[] = "Email válido é obrigatório";
    }
    
    if (empty($mensagem)) {
        $errors[] = "Mensagem é obrigatória";
    }
    
    // Check message length to prevent abuse
    if (strlen($mensagem) > 5000) {
        $errors[] = "Mensagem muito longa. Máximo de 5000 caracteres";
    }
    
    if (!empty($errors)) {
        log_email('contato', $email, false, 'Validation failed: ' . implode(', ', $errors));
        http_response_code(400);
        echo json_encode(["success" => false, "errors" => $errors]);
        exit;
    }
    
    // Prepare email with proper header sanitization
    $assunto = "Novo Contato - " . html_entity_decode($nome, ENT_QUOTES, 'UTF-8');
    $corpo_email = "Nome: " . html_entity_decode($nome, ENT_QUOTES, 'UTF-8') . "\n";
    $corpo_email .= "Email: " . $email . "\n";
    $corpo_email .= "Data: " . date('d/m/Y H:i:s') . "\n";
    $corpo_email .= "IP: " . sanitize_input($_SERVER['REMOTE_ADDR']) . "\n";
    $corpo_email .= "Mensagem:\n" . html_entity_decode($mensagem, ENT_QUOTES, 'UTF-8');
    
    // Sanitize headers to prevent header injection
    $headers = "From: " . sanitize_email_header($email) . "\r\n";
    $headers .= "Reply-To: " . sanitize_email_header($email) . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    // Send email
    if (@mail($admin_email, $assunto, $corpo_email, $headers)) {
        // Send confirmation email to user
        $confirmacao_assunto = "Recebemos seu contato";
        $confirmacao_corpo = "Olá " . $nome . ",\n\n";
        $confirmacao_corpo .= "Obrigado por entrar em contato conosco.\n";
        $confirmacao_corpo .= "Sua mensagem foi recebida e responderemos assim que possível.\n\n";
        $confirmacao_corpo .= "Atenciosamente,\nEquipe Matos Teixeira Engenharia e Serviços";
        
        $confirmacao_headers = "From: " . sanitize_email_header($admin_email) . "\r\n";
        $confirmacao_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $confirmacao_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        @mail($email, $confirmacao_assunto, $confirmacao_corpo, $confirmacao_headers);
        
        log_email('contato', $email, true);
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Email enviado com sucesso!"]);
    } else {
        log_email('contato', $email, false, 'Mail function failed');
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erro ao enviar email. Tente novamente mais tarde."]);
    }
    exit;
}

// Handle Trabalhe Conosco form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["form_type"]) && $_POST["form_type"] == "trabalhe") {
    // Check rate limiting
    if (!check_rate_limit()) {
        http_response_code(429);
        echo json_encode(["success" => false, "message" => "Muitas requisições. Tente novamente em alguns segundos."]);
        exit;
    }
    
    // Validate CSRF token
    if (!isset($_POST["csrf_token"]) || !validate_csrf_token($_POST["csrf_token"])) {
        log_email('trabalhe', $_POST["email"] ?? 'unknown', false, 'Invalid CSRF token');
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Requisição inválida"]);
        exit;
    }
    
    // Check honeypot
    if (!empty($_POST["website"] ?? '')) {
        // Honeypot field filled, likely a bot
        log_email('trabalhe', $_POST["email"] ?? 'unknown', false, 'Honeypot triggered');
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Erro ao processar formulário"]);
        exit;
    }
    
    $nome = sanitize_input($_POST["nome"] ?? "");
    $email = sanitize_input($_POST["email"] ?? "");
    $telefone = sanitize_input($_POST["telefone"] ?? "");
    $cargo = sanitize_input($_POST["cargo"] ?? "");
    
    // Validation
    $errors = [];
    
    if (empty($nome)) {
        $errors[] = "Nome é obrigatório";
    }
    
    if (empty($email) || !validate_email($email)) {
        $errors[] = "Email válido é obrigatório";
    }
    
    if (empty($telefone)) {
        $errors[] = "Telefone é obrigatório";
    }
    
    // Validate phone format (basic)
    if (!preg_match('/^[0-9\s\(\)\-\+]+$/', $telefone) || strlen(preg_replace('/\D/', '', $telefone)) < 10) {
        $errors[] = "Telefone inválido";
    }
    
    if (empty($cargo)) {
        $errors[] = "Cargo é obrigatório";
    }
    
    // Validate cargo is from allowed list
    $allowed_cargos = ['engenheiro', 'tecnico', 'operador', 'outro'];
    if (!in_array($cargo, $allowed_cargos)) {
        $errors[] = "Cargo inválido";
    }
    
    // Check if file was uploaded
    if (!isset($_FILES["curriculo"]) || $_FILES["curriculo"]["error"] != 0) {
        $errors[] = "Currículo é obrigatório";
    } else {
        // Validate file
        $allowed_types = ["application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"];
        $file_type = mime_content_type($_FILES["curriculo"]["tmp_name"]);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Tipo de arquivo não permitido. Use PDF ou DOC/DOCX";
        }
        
        if ($_FILES["curriculo"]["size"] > $max_file_size) {
            $errors[] = "Arquivo muito grande. Máximo de 5MB";
        }
        
        // Validate file name
        $file_name = $_FILES["curriculo"]["name"];
        if (preg_match('/[^a-zA-Z0-9._\-]/', $file_name)) {
            $errors[] = "Nome do arquivo contém caracteres inválidos";
        }
    }
    
    if (!empty($errors)) {
        log_email('trabalhe', $email, false, 'Validation failed: ' . implode(', ', $errors));
        http_response_code(400);
        echo json_encode(["success" => false, "errors" => $errors]);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Process file upload with secure naming
    $file_ext = pathinfo($_FILES["curriculo"]["name"], PATHINFO_EXTENSION);
    $file_name = "curriculo_" . time() . "_" . bin2hex(random_bytes(8)) . "." . strtolower($file_ext);
    $file_path = $upload_dir . $file_name;
    
    if (!move_uploaded_file($_FILES["curriculo"]["tmp_name"], $file_path)) {
        log_email('trabalhe', $email, false, 'File upload failed');
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erro ao fazer upload do arquivo"]);
        exit;
    }
    
    // Make file readable but not executable
    chmod($file_path, 0644);
    
    // Prepare email
    $assunto = "Nova Candidatura - " . html_entity_decode($nome, ENT_QUOTES, 'UTF-8') . " para " . html_entity_decode($cargo, ENT_QUOTES, 'UTF-8');
    $corpo_email = "Nome: " . html_entity_decode($nome, ENT_QUOTES, 'UTF-8') . "\n";
    $corpo_email .= "Email: " . $email . "\n";
    $corpo_email .= "Telefone: " . $telefone . "\n";
    $corpo_email .= "Cargo: " . html_entity_decode($cargo, ENT_QUOTES, 'UTF-8') . "\n";
    $corpo_email .= "Data: " . date('d/m/Y H:i:s') . "\n";
    $corpo_email .= "IP: " . sanitize_input($_SERVER['REMOTE_ADDR']) . "\n";
    $corpo_email .= "Arquivo: " . $_FILES["curriculo"]["name"] . "\n";
    
    // Sanitize headers
    $headers = "From: " . sanitize_email_header($email) . "\r\n";
    $headers .= "Reply-To: " . sanitize_email_header($email) . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    // Send email
    if (@mail($admin_email, $assunto, $corpo_email, $headers)) {
        // Send confirmation email to user
        $confirmacao_assunto = "Recebemos sua candidatura";
        $confirmacao_corpo = "Olá " . $nome . ",\n\n";
        $confirmacao_corpo .= "Obrigado por se candidatar para a posição de " . $cargo . ".\n";
        $confirmacao_corpo .= "Sua candidatura foi recebida e analisaremos seu currículo em breve.\n\n";
        $confirmacao_corpo .= "Atenciosamente,\nEquipe Matos Teixeira Engenharia e Serviços";
        
        $confirmacao_headers = "From: " . sanitize_email_header($admin_email) . "\r\n";
        $confirmacao_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $confirmacao_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        @mail($email, $confirmacao_assunto, $confirmacao_corpo, $confirmacao_headers);
        
        log_email('trabalhe', $email, true);
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Candidatura enviada com sucesso!"]);
    } else {
        log_email('trabalhe', $email, false, 'Mail function failed');
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erro ao enviar candidatura. Tente novamente mais tarde."]);
    }
    exit;
}

// If not a POST request or invalid form_type
http_response_code(400);
echo json_encode(["success" => false, "message" => "Requisição inválida"]);
?>

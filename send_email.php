<?php
// Configuration
$admin_email = "seu_email@seudominio.com"; // Change this to your email
$max_file_size = 5 * 1024 * 1024; // 5MB in bytes

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Handle Contato form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["form_type"]) && $_POST["form_type"] == "contato") {
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
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["success" => false, "errors" => $errors]);
        exit;
    }
    
    // Prepare email
    $assunto = "Novo Contato - " . $nome;
    $corpo_email = "Nome: " . $nome . "\n";
    $corpo_email .= "Email: " . $email . "\n";
    $corpo_email .= "Mensagem:\n" . $mensagem;
    
    $headers = "From: " . $email . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Send email
    if (mail($admin_email, $assunto, $corpo_email, $headers)) {
        // Send confirmation email to user
        $confirmacao_assunto = "Recebemos seu contato";
        $confirmacao_corpo = "Olá " . $nome . ",\n\n";
        $confirmacao_corpo .= "Obrigado por entrar em contato conosco.\n";
        $confirmacao_corpo .= "Sua mensagem foi recebida e responderemos assim que possível.\n\n";
        $confirmacao_corpo .= "Atenciosamente,\nEquipe MT Engenharia";
        
        $confirmacao_headers = "From: " . $admin_email . "\r\n";
        $confirmacao_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        mail($email, $confirmacao_assunto, $confirmacao_corpo, $confirmacao_headers);
        
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Email enviado com sucesso!"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erro ao enviar email. Tente novamente mais tarde."]);
    }
    exit;
}

// Handle Trabalhe Conosco form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["form_type"]) && $_POST["form_type"] == "trabalhe") {
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
    
    if (empty($cargo)) {
        $errors[] = "Cargo é obrigatório";
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
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["success" => false, "errors" => $errors]);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Process file upload
    $file_ext = pathinfo($_FILES["curriculo"]["name"], PATHINFO_EXTENSION);
    $file_name = "curriculo_" . time() . "_" . $nome . "." . $file_ext;
    $file_path = $upload_dir . $file_name;
    
    if (!move_uploaded_file($_FILES["curriculo"]["tmp_name"], $file_path)) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erro ao fazer upload do arquivo"]);
        exit;
    }
    
    // Prepare email
    $assunto = "Nova Candidatura - " . $nome . " para " . $cargo;
    $corpo_email = "Nome: " . $nome . "\n";
    $corpo_email .= "Email: " . $email . "\n";
    $corpo_email .= "Telefone: " . $telefone . "\n";
    $corpo_email .= "Cargo: " . $cargo . "\n";
    $corpo_email .= "Arquivo: " . $_FILES["curriculo"]["name"] . "\n";
    
    $headers = "From: " . $email . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Send email
    if (mail($admin_email, $assunto, $corpo_email, $headers)) {
        // Send confirmation email to user
        $confirmacao_assunto = "Recebemos sua candidatura";
        $confirmacao_corpo = "Olá " . $nome . ",\n\n";
        $confirmacao_corpo .= "Obrigado por se candidatar para a posição de " . $cargo . ".\n";
        $confirmacao_corpo .= "Sua candidatura foi recebida e analisaremos seu currículo em breve.\n\n";
        $confirmacao_corpo .= "Atenciosamente,\nEquipe MT Engenharia";
        
        $confirmacao_headers = "From: " . $admin_email . "\r\n";
        $confirmacao_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        mail($email, $confirmacao_assunto, $confirmacao_corpo, $confirmacao_headers);
        
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Candidatura enviada com sucesso!"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erro ao enviar candidatura. Tente novamente mais tarde."]);
    }
    exit;
}

// If not a POST request or invalid form_type
http_response_code(400);
echo json_encode(["success" => false, "message" => "Requisição inválida"]);
?>

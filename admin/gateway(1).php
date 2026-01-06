<?php
include '../includes/session.php';
include '../conexao.php';
include '../includes/notiflix.php';

$usuarioId = $_SESSION['usuario_id'];
$admin = ($stmt = $pdo->prepare("SELECT admin FROM usuarios WHERE id = ?"))->execute([$usuarioId]) ? $stmt->fetchColumn() : null;

if ($admin != 1) {
    $_SESSION['message'] = ['type' => 'warning', 'text' => 'Você não é um administrador!'];
    header("Location: /");
    exit;
}

// Processar formulário de credenciais SilverPay
if (isset($_POST['salvar_silverpay'])) {
    $client_id = $_POST['client_id'];
    $client_secret = $_POST['client_secret'];
    $urlnoty = $_POST['urlnoty'];

    // Supondo que você tenha uma tabela 'silverpay' no seu banco de dados
    $stmt = $pdo->prepare("UPDATE silverpay SET client_id = ?, client_secret = ?, urlnoty = ?");
    if ($stmt->execute([$client_id, $client_secret, $urlnoty])) {
        $_SESSION['success'] = 'Credenciais SilverPay alteradas com sucesso!';
    } else {
        $_SESSION['failure'] = 'Erro ao alterar as credenciais da SilverPay!';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar dados
$nome = ($stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?"))->execute([$usuarioId]) ? $stmt->fetchColumn() : null;
$nome = $nome ? explode(' ', $nome)[0] : null;

// Buscar dados da SilverPay
$stmt = $pdo->query("SELECT client_id, client_secret, urlnoty FROM silverpay LIMIT 1");
$silverpay = $stmt->fetch(PDO::FETCH_ASSOC);

// Garantir que temos valores padrão caso a consulta falhe
if (!$silverpay) {
    $silverpay = [
        'client_id' => '',
        'client_secret' => '',
        'urlnoty' => 'https://seusite.com/silverpay_webhook.php'
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nomeSite ?? 'Admin'; ?> - Configuração de Gateway</title>
    <?php
    if (!isset($faviconSite)) {
        try {
            $stmt = $pdo->prepare("SELECT favicon FROM config WHERE id = 1 LIMIT 1");
            $stmt->execute();
            $config_favicon = $stmt->fetch(PDO::FETCH_ASSOC);
            $faviconSite = $config_favicon['favicon'] ?? null;
            if (!isset($nomeSite)) {
                $stmt = $pdo->prepare("SELECT nome_site FROM config WHERE id = 1 LIMIT 1");
                $stmt->execute();
                $config_nome = $stmt->fetch(PDO::FETCH_ASSOC);
                $nomeSite = $config_nome['nome_site'] ?? 'Raspadinha';
            }
        } catch (PDOException $e) {
            $faviconSite = null;
            $nomeSite = $nomeSite ?? 'Raspadinha';
        }
    }
    ?>
    <?php if ($faviconSite && file_exists($_SERVER['DOCUMENT_ROOT'] . $faviconSite)): ?>
        <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($faviconSite) ?>"/>
        <link rel="shortcut icon" href="<?= htmlspecialchars($faviconSite) ?>"/>
        <link rel="apple-touch-icon" href="<?= htmlspecialchars($faviconSite) ?>"/>
    <?php else: ?>
        <link rel="icon" href="data:image/svg+xml,<?= urlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#22c55e"/><text x="50" y="50" text-anchor="middle" dominant-baseline="middle" fill="white" font-family="Arial" font-size="40" font-weight="bold">' . strtoupper(substr($nomeSite, 0, 1)) . '</text></svg>') ?>"/>
    <?php endif; ?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #000000;
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 320px;
            height: 100vh;
            background: linear-gradient(145deg, #0a0a0a 0%, #141414 25%, #1a1a1a 50%, #0f0f0f 100%);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(34, 197, 94, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: 
                0 0 50px rgba(34, 197, 94, 0.1),
                inset 1px 0 0 rgba(255, 255, 255, 0.05);
        }
        
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(34, 197, 94, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(59, 130, 246, 0.05) 0%, transparent 50%);
            opacity: 0.8;
            pointer-events: none;
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar-header {
            position: relative;
            padding: 2.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, transparent 100%);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            position: relative;
            z-index: 2;
        }
        
        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #ffffff;
            box-shadow: 
                0 8px 20px rgba(34, 197, 94, 0.3),
                0 4px 8px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .logo-icon::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, #22c55e, #16a34a, #22c55e);
            border-radius: 18px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .logo:hover .logo-icon::after {
            opacity: 1;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .logo-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
        }
        
        .nav-menu {
            padding: 2rem 0;
            position: relative;
        }
        
        .nav-section {
            margin-bottom: 2rem;
        }
        
        .nav-section-title {
            padding: 0 2rem 0.75rem 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 2rem;
            color: #a1a1aa;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            margin: 0.25rem 1rem;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 0 4px 4px 0;
            transform: scaleY(0);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .nav-item:hover::before,
        .nav-item.active::before {
            transform: scaleY(1);
        }
        
        .nav-item:hover,
        .nav-item.active {
            color: #ffffff;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(34, 197, 94, 0.05) 100%);
            border: 1px solid rgba(34, 197, 94, 0.2);
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.1);
        }
        
        .nav-icon {
            width: 24px;
            height: 24px;
            margin-right: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            position: relative;
        }
        
        .nav-text {
            font-size: 0.95rem;
            flex: 1;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 320px;
            min-height: 100vh;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: 
                radial-gradient(circle at 10% 20%, rgba(34, 197, 94, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(16, 185, 129, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(59, 130, 246, 0.01) 0%, transparent 50%);
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        .header {
            position: sticky;
            top: 0;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem 2.5rem;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .menu-toggle {
            display: none;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05));
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #22c55e;
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .menu-toggle:hover {
            background: rgba(34, 197, 94, 0.2);
            transform: scale(1.05);
        }
        
        .page-content {
            padding: 2.5rem;
        }
        
        .welcome-section {
            margin-bottom: 3rem;
        }
        
        .welcome-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #ffffff 0%, #fff 50%, #fff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        
        .welcome-subtitle {
            font-size: 1.25rem;
            color: #6b7280;
            font-weight: 400;
            margin-bottom: 2rem;
        }
        
        /* Form Container */
        .form-container {
            background: linear-gradient(135deg, rgba(20, 20, 20, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 2.5rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            height: fit-content;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .form-container:hover::before {
            opacity: 1;
        }
        
        .form-container:hover {
            transform: translateY(-4px);
            border-color: rgba(34, 197, 94, 0.2);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
        }
        
        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-title i {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(34, 197, 94, 0.1) 100%);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #22c55e;
            font-size: 1rem;
        }

        /* SilverPay Specific Warning */
        .silverpay-warning {
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.15) 0%, rgba(168, 85, 247, 0.05) 100%);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .silverpay-warning i {
            color: #a855f7;
            font-size: 1.25rem;
            margin-top: 0.2rem;
        }
        
        .silverpay-warning-content {
            color: #a855f7;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            color: #e5e7eb;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-label i {
            color: #22c55e;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            color: white;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .form-input.with-toggle {
            padding-right: 3.5rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: rgba(34, 197, 94, 0.5);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
            background: rgba(0, 0, 0, 0.6);
        }
        
        .form-input::placeholder {
            color: #6b7280;
        }
        
        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 68%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
            transition: all 0.3s ease;
            z-index: 10;
            border-radius: 6px;
        }
        
        .password-toggle:hover {
            color: #22c55e;
            background: rgba(34, 197, 94, 0.1);
        }
        
        .password-toggle i {
            font-size: 1rem;
        }
        
        /* Submit Button */
        .submit-button {
            width: 100%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            padding: 1.25rem 2rem;
            border-radius: 16px;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 2rem;
            box-shadow: 0 8px 25px rgba(34, 197, 94, 0.3);
        }
        
        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(34, 197, 94, 0.4);
        }
        
        .submit-button:active {
            transform: translateY(0);
        }
        
        /* Mobile Styles */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                width: 300px;
                z-index: 1001;
            }
            
            .sidebar:not(.hidden) {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }
            
            .page-content {
                padding: 1.5rem;
            }
            
            .welcome-title {
                font-size: 2.25rem;
            }
            
            .form-container {
                padding: 2rem;
            }
            
            .sidebar {
                width: 280px;
            }
        }
        
        @media (max-width: 480px) {
            .welcome-title {
                font-size: 1.875rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .sidebar {
                width: 260px;
            }
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(4px);
        }
        
        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.8/dist/notiflix-aio-3.2.8.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/notiflix@3.2.8/src/notiflix.min.css" rel="stylesheet">
    <?php if (isset($_SESSION['success'])): ?>
        <script>
            Notiflix.Notify.success('<?= $_SESSION['success'] ?>');
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php elseif (isset($_SESSION['failure'])): ?>
        <script>
            Notiflix.Notify.failure('<?= $_SESSION['failure'] ?>');
        </script>
        <?php unset($_SESSION['failure']); ?>
    <?php endif; ?>

    <div class="overlay" id="overlay"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="logo-text">
                    <div class="logo-title">Dashboard</div>
                </div>
            </a>
       </div>
        
       <nav class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="index.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-chart-pie"></i></div>
                    <div class="nav-text">Dashboard</div>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Gestão</div>
                <a href="usuarios.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-user"></i></div>
                    <div class="nav-text">Usuários</div>
                </a>
                <a href="afiliados.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="nav-text">Afiliados</div>
                </a>
                <a href="depositos.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-credit-card"></i></div>
                    <div class="nav-text">Depósitos</div>
                </a>
                <a href="saques.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="nav-text">Saques</div>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Sistema</div>
                <a href="config.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-cogs"></i></div>
                    <div class="nav-text">Configurações</div>
                </a>
                <a href="gateway.php" class="nav-item active">
                    <div class="nav-icon"><i class="fas fa-usd"></i></div>
                    <div class="nav-text">Gateway</div>
                </a>
                <a href="banners.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-images"></i></div>
                    <div class="nav-text">Banners</div>
                </a>
                <a href="cartelas.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-diamond"></i></div>
                    <div class="nav-text">Raspadinhas</div>
                </a>
                <a href="../logout" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-sign-out-alt"></i></div>
                    <div class="nav-text">Sair</div>
                </a>
            </div>
        </nav>
    </aside>
    
    <main class="main-content" id="mainContent">
        <header class="header">
            <div class="header-content">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span style="color: #a1a1aa; font-size: 0.9rem;">Bem-vindo, <?= htmlspecialchars($nome) ?></span>
                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #22c55e, #16a34a); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #ffffff; font-size: 1rem;">
                        <?= strtoupper(substr($nome, 0, 1)) ?>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="page-content">
            <section class="welcome-section">
                <h2 class="welcome-title">Gateway de Pagamento</h2>
                <p class="welcome-subtitle">Configure e gerencie o gateway de pagamento da plataforma</p>
            </section>
            
            <div class="form-container">
                <form method="POST">
                    <h2 class="form-title">
                        <i class="fas fa-key"></i>
                        Credenciais SilverPay
                    </h2>
                    
                    <div class="silverpay-warning">
                        <i class="fas fa-info-circle"></i>
                        <div class="silverpay-warning-content">
                            <strong>SilverPay:</strong> Certifique-se de que suas credenciais e a URL do webhook estão configuradas corretamente para receber notificações de pagamento.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-id-card"></i>
                            Client ID
                        </label>
                        <input type="text" name="client_id" value="<?= htmlspecialchars($silverpay['client_id'] ?? '') ?>" class="form-input" placeholder="Digite seu Client ID da SilverPay" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i>
                            Client Secret
                        </label>
                        <input type="password" name="client_secret" value="<?= htmlspecialchars($silverpay['client_secret'] ?? '') ?>" class="form-input with-toggle" placeholder="Digite seu Client Secret da SilverPay" required id="silverpaySecret">
                        <button type="button" class="password-toggle" onclick="togglePassword('silverpaySecret', 'silverpayToggleIcon')">
                            <i class="fas fa-eye" id="silverpayToggleIcon"></i>
                        </button>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-link"></i>
                            URL de Notificação (Webhook)
                        </label>
                        <input type="url" name="urlnoty" value="<?= htmlspecialchars($silverpay['urlnoty'] ?? '') ?>" class="form-input" placeholder="https://seusite.com/silverpay_webhook.php" required>
                    </div>

                    <button type="submit" name="salvar_silverpay" class="submit-button">
                        <i class="fas fa-shield-alt"></i>
                        Salvar Credenciais SilverPay
                    </button>
                </form>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const overlay = document.getElementById('overlay');
        
        menuToggle.addEventListener('click', () => {
            const isHidden = sidebar.classList.contains('hidden');
            
            if (isHidden) {
                sidebar.classList.remove('hidden');
                overlay.classList.add('active');
            } else {
                sidebar.classList.add('hidden');
                overlay.classList.remove('active');
            }
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.add('hidden');
            overlay.classList.remove('active');
        });
        
        // Close sidebar on window resize if it's mobile
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 1024) {
                sidebar.classList.add('hidden');
                overlay.classList.remove('active');
            } else {
                sidebar.classList.remove('hidden');
                overlay.classList.remove('active');
            }
        });
        
        // Enhanced hover effects for nav items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(8px)';
            });
            
            item.addEventListener('mouseleave', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'translateX(0)';
                }
            });
        });
        
        // Toggle password visibility
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            console.log('%c🏦 Configuração de Gateway carregada!', 'color: #22c55e; font-size: 16px; font-weight: bold;');
            
            // Check if mobile on load
            if (window.innerWidth <= 1024) {
                sidebar.classList.add('hidden');
            }
            
            // Animate form containers on load
            const formContainers = document.querySelectorAll('.form-container');
            formContainers.forEach((container, index) => {
                container.style.opacity = '0';
                container.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    container.style.transition = 'all 0.6s ease';
                    container.style.opacity = '1';
                    container.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
    <script
      disable-devtool-auto
      src="https://cdn.jsdelivr.net/npm/disable-devtool@latest"
    ></script>
    <script>
      document.addEventListener("keydown", function (event) {
        if (event.key === "F12") {
          event.preventDefault();
          window.close();
        }

        if (event.ctrlKey && event.shiftKey && event.key === "C") {
          event.preventDefault();
          window.close();
        }

        if (event.ctrlKey && event.key === "U") {
          event.preventDefault();
          window.close();
        }
      });
    </script>
</body>
</html>
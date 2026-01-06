// Função simples para carregar variáveis de ambiente do arquivo .env
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

// Tenta carregar do diretório atual ou do pai (caso esteja em subpasta)
if (!loadEnv(__DIR__ . '/.env')) {
    // Fallback: Tenta carregar do diretório pai se não achar no atual
    loadEnv(__DIR__ . '/../.env');
}

$host = getenv('DB_HOST') ?: 'db.kfvadrowjzgcandefodu.supabase.co';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASS') ?: 'Resident4567'; // O ideal é que esta senha esteja no .env no servidor

$dsn = "pgsql:host=$host;port=$port;dbname=$db";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException("Erro de conexão com o banco de dados Supabase. Verifique o arquivo .env ou as configurações de rede. " . $e->getMessage(), (int)$e->getCode());
}

$site = $pdo->query("SELECT nome_site, logo, deposito_min, saque_min, cpa_padrao, revshare_padrao FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$nomeSite = $site['nome_site'] ?? ''; 
$logoSite = $site['logo'] ?? '';
$depositoMin = $site['deposito_min'] ?? 10;
$saqueMin = $site['saque_min'] ?? 50;
$cpaPadrao = $site['cpa_padrao'] ?? 10;
$revshare_padrao = $site['revshare_padrao'] ?? 10;
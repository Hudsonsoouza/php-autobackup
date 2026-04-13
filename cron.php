<?php
// ==========================================
// 1. CONFIGURAÇÕES PRINCIPAIS
// ==========================================

set_time_limit(0); 
ini_set('memory_limit', '512M');

$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = '';

// Diretório base onde ficarão as subpastas
$base_backup_dir = __DIR__ . '/backups/'; 

// Limite de pastas (backups) a serem mantidas
$limite_pastas = 3;

$discord_webhook_url = '';
$tempo_pausa = 10; 
$bancos_ignorados = ['information_schema', 'performance_schema', 'mysql', 'sys'];

// ==========================================
// 2. ROTINA DE BACKUP
// ==========================================
$sucessos = [];
$erros = [];

try {
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SHOW DATABASES");
    $todos_os_bancos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $databases = array_diff($todos_os_bancos, $bancos_ignorados);

    if (empty($databases)) {
        die("Nenhum banco de dados atrelado ao usuário '{$db_user}' foi encontrado.\n");
    }

    $date = date('Y-m-d_H-i-s');
    
    // Define o caminho da pasta específica deste backup (ex: /backups/2026-04-12_03-00-00/)
    $current_run_dir = $base_backup_dir . $date . '/';

    // Cria a pasta do backup de hoje e garante as travas de segurança na pasta base
    if (!is_dir($current_run_dir)) {
        mkdir($current_run_dir, 0755, true);
        
        // Garante que a pasta pai (/backups/) tenha proteção contra curiosos
        if (!file_exists($base_backup_dir . '.htaccess')) {
            file_put_contents($base_backup_dir . '.htaccess', "Deny from all\nOptions -Indexes");
            file_put_contents($base_backup_dir . 'index.html', ""); 
        }
    }

    $total_bancos = count($databases);
    echo "Iniciando backup de {$total_bancos} banco(s) na subpasta: {$date}\n";

    $contador = 0;

    foreach ($databases as $db) {
        $contador++;
        $file_name = "{$db}_{$date}.sql.gz";
        
        // Agora salva dentro da subpasta criada
        $file_path = $current_run_dir . $file_name;
        
        $command = "nice -n 19 mysqldump -h {$db_host} -u {$db_user} -p'{$db_pass}' {$db} 2>/dev/null | gzip > {$file_path}";
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $sucessos[] = $db;
            echo "[OK] Banco '{$db}' salvo. ({$contador}/{$total_bancos})\n";
        } else {
            $erros[] = $db;
            echo "[ERRO] Falha no banco '{$db}'. ({$contador}/{$total_bancos})\n";
        }

        if ($contador < $total_bancos) {
            echo "Aguardando {$tempo_pausa} segundos...\n";
            sleep($tempo_pausa);
        }
    }

    // ==========================================
    // 3. LIMPEZA DE PASTAS ANTIGAS (RETENÇÃO)
    // ==========================================
    echo "\nVerificando limite de retenção de pastas...\n";
    
    // Busca apenas diretórios dentro da pasta /backups/
    $pastas_encontradas = glob($base_backup_dir . '*', GLOB_ONLYDIR);
    
    // O glob já retorna em ordem alfabética (o que equivale à cronológica devido ao formato Y-m-d)
    // Mas passamos um sort por segurança
    sort($pastas_encontradas);
    
    $pastas_apagadas = 0;

    // Se tivermos mais pastas do que o limite permitido (ex: 4 pastas > limite de 3)
    if (count($pastas_encontradas) > $limite_pastas) {
        $quantidade_para_apagar = count($pastas_encontradas) - $limite_pastas;
        
        // Faz um loop apagando as pastas mais velhas (que estão no início do array)
        for ($i = 0; $i < $quantidade_para_apagar; $i++) {
            $pasta_alvo = $pastas_encontradas[$i];
            
            // O PHP só apaga pastas vazias. Primeiro, deletamos os arquivos .sql.gz lá dentro
            $arquivos_na_pasta = glob($pasta_alvo . '/*');
            foreach ($arquivos_na_pasta as $arquivo) {
                if (is_file($arquivo)) {
                    unlink($arquivo);
                }
            }
            
            // Com a pasta vazia, agora deletamos ela
            rmdir($pasta_alvo);
            $pastas_apagadas++;
            echo "[LIMPEZA] Pasta antiga removida: " . basename($pasta_alvo) . "\n";
        }
    }

    // ==========================================
    // 4. ENVIO PARA O DISCORD
    // ==========================================
    $cor_embed = empty($erros) ? 3066993 : 15158332; 
    
    $lista_sucesso = empty($sucessos) ? "Nenhum" : implode(", ", $sucessos);
    $lista_erros = empty($erros) ? "Nenhuma" : implode(", ", $erros);

    $json_data = json_encode([
        "content" => "🚀 **Backup do usuário `{$db_user}` finalizado!**",
        "embeds" => [
            [
                "title" => "Relatório Automático de Backup",
                "color" => $cor_embed,
                "fields" => [
                    [
                        "name" => "🟢 Salvos com Sucesso (" . count($sucessos) . ")",
                        "value" => "```\n" . $lista_sucesso . "\n```",
                        "inline" => false
                    ],
                    [
                        "name" => "🔴 Falhas (" . count($erros) . ")",
                        "value" => "```\n" . $lista_erros . "\n```",
                        "inline" => false
                    ]
                ],
                "footer" => [
                    // Informar sobre a retenção de pastas
                    "text" => "Salvos em /backups/{$date}/ | Mantendo os últimos {$limite_pastas} backups"
                ],
                "timestamp" => date("c")
            ]
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init($discord_webhook_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_exec($ch);
    curl_close($ch);

    echo "Processo finalizado e notificação enviada.\n";

} catch (PDOException $e) {
    echo "Erro Crítico de Conexão: " . $e->getMessage() . "\n";
}
?>
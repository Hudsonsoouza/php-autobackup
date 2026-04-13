# 🛡️ Sistema de Backup Automático MySQL (PHP)

Um script PHP robusto e leve projetado para rodar via CLI (linha de comando / cron) que realiza o backup automatizado de todos os bancos de dados vinculados a um usuário específico do MySQL.

## ✨ Principais Recursos

* **Automático e Dinâmico:** Não é necessário listar os bancos manualmente. O script descobre automaticamente todos os bancos aos quais o usuário tem acesso.
* **Seguro (Zero-Downtime):** Utiliza `--single-transaction` para realizar o backup em bancos InnoDB sem "trancar" (lock) as tabelas, garantindo que suas aplicações continuem no ar.
* **Leve para o Servidor:** Usa `nice -n 19` para rodar com baixa prioridade de CPU e inclui pausas (`sleep`) configuráveis entre os backups para não sobrecarregar discos em servidores compartilhados ou VPS.
* **Organização por Data:** Cria subpastas diárias (ex: `2026-04-12_03-00-00/`) e compacta todos os arquivos em `.sql.gz`.
* **Retenção Inteligente (Auto-limpeza):** Mantém apenas os últimos `X` dias/backups configurados, apagando automaticamente as pastas mais antigas para evitar que o disco fique lotado.
* **Notificações via Discord:** Envia um relatório detalhado (Embed) para um canal do Discord via Webhook informando os sucessos e as falhas do processo.
* **Proteção Web Integrada:** Gera automaticamente arquivos `.htaccess` e `index.html` vazios para bloquear acessos indevidos caso a pasta de backup esteja em um diretório web público (embora não seja o recomendado).

---

## 📋 Pré-requisitos

Para que este script funcione corretamente no seu servidor (Linux/VPS), você precisará de:

1. **PHP-CLI** (versão 7.4 ou superior recomendada) com as extensões `PDO` e `cURL` ativadas.
2. Servidor de banco de dados **MySQL** ou **MariaDB**.
3. O utilitário **`mysqldump`** e o compactador **`gzip`** instalados no servidor (padrão na maioria das distribuições Linux).
4. Permissões para configurar tarefas agendadas (**Cron Jobs**).

---

## 🚀 Como Configurar e Usar

### 1. Configuração do Script
Abra o arquivo `cron.php` e edite a seção principal de configurações:

```php
$db_host = 'localhost';
$db_user = 'seu_usuario_aqui';      // Usuário com permissão de leitura nos bancos
$db_pass = 'sua_senha_aqui';        // Senha do usuário

$discord_webhook_url = '';          // Caso queira ser notificado sobre o backup

$limite_pastas = 3;                 // Quantidade de backups antigos para manter
$tempo_pausa = 10;                  // Pausa em segundos entre o backup de cada banco
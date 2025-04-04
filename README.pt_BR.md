# AntiVPN Plugin para PocketMine-MP

### Descrição

O **AntiVPN** é um plugin de segurança para servidores PocketMine-MP que detecta e impede jogadores de acessarem o servidor usando VPNs ou proxies. Com suporte a múltiplas APIs de verificação e recursos avançados de cache, este plugin ajuda a manter a integridade e segurança do seu servidor.

### Características Principais

- Detecção de VPN/Proxy usando múltiplas APIs
- Bloqueio automático de conexões VPN
- Registro detalhado de tentativas de conexão
- Configuração altamente personalizável
- Suporte a APIs de fallback
- Sistema de cache inteligente

### APIs Suportadas

- ProxyCheck
- IPHub

### Requisitos

- PocketMine-MP (Versão 2.0.0)
- PHP 7.0.0 ou superior
- Minecraft: Pocket Edition 0.15.10 ou superior

### Instalação

1. Baixe o plugin `AntiVPN.phar`
2. Coloque o arquivo na pasta `plugins/` do seu servidor PocketMine-MP
3. Reinicie o servidor

### Configuração (`config.yml`)

```yaml
# Configuração do AntiVPN

# Mensagem para jogadores usando VPN
kick-message: "§cVPN detectada! Por favor, desative sua VPN para jogar."

# Escolha qual API de verificação utilizar (proxycheck, iphub)
primary-api: "proxycheck"
fallback-api: "iphub"

# Cache para evitar consultas repetidas (em segundos)
# 86400 segundos = 24 horas
# Impede verificações de VPN repetidas para o mesmo IP dentro de 24 horas
cache-time: 86400

# Intervalo para limpeza do cache (em minutos)
# 1440 minutos = 24 horas
# Remove entradas de cache desatualizadas uma vez por dia
cache-cleanup-interval: 1440

# Logs de conexões detectadas como VPN
enable-logs: true

# Limite de tentativas por API antes de mudar para fallback
api-retry-limit: 3

# Configurações das APIs
api:
  proxycheck:
    enabled: true
    api-key: "YOUR_PROXYCHECK_API_KEY"
    # Deixe em branco para usar versão gratuita

  iphub:
    enabled: true
    api-key: "YOUR_IPHUB_API_KEY"

# Whitelist de IPs (que serão ignorados na verificação)
ip-whitelist:
  - "127.0.0.1"
  - "192.168.1.1"
```

### Comandos

| Comando | Descrição |
|---------|-----------|
| `/antivpn` | Menu de ajuda |
| `/antivpn check <player>` | Verifica se um jogador está usando VPN |
| `/antivpn checkip <ip>` | Verifica se um IP está usando VPN |
| `/antivpn reload` | Recarrega a configuração |
| `/antivpn clearcache` | Limpa o cache de verificações |
| `/antivpn stats` | Exibe estatísticas das APIs |
| `/antivpn whitelist <add\|remove> <ip>` | Gerencia IPs na whitelist |
| `/antivpn savecache` | Força o salvamento do cache |

### Permissões

- `antivpn.admin`: Permite usar comandos administrativos
- `antivpn.bypass`: Permite ignorar verificações de VPN

### Contribuição

Contribuições são bem-vindas! Por favor, abra issues ou envie pull requests neste repositório.

---

Desenvolvido por PocketDev
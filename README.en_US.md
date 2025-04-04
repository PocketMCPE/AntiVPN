# AntiVPN Plugin for PocketMine-MP

### Description

**AntiVPN** is a security plugin for PocketMine-MP servers that detects and prevents players from accessing the server using VPNs or proxies. With support for multiple verification APIs and advanced caching features, this plugin helps maintain the integrity and security of your server.

### Main Features

- VPN/Proxy detection using multiple APIs
- Automatic VPN connection blocking
- Detailed logging of connection attempts
- Highly customizable configuration
- Fallback API support
- Intelligent caching system

### Supported APIs

- ProxyCheck
- IPHub

### Requirements

- PocketMine-MP (Version 2.0.0)
- PHP 7.0.0 or higher
- Minecraft: Pocket Edition 0.15.10 or higher

### Installation

1. Download the `AntiVPN.phar` plugin
2. Place the file in the `plugins/` folder of your PocketMine-MP server
3. Restart the server

### Configuration (`config.yml`)

```yaml
# AntiVPN Configuration

# Message for players using VPN
kick-message: "§cVPN detected! Please disable your VPN to play."

# Choose which verification API to use (proxycheck, iphub)
primary-api: "proxycheck"
fallback-api: "iphub"

# Cache to avoid repeated queries (in seconds)
# 86400 seconds = 24 hours
# Prevents repeated VPN checks for the same IP within 24 hours
cache-time: 86400

# Interval for cache cleanup (in minutes)
# 1440 minutes = 24 hours
# Removes outdated cache entries once per day
cache-cleanup-interval: 1440

# Logs of connections detected as VPN
enable-logs: true

# Limit of attempts per API before switching to fallback
api-retry-limit: 3

# API Settings
api:
  proxycheck:
    enabled: true
    api-key: "YOUR_PROXYCHECK_API_KEY"
    # Leave blank to use free version

  iphub:
    enabled: true
    api-key: "YOUR_IPHUB_API_KEY"

# IP Whitelist (IPs that will be ignored in verification)
ip-whitelist:
  - "127.0.0.1"
  - "192.168.1.1"
```

### Commands

| Command | Description |
|---------|-------------|
| `/antivpn` | Help menu |
| `/antivpn check <player>` | Checks if a player is using VPN |
| `/antivpn checkip <ip>` | Checks if an IP is using VPN |
| `/antivpn reload` | Reloads the configuration |
| `/antivpn clearcache` | Clears verification cache |
| `/antivpn stats` | Displays API statistics |
| `/antivpn whitelist <add\|remove> <ip>` | Manages IP whitelist |
| `/antivpn savecache` | Forces cache saving |

### Permissions

- `antivpn.admin`: Allows use of administrative commands
- `antivpn.bypass`: Allows bypassing VPN checks

### Contribution

Contributions are welcome! Please open issues or send pull requests in this repository.

---

Developed by PocketDev
# 中国航空（代理订阅储存器）

一个轻量的 PHP + SQLite 订阅储存/分享网页：

- 后台添加订阅链接，一键抓取缓存
- 前台展示订阅信息、二维码、节点列表（从缓存解析）
- 支持定时自动抓取（后台配置抓取间隔）
- 支持 SEO `keywords/description` 与底部友链/联系方式

## 目录结构

```
proxystore/
  index.php                 前台
  share.php                 分享页（二维码 + 节点列表）
  sub.php                   输出缓存订阅内容
  admin/                    后台
  lib/                      逻辑
  static/                   静态资源
  .data/                    数据目录（本仓库已忽略，不要提交）
```

## 环境要求

- Nginx / Apache + PHP 7.4+（推荐 8.x）
- PHP 扩展：`pdo_sqlite`（必须）
- `curl` 扩展（可选，有则抓取更稳；没有会回退 `file_get_contents`）

## 安装部署（以 Nginx 为例）

1. 上传整个 `proxystore/` 到你的网站目录的子目录，例如：

```
/www/wwwroot/你的域名/proxystore/
```

2. 确保网站根目录仍然是：

```
/www/wwwroot/你的域名
```

不要把站点 `root` 改到 `.../proxystore`，否则访问 `/proxystore/static/*` 容易 404。

3. 权限（宝塔常见用户为 `www`）：

```
chown -R www:www /www/wwwroot/你的域名/proxystore
mkdir -p /www/wwwroot/你的域名/.data/proxystore
chown -R www:www /www/wwwroot/你的域名/.data/proxystore
chmod 700 /www/wwwroot/你的域名/.data/proxystore
```

首次访问会自动创建：

- `/www/wwwroot/你的域名/.data/proxystore/proxystore.sqlite`
- `/www/wwwroot/你的域名/.data/proxystore/config.php`（后台账号/cron token）

## 使用方式

### 前台

- `https://你的域名/proxystore/`
- 每条订阅可进入 `分享/二维码`，查看二维码与节点列表

### 后台

- `https://你的域名/proxystore/admin/login.php`
- 默认账号：`admin`
- 默认密码：`admin123`

首次部署后请立刻修改后台账号/密码：

- 配置文件：`/www/wwwroot/你的域名/.data/proxystore/config.php`
- `admin_pass_hash` 需要是 `password_hash()` 生成的哈希（不要明文存密码）

### 系统设置

- `https://你的域名/proxystore/admin/settings.php`
- 可设置站点名称（默认：`中国航空`）
- 可设置 SEO `keywords/description`
- 可设置底部友链/联系方式（`footer_html`）
- 可设置自动抓取间隔（分钟，`0` 为关闭）

## 定时自动抓取（Cron）

后台 `系统设置` 页面会显示一个带 token 的 URL，例如：

```
https://你的域名/proxystore/admin/cron.php?token=xxxx
```

在服务器 crontab 里每分钟调用一次即可（真正抓取频率由后台“自动抓取间隔”控制）：

```
* * * * * curl -sk --resolve 你的域名:443:127.0.0.1 https://你的域名/proxystore/admin/cron.php?token=xxxx >/dev/null 2>&1
```

说明：
- 使用 `--resolve` 让请求走本机回环（避免外网 DNS/回源问题）
- token 不要泄露

## 常见问题

### 1) 抓取失败 / HTTP 403

订阅源可能做了 IP 白名单限制，导致服务器抓取返回 403/401。
解决办法：在订阅源侧放行你的服务器出口 IP，或换一个可从服务器访问的订阅链接。

### 2) 二维码不显示

通常是浏览器缓存导致的静态资源没有更新。项目已对 `static/*.js/css` 加了版本号参数；
仍建议按一次强刷：Windows `Ctrl+F5` / macOS `Cmd+Shift+R`。

## 安全建议

- 不要把 `.data/`（数据库、token、配置）提交到公开仓库
- 将后台目录加入访问控制（例如仅允许固定 IP 访问）


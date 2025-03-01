# TelegramRSS
RSS/JSON generator for telegram

Get posts from my [TelegramApiServer](https://github.com/xtrime-ru/TelegramApiServer) and output them as RSS or JSON.

## Features
* Fast async Swoole server
* Use as micro-service to access Telegram API
* Get any public telegram posts from groups as json or RSS
* fail2ban, RPM limits, IP blacklist
* Full media support. Access any media from messages via direct links.

## Architecture Example

![Architecture Example](https://hsto.org/webt/j-/ob/ky/j-obkye1dv68ngsrgi12qevutra.png)

## Installation
 
1. Install and start [Telegram Api Server](https://github.com/xtrime-ru/TelegramApiServer)
1. Clone this project: `git clone https://github.com/xtrime-ru/TelegramRSS.git TelegramRSS`
1. Start:
    * Docker: 
        1. `docker-compose pull`
        2. `docker-compose up -d`
  
    * Manual:
        1. [Install Swoole php extension](https://github.com/swoole/swoole-src#%EF%B8%8F-installation)
        1. `composer install -o --no-dev`
        1. `php server.php`
   
## Setup
1. Edit `.env` or `.env.docker` if needed. 
1. Restart RSS server.
    * Docker: 
        1. `docker-compose restart`
    * Manual:
        1. ctrl + c
        1. `php server.php`
1. [Run in background](https://github.com/xtrime-ru/TelegramApiServer#run-in-background)
1. Example of Nginx config 
    ```
    server {
        listen      %ip%:443 ssl;
        server_name tg.i-c-a.su;
    
        ssl_certificate      /home/admin/conf/web/ssl.tg.i-c-a.su.pem;
        ssl_certificate_key  /home/admin/conf/web/ssl.tg.i-c-a.su.key;
    
        location / {
            proxy_set_header Host $http_host;
            proxy_set_header SERVER_PORT $server_port;
            proxy_set_header REMOTE_ADDR $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header Upgrade $http_upgrade;
    
            fastcgi_param REMOTE_ADDR $http_x_real_ip;
            proxy_http_version 1.1;
            proxy_set_header Connection "keep-alive";
    
            proxy_pass http://127.0.0.1:9504;
        }
    
    }
    ```
  
## Examples    
### JSON
* URL: https://tg.i-c-a.su/json/breakingmash
* Custom limit: https://tg.i-c-a.su/json/breakingmash?limit=50 
  
  Maximum: 100 posts
  
* Pagination: https://tg.i-c-a.su/json/breakingmash?page=2

### RSS
* URL: https://tg.i-c-a.su/rss/breakingmash
* Custom limit: https://tg.i-c-a.su/json/breakingmash?limit=50 

  Maximum: 100 posts
  
* Pagination: https://tg.i-c-a.su/rss/breakingmash/2

### Media
* https://tg.i-c-a.su/media/breakingmash/10738/preview
* https://tg.i-c-a.su/media/breakingmash/10738

Default address of RSS server is `http://127.0.0.1:9504/`
    
## Contacts

* Telegram: [@xtrime](tg://resolve?domain=xtrime)
* Email: alexander(at)i-c-a.su

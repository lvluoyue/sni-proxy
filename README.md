# SNI 动态路由代理
基于 TLS Hello包中的 SNI 信息实时解析并转发至目标服务端口。

## 环境准备
- php 8.1+
- swoole/swow/fiber/event扩展
- 两个服务端，一个客户端（强制启用 TLS）
- dns 解析

## 使用说明
1. 启动代理服务并放行防火墙代理端口。
```php
php start.php start -d
```
2. 将域名解析到代理服务ip。实际情况根据自己情况调整。
```text
a.example.com  192.168.1.100  // A 记录
b.example.com  192.168.1.100  // A 记录
_a.example.com  ip=127.0.0.1;port=5000  // TXT 记录
_b.example.com  ip=127.0.0.1;port=5001  // TXT 记录
```
3. 访问a.example.com和b.example.com可查看服务器返回不同端口的页面及为成功。

## 自定义路由
本项目采用dns管理转发的服务器及端口，可以通过更改parseHost方法来实现自定义逻辑。

## 架构图
```mermaid
graph TD
subgraph Clients
C1[client①] -->|a.example.com| S(Server 7000)
C2[client②] -->|a.example.com| S(Server 7000)
C3[client③] -->|b.example.com| S(Server 7000)
C4[client④] -->|b.example.com| S(Server 7000)
C5[client⑤] -->|c.example.com| S(Server 7000)
C6[client⑥] -->|c.example.com| S(Server 7000)
C7[client⑦] -->|d.example.com| S(Server 7000)
end
S(Server 7000) -->|SNI: a.example.com| D1(DNS TXT: _a.example.com)
S(Server 7000) -->|SNI: b.example.com| D2(DNS TXT: _b.example.com)
S(Server 7000) -->|SNI: c.example.com| D3(DNS TXT: _c.example.com)
S(Server 7000) -->|SNI: d.example.com| D4(DNS TXT: _d.example.com)

D1 -->|ip=127.0.0.1;port=5000| T1(Target Service 5000)
D2 -->|ip=127.0.0.1;port=5001| T2(Target Service 5001)
D3 -->|ip=127.0.0.1;port=5002| T3(Target Service 5002)
D4 -->|ip=127.0.0.1;port=5003| T4(Target Service 5003)

```

## 特别感谢
- [workerman](https://github.com/walkor/workerman)
- [通义灵码](https://lingma.aliyun.com/?utm_content=se_1021066852)
- [PhpStorm](https://www.jetbrains.com/phpstorm/)
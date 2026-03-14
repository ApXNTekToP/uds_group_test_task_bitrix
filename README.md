## Структура проекта

```
.
├─ docker-compose.yml
├─ .env
├─ docker/
│  ├─ php/
│  │  ├─ Dockerfile
│  │  └─ php.ini
│  └─ nginx/
│     └─ default.conf
└─ www/ <- здесь код сайта
```

## Запуск

```bash
docker compose up -d --build
```
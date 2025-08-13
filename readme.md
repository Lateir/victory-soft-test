# Тестовое задание для Victory Soft

---

# Локальный запуск проекта
Ниже — пошаговая инструкция, как поднять проект локально с помощью Docker и Docker Compose, пробросить порты и проверить работу сервисов и эндпоинтов
## Предпосылки
- Установлены:
    - Docker (Desktop/Engine)
    - Docker Compose v2 (обычно входит в Docker Desktop)

- Свободны порты: 80 (Nginx), 5432 (PostgreSQL), 6379 (Redis)

Порты 5432 и 6379 используются для отладки извне

## Быстрый старт
1. Скопируйте переменные окружения
``` bash
# в корне репозитория
cp .env.example .env
```
При желании отредактируйте .env (локально достаточно значений по умолчанию).
2. Соберите и поднимите контейнеры
``` bash
docker compose up -d --build
```
3. Проверьте, что контейнеры запущены
``` bash
docker compose ps
```
4. Выполните миграцию в бд
``` bash
# Скопируйте файл в контейнер
docker cp .\migratons\001_init.sql victory-soft-test-postgres-1:/migrations.sql

# запускаем psql внутри контейнера
docker exec -it victory-soft-test-postgres-1 psql -U app -d app -f /migrations.sql
```
5. Откройте приложение в браузере

- [http://localhost/](http://localhost/) — клиентская страница

## Сервисная схема
- Nginx (порт 80) проксирует PHP-запросы в PHP-FPM
- PHP (FPM + CLI) подключается к:
    - PostgreSQL (порт 5432)
    - Redis (порт 6379)

## Переменные окружения
По умолчанию используются:
``` 
APP_ENV=prod
DB_HOST=postgres
DB_PORT=5432
DB_NAME=app
DB_USER=app
DB_PASS=app
REDIS_HOST=redis
REDIS_PORT=6379
```
Их можно изменять в .env перед запуском.

## Полезные команды

- Полная остановка с очисткой данных БД
``` bash
docker compose down -v
```
- Подключение к PostgreSQL внутри контейнера
``` bash
# Узнайте имя контейнера: docker compose ps
docker exec -it <postgres_container_name> psql -U app -d app
```
- Проверка Redis
``` bash
docker exec -it <redis_container_name> redis-cli ping
# Должно вернуть: PONG
```
## Доступные эндпоинты
- Клиентская страница:
    - GET [http://localhost/](http://localhost/)

- Запуск фоновых задач:
    - GET [http://localhost/beta.php?n=1000](http://localhost/beta.php?n=1000)
    - Параметр n — количество запусков (1…20000). На клиентской странице есть кнопка для вызова.

- Текущая статистика:
    - GET [http://localhost/gamma.php](http://localhost/gamma.php)
    - Клиентская страница автоматически опрашивает этот эндпоинт раз в секунду.

- Одиночный запуск «Alpha»:
    - GET [http://localhost/alpha.php](http://localhost/alpha.php)

Примечания:
- Фоновые задачи исполняются как процессы CLI внутри PHP-контейнера.
- При больших n учитывайте лимиты по времени и ресурсам машины.